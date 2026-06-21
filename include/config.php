<?php
if(substr($_SERVER["REQUEST_URI"], -10) == "config.php"){header("Location:./");};

// Load autoloader fallback
require_once dirname(__FILE__) . '/autoload.php';
require_once dirname(__FILE__) . '/env_config.php';

// Initialize models
$dbSettings = new \App\Models\AppSettings();
$dbSessions = new \App\Models\RouterSession();

// Populate admin settings
$adminCreds = $dbSettings->getAdminCredentials();
$adminUser = $adminCreds['username'];
$adminPassHash = $adminCreds['password_hash'];
$data['mikhmon'] = array('1' => "mikhmon<|<$adminUser", "mikhmon>|>$adminPassHash");

// Populate router sessions
$sessionsList = $dbSessions->getAll();
foreach ($sessionsList as $sessionData) {
    $sName = $sessionData['session_name'];
    $ip = $sessionData['ip_address'];
    $usr = $sessionData['username'];
    $pwd = $sessionData['password'];
    $hName = $sessionData['hotspot_name'];
    $dns = $sessionData['dns_name'];
    $curr = $sessionData['currency'];
    $reload = $sessionData['auto_reload'];
    $iface = $sessionData['traffic_interface'];
    $idle = $sessionData['idle_timeout'];
    $live = $sessionData['live_report'];
    
    // Format exactly as old mikhmon layout
    $data[$sName] = array(
        '1' => "{$sName}!{$ip}",
        "{$sName}@|@{$usr}",
        "{$sName}#|#{$pwd}",
        "{$sName}%{$hName}",
        "{$sName}^{$dns}",
        "{$sName}&{$curr}",
        "{$sName}*{$reload}",
        "{$sName}({$iface}",
        "{$sName})",
        "{$sName}={$idle}",
        "{$sName}@!@{$live}"
    );
}
