<?php
// Fallback autoloader if not loaded
if (!class_exists('App\Database')) {
    require_once dirname(__FILE__) . '/autoload.php';
}
$dbSettings = \App\Database::getInstance();
$qrbt = $dbSettings->getData('app_settings', 'quick_print_qr', 'disable');
?>