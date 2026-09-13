<?php
/**
 * GoPay Merchant Mutation Auto-Sync Background Job (Cron / Worker)
 * MikhPay Engine
 * 
 * Script ini dapat dijalankan secara periodik melalui:
 * 1. CLI (Windows Task Scheduler / Linux Cron):
 *    php process/gopay_sync.php
 * 2. HTTP Web Request (CURL / Browser):
 *    http://localhost/mikhmon/process/gopay_sync.php?api_key=YOUR_MIKHMON_API_KEY
 */

// Disable execution time limit for CLI
if (php_sapi_name() === 'cli') {
    set_time_limit(0);
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Load config and dependencies
require_once dirname(__FILE__) . '/../include/config.php';
require_once dirname(__FILE__) . '/../include/env_config.php';
require_once dirname(__FILE__) . '/../include/autoload.php';

// Security check for HTTP/Web requests
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

// Inisialisasi Service GoPay Merchant
$gopayService = new \App\Services\GoPayMerchantService();
$status = $gopayService->getStatus();

// Periksa apakah fitur sinkronisasi diaktifkan dan akun terhubung
if (!$status['is_connected']) {
    $output = [
        'status' => 'skipped',
        'message' => 'Akun GoPay Merchant belum terhubung. Konfigurasikan di menu MikhPay Billing > GoPay Merchant.',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if (php_sapi_name() === 'cli') {
        echo "[GOPAY_SYNC] " . $output['message'] . PHP_EOL;
    } else {
        echo json_encode($output);
    }
    exit;
}

if (!$status['enabled'] && php_sapi_name() === 'cli') {
    $output = [
        'status' => 'disabled',
        'message' => 'Auto-Sync GoPay sedang dinonaktifkan di Pengaturan.',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo "[GOPAY_SYNC] " . $output['message'] . PHP_EOL;
    exit;
}

// Jalankan pencocokan transaksi pending
$syncResult = $gopayService->syncPendingOrders();

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] [GOPAY_SYNC] " . $syncResult['message'] . PHP_EOL;
    if (!empty($syncResult['details'])) {
        foreach ($syncResult['details'] as $det) {
            echo "  -> Order ID: {$det['order_id']} | Rp {$det['amount']} | Voucher: {$det['voucher']} | GoPay ID: {$det['gopay_trans_id']}" . PHP_EOL;
        }
    }
} else {
    echo json_encode([
        'status' => $syncResult['success'] ? 'success' : 'error',
        'matched_count' => $syncResult['matched'],
        'message' => $syncResult['message'],
        'details' => isset($syncResult['details']) ? $syncResult['details'] : [],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
