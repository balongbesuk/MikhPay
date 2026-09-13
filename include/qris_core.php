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
 * Memproses verifikasi transaksi QRIS berdasarkan nominal unik.
 *
 * @param int $nominal Nominal transfer masuk dalam Rupiah (contoh: 10045)
 * @return array Hasil pemrosesan status, pesan, dan order_id
 */
function processQrisSettlement($nominal) {
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

    // Cari transaksi pending dengan nominal tersebut
    $dir = __DIR__ . '/../voucher/';
    $found_order_id = null;
    $trans = null;

    if (is_dir($dir)) {
        $files = array_merge(
            glob($dir . 'trans-*.json'),
            glob($dir . 'trans-*.php')
        );
        foreach ($files as $file) {
            $transData = readTransactionFile($file);
            if ($transData && isset($transData['status']) && $transData['status'] === 'pending' && isset($transData['price']) && (int)$transData['price'] === $nominal) {
                $found_order_id = $transData['order_id'];
                $trans = $transData;
                break;
            }
        }
    }

    if (!$found_order_id) {
        return [
            'status' => 'not_found',
            'code' => 404,
            'message' => 'Transaksi pending dengan nominal ' . $nominal . ' tidak ditemukan.'
        ];
    }

    // Proteksi Idempotency: Segera tandai sebagai 'processing' agar request duplikat
    // tidak bisa memproses ulang transaksi ini
    $trans['status'] = 'processing';
    $trans['paid_at'] = time();

    // Simpan status 'processing' ke file SEBELUM generate voucher
    $file_ext_lock = file_exists($dir . "trans-" . $found_order_id . ".php") ? ".php" : (file_exists($dir . "trans-" . $found_order_id . ".json") ? ".json" : ".php");
    if ($file_ext_lock === ".php") {
        writeTransactionFile($dir . "trans-" . $found_order_id . ".php", $trans);
    } else {
        file_put_contents($dir . "trans-" . $found_order_id . ".json", json_encode($trans));
    }

    // Hubungkan ke MikroTik untuk buat voucher
    include_once(__DIR__ . '/../lib/routeros_api.class.php');
    include_once(__DIR__ . '/../lib/formatbytesbites.php');
    $selected_session = $trans['session'];
    $profile_to_buy = $trans['profile'];

    if (isset($data[$selected_session])) {
        $iphost = explode('!', $data[$selected_session][1])[1];
        $userhost = explode('@|@', $data[$selected_session][2])[1];
        $passwdhost = explode('#|#', $data[$selected_session][3])[1];
        
        $API = new RouterosAPI();
        $API->debug = false;
        
        if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $userLength = 5;
            if (function_exists('randNLC')) {
                $username = randNLC($userLength);
            } else {
                $username = substr(str_shuffle("abcdefghjkmnpqrstuvwxyz23456789"), 0, $userLength);
            }
            $password = $username;
            $comment = "vc-QRIS-" . $found_order_id . "-" . date("m.d.y");
            
            $addParams = [
                "server" => "all",
                "name" => $username,
                "password" => $password,
                "profile" => $profile_to_buy,
                "comment" => $comment
            ];
            
            $result = $API->comm("/ip/hotspot/user/add", $addParams);
            
            if (!isset($result['!trap'])) {
                // Berhasil — update status dari 'processing' ke 'settlement'
                $trans['status'] = 'settlement';
                $trans['username'] = $username;
                $trans['password'] = $password;
                writeAppLog("TRANSACTION_SUCCESS", "Voucher " . $username . " berhasil digenerate via QRIS untuk Order ID: " . $found_order_id . " | Profil: " . $profile_to_buy . " | Nominal: Rp " . number_format($nominal, 0, ',', '.'));
                
                // Kirim notifikasi Telegram ke Admin
                $telegramMessage = "✅ <b>[MikhPay] Pembayaran QRIS Berhasil!</b>\n\n"
                    . "<b>Order ID:</b> <code>{$found_order_id}</code>\n"
                    . "<b>Sesi Router:</b> <code>{$selected_session}</code>\n"
                    . "<b>Profil:</b> <code>{$profile_to_buy}</code>\n"
                    . "<b>Voucher:</b> <code>{$username}</code>\n"
                    . "<b>Nominal:</b> Rp " . number_format($nominal, 0, ',', '.') . "\n"
                    . "Voucher telah otomatis diterbitkan.";
                sendTelegramNotification($telegramMessage);
            } else {
                // Gagal buat user MikroTik
                $trans['status'] = 'paid_pending_generate';
                writeAppLog("QRIS_VERIFY_ERROR", "Gagal menambahkan user hotspot: " . json_encode($result));
            }
            $API->disconnect();
        } else {
            $trans['status'] = 'paid_pending_generate';
            $trans['router_error'] = "ErrNo: " . $API->error_no . ", ErrStr: " . $API->error_str;
            writeAppLog("QRIS_VERIFY_ERROR", "Gagal koneksi ke router (" . $trans['router_error'] . ") saat verifikasi QRIS nominal: " . $nominal);
        }
    } else {
        $trans['status'] = 'paid_pending_generate';
        $trans['router_error'] = "Session " . $selected_session . " tidak ditemukan di database.";
        writeAppLog("QRIS_VERIFY_ERROR", "Session router '" . $selected_session . "' tidak ditemukan saat verifikasi QRIS nominal: " . $nominal);
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
