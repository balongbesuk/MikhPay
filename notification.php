<?php
/**
 * Webhook/Notification Handler Midtrans
 * Menerima sinyal pembayaran dari Midtrans, memverifikasi tanda tangan keamanan,
 * lalu men-generate voucher otomatis di MikroTik secara aman & idempoten.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Matikan display_errors di produksi untuk keamanan
date_default_timezone_set('Asia/Jakarta');

// Rate Limiting (Maksimum 60 request per menit per IP untuk webhook)
$ipAddress = isset($_SERVER['HTTP_CLIENT_IP']) 
    ? $_SERVER['HTTP_CLIENT_IP'] 
    : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) 
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] 
        : $_SERVER['REMOTE_ADDR']);

$rateLimitFile = __DIR__ . '/data/rate_limit.json';
$limit = 60; // request
$period = 60; // seconds

$allowed = true;
$now = time();

if (file_exists($rateLimitFile) || is_writable(dirname($rateLimitFile))) {
    $fp = fopen($rateLimitFile, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $size = @filesize($rateLimitFile);
            $data = $size > 0 ? json_decode(fread($fp, $size), true) : [];
            if (!is_array($data)) $data = [];
            
            // Clean up expired records
            foreach ($data as $ip => $record) {
                if (isset($record['reset_time']) && $record['reset_time'] < $now) {
                    unset($data[$ip]);
                }
            }
            
            if (isset($data[$ipAddress])) {
                $record = $data[$ipAddress];
                if (isset($record['reset_time']) && $record['reset_time'] > $now) {
                    if (isset($record['count']) && $record['count'] >= $limit) {
                        $allowed = false;
                    } else {
                        $data[$ipAddress]['count'] = isset($data[$ipAddress]['count']) ? $data[$ipAddress]['count'] + 1 : 1;
                    }
                } else {
                    $data[$ipAddress] = [
                        'count' => 1,
                        'reset_time' => $now + $period
                    ];
                }
            } else {
                $data[$ipAddress] = [
                    'count' => 1,
                    'reset_time' => $now + $period
                ];
            }
            
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

if (!$allowed) {
    http_response_code(429);
    echo "Too Many Requests. Webhook rate limit exceeded.";
    exit;
}

// Load config
if (!file_exists(__DIR__ . '/include/config.php')) {
    http_response_code(500);
    echo "Config file not found.";
    exit;
}
include_once(__DIR__ . '/include/config.php');
include_once(__DIR__ . '/include/env_config.php');
include_once(__DIR__ . '/lib/routeros_api.class.php');
include_once(__DIR__ . '/lib/formatbytesbites.php');

// Helper untuk memicu event Pusher/Soketi tanpa dependensi eksternal Composer
function triggerWebSocketPaidEvent($app_id, $key, $secret, $cluster, $channel, $event, $data) {
    $auth_version = '1.0';
    $auth_key = $key;
    $auth_timestamp = time();
    
    $body = json_encode([
        'name' => $event,
        'channel' => $channel,
        'data' => json_encode($data)
    ]);
    
    $body_md5 = md5($body);
    $path = "/apps/{$app_id}/events";
    
    // Buat signature Pusher REST API
    $params = "auth_key={$auth_key}&auth_timestamp={$auth_timestamp}&auth_version={$auth_version}&body_md5={$body_md5}";
    $string_to_sign = "POST\n{$path}\n{$params}";
    $auth_signature = hash_hmac('sha256', $string_to_sign, $secret);
    
    // Deteksi host WebSocket (Self-hosted Soketi atau official Cloud Pusher)
    $ws_host = getenv('WS_HOST') ?: "api-{$cluster}.pusher.com";
    $ws_scheme = getenv('WS_SCHEME') ?: "http";
    $ws_port = getenv('WS_PORT') ?: ($ws_scheme === 'https' ? '443' : '80');
    
    $url = "{$ws_scheme}://{$ws_host}:{$ws_port}{$path}?{$params}&auth_signature={$auth_signature}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    return $response;
}

// Whitelist IP Midtrans (Hanya diaktifkan secara ketat pada mode produksi)
$incoming_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$is_midtrans_ip = false;
$midtrans_prefixes = ['103.208.23.', '103.56.202.', '103.56.200.'];
foreach ($midtrans_prefixes as $prefix) {
    if (strpos($incoming_ip, $prefix) === 0) {
        $is_midtrans_ip = true;
        break;
    }
}
// Selalu ijinkan localhost/loopback, dan non-produksi (sandbox / testing tunnel)
if ($incoming_ip === '127.0.0.1' || $incoming_ip === '::1' || !$midtrans_is_production) {
    $is_midtrans_ip = true;
}

if (!$is_midtrans_ip) {
    writeAppLog("SECURITY_WARNING", "Webhook diakses dari IP tidak sah: " . $incoming_ip);
    http_response_code(403);
    echo "Access Denied. Invalid IP Origin.";
    exit;
}

// Tangkap POST data dari Midtrans
$json_str = file_get_contents('php://input');
$notification = json_decode($json_str, true);

if (!$notification) {
    http_response_code(400);
    echo "Invalid request payload.";
    exit;
}

$order_id = isset($notification['order_id']) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $notification['order_id']) : '';
$status_code = isset($notification['status_code']) ? $notification['status_code'] : '';
$gross_amount = isset($notification['gross_amount']) ? $notification['gross_amount'] : '';
$transaction_status = isset($notification['transaction_status']) ? $notification['transaction_status'] : '';
$signature_key = isset($notification['signature_key']) ? $notification['signature_key'] : '';

// Validasi Keamanan (Signature Key Verification)
$local_signature = hash("sha512", $order_id . $status_code . $gross_amount . $midtrans_server_key);

if ($local_signature !== $signature_key) {
    writeAppLog("SECURITY_WARNING", "Akses ilegal! Signature mismatch untuk Order ID: " . $order_id);
    http_response_code(401);
    echo "Unauthorized. Signature key mismatch.";
    exit;
}

// Buka file transaksi pending
$filepath = __DIR__ . "/voucher/trans-" . $order_id . ".json";
if (!file_exists($filepath)) {
    writeAppLog("WEBHOOK_ERROR", "Order ID tidak ditemukan: " . $order_id);
    http_response_code(404);
    echo "Transaction order file not found.";
    exit;
}

$trans = json_decode(file_get_contents($filepath), true);

// Jika status pembayaran sukses (settlement atau capture)
if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
    
    // Cek Idempotensi (jika sudah lunas sebelumnya, kembalikan HTTP 200 sukses langsung)
    if (isset($trans['status']) && $trans['status'] === 'settlement' && !empty($trans['username'])) {
        writeAppLog("WEBHOOK_INFO", "Respon idempoten: Order ID " . $order_id . " sudah lunas sebelumnya.");
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    // Verifikasi Ganda: tanyakan langsung ke API Midtrans
    $statusUrl = $midtrans_is_production 
        ? "https://api.midtrans.com/v2/{$order_id}/status" 
        : "https://api.sandbox.midtrans.com/v2/{$order_id}/status";
        
    $auth = base64_encode($midtrans_server_key . ":");
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
                        "Authorization: Basic $auth\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    
    $statusResponse = @file_get_contents($statusUrl, false, $context);
    $statusData = $statusResponse ? json_decode($statusResponse, true) : null;
    
    if (!$statusData || !isset($statusData['transaction_status']) || !in_array($statusData['transaction_status'], ['settlement', 'capture'])) {
        writeAppLog("SECURITY_WARNING", "Verifikasi ganda gagal! Status transaksi di Midtrans tidak valid untuk Order ID: " . $order_id);
        http_response_code(400);
        echo "Failed double check transaction verification.";
        exit;
    }
    
    // Cocokkan nominal pembayaran
    $apiAmount = isset($statusData['gross_amount']) ? (float)$statusData['gross_amount'] : 0.0;
    $localAmount = isset($trans['price']) ? (float)$trans['price'] : 0.0;
    
    if (abs($apiAmount - $localAmount) > 0.01) {
        writeAppLog("SECURITY_WARNING", "Verifikasi ganda gagal! Nominal tidak cocok. Midtrans: {$apiAmount}, Lokal: {$localAmount} untuk Order ID: " . $order_id);
        http_response_code(400);
        echo "Transaction amount mismatch.";
        exit;
    }

    $session = $trans['session'];
    $profile = $trans['profile'];

    if (!isset($data[$session])) {
        writeAppLog("WEBHOOK_ERROR", "Sesi router tidak terkonfigurasi untuk: " . $session);
        http_response_code(500);
        echo "Router session not configured.";
        exit;
    }

    // Sambungkan ke MikroTik
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
        
        // Pembuatan Kode Voucher (User = Password, panjang 5 karakter)
        $userLength = 5;
        $shuf = $userLength;
        $a = ["1" => "", "", 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6];
        $shuf = $userLength - (isset($a[$userLength]) ? (int)$a[$userLength] : 2);
        
        $username = randNLC($shuf) . randN($userLength - $shuf);
        $password = $username;
        
        $comment = "API-" . rand(100, 999) . "-" . date("m.d.y") . "-Paid QRIS";

        // Tambah ke MikroTik
        $addParams = [
            "server" => "all",
            "name" => $username,
            "password" => $password,
            "profile" => $profile,
            "comment" => $comment
        ];

        $API->comm("/ip/hotspot/user/add", $addParams);
        $API->disconnect();

        // Update data transaksi menjadi Lunas & simpan detail voucher
        $trans['status'] = 'settlement';
        $trans['username'] = $username;
        $trans['password'] = $password;
        $trans['paid_at'] = time();

        file_put_contents($filepath, json_encode($trans));
        writeAppLog("WEBHOOK_SUCCESS", "Voucher " . $username . " sukses digenerate untuk Order ID: " . $order_id);

        // Jika integrasi WebSocket diaktifkan, push notifikasi langsung ke browser pelanggan
        if (!empty($ws_app_key)) {
            triggerWebSocketPaidEvent(
                $ws_app_id, 
                $ws_app_key, 
                $ws_app_secret, 
                $ws_cluster, 
                'order-' . $order_id, 
                'paid', 
                [
                    'status' => 'success',
                    'order_id' => $order_id
                ]
            );
        }

        http_response_code(200);
        echo "Success. Voucher generated: " . $username;
        exit;
    } else {
        writeAppLog("WEBHOOK_ERROR", "Gagal koneksi API MikroTik saat notifikasi lunas untuk Order ID: " . $order_id);
        
        $trans['status'] = 'paid_pending_generate';
        $trans['paid_at'] = time();
        file_put_contents($filepath, json_encode($trans));
        
        // Kirim notifikasi Telegram ke Admin
        $telegramMessage = "⚠️ <b>[MikhPay Alert] Gagal Generate Voucher!</b>\n\n"
            . "<b>Order ID:</b> <code>{$order_id}</code>\n"
            . "<b>Sesi Router:</b> <code>{$session}</code>\n"
            . "<b>Profil/Paket:</b> {$profile}\n"
            . "<b>Nominal:</b> Rp " . number_format($trans['price'], 0, ',', '.') . "\n"
            . "<b>Status:</b> Tertunda (Router Offline)\n\n"
            . "Silakan periksa koneksi router Anda dan lakukan generate manual melalui panel <b>Antrean Webhook</b>.";
        sendTelegramNotification($telegramMessage);
        
        http_response_code(500);
        echo "Failed to connect to MikroTik router to generate voucher. Marked as paid_pending_generate.";
        exit;
    }

} elseif (in_array($transaction_status, ['deny', 'cancel', 'expire'])) {
    // Transaksi gagal / dibatalkan
    $trans['status'] = 'failed';
    file_put_contents($filepath, json_encode($trans));
    writeAppLog("WEBHOOK_INFO", "Order ID " . $order_id . " diperbarui ke status: failed (" . $transaction_status . ")");
    http_response_code(200);
    echo "Transaction updated to failed.";
    exit;
}

http_response_code(200);
echo "Transaction state: " . $transaction_status;
