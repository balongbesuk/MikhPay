<?php
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'version.php' && !isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

if (!defined('MIKHPAY_VERSION')) {
    $detectedVer = '3.2.0';
    $detectedDate = '2026-11-07';
    $changelogFile = __DIR__ . '/../changelog.md';
    if (is_file($changelogFile)) {
        $head = @file_get_contents($changelogFile, false, null, 0, 2048);
        if (is_string($head) && preg_match('/^###\s+\[MikhPay\s+v([0-9.]+)\](?:\s*-\s*([0-9-]+))?/m', $head, $m)) {
            $detectedVer = $m[1];
            if (!empty($m[2])) {
                $detectedDate = $m[2];
            }
        }
    }
    define('MIKHPAY_VERSION', $detectedVer);
    define('MIKHPAY_VERSION_SHORT', preg_replace('/\.0$/', '', $detectedVer));
    define('MIKHPAY_RELEASE_DATE', $detectedDate);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION["v"] = MIKHPAY_VERSION . (MIKHPAY_RELEASE_DATE !== '' ? " (" . MIKHPAY_RELEASE_DATE . ")" : "");
}

