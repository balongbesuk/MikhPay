<?php
// Helper function to load environment variables from .env
if (!function_exists('loadMikhmonEnv')) {
    function loadMikhmonEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Strip surrounding quotes
                $value = trim($value, '"\'');
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    if (function_exists('putenv')) {
                        putenv(sprintf('%s=%s', $name, $value));
                    }
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Helper function to safely read environment variables
if (!function_exists('mikhmonEnv')) {
    function mikhmonEnv($name, $default = null) {
        if (isset($_ENV[$name])) {
            return $_ENV[$name];
        }
        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }
        $val = function_exists('getenv') ? getenv($name) : false;
        return $val !== false ? $val : $default;
    }
}

// Load .env configuration from root
loadMikhmonEnv(__DIR__ . '/../.env');

// Helper function for structured logging
if (!function_exists('writeAppLog')) {
    function writeAppLog($type, $message) {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
            file_put_contents($logDir . '/.htaccess', "Deny from all\n");
        }
        
        $logFile = $logDir . '/error.log.php';
        $prefix = "<?php header('HTTP/1.0 403 Forbidden'); exit; ?>\n";
        
        // Log Rotation if size > 5MB
        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            $archiveFile = $logDir . '/error-' . date('Ymd-His') . '.log.php';
            @rename($logFile, $archiveFile);
            
            // Clean up old archives, keep only the latest 3
            $archives = glob($logDir . '/error-*.log.php');
            if (is_array($archives) && count($archives) > 3) {
                sort($archives);
                for ($i = 0; $i < count($archives) - 3; $i++) {
                    @unlink($archives[$i]);
                }
            }
        }
        
        $isNewFile = !file_exists($logFile);
        $fp = fopen($logFile, 'ab');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                if ($isNewFile) {
                    fwrite($fp, $prefix);
                }
                $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
                $logMessage = sprintf("[%s] [%s] [%s] %s\n", date('Y-m-d H:i:s'), $type, $ip, $message);
                fwrite($fp, $logMessage);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}

// Helper untuk mengirim notifikasi Telegram
if (!function_exists('sendTelegramNotification')) {
    function sendTelegramNotification($message) {
        $dbSettings = new \App\Models\AppSettings();
        $token = $dbSettings->get('telegram_bot_token', mikhmonEnv('TELEGRAM_BOT_TOKEN', ''));
        $chatId = $dbSettings->get('telegram_chat_id', mikhmonEnv('TELEGRAM_CHAT_ID', ''));
        
        if (empty($token) || empty($chatId)) {
            return false;
        }
        
        $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
        $data = array(
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        );
        
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            )
        );
        
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            writeAppLog("TELEGRAM_ERROR", "Gagal mengirim notifikasi Telegram.");
            return false;
        }
        return true;
    }
}

// Midtrans & WebSocket environment variables config overrides
$mikhmon_api_key = mikhmonEnv('MIKHMON_API_KEY', "YOUR_MIKHMON_API_KEY_HERE");
$midtrans_server_key = mikhmonEnv('MIDTRANS_SERVER_KEY', "YOUR_MIDTRANS_SERVER_KEY_HERE");
$midtrans_client_key = mikhmonEnv('MIDTRANS_CLIENT_KEY', "YOUR_MIDTRANS_CLIENT_KEY_HERE");
$midtrans_is_production = filter_var(mikhmonEnv('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);

$ws_app_id = mikhmonEnv('WS_APP_ID', "");
$ws_app_key = mikhmonEnv('WS_APP_KEY', "");
$ws_app_secret = mikhmonEnv('WS_APP_SECRET', "");
$ws_cluster = mikhmonEnv('WS_CLUSTER', "ap1");

$ws_host = mikhmonEnv('WS_HOST', "");
$ws_port = mikhmonEnv('WS_PORT', "");
$ws_scheme = mikhmonEnv('WS_SCHEME', "");

$encryption_key = mikhmonEnv('ENCRYPTION_KEY', "128");
