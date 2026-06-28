<?php
/**
 * Admin Check Updates Endpoint
 * Memeriksa transaksi sukses terbaru untuk notifikasi suara & visual di panel admin.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Proteksi akses: wajib login admin mikhmon
if (!isset($_SESSION["mikhmon"])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once(__DIR__ . '/../include/config.php');
include_once(__DIR__ . '/../include/env_config.php');

$last_check_time = isset($_GET['last_check_time']) ? (int)$_GET['last_check_time'] : time();

$dir = __DIR__ . '/../voucher/';
$files = array_merge(
    glob($dir . 'trans-*.json'),
    glob($dir . 'trans-*.php')
);

$new_payments = [];
$total_revenue = 0;
$success_count = 0;
$pending_count = 0;

if (is_array($files)) {
    foreach ($files as $file) {
        $transData = readTransactionFile($file);
        if ($transData) {
            $status = isset($transData['status']) ? $transData['status'] : 'pending';
            $price = isset($transData['price']) ? (float)$transData['price'] : 0.0;
            
            if ($status === 'settlement' || $status === 'capture') {
                $total_revenue += $price;
                $success_count++;
                
                $paid_at = isset($transData['paid_at']) ? (int)$transData['paid_at'] : filemtime($file);
                if ($paid_at > $last_check_time) {
                    $new_payments[] = [
                        'order_id' => isset($transData['order_id']) ? $transData['order_id'] : basename($file),
                        'profile' => isset($transData['profile']) ? $transData['profile'] : 'Unknown',
                        'price' => $price,
                        'paid_at' => $paid_at
                    ];
                }
            } elseif ($status === 'paid_pending_generate') {
                $pending_count++;
            }
        }
    }
}

echo json_encode([
    'status' => 'success',
    'total_revenue' => $total_revenue,
    'success_count' => $success_count,
    'pending_count' => $pending_count,
    'new_payments' => $new_payments,
    'server_time' => time()
]);
exit;
