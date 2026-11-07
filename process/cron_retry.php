<?php
/**
 * MikhPay Auto-Retry Background Job (Cron)
 * v1.4
 */

// Disable execution time limit for CLI cron job
if (php_sapi_name() === 'cli') {
    set_time_limit(0);
}

// Set timezone Asia/Jakarta agar catatan waktu lunas sinkron dengan MikroTik
date_default_timezone_set('Asia/Jakarta');

// Load config and dependencies
require_once dirname(__FILE__) . '/../include/config.php';
require_once dirname(__FILE__) . '/../include/qris_core.php';

// Security check for HTTP/Web requests
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    // Support X-API-Key in HTTP headers with fallback to query parameter
    $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : (isset($headers['x-api-key']) ? $headers['x-api-key'] : (isset($_GET['api_key']) ? $_GET['api_key'] : ''));
    
    if (empty($mikhmon_api_key) || $apiKey !== $mikhmon_api_key) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden. Invalid API Key.']);
        exit;
    }
}

$dir = dirname(__FILE__) . '/../voucher/';
$files = array_merge(
    glob($dir . 'trans-*.json'),
    glob($dir . 'trans-*.php')
);
$processedCount = 0;
$failedCount = 0;

if (is_array($files)) {
    $now = time();
    foreach ($files as $file) {
        $trans = readTransactionFile($file);
        if (!$trans || !isset($trans['status'])) continue;
        $st = $trans['status'];
        $paidAt = isset($trans['paid_at']) ? (int)$trans['paid_at'] : (isset($trans['created_at']) ? (int)$trans['created_at'] : 0);
        // Hanya retry 'processing' jika sudah menggantung > 60 detik agar tidak bentrok dengan settlement aktif
        if ($st === 'paid_pending_generate' || ($st === 'processing' && ($paidAt === 0 || ($now - $paidAt) > 60))) {
            $order_id = isset($trans['order_id']) ? $trans['order_id'] : basename($file, (strpos($file, '.php') !== false ? '.php' : '.json'));
            $session = $trans['session'];
            $comment = "vc-API-Cron-" . rand(100, 999) . "-" . date("m.d.y") . "-Paid QRIS";

            if (generateHotspotVoucherForOrder($trans, $comment)) {
                $username = $trans['username'];
                if (strpos($file, '.php') !== false) {
                    writeTransactionFile($file, $trans);
                } else {
                    file_put_contents($file, json_encode($trans));
                }
                $processedCount++;
                writeAppLog("CRON_RETRY_SUCCESS", "Voucher " . $username . " sukses digenerate via Cron untuk Order ID: " . $order_id);

                $telegramMessage = "✅ <b>[MikhPay Cron] Voucher Berhasil Diterbitkan!</b>\n\n"
                    . "<b>Order ID:</b> <code>{$order_id}</code>\n"
                    . "<b>Sesi Router:</b> <code>{$session}</code>\n"
                    . "<b>Voucher:</b> <code>{$username}</code>\n"
                    . "<b>Nominal:</b> Rp " . number_format($trans['price'], 0, ',', '.') . "\n"
                    . "Status tertunda sebelumnya telah diselesaikan secara otomatis.";
                sendTelegramNotification($telegramMessage);
            } else {
                $failedCount++;
                writeAppLog("CRON_RETRY_ERROR", "Gagal retry Order ID: " . $order_id . " (" . (isset($trans['router_error']) ? $trans['router_error'] : 'unknown') . ")");
            }
        }
    }
}

// Output summary response
if (php_sapi_name() === 'cli') {
    echo "Cron completed. Processed: " . $processedCount . ", Failed: " . $failedCount . "\n";
} else {
    echo json_encode([
        'status' => 'completed',
        'processed_retries' => $processedCount,
        'failed_retries' => $failedCount
    ]);
}
?>
