<?php
/**
 * Cronjob / Cleaner untuk Transaksi QRIS Pending
 * Berfungsi untuk menghapus transaksi yang menggantung (lebih dari 15 menit)
 * agar kode unik bisa digunakan kembali.
 */

require_once dirname(__FILE__) . '/../include/config.php';

// Security check for HTTP/Web requests — wajib API Key
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : (isset($headers['x-api-key']) ? $headers['x-api-key'] : (isset($_GET['api_key']) ? $_GET['api_key'] : ''));
    
    if (empty($mikhmon_api_key) || $apiKey !== $mikhmon_api_key) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden. Invalid API Key.']);
        exit;
    }
}
$dir = __DIR__ . '/../voucher/';
$expiration_time = 10 * 60; // 10 menit

if (is_dir($dir)) {
    $files = array_merge(
        glob($dir . 'trans-*.json'),
        glob($dir . 'trans-*.php')
    );
    $now = time();
    $deleted_count = 0;
    
    foreach ($files as $file) {
        $data = readTransactionFile($file);
        
        // Hapus hanya yang status pending dan usianya melebihi expiration_time
        if ($data && isset($data['status']) && $data['status'] === 'pending') {
            $created = isset($data['created_at']) ? $data['created_at'] : filemtime($file);
            if (($now - $created) > $expiration_time) {
                @unlink($file);
                $deleted_count++;
            }
        }
    }
    
    if (php_sapi_name() === 'cli' || isset($_GET['debug'])) {
        echo "Deleted $deleted_count expired pending transaction(s).\n";
    }
}
?>
