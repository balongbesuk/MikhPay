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
require_once dirname(__FILE__) . '/../lib/routeros_api.class.php';
require_once dirname(__FILE__) . '/../lib/formatbytesbites.php';

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
    foreach ($files as $file) {
        $trans = readTransactionFile($file);
        if ($trans && isset($trans['status']) && ($trans['status'] === 'paid_pending_generate' || $trans['status'] === 'processing')) {
            $order_id = isset($trans['order_id']) ? $trans['order_id'] : basename($file, (strpos($file, '.php') !== false ? '.php' : '.json'));
            $session = $trans['session'];
            $profile = $trans['profile'];
            
            if (isset($data[$session])) {
                $iphost = explode('!', $data[$session][1])[1];
                $userhost = explode('@|@', $data[$session][2])[1];
                $passwdhost = explode('#|#', $data[$session][3])[1];
                
                $API = new RouterosAPI();
                $API->debug = false;
                
                if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
                    // Generate voucher code
                    $userLength = 5;
                    $shuf = $userLength;
                    $a = ["1" => "", "", 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6];
                    $shuf = $userLength - (isset($a[$userLength]) ? (int)$a[$userLength] : 2);
                    
                    $username = randNLC($shuf) . randN($userLength - $shuf);
                    $password = $username;
                    
                    $comment = "vc-API-Cron-" . rand(100, 999) . "-" . date("m.d.y") . "-Paid QRIS";
                    
                    $addParams = [
                        "server" => "all",
                        "name" => $username,
                        "password" => $password,
                        "profile" => $profile,
                        "comment" => $comment
                    ];
                    
                    $API->comm("/ip/hotspot/user/add", $addParams);
                    $API->disconnect();
                    
                    // Update transaction
                    $trans['status'] = 'settlement';
                    $trans['username'] = $username;
                    $trans['password'] = $password;
                    $trans['paid_at'] = time();
                    
                    if (strpos($file, '.php') !== false) {
                        writeTransactionFile($file, $trans);
                    } else {
                        file_put_contents($file, json_encode($trans));
                    }
                    $processedCount++;
                    writeAppLog("CRON_RETRY_SUCCESS", "Voucher " . $username . " sukses digenerate via Cron untuk Order ID: " . $order_id);
                    
                    // Kirim notifikasi Telegram sukses ke Admin
                    $telegramMessage = "✅ <b>[MikhPay Cron] Voucher Berhasil Diterbitkan!</b>\n\n"
                        . "<b>Order ID:</b> <code>{$order_id}</code>\n"
                        . "<b>Sesi Router:</b> <code>{$session}</code>\n"
                        . "<b>Voucher:</b> <code>{$username}</code>\n"
                        . "<b>Nominal:</b> Rp " . number_format($trans['price'], 0, ',', '.') . "\n"
                        . "Status tertunda sebelumnya telah diselesaikan secara otomatis.";
                    sendTelegramNotification($telegramMessage);
                } else {
                    $failedCount++;
                }
            } else {
                writeAppLog("CRON_RETRY_ERROR", "Sesi router '{$session}' untuk Order ID: " . $order_id . " tidak terkonfigurasi.");
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
