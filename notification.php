<?php
/**
 * Webhook/Notification Handler Midtrans
 * Menerima sinyal pembayaran dari Midtrans, memverifikasi tanda tangan keamanan,
 * lalu men-generate voucher otomatis di MikroTik secara aman & idempoten.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

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
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    return $response;
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
        http_response_code(500);
        echo "Failed to connect to MikroTik router to generate voucher.";
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
