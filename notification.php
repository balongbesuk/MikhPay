<?php
/**
 * Webhook/Notification Handler Midtrans
 * Menerima sinyal pembayaran dari Midtrans, memverifikasi tanda tangan keamanan,
 * lalu men-generate voucher otomatis di MikroTik.
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
include_once(__DIR__ . '/lib/routeros_api.class.php');
include_once(__DIR__ . '/lib/formatbytesbites.php');

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
    http_response_code(401);
    echo "Unauthorized. Signature key mismatch.";
    exit;
}

// Buka file transaksi pending
$filepath = __DIR__ . "/voucher/trans-" . $order_id . ".json";
if (!file_exists($filepath)) {
    http_response_code(404);
    echo "Transaction order file not found.";
    exit;
}

$trans = json_decode(file_get_contents($filepath), true);

// Jika status pembayaran sukses (settlement atau capture)
if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
    
    // Cek apakah voucher sudah pernah dibuat sebelumnya (mencegah duplikasi)
    if (isset($trans['status']) && $trans['status'] === 'settlement' && !empty($trans['username'])) {
        echo "Transaction already processed.";
        exit;
    }

    $session = $trans['session'];
    $profile = $trans['profile'];

    if (!isset($data[$session])) {
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
        echo "Success. Voucher generated: " . $username;
        exit;
    } else {
        http_response_code(500);
        echo "Failed to connect to MikroTik router to generate voucher.";
        exit;
    }

} elseif (in_array($transaction_status, ['deny', 'cancel', 'expire'])) {
    // Transaksi gagal / dibatalkan
    $trans['status'] = 'failed';
    file_put_contents($filepath, json_encode($trans));
    echo "Transaction updated to failed.";
    exit;
}

echo "Transaction state: " . $transaction_status;
