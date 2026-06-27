<?php
/**
 * Cronjob / Cleaner untuk Transaksi QRIS Pending
 * Berfungsi untuk menghapus transaksi yang menggantung (lebih dari 15 menit)
 * agar kode unik bisa digunakan kembali.
 */

$dir = __DIR__ . '/../voucher/';
$expiration_time = 15 * 60; // 15 menit

if (is_dir($dir)) {
    $files = glob($dir . 'trans-*.json');
    $now = time();
    $deleted_count = 0;
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        
        // Hapus hanya yang status pending dan usianya melebihi expiration_time
        if (isset($data['status']) && $data['status'] === 'pending') {
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
