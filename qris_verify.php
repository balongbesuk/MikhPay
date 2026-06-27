<?php
/**
 * QRIS Verification Endpoint
 * Digunakan oleh MacroDroid atau admin secara manual untuk memverifikasi
 * pembayaran QRIS yang masuk, menggunakan pencocokan "nominal unik".
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once(__DIR__ . '/include/config.php');
include_once(__DIR__ . '/include/env_config.php');

// Set header JSON
header('Content-Type: application/json');

// Ambil input GET atau POST
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
$nominal = isset($_REQUEST['nominal']) ? (int)preg_replace('/[^0-9]/', '', $_REQUEST['nominal']) : 0;

// Validasi Token
if (empty($qris_secret_token) || $token !== $qris_secret_token) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid.']);
    exit;
}

// Validasi Nominal
if ($nominal <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nominal tidak valid.']);
    exit;
}

// Cari transaksi pending dengan nominal tersebut
$dir = __DIR__ . '/voucher/';
$found_order_id = null;
$trans = null;

if (is_dir($dir)) {
    $files = glob($dir . 'trans-*.json');
    foreach ($files as $file) {
        $transData = json_decode(file_get_contents($file), true);
        if (isset($transData['status']) && $transData['status'] === 'pending' && isset($transData['price']) && (int)$transData['price'] === $nominal) {
            $found_order_id = $transData['order_id'];
            $trans = $transData;
            break;
        }
    }
}

if (!$found_order_id) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Transaksi pending dengan nominal ' . $nominal . ' tidak ditemukan.']);
    exit;
}

// Tandai sebagai settlement
$trans['status'] = 'settlement';
$trans['paid_at'] = time();

// Hubungkan ke MikroTik untuk buat voucher
include_once(__DIR__ . '/lib/routeros_api.class.php');
$selected_session = $trans['session'];
$profile_to_buy = $trans['profile'];

// Ambil login data router
global $data; // dari config.php
if (isset($data[$selected_session])) {
    $iphost = explode('!', $data[$selected_session][1])[1];
    $userhost = explode('@|@', $data[$selected_session][2])[1];
    $passwdhost = explode('#|#', $data[$selected_session][3])[1];
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
        // Generate Username & Password acak
        include_once(__DIR__ . '/include/functions.php'); // Jika ada fungsi helper tambahan
        
        $userLength = 5;
        // Basic random string jika fungsi randNLC tidak tersedia
        $username = substr(str_shuffle("abcdefghjkmnpqrstuvwxyz23456789"), 0, $userLength);
        $password = $username;
        $comment = "QRIS-" . $found_order_id . "-" . date("m.d.y");
        
        $addParams = [
            "server" => "all",
            "name" => $username,
            "password" => $password,
            "profile" => $profile_to_buy,
            "comment" => $comment
        ];
        
        $result = $API->comm("/ip/hotspot/user/add", $addParams);
        
        if (!isset($result['!trap'])) {
            // Berhasil
            $trans['username'] = $username;
            $trans['password'] = $password;
        } else {
            // Gagal buat voucher
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
}

// Simpan kembali status JSON
file_put_contents($dir . "trans-" . $found_order_id . ".json", json_encode($trans));

echo json_encode([
    'status' => 'success',
    'message' => 'Pembayaran berhasil dikonfirmasi.',
    'order_id' => $found_order_id,
    'voucher_status' => $trans['status'],
    'router_error' => isset($trans['router_error']) ? $trans['router_error'] : null
]);
?>
