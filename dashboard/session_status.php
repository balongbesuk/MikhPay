<?php
/**
 * Real-Time Session Status JSON API
 * MikhTrans v1.1
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);

// Validate admin session
if (!isset($_SESSION["mikhmon"])) {
    http_response_code(403);
    echo json_encode(['status' => 'offline', 'error' => 'Forbidden. Admin session required.']);
    exit;
}

header('Content-Type: application/json');

// Load App configuration
if (!file_exists(__DIR__ . '/../include/config.php')) {
    echo json_encode(['status' => 'offline', 'error' => 'Config not found']);
    exit;
}
include_once(__DIR__ . '/../include/config.php');
include_once(__DIR__ . '/../lib/routeros_api.class.php');

$session = isset($_GET['session']) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['session']) : '';

if (empty($session) || !isset($data[$session])) {
    echo json_encode(['status' => 'offline', 'error' => 'Invalid or missing session name']);
    exit;
}

$iphost = explode('!', $data[$session][1])[1];
$userhost = explode('@|@', $data[$session][2])[1];
$passwdhost = explode('#|#', $data[$session][3])[1];

$API = new RouterosAPI();
$API->debug = false;

// Attempt to connect to MikroTik router (timeout 2 seconds for quick response)
$API->timeout = 2;
if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
    // 1. Fetch system resource (uptime, cpu-load)
    $resource = $API->comm("/system/resource/print", [
        ".proplist" => "uptime,cpu-load"
    ]);
    
    // 2. Fetch active hotspot users (count only)
    $activeUsers = $API->comm("/ip/hotspot/active/print", [
        ".proplist" => ".id"
    ]);
    
    // 3. Fetch total hotspot users (count only)
    $totalUsers = $API->comm("/ip/hotspot/user/print", [
        ".proplist" => ".id"
    ]);
    
    $API->disconnect();
    
    $uptime = isset($resource[0]['uptime']) ? $resource[0]['uptime'] : '-';
    $cpu = isset($resource[0]['cpu-load']) ? $resource[0]['cpu-load'] . '%' : '-';
    
    $activeCount = is_array($activeUsers) ? count($activeUsers) : 0;
    $totalCount = is_array($totalUsers) ? count($totalUsers) : 0;
    
    echo json_encode([
        'status' => 'online',
        'uptime' => $uptime,
        'cpu' => $cpu,
        'active_users' => $activeCount,
        'total_users' => $totalCount
    ]);
} else {
    echo json_encode([
        'status' => 'offline'
    ]);
}
