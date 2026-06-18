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
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
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
        $logFile = $logDir . '/error.log';
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
        $logMessage = sprintf("[%s] [%s] [%s] %s\n", date('Y-m-d H:i:s'), $type, $ip, $message);
        error_log($logMessage, 3, $logFile);
    }
}

// Midtrans & WebSocket environment variables config overrides
$mikhmon_api_key = getenv('MIKHMON_API_KEY') ?: "YOUR_MIKHMON_API_KEY_HERE";
$midtrans_server_key = getenv('MIDTRANS_SERVER_KEY') ?: "YOUR_MIDTRANS_SERVER_KEY_HERE";
$midtrans_client_key = getenv('MIDTRANS_CLIENT_KEY') ?: "YOUR_MIDTRANS_CLIENT_KEY_HERE";
$midtrans_is_production = filter_var(getenv('MIDTRANS_IS_PRODUCTION') ?: false, FILTER_VALIDATE_BOOLEAN);

$ws_app_id = getenv('WS_APP_ID') ?: "";
$ws_app_key = getenv('WS_APP_KEY') ?: "";
$ws_app_secret = getenv('WS_APP_SECRET') ?: "";
$ws_cluster = getenv('WS_CLUSTER') ?: "ap1";

$ws_host = getenv('WS_HOST') ?: "";
$ws_port = getenv('WS_PORT') ?: "";
$ws_scheme = getenv('WS_SCHEME') ?: "";
