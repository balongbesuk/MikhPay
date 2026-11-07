<?php
/**
 * QRIS Settlement Core Processor
 * MikhPay Engine
 * 
 * Fungsi modular untuk memproses verifikasi transaksi QRIS nominal unik
 * dan pembuatan voucher MikroTik secara otomatis.
 * Digunakan oleh:
 * 1. qris_verify.php (Webhook / Android MikhPay Forwarder / MacroDroid)
 * 2. GoPayMerchantService.php (Background API Poller & Scraper)
 */

if (!defined('MIKHPAY_QRIS_CORE_LOADED')) {
    define('MIKHPAY_QRIS_CORE_LOADED', true);
}

/**
 * Buat voucher hotspot di MikroTik untuk data transaksi dan perbarui statusnya.
 */
function generateHotspotVoucherForOrder(&$trans, $comment = '') {
    global $data;
    if (!isset($data)) {
        @include_once(__DIR__ . '/config.php');
    }
    include_once(__DIR__ . '/../lib/routeros_api.class.php');
    include_once(__DIR__ . '/../lib/formatbytesbites.php');

    $selected_session = isset($trans['session']) ? $trans['session'] : '';
    $profile_to_buy = isset($trans['profile']) ? $trans['profile'] : '';
    $order_id = isset($trans['order_id']) ? $trans['order_id'] : '';

    if (!isset($data[$selected_session])) {
        $trans['status'] = 'paid_pending_generate';
        $trans['router_error'] = "Session " . $selected_session . " tidak ditemukan di database.";
        return false;
    }

    $iphost = explode('!', $data[$selected_session][1])[1];
    $userhost = explode('@|@', $data[$selected_session][2])[1];
    $passwdhost = explode('#|#', $data[$selected_session][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        $trans['status'] = 'paid_pending_generate';
        $trans['router_error'] = "ErrNo: " . $API->error_no . ", ErrStr: " . $API->error_str;
        return false;
    }

    $userLength = 5;
    $username = function_exists('randNLC')
        ? randNLC($userLength)
        : substr(str_shuffle("abcdefghjkmnpqrstuvwxyz23456789"), 0, $userLength);
    $password = $username;
    if (empty($comment)) {
        $comment = "vc-QRIS-" . $order_id . "-" . date("m.d.y");
    }

    $result = $API->comm("/ip/hotspot/user/add", [
        "server" => "all",
        "name" => $username,
        "password" => $password,
        "profile" => $profile_to_buy,
        "comment" => $comment
    ]);
    $API->disconnect();

    if (is_array($result) && isset($result['!trap'])) {
        $trans['status'] = 'paid_pending_generate';
        $trans['router_error'] = json_encode($result);
        return false;
    }

    $trans['status'] = 'settlement';
    $trans['username'] = $username;
    $trans['password'] = $password;
    $trans['paid_at'] = time();
    unset($trans['router_error']);
    return true;
}

/**
 * Memproses verifikasi transaksi QRIS berdasarkan nominal unik.
 *
 * @param int   $nominal Nominal transfer masuk dalam Rupiah (contoh: 10045)
 * @param array $meta    Metadata opsional: ['source' => 'gopay_api'|'webhook_android', 'ref_id' => string, 'paid_time' => int]
 * @return array Hasil pemrosesan status, pesan, dan order_id
 */
function processQrisSettlement($nominal, $meta = []) {
    global $data, $qris_secret_token, $ws_app_id, $ws_app_key, $ws_app_secret, $ws_cluster;

    // Load config jika belum dimuat
    if (!isset($data)) {
        @include_once(__DIR__ . '/config.php');
    }
    if (!function_exists('writeAppLog')) {
        @include_once(__DIR__ . '/env_config.php');
    }

    $nominal = (int)$nominal;
    if ($nominal <= 0) {
        return [
            'status' => 'error',
            'code' => 400,
            'message' => 'Nominal tidak valid.'
        ];
    }

    // Jika ini adalah request pengujian koneksi
    if ($nominal === 12345) {
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Koneksi ke Webhook MikhPay Berhasil! (Test OK)'
        ];
    }

    $refId = isset($meta['ref_id']) ? trim((string)$meta['ref_id']) : '';
    $source = isset($meta['source']) && !empty($meta['source']) ? (string)$meta['source'] : 'webhook';
    $paidTime = isset($meta['paid_time']) ? (int)$meta['paid_time'] : 0;

    // Cari transaksi pending dengan nominal tersebut di bawah exclusive lock agar bebas race condition
    $dir = __DIR__ . '/../voucher/';
    $lockFile = __DIR__ . '/../data/settlement.lock';
    $refsFile = __DIR__ . '/../data/processed_refs.json';
    $lockFp = @fopen($lockFile, 'c+');
    if ($lockFp) {
        flock($lockFp, LOCK_EX);
    }

    // Deduplikasi berdasarkan ref_id / event_id jika dikirim
    $processedRefs = [];
    if (file_exists($refsFile)) {
        $decodedRefs = json_decode(@file_get_contents($refsFile), true);
        if (is_array($decodedRefs)) {
            $processedRefs = $decodedRefs;
        }
    }
    if ($refId !== '' && in_array($refId, $processedRefs, true)) {
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        return [
            'status' => 'already_processed',
            'code' => 200,
            'message' => 'Referensi pembayaran ini sudah pernah diproses.'
        ];
    }

    $found_order_id = null;
    $trans = null;
    $now = time();
    $orderTtl = 1800; // Maksimal 30 menit sejak order dibuat

    if (is_dir($dir)) {
        $files = array_merge(
            glob($dir . 'trans-*.json'),
            glob($dir . 'trans-*.php')
        );
        $bestCreatedAt = -1;
        foreach ($files as $file) {
            $transData = readTransactionFile($file);
            if (!$transData || !isset($transData['status']) || $transData['status'] !== 'pending') {
                continue;
            }
            if (!isset($transData['price']) || (int)$transData['price'] !== $nominal) {
                continue;
            }
            $createdAt = isset($transData['created_at']) ? (int)$transData['created_at'] : 0;
            // Abaikan order pending basi (> 30 menit)
            if ($createdAt > 0 && ($now - $createdAt) > $orderTtl) {
                continue;
            }
            // Jika waktu bayar diketahui (GoPay API / notifikasi HP), pastikan tidak terjadi sebelum order dibuat (toleransi 120 detik)
            if ($paidTime > 0 && $createdAt > 0 && $paidTime < ($createdAt - 120)) {
                continue;
            }
            // Pilih order pending valid yang paling baru
            if ($createdAt >= $bestCreatedAt) {
                $bestCreatedAt = $createdAt;
                $found_order_id = $transData['order_id'];
                $trans = $transData;
            }
        }
    }

    if (!$found_order_id) {
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        return [
            'status' => 'not_found',
            'code' => 404,
            'message' => 'Transaksi pending aktif dengan nominal ' . $nominal . ' tidak ditemukan.'
        ];
    }

    // Proteksi Idempotency: Segera tandai sebagai 'processing' dan simpan metadata verifikasi
    $trans['status'] = 'processing';
    $trans['paid_at'] = $now;
    $trans['verified_by'] = $source;
    if ($refId !== '') {
        $trans['payment_ref'] = $refId;
        $processedRefs[] = $refId;
        if (count($processedRefs) > 500) {
            $processedRefs = array_slice($processedRefs, -500);
        }
        @file_put_contents($refsFile, json_encode($processedRefs));
    }

    // Simpan status 'processing' ke file SEBELUM melepas lock & generate voucher
    $file_ext_lock = file_exists($dir . "trans-" . $found_order_id . ".php") ? ".php" : (file_exists($dir . "trans-" . $found_order_id . ".json") ? ".json" : ".php");
    if ($file_ext_lock === ".php") {
        writeTransactionFile($dir . "trans-" . $found_order_id . ".php", $trans);
    } else {
        file_put_contents($dir . "trans-" . $found_order_id . ".json", json_encode($trans));
    }

    if ($lockFp) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }

    // Hubungkan ke MikroTik untuk buat voucher
    $selected_session = $trans['session'];
    $profile_to_buy = $trans['profile'];

    if (generateHotspotVoucherForOrder($trans)) {
        $username = $trans['username'];
        writeAppLog("TRANSACTION_SUCCESS", "Voucher " . $username . " berhasil digenerate via QRIS untuk Order ID: " . $found_order_id . " | Profil: " . $profile_to_buy . " | Nominal: Rp " . number_format($nominal, 0, ',', '.'));

        $telegramMessage = "✅ <b>[MikhPay] Pembayaran QRIS Berhasil!</b>\n\n"
            . "<b>Order ID:</b> <code>{$found_order_id}</code>\n"
            . "<b>Sesi Router:</b> <code>{$selected_session}</code>\n"
            . "<b>Profil:</b> <code>{$profile_to_buy}</code>\n"
            . "<b>Voucher:</b> <code>{$username}</code>\n"
            . "<b>Nominal:</b> Rp " . number_format($nominal, 0, ',', '.') . "\n"
            . "Voucher telah otomatis diterbitkan.";
        sendTelegramNotification($telegramMessage);
    } else {
        writeAppLog("QRIS_VERIFY_ERROR", "Gagal generate voucher (" . (isset($trans['router_error']) ? $trans['router_error'] : 'unknown') . ") saat verifikasi QRIS nominal: " . $nominal);
    }

    // Simpan kembali data transaksi
    $file_ext = file_exists($dir . "trans-" . $found_order_id . ".php") ? ".php" : (file_exists($dir . "trans-" . $found_order_id . ".json") ? ".json" : ".php");
    if ($file_ext === ".php") {
        writeTransactionFile($dir . "trans-" . $found_order_id . ".php", $trans);
    } else {
        file_put_contents($dir . "trans-" . $found_order_id . ".json", json_encode($trans));
    }

    // Jika integrasi WebSocket diaktifkan, push notifikasi langsung ke browser pelanggan & admin
    if (!empty($ws_app_key)) {
        $total_revenue = 0;
        $success_count = 0;
        $pending_count = 0;
        if (is_dir($dir)) {
            $all_files = array_merge(glob($dir . 'trans-*.json'), glob($dir . 'trans-*.php'));
            foreach ($all_files as $file) {
                $tData = readTransactionFile($file);
                if ($tData) {
                    $st = isset($tData['status']) ? $tData['status'] : 'pending';
                    $pr = isset($tData['price']) ? (float)$tData['price'] : 0.0;
                    if ($st === 'settlement' || $st === 'capture') {
                        $total_revenue += $pr;
                        $success_count++;
                    } elseif ($st === 'paid_pending_generate') {
                        $pending_count++;
                    }
                }
            }
        }

        // 1. Kirim ke channel pelanggan (order individual)
        triggerWebSocketPaidEvent(
            $ws_app_id, 
            $ws_app_key, 
            $ws_app_secret, 
            $ws_cluster, 
            'order-' . $found_order_id, 
            'paid', 
            [
                'status' => 'success',
                'order_id' => $found_order_id
            ]
        );

        // 2. Kirim ke channel admin (global events)
        triggerWebSocketPaidEvent(
            $ws_app_id, 
            $ws_app_key, 
            $ws_app_secret, 
            $ws_cluster, 
            'admin-events', 
            'new-payment', 
            [
                'order_id' => $found_order_id,
                'profile' => $trans['profile'],
                'price' => $trans['price'],
                'total_revenue' => $total_revenue,
                'success_count' => $success_count,
                'pending_count' => $pending_count
            ]
        );
    }

    return [
        'status' => 'success',
        'code' => 200,
        'message' => 'Pembayaran berhasil dikonfirmasi.',
        'order_id' => $found_order_id,
        'voucher_status' => $trans['status'],
        'username' => isset($trans['username']) ? $trans['username'] : null,
        'password' => isset($trans['password']) ? $trans['password'] : null,
        'router_error' => isset($trans['router_error']) ? $trans['router_error'] : null
    ];
}
