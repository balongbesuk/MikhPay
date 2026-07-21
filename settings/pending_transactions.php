<?php
/**
 * Webhook Pending Transactions & History Manager
 * MikhPay v1.1
 */

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

// Set timezone untuk menyinkronkan jam tampilan dengan MikroTik
$timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : 'Asia/Jakarta';
date_default_timezone_set($timezone);

// CSRF check
include_once('./include/csrf.php');

$dbSessions = new \App\Models\RouterSession();
$settingsModel = new \App\Models\AppSettings();
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['mikhtrans_success_msg'])) {
    $success_msg = $_SESSION['mikhtrans_success_msg'];
    unset($_SESSION['mikhtrans_success_msg']);
}

// Helper to generate backup ZIP file
function createBackupZip() {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $backupDir = __DIR__ . '/../data';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $zipFile = $backupDir . '/mikhtrans-backup-' . date('Ymd-His') . '.zip';
    $zip = new \ZipArchive();
    
    if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    
    // 1. Add .env & .env.php (if exist)
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $zip->addFile($envFile, '.env');
    }
    $envPhpFile = __DIR__ . '/../.env.php';
    if (file_exists($envPhpFile)) {
        $zip->addFile($envPhpFile, '.env.php');
    }
    
    // 2. Add data/database.php
    $dbFile = __DIR__ . '/../data/database.php';
    if (file_exists($dbFile)) {
        $zip->addFile($dbFile, 'data/database.php');
    }
    
    // 3. Add voucher/* files
    $voucherDir = __DIR__ . '/../voucher/';
    if (file_exists($voucherDir)) {
        $files = array_merge(
            glob($voucherDir . 'trans-*.json'),
            glob($voucherDir . 'trans-*.php')
        );
        if (is_array($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, 'voucher/' . basename($file));
                }
            }
        }
    }
    
    $zip->close();
    return $zipFile;
}

// Helper to generate backup TAR file (Pure PHP Fallback)
function createBackupTar() {
    $backupDir = __DIR__ . '/../data';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $tarFile = $backupDir . '/mikhtrans-backup-' . date('Ymd-His') . '.tar';
    $handle = fopen($tarFile, 'wb');
    if (!$handle) {
        return false;
    }
    
    $filesToArchive = array();
    
    // Add .env & .env.php
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $filesToArchive['.env'] = $envFile;
    }
    $envPhpFile = __DIR__ . '/../.env.php';
    if (file_exists($envPhpFile)) {
        $filesToArchive['.env.php'] = $envPhpFile;
    }
    
    // Add data/database.php
    $dbFile = __DIR__ . '/../data/database.php';
    if (file_exists($dbFile)) {
        $filesToArchive['data/database.php'] = $dbFile;
    }
    
    // Add voucher/*
    $voucherDir = __DIR__ . '/../voucher/';
    if (file_exists($voucherDir)) {
        $files = array_merge(
            glob($voucherDir . 'trans-*.json'),
            glob($voucherDir . 'trans-*.php')
        );
        if (is_array($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $filesToArchive['voucher/' . basename($file)] = $file;
                }
            }
        }
    }
    
    foreach ($filesToArchive as $archivePath => $realPath) {
        $content = file_get_contents($realPath);
        $size = strlen($content);
        
        // Build Tar Header (UStar format)
        $header = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155',
            $archivePath, // file name
            '0000644',    // file mode
            '0000000',    // owner ID
            '0000000',    // group ID
            sprintf('%011o', $size), // size
            sprintf('%011o', filemtime($realPath)), // mtime
            '        ',   // checksum placeholder
            '0',          // type flag (regular file)
            '',           // link name
            'ustar',      // magic
            '00',         // version
            '',           // owner name
            '',           // group name
            '',           // device major
            '',           // device minor
            ''            // prefix
        );
        
        // Pad header to 512 bytes
        $header = str_pad($header, 512, "\0");
        
        // Calculate checksum
        $checksum = 0;
        for ($i = 0; $i < 512; $i++) {
            $checksum += ord($header[$i]);
        }
        $checksumStr = sprintf('%06o', $checksum) . "\0 ";
        $header = substr_replace($header, $checksumStr, 148, 8);
        
        // Write header & content
        fwrite($handle, $header);
        fwrite($handle, $content);
        $padSize = (512 - ($size % 512)) % 512;
        if ($padSize > 0) {
            fwrite($handle, str_repeat("\0", $padSize));
        }
    }
    
    // End of tar: two 512-byte null blocks
    fwrite($handle, str_repeat("\0", 1024));
    fclose($handle);
    
    return $tarFile;
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    
    if ($_POST['action'] === 'retry') {
        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '';
        
        if (empty($order_id) || !preg_match('/^[a-zA-Z0-9\-]+$/', $order_id)) {
            $error_msg = "Order ID tidak valid.";
        } else {
            $filepath = __DIR__ . '/../voucher/trans-' . $order_id . '.php';
            if (!file_exists($filepath)) {
                $filepath = __DIR__ . '/../voucher/trans-' . $order_id . '.json';
            }
            $real_path = realpath(dirname($filepath));
            $voucher_dir = realpath(__DIR__ . '/../voucher');
            
            if ($real_path !== $voucher_dir || !file_exists($filepath)) {
                $error_msg = "Berkas data transaksi tidak ditemukan.";
            } else {
                $trans = readTransactionFile($filepath);
                
                if (isset($trans['status']) && ($trans['status'] === 'paid_pending_generate' || $trans['status'] === 'processing')) {
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
                            
                            $comment = "vc-API-Retry-" . rand(100, 999) . "-" . date("m.d.y") . "-Paid QRIS";
                            
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
                            
                            if (strpos($filepath, '.php') !== false) {
                                writeTransactionFile($filepath, $trans);
                            } else {
                                file_put_contents($filepath, json_encode($trans));
                            }
                            $success_msg = ($langid == 'id') 
                                ? "Sukses! Voucher {$username} berhasil dibuat untuk Order ID: {$order_id}." 
                                : "Success! Voucher {$username} generated for Order ID: {$order_id}.";
                            writeAppLog("WEBHOOK_RETRY_SUCCESS", "Voucher " . $username . " sukses digenerate manual via retry queue untuk Order ID: " . $order_id);
                        } else {
                            $error_msg = ($langid == 'id')
                                ? "Gagal menghubungkan ke router untuk sesi '{$session}'. Pastikan router online."
                                : "Failed to connect to router for session '{$session}'. Make sure the router is online.";
                        }
                    } else {
                        $error_msg = "Sesi router '{$session}' tidak ditemukan dalam konfigurasi.";
                    }
                } else {
                    $error_msg = "Transaksi tidak dalam status tertunda atau sudah terproses.";
                }
            }
        }
    } elseif ($_POST['action'] === 'save_settings') {
        $bot_token = isset($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : '';
        $chat_id = isset($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : '';
        $log_retention = isset($_POST['log_retention_days']) ? (int)$_POST['log_retention_days'] : 2;
        $p_title = isset($_POST['portal_title']) ? trim($_POST['portal_title']) : '';
        $p_logo = isset($_POST['portal_logo_url']) ? trim($_POST['portal_logo_url']) : '';
        $p_accent = isset($_POST['portal_accent_color']) ? trim($_POST['portal_accent_color']) : '#008BC9';
        $p_wa = isset($_POST['portal_support_wa']) ? trim($_POST['portal_support_wa']) : '';
        $p_tele = isset($_POST['portal_support_telegram']) ? trim($_POST['portal_support_telegram']) : '';
        $p_email = isset($_POST['portal_support_email']) ? trim($_POST['portal_support_email']) : '';
        $p_address = isset($_POST['portal_office_address']) ? trim($_POST['portal_office_address']) : '';
        $p_hours = isset($_POST['portal_operational_hours']) ? trim($_POST['portal_operational_hours']) : '';
        
        $settingsModel->set('telegram_bot_token', $bot_token);
        $settingsModel->set('telegram_chat_id', $chat_id);
        $settingsModel->set('log_retention_days', $log_retention);
        $settingsModel->set('portal_title', $p_title);
        $settingsModel->set('portal_logo_url', $p_logo);
        $settingsModel->set('portal_accent_color', $p_accent);
        $settingsModel->set('portal_support_wa', $p_wa);
        $settingsModel->set('portal_support_telegram', $p_tele);
        $settingsModel->set('portal_support_email', $p_email);
        $settingsModel->set('portal_office_address', $p_address);
        $settingsModel->set('portal_operational_hours', $p_hours);
        
        $success_msg = ($langid == 'id') 
            ? "Sukses! Pengaturan MikhPay berhasil disimpan." 
            : "Success! MikhPay settings successfully saved.";
    } elseif ($_POST['action'] === 'test_telegram') {
        $testMsg = "🔔 <b>[MikhPay] Test Notifikasi Sukses!</b>\n\nIntegrasi Telegram Bot Anda telah berhasil dikonfigurasi.";
        if (sendTelegramNotification($testMsg)) {
            $success_msg = ($langid == 'id')
                ? "Sukses! Test notifikasi terkirim ke Telegram."
                : "Success! Test notification sent to Telegram.";
        } else {
            $error_msg = ($langid == 'id')
                ? "Gagal mengirim notifikasi. Pastikan Bot Token dan Chat ID benar, serta Bot telah di-start (/start) oleh akun Anda."
                : "Failed to send notification. Make sure Bot Token and Chat ID are correct, and Bot has been started (/start) by your account.";
        }
    } elseif ($_POST['action'] === 'download_backup') {
        if (class_exists('ZipArchive')) {
            $backupPath = createBackupZip();
            $mimeType = 'application/zip';
            $ext = 'zip';
        } else {
            $backupPath = createBackupTar();
            $mimeType = 'application/x-tar';
            $ext = 'tar';
        }
        
        if ($backupPath && file_exists($backupPath)) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="mikhtrans-backup-' . date('Ymd-His') . '.' . $ext . '"');
            header('Content-Length: ' . filesize($backupPath));
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($backupPath);
            @unlink($backupPath);
            exit;
        } else {
            $error_msg = ($langid == 'id')
                ? "Gagal membuat file backup. Periksa izin menulis pada direktori 'data/'."
                : "Failed to generate backup file. Check write permissions in the 'data/' directory.";
        }
    }
}

// Load MikhPay credentials and configs
$telegram_bot_token = $settingsModel->get('telegram_bot_token', mikhmonEnv('TELEGRAM_BOT_TOKEN', ''));
$telegram_chat_id = $settingsModel->get('telegram_chat_id', mikhmonEnv('TELEGRAM_CHAT_ID', ''));
$log_retention_days = (int)$settingsModel->get('log_retention_days', 2);
$portal_title = $settingsModel->get('portal_title', 'MikhPay Portal');
$portal_logo_url = $settingsModel->get('portal_logo_url', '');
$portal_accent_color = $settingsModel->get('portal_accent_color', '#008BC9');
$portal_support_wa = $settingsModel->get('portal_support_wa', '');
$portal_support_telegram = $settingsModel->get('portal_support_telegram', '');
$portal_support_email = $settingsModel->get('portal_support_email', '');
$portal_office_address = $settingsModel->get('portal_office_address', '');
$portal_operational_hours = $settingsModel->get('portal_operational_hours', '');

// Load and group transactions
$dir = __DIR__ . '/../voucher/';
$files = array_merge(
    glob($dir . 'trans-*.json'),
    glob($dir . 'trans-*.php')
);

$pendingTransactions = [];
$historyTransactions = [];
$now = time();

if (is_array($files)) {
    // Sort files by last modified time descending (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($files as $file) {
        $dataTrans = readTransactionFile($file);
        if ($dataTrans) {
            $order = isset($dataTrans['order_id']) ? $dataTrans['order_id'] : basename($file, (strpos($file, '.php') !== false ? '.php' : '.json'));
            $status = isset($dataTrans['status']) ? $dataTrans['status'] : 'pending';
            
            // Add file details
            $dataTrans['order_id'] = $order;
            $dataTrans['file_time'] = filemtime($file);
            
            if ($status === 'paid_pending_generate' || $status === 'processing') {
                $pendingTransactions[] = $dataTrans;
            } else {
                // Limit history to 50 items for performance
                if (count($historyTransactions) < 50) {
                    $historyTransactions[] = $dataTrans;
                }
            }
        }
    }
}

// Calculate Sales Analytics (Omzet 30 hari & Paket Terlaris)
$totalRevenue = 0;
$successCount = 0;

$dailySales = [];
for ($i = 29; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $dailySales[$dateKey] = 0;
}

$profileSales = [];

if (is_array($files)) {
    foreach ($files as $file) {
        $dataTrans = readTransactionFile($file);
        if ($dataTrans) {
            $status = isset($dataTrans['status']) ? $dataTrans['status'] : 'pending';
            $price = isset($dataTrans['price']) ? (float)$dataTrans['price'] : 0.0;
            $profile = isset($dataTrans['profile']) ? $dataTrans['profile'] : 'Unknown';
            
            // Use paid_at or file_time
            $time = isset($dataTrans['paid_at']) ? $dataTrans['paid_at'] : (isset($dataTrans['created_at']) ? $dataTrans['created_at'] : filemtime($file));
            
            if ($status === 'settlement' || $status === 'capture') {
                $totalRevenue += $price;
                $successCount++;
                
                $dateStr = date('Y-m-d', $time);
                if (isset($dailySales[$dateStr])) {
                    $dailySales[$dateStr] += $price;
                }
                
                if (!isset($profileSales[$profile])) {
                    $profileSales[$profile] = [
                        'revenue' => 0,
                        'count' => 0
                    ];
                }
                $profileSales[$profile]['revenue'] += $price;
                $profileSales[$profile]['count']++;
            }
        }
    }
}

// Sort profile sales by count descending to show top packages
uasort($profileSales, function($a, $b) {
    return $b['count'] - $a['count'];
});
?>

<style>
/* Welcome Status Banner Styles */
.dash-welcome {
    background: <?= $portal_accent_color ?> !important;
    background: linear-gradient(135deg, <?= $portal_accent_color ?> 0%, color-mix(in srgb, <?= $portal_accent_color ?>, #000 25%) 50%, color-mix(in srgb, <?= $portal_accent_color ?>, #000 45%) 100%) !important;
    border-radius: 24px;
    padding: 36px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    border: none !important;
    box-shadow: 0 20px 40px -15px color-mix(in srgb, <?= $portal_accent_color ?>, transparent 60%) !important;
}
@media (max-width: 750px) {
    .dash-welcome {
        padding: 24px 28px !important;
        border-radius: 16px !important;
    }
    .dash-welcome h2 {
        font-size: 20px !important;
        margin-bottom: 12px !important;
    }
}
@media (max-width: 576px) {
    .dash-welcome-content {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 20px !important;
    }
    .dash-welcome-time {
        align-self: flex-end !important;
    }
}
.dash-welcome::before {
    content: '';
    position: absolute;
    top: -80px;
    right: -80px;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.25) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.dash-welcome::after {
    content: '';
    position: absolute;
    bottom: -100px;
    left: -60px;
    width: 360px;
    height: 360px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.dash-welcome-content {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}
.dash-welcome h2 {
    font-size: 28px;
    font-weight: 800;
    color: #ffffff !important;
    margin: 8px 0 16px 0;
    letter-spacing: -0.5px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}
.dash-welcome-tag {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.8) !important;
}
.sync-status.connected {
    background: rgba(16, 185, 129, 0.22) !important;
    color: #a7f3d0 !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
    padding: 4px 14px !important;
    border-radius: 30px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
}
.sync-status.disconnected {
    background: rgba(239, 68, 68, 0.22) !important;
    color: #fca5a5 !important;
    border: 1px solid rgba(239, 68, 68, 0.35) !important;
    padding: 4px 14px !important;
    border-radius: 30px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
}
.status-dot.connected {
    background: #10B981 !important;
    box-shadow: 0 0 8px #10B981 !important;
}
.status-dot.disconnected {
    background: #EF4444 !important;
    box-shadow: 0 0 8px #EF4444 !important;
}
.dash-welcome-badges {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}
.welcome-badge {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    padding: 10px 18px !important;
    border-radius: 14px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.welcome-badge:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}
.dash-welcome-time {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}
.dash-welcome-time:hover {
    transform: rotate(15deg) scale(1.05);
    background: rgba(255, 255, 255, 0.2) !important;
}
.dash-welcome-time i {
    font-size: 32px;
    color: #ffffff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.dash-welcome-time .time-date {
    font-size: 10px;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.85);
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Tabs & Containers */
.tab-container {
    margin-bottom: 24px;
}
.tab-headers {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 12px;
    margin-bottom: 24px;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Hide scrollbar for Firefox */
}
.tab-headers::-webkit-scrollbar {
    display: none; /* Hide scrollbar for Chrome/Safari/Opera */
}
.tab-header-btn {
    background: transparent;
    border: none;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    white-space: nowrap;
}
.tab-header-btn:hover {
    color: var(--text-main);
    background: var(--hover-bg);
}
.tab-header-btn.active {
    background: var(--primary-glow);
    color: var(--primary);
}
.tab-panel {
    display: none;
}
.tab-panel.active {
    display: block;
    background: transparent !important;
    border-radius: 0 !important;
}

/* Status Badges */
.badge-status {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: bold;
}
.badge-status.pending {
    background: #FEF3C7;
    color: #D97706;
}
.badge-status.paid_pending {
    background: #FEE2E2;
    color: #EF4444;
    animation: pulse-border 1.5s infinite;
}
.badge-status.success {
    background: #D1FAE5;
    color: #059669;
}
.badge-status.failed {
    background: #E5E7EB;
    color: #4B5563;
}

/* Alerts */
.alert-box {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.alert-box.success {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-box.error {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FCA5A5;
}
@keyframes pulse-border {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.toast-notification {
    background: #1e293b;
    color: #ffffff;
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
    border-left: 4px solid #10b981;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 280px;
    max-width: 360px;
    animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'Plus Jakarta Sans', sans-serif;
    text-align: left;
}
@keyframes toastSlideIn {
    from { transform: translateX(120%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.toast-notification.fade-out {
    animation: toastSlideOut 0.3s forwards;
}
@keyframes toastSlideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(120%); opacity: 0; }
}
.toast-content {
    flex: 1;
}
.toast-title {
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 2px;
    color: #ffffff;
}
.toast-desc {
    font-size: 11px;
    color: #94a3b8;
    line-height: 1.4;
}
</style>

<div class="row">
    <div class="col-12">
        <div id="toastContainer" class="toast-container"></div>
        
        <!-- Welcome Status Banner -->
        <div class="dash-welcome">
            <div class="dash-welcome-content">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                        <div class="dash-welcome-tag" style="margin-bottom: 0 !important;">System Status</div>
                        <?php if (!empty($telegram_bot_token) && !empty($telegram_chat_id)): ?>
                            <div class="sync-status connected"><span class="status-dot connected"></span> Telegram Bot Active</div>
                        <?php else: ?>
                            <div class="sync-status disconnected"><span class="status-dot disconnected"></span> Telegram Config Required</div>
                        <?php endif; ?>
                    </div>
                    <h2>MikhPay Billing Manager</h2>
                    <div class="dash-welcome-badges">
                        <span class="welcome-badge"><i class="fa fa-clock-o"></i> <?= ($langid == 'id') ? 'Antrean' : 'Queue' ?>: <?= count($pendingTransactions) ?></span>
                        <span class="welcome-badge"><i class="fa fa-check-circle"></i> <?= ($langid == 'id') ? 'Sukses' : 'Success' ?>: <?= $successCount ?></span>
                        <span class="welcome-badge"><i class="fa fa-money"></i> Rp <?= number_format($totalRevenue, 0, ',', '.') ?></span>
                    </div>
                </div>
                <div class="dash-welcome-time">
                    <i class="fa fa-credit-card"></i>
                    <div class="time-date">v2.0</div>
                </div>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-box success">
                <i class="fa fa-circle-check"></i>
                <div><?= htmlspecialchars($success_msg) ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert-box error">
                <i class="fa fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error_msg) ?></div>
            </div>
        <?php endif; ?>

        <div class="tab-container">
            <div class="tab-headers" style="display: none;">
                <button class="tab-header-btn active" onclick="openTab('tab-pending', this)">
                    <i class="fa fa-clock-o"></i> <?= ($langid == 'id') ? 'Antrean Tertunda' : 'Pending Queue' ?> (<?= count($pendingTransactions) ?>)
                </button>
                <button class="tab-header-btn" onclick="openTab('tab-analytics', this)">
                    <i class="fa fa-line-chart"></i> <?= ($langid == 'id') ? 'Analitik Penjualan' : 'Sales Analytics' ?>
                </button>
                <button class="tab-header-btn" onclick="openTab('tab-history', this)">
                    <i class="fa fa-history"></i> <?= ($langid == 'id') ? 'Riwayat Transaksi' : 'Transaction History' ?>
                </button>
                <button class="tab-header-btn" onclick="openTab('tab-logs', this)">
                    <i class="fa fa-terminal"></i> Log Aktivitas <span id="wsStatusBadge" style="margin-left: 6px; padding: 2px 6px; border-radius: 4px; font-size: 9px; background: #94a3b8; color: #ffffff;">Mengecek...</span>
                </button>
                <button class="tab-header-btn" onclick="openTab('tab-settings', this)">
                    <i class="fa fa-sliders"></i> <?= ($langid == 'id') ? 'Pengaturan & Backup' : 'Settings & Backup' ?>
                </button>
            </div>

            <!-- Panel Pending Transactions -->
            <div id="tab-pending" class="tab-panel active">
                <?php if (empty($pendingTransactions)): ?>
                    <div class="card">
                        <div class="card-body" style="text-align: center; padding: 40px 20px;">
                            <i class="fa fa-circle-check" style="font-size: 48px; color: #10B981; margin-bottom: 12px; display: block;"></i>
                            <strong style="color: var(--text-main); font-size: 15px; display: block; margin-bottom: 4px;">
                                <?= ($langid == 'id') ? 'Antrean Bersih' : 'Queue is Clean' ?>
                            </strong>
                            <span style="color: var(--text-muted); font-size: 13px;">
                                <?= ($langid == 'id') ? 'Tidak ada transaksi tertunda. Semua voucher sukses diterbitkan ke router.' : 'No pending voucher generation tasks found.' ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa fa-clock-o"></i> <?= ($langid == 'id') ? 'Antrean Transaksi Tertunda' : 'Pending Transactions Queue' ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="overflow">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Waktu Pembayaran</th>
                                            <th>Order ID</th>
                                            <th>Sesi Router</th>
                                            <th>Paket / Profil</th>
                                            <th>Nominal</th>
                                            <th style="text-align: center;">Status</th>
                                            <th style="text-align: center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingTransactions as $pt): ?>
                                            <tr>
                                                <td><?= date("Y-m-d H:i:s", isset($pt['paid_at']) ? $pt['paid_at'] : $pt['file_time']) ?></td>
                                                <td style="font-family: monospace; font-weight: bold;"><?= htmlspecialchars($pt['order_id']) ?></td>
                                                <td><span class="badge bg-blue"><?= htmlspecialchars($pt['session']) ?></span></td>
                                                <td><strong><?= htmlspecialchars($pt['profile']) ?></strong></td>
                                                <td>Rp <?= number_format($pt['price'], 0, ',', '.') ?></td>
                                                <td style="text-align: center;">
                                                    <span class="badge-status paid_pending">Router Offline</span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <form method="post" action="" style="display: inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="retry" />
                                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($pt['order_id']) ?>" />
                                                        <button type="submit" class="btn bg-red" style="margin: 0; padding: 6px 12px; font-size: 12px;">
                                                            <i class="fa fa-refresh"></i> <?= ($langid == 'id') ? 'Generate' : 'Retry' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Panel Sales Analytics -->
            <div id="tab-analytics" class="tab-panel">
                <!-- Summary Stats -->
                <div class="row" style="margin-bottom: 20px; display: flex !important; flex-wrap: wrap !important;">
                    <div class="col-4" style="display: flex !important; flex-direction: column !important;">
                        <div class="box bmh-75 box-bordered" style="height: 100%; padding: 24px !important; box-shadow: var(--shadow-card) !important; flex: 1 !important;">
                            <div class="box-group" style="display: flex !important; align-items: center !important;">
                                <div class="box-group-icon icon-cyan" style="color: #10b981 !important; background: rgba(16, 185, 129, 0.12) !important; width: 44px !important; height: 44px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; margin-right: 14px !important; flex-shrink: 0;"><i class="fa fa-money"></i></div>
                                <div class="box-group-area" style="flex: 1; text-align: left !important;">
                                    <div class="stat-title" style="font-size: 11px !important; font-weight: 700 !important; color: #6b7280 !important; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px;"><?= ($langid == 'id') ? 'Total Omzet' : 'Total Revenue' ?></div>
                                    <div class="stat-main-val" style="font-size: 20px !important; font-weight: 800 !important; color: var(--success, #10b981) !important; margin-bottom: 3px; letter-spacing: -0.5px; line-height: 1.2;">Rp <?= number_format($totalRevenue, 0, ',', '.') ?></div>
                                    <div class="stat-sub-val" style="font-size: 11px !important; color: #6b7280 !important; font-weight: 500;"><?= ($langid == 'id') ? 'Semua transaksi sukses' : 'All successful transactions' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4" style="display: flex !important; flex-direction: column !important;">
                        <div class="box bmh-75 box-bordered" style="height: 100%; padding: 24px !important; box-shadow: var(--shadow-card) !important; flex: 1 !important;">
                            <div class="box-group" style="display: flex !important; align-items: center !important;">
                                <div class="box-group-icon icon-indigo" style="color: var(--primary) !important; background: var(--primary-glow) !important; width: 44px !important; height: 44px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; margin-right: 14px !important; flex-shrink: 0;"><i class="fa fa-check-circle"></i></div>
                                <div class="box-group-area" style="flex: 1; text-align: left !important;">
                                    <div class="stat-title" style="font-size: 11px !important; font-weight: 700 !important; color: #6b7280 !important; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px;"><?= ($langid == 'id') ? 'Transaksi Sukses' : 'Successful' ?></div>
                                    <div class="stat-main-val" style="font-size: 20px !important; font-weight: 800 !important; color: var(--primary) !important; margin-bottom: 3px; letter-spacing: -0.5px; line-height: 1.2;"><?= $successCount ?></div>
                                    <div class="stat-sub-val" style="font-size: 11px !important; color: #6b7280 !important; font-weight: 500;"><?= ($langid == 'id') ? 'Voucher berhasil diterbitkan' : 'Vouchers generated' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4" style="display: flex !important; flex-direction: column !important;">
                        <div class="box bmh-75 box-bordered" style="height: 100%; padding: 24px !important; box-shadow: var(--shadow-card) !important; flex: 1 !important;">
                            <div class="box-group" style="display: flex !important; align-items: center !important;">
                                <div class="box-group-icon icon-violet" style="color: var(--danger) !important; background: rgba(211,64,83,0.12) !important; width: 44px !important; height: 44px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; margin-right: 14px !important; flex-shrink: 0;"><i class="fa fa-clock-o"></i></div>
                                <div class="box-group-area" style="flex: 1; text-align: left !important;">
                                    <div class="stat-title" style="font-size: 11px !important; font-weight: 700 !important; color: #6b7280 !important; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px;"><?= ($langid == 'id') ? 'Antrean Tertunda' : 'Pending Queue' ?></div>
                                    <div class="stat-main-val" style="font-size: 20px !important; font-weight: 800 !important; color: var(--danger) !important; margin-bottom: 3px; letter-spacing: -0.5px; line-height: 1.2;"><?= count($pendingTransactions) ?></div>
                                    <div class="stat-sub-val" style="font-size: 11px !important; color: #6b7280 !important; font-weight: 500;"><?= ($langid == 'id') ? 'Menunggu router online' : 'Waiting for router' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-8">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fa fa-line-chart"></i> <?= ($langid == 'id') ? 'Tren Omzet Harian (30 Hari)' : 'Daily Revenue (30 Days)' ?></h3>
                            </div>
                            <div class="card-body" style="position: relative; min-height: 250px;">
                                <canvas id="dailySalesChart" style="max-height: 300px; width: 100%;"></canvas>
                                <div id="chart-offline-msg" style="display:none; text-align:center; padding:40px 20px; color:var(--text-muted);">
                                    <i class="fa fa-wifi" style="font-size:24px; margin-bottom:8px; display:block; opacity:0.4;"></i>
                                    <?= ($langid == 'id') ? 'Chart.js tidak termuat (offline).' : 'Chart.js could not load (offline).' ?>
                                    <div style="max-height:120px; overflow-y:auto; margin-top:10px; font-size:11px; font-family:monospace; text-align:left; background:var(--bg-card-hover); padding:10px; border-radius:var(--radius); border:1px solid var(--border-color);">
                                        <?php foreach($dailySales as $date => $rev): ?>
                                            <?php if($rev > 0): ?>
                                                <?= $date ?>: Rp <?= number_format($rev, 0, ',', '.') ?><br>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fa fa-pie-chart"></i> <?= ($langid == 'id') ? 'Distribusi Paket' : 'Top Packages' ?></h3>
                            </div>
                            <div class="card-body" style="position: relative; min-height: 250px;">
                                <canvas id="profileSalesChart" style="max-height: 300px; width: 100%;"></canvas>
                                <div id="doughnut-offline-msg" style="display:none; text-align:center; padding:40px 20px; color:var(--text-muted);">
                                    <i class="fa fa-wifi" style="font-size:24px; margin-bottom:8px; display:block; opacity:0.4;"></i>
                                    <?= ($langid == 'id') ? 'Chart.js tidak termuat.' : 'Chart.js could not load.' ?>
                                    <div style="max-height:120px; overflow-y:auto; margin-top:10px; font-size:11px; font-family:monospace; text-align:left; background:var(--bg-card-hover); padding:10px; border-radius:var(--radius); border:1px solid var(--border-color);">
                                        <?php foreach($profileSales as $prof => $pdata): ?>
                                            <?= htmlspecialchars($prof) ?>: <?= $pdata['count'] ?>x (Rp <?= number_format($pdata['revenue'], 0, ',', '.') ?>)<br>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Transaction History -->
            <div id="tab-history" class="tab-panel">
                <?php if (empty($historyTransactions)): ?>
                    <div class="card">
                        <div class="card-body" style="text-align: center; padding: 40px 20px;">
                            <i class="fa fa-folder-open" style="font-size: 48px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                            <span style="color: var(--text-muted); font-size: 13px;">
                                <?= ($langid == 'id') ? 'Belum ada riwayat transaksi.' : 'No transaction history available.' ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa fa-history"></i> <?= ($langid == 'id') ? 'Riwayat Transaksi Sukses' : 'Successful Transaction History' ?></h3>
                        </div>
                        <div class="card-body">
                            <!-- Filter Status Dropdown -->
                            <div class="form-group" style="max-width: 250px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                <label for="historyStatusFilter" style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin: 0; white-space: nowrap;">
                                    <i class="fa fa-filter"></i> Filter Status:
                                </label>
                                <select id="historyStatusFilter" class="form-control" onchange="filterHistoryTable()" style="padding: 6px 12px; border-radius: 8px; font-size: 13px; cursor: pointer;">
                                    <option value="all"><?= ($langid == 'id') ? 'Semua Status' : 'All Statuses' ?></option>
                                    <option value="success"><?= ($langid == 'id') ? 'Lunas (Success)' : 'Paid (Success)' ?></option>
                                    <option value="pending">Pending</option>
                                    <option value="failed"><?= ($langid == 'id') ? 'Gagal (Failed/Expired)' : 'Failed/Expired' ?></option>
                                </select>
                            </div>
                            
                            <div class="overflow">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Waktu Transaksi</th>
                                            <th>Order ID</th>
                                            <th>Sesi Router</th>
                                            <th>Paket / Profil</th>
                                            <th>Nominal</th>
                                            <th>Voucher (User / Pass)</th>
                                            <th style="text-align: center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <?php foreach ($historyTransactions as $ht): ?>
                                            <?php
                                            $stVal = isset($ht['status']) ? $ht['status'] : 'pending';
                                            $badgeClass = 'pending';
                                            $badgeLabel = 'Pending';
                                            
                                            $txTime = isset($ht['paid_at']) ? $ht['paid_at'] : (isset($ht['created_at']) ? $ht['created_at'] : $ht['file_time']);
                                            
                                            if ($stVal === 'settlement' || $stVal === 'capture') {
                                                $badgeClass = 'success';
                                                $badgeLabel = 'Lunas';
                                            } elseif ($stVal === 'failed') {
                                                $badgeClass = 'failed';
                                                $badgeLabel = 'Gagal';
                                            } elseif ($stVal === 'pending' && (time() - $txTime > 600)) {
                                                $badgeClass = 'failed';
                                                $badgeLabel = 'Expired';
                                            }
                                            ?>
                                            <tr data-status="<?= $badgeClass ?>">
                                                <td><?= date("Y-m-d H:i:s", isset($ht['paid_at']) ? $ht['paid_at'] : (isset($ht['created_at']) ? $ht['created_at'] : $ht['file_time'])) ?></td>
                                                <td style="font-family: monospace;"><?= htmlspecialchars($ht['order_id']) ?></td>
                                                <td><span class="badge bg-grey"><?= htmlspecialchars($ht['session']) ?></span></td>
                                                <td><?= htmlspecialchars($ht['profile']) ?></td>
                                                <td>Rp <?= number_format($ht['price'], 0, ',', '.') ?></td>
                                                <td style="font-family: monospace; font-weight: bold;">
                                                    <?php if (isset($ht['username'])): ?>
                                                        <?= htmlspecialchars($ht['username']) ?> / <?= htmlspecialchars(isset($ht['password']) ? $ht['password'] : $ht['username']) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <span class="badge-status <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 16px; text-align: left;">
                                * Menampilkan hingga 50 transaksi terbaru. Log transaksi di disk dibersihkan secara otomatis jika usia berkas lebih dari 2 hari.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Panel Live Activity Logs -->
            <div id="tab-logs" class="tab-panel">
                <style>
                    .console-container {
                        background: #0f172a !important; /* Slate 900 */
                        border: 1px solid #1e293b;
                        border-radius: 12px;
                        padding: 16px;
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 12px;
                        color: #cbd5e1; /* Slate 300 */
                        min-height: 380px;
                        max-height: 520px;
                        overflow-y: auto;
                        box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
                    }
                    .console-line {
                        margin-bottom: 6px;
                        line-height: 1.5;
                        word-break: break-all;
                        animation: consoleFadeIn 0.2s ease-out;
                    }
                    @keyframes consoleFadeIn {
                        from { opacity: 0; transform: translateY(4px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    .log-time { color: #64748b; margin-right: 8px; }
                    .log-ip { color: #38bdf8; margin-right: 8px; font-weight: bold; }
                    .log-type {
                        display: inline-block;
                        padding: 1px 6px;
                        border-radius: 4px;
                        font-size: 10px;
                        font-weight: bold;
                        text-transform: uppercase;
                        margin-right: 8px;
                    }
                    .log-type.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
                    .log-type.error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
                    .log-type.info { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
                    .log-type.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
                    
                    .console-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 12px;
                        flex-wrap: wrap;
                        gap: 8px;
                    }
                </style>
                <div class="card">
                    <div class="card-header">
                        <div class="console-header">
                            <h3 style="margin: 0;"><i class="fa fa-terminal"></i> Log Aktivitas Real-Time</h3>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <select id="logFilterLevel" onchange="filterConsoleLogs()" class="form-control" style="padding: 4px 8px; font-size: 12px; border-radius: 6px; width: 130px; display: inline-block; height: auto; cursor: pointer; background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-color);">
                                    <option value="all">Semua Level</option>
                                    <option value="success">Sukses / Settlement</option>
                                    <option value="error">Error / Gagal</option>
                                    <option value="info">Info / Pending</option>
                                </select>
                                <button type="button" class="btn bg-grey" onclick="clearConsoleLogUI()" style="margin: 0; padding: 4px 10px; font-size: 12px;">
                                    <i class="fa fa-trash-o"></i> Clear UI
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="logsConsole" class="console-container">
                            <div class="console-line" style="color: #64748b;"><i class="fa fa-info-circle"></i> Memuat catatan log aktivitas...</div>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px; text-align: left;">
                            * Menampilkan 30 aktivitas sistem terbaru. Log aktivitas disinkronkan secara otomatis.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Settings & Backup -->
            <div id="tab-settings" class="tab-panel">
                <style>
                    #tab-settings .form-group { margin-bottom: 20px; }
                    #tab-settings .form-group label {
                        display: block;
                        font-size: 13px;
                        font-weight: 700;
                        color: var(--text-muted);
                        margin-bottom: 8px;
                        text-align: left;
                    }
                    #tab-settings .form-control {
                        width: 100% !important;
                        height: 48px !important;
                        border: 1px solid var(--border-color) !important;
                        border-radius: 12px !important;
                        background: var(--bg-card, #ffffff) !important;
                        color: var(--text-main) !important;
                        padding: 0 16px !important;
                        font-size: 14px !important;
                        outline: none !important;
                        transition: all 0.25s ease !important;
                        box-sizing: border-box !important;
                    }
                    #tab-settings .form-control:focus {
                        border-color: var(--primary) !important;
                        box-shadow: 0 0 0 3.5px var(--primary-glow) !important;
                    }
                    #tab-settings textarea.form-control {
                        height: auto !important;
                        padding: 12px 16px !important;
                    }
                    #tab-settings .mt-hint {
                        font-size: 12px;
                        color: var(--text-muted);
                        line-height: 1.5;
                        margin-top: 6px;
                        text-align: left;
                    }
                    #tab-settings .mt-color-row {
                        display: flex;
                        gap: 12px;
                        align-items: center;
                    }
                    #tab-settings .mt-color-row input[type="color"] {
                        width: 44px;
                        height: 44px;
                        border: 1px solid var(--border-color);
                        border-radius: 10px;
                        cursor: pointer;
                        padding: 4px;
                        background: var(--bg-card, #ffffff);
                        box-sizing: border-box;
                    }
                    #tab-settings .mt-color-row span {
                        font-family: monospace;
                        font-size: 14px;
                        color: var(--text-main);
                        font-weight: 700;
                    }
                    #tab-settings .mt-info-box {
                        background: var(--background-alt, #F8FAFC);
                        border: 1px solid var(--border-color);
                        border-radius: 12px;
                        padding: 16px;
                        margin-bottom: 20px;
                        font-size: 13px;
                        color: var(--text-muted);
                        line-height: 1.6;
                        text-align: left;
                    }
                    #tab-settings .mt-info-box strong { color: var(--text-main); }
                    #tab-settings .mt-info-box code {
                        background: var(--primary-glow);
                        color: var(--primary);
                        padding: 2px 6px;
                        border-radius: 6px;
                        font-size: 12px;
                        font-family: monospace;
                    }
                    #tab-settings .mt-info-box ul { margin: 8px 0 0 20px; padding: 0; }

                    /* Subtabs switch styling */
                    .settings-subtabs-nav {
                        display: flex !important;
                        gap: 8px !important;
                        border-bottom: 1px solid var(--border-color) !important;
                        padding-bottom: 12px !important;
                        margin-bottom: 24px !important;
                        flex-wrap: nowrap !important;
                        overflow-x: auto !important;
                        white-space: nowrap !important;
                        -webkit-overflow-scrolling: touch !important;
                        scrollbar-width: none !important; /* Hide scrollbar for Firefox */
                    }
                    .settings-subtabs-nav::-webkit-scrollbar {
                        display: none !important; /* Hide scrollbar for Chrome/Safari/Opera */
                    }
                    .subtab-btn {
                        background: transparent !important;
                        border: 1px solid transparent !important;
                        color: var(--text-muted) !important;
                        padding: 8px 16px !important;
                        font-size: 13px !important;
                        font-weight: 700 !important;
                        border-radius: 8px !important;
                        cursor: pointer !important;
                        transition: all 0.2s ease !important;
                        display: inline-flex !important;
                        align-items: center !important;
                        gap: 8px !important;
                        flex-shrink: 0 !important;
                        white-space: nowrap !important;
                    }
                    .subtab-btn:hover {
                        background: var(--primary-glow) !important;
                        color: var(--primary) !important;
                    }
                    .subtab-btn.active {
                        background: var(--primary) !important;
                        color: #ffffff !important;
                        box-shadow: 0 4px 12px rgba(0, 139, 201, 0.15) !important;
                    }
                    
                    #tab-settings .card,
                    #tab-settings .card-body {
                        background-color: var(--bg-card, #ffffff) !important;
                    }

                    .subtab-panel {
                        display: none;
                        width: 100%;
                        background: var(--bg-card, #ffffff) !important;
                    }
                    .subtab-panel.active {
                        display: block;
                    }

                    .subtab-panel-title {
                        font-size: 12px !important;
                        font-weight: 800 !important;
                        text-transform: uppercase !important;
                        letter-spacing: 1px !important;
                        color: var(--primary) !important;
                        margin-bottom: 20px !important;
                        border-bottom: 1px solid var(--primary-glow) !important;
                        padding-bottom: 8px !important;
                        text-align: left !important;
                        background: var(--bg-card, #ffffff) !important;
                    }

                    .btn-modern-settings {
                        display: inline-flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        height: 42px !important;
                        padding: 0 20px !important;
                        border-radius: 10px !important;
                        font-size: 13px !important;
                        font-weight: 700 !important;
                        gap: 8px !important;
                        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                        border: 1px solid transparent !important;
                        cursor: pointer !important;
                        text-decoration: none !important;
                        width: 100% !important;
                    }
                    .btn-modern-settings.btn-save {
                        background: var(--primary) !important;
                        color: #ffffff !important;
                        box-shadow: 0 4px 12px rgba(0, 139, 201, 0.2) !important;
                    }
                    .btn-modern-settings.btn-save:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 6px 16px rgba(0, 139, 201, 0.3) !important;
                    }
                    .btn-modern-settings.btn-test {
                        background: rgba(16, 185, 129, 0.08) !important;
                        color: #10b981 !important;
                        border-color: rgba(16, 185, 129, 0.15) !important;
                    }
                    .btn-modern-settings.btn-test:hover {
                        background: #10b981 !important;
                        color: #ffffff !important;
                        transform: translateY(-1px);
                    }

                    .settings-form-grid {
                        display: grid !important;
                        grid-template-columns: repeat(2, 1fr) !important;
                        gap: 20px !important;
                    }
                    .settings-form-full {
                        grid-column: span 2 !important;
                    }
                    @media (max-width: 768px) {
                        .settings-form-grid {
                            grid-template-columns: 1fr !important;
                        }
                        .settings-form-full {
                            grid-column: span 1 !important;
                        }
                    }
                </style>

                <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
                    <div class="card-header" style="padding: 16px 24px !important;">
                        <h3 class="card-title" style="margin: 0;"><i class="fa fa-sliders"></i> <?= ($langid == 'id') ? 'Pengaturan & Backup' : 'Settings & Backup' ?></h3>
                    </div>
                    <div class="card-body" style="padding: 24px !important;">
                        
                        <!-- Segmented local sub-tabs navigation -->
                        <div class="settings-subtabs-nav">
                            <button type="button" class="subtab-btn active" onclick="openSubTab('subtab-portal', this)">
                                <i class="fa fa-paint-brush"></i> <?= ($langid == 'id') ? 'Portal Pelanggan' : 'Customer Portal' ?>
                            </button>
                            <button type="button" class="subtab-btn" onclick="openSubTab('subtab-telegram', this)">
                                <i class="fa fa-paper-plane"></i> <?= ($langid == 'id') ? 'Telegram & Retensi' : 'Telegram & Retention' ?>
                            </button>
                            <button type="button" class="subtab-btn" onclick="openSubTab('subtab-backup', this)">
                                <i class="fa fa-file-archive-o"></i> Backup Data
                            </button>
                        </div>

                        <!-- Subtab 1: Portal Pelanggan -->
                        <div id="subtab-portal" class="subtab-panel active">
                            <form method="post" action="" autocomplete="off">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="save_settings" />
                                <input type="hidden" name="telegram_bot_token" value="<?= htmlspecialchars($telegram_bot_token) ?>" />
                                <input type="hidden" name="telegram_chat_id" value="<?= htmlspecialchars($telegram_chat_id) ?>" />
                                <input type="hidden" name="log_retention_days" value="<?= $log_retention_days ?>" />

                                <div class="subtab-panel-title">Tampilan & Informasi Kontak Portal</div>

                                <div class="settings-form-grid">
                                    <div class="form-group">
                                        <label for="portal_title">Judul Portal</label>
                                        <input class="form-control" type="text" id="portal_title" name="portal_title" value="<?= htmlspecialchars($portal_title) ?>" required placeholder="Masukkan judul portal"/>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="portal_logo_url">URL Logo (Opsional)</label>
                                        <input class="form-control" type="text" id="portal_logo_url" name="portal_logo_url" value="<?= htmlspecialchars($portal_logo_url) ?>" placeholder="https://domain.com/logo.png"/>
                                    </div>

                                    <div class="form-group">
                                        <label>Warna Aksen</label>
                                        <div class="mt-color-row">
                                            <input type="color" name="portal_accent_color" value="<?= htmlspecialchars($portal_accent_color) ?>"/>
                                            <span><?= htmlspecialchars($portal_accent_color) ?></span>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="portal_support_wa">WhatsApp Support</label>
                                        <input class="form-control" type="text" id="portal_support_wa" name="portal_support_wa" value="<?= htmlspecialchars($portal_support_wa) ?>" placeholder="Contoh: +628123456789"/>
                                    </div>

                                    <div class="form-group">
                                        <label for="portal_support_telegram">Telegram Support</label>
                                        <input class="form-control" type="text" id="portal_support_telegram" name="portal_support_telegram" value="<?= htmlspecialchars($portal_support_telegram) ?>" placeholder="Username Telegram tanpa @"/>
                                    </div>

                                    <div class="form-group">
                                        <label for="portal_support_email">Email Resmi Support</label>
                                        <input class="form-control" type="email" id="portal_support_email" name="portal_support_email" value="<?= htmlspecialchars($portal_support_email) ?>" placeholder="support@domain.my.id"/>
                                    </div>

                                    <div class="form-group settings-form-full">
                                        <label for="portal_operational_hours">Jam Operasional Support</label>
                                        <input class="form-control" type="text" id="portal_operational_hours" name="portal_operational_hours" value="<?= htmlspecialchars($portal_operational_hours) ?>" placeholder="Contoh: Setiap Hari: 08.00 WIB - 22.00 WIB"/>
                                    </div>

                                    <div class="form-group settings-form-full">
                                        <label for="portal_office_address">Alamat Kantor</label>
                                        <textarea class="form-control" id="portal_office_address" name="portal_office_address" rows="3" style="resize: vertical; min-height: 80px;" placeholder="Tuliskan Alamat Kantor Pusat / Cabang..."><?= htmlspecialchars($portal_office_address) ?></textarea>
                                    </div>

                                    <div class="settings-form-full">
                                        <button type="submit" class="btn-modern-settings btn-save">
                                            <i class="fa fa-save"></i> Simpan Pengaturan Portal
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Subtab 2: Telegram Bot & Retensi Log -->
                        <div id="subtab-telegram" class="subtab-panel">
                            
                            <!-- Bot Credentials Form -->
                            <form method="post" action="" autocomplete="off" style="margin-bottom: 24px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="save_settings" />
                                <input type="hidden" name="portal_title" value="<?= htmlspecialchars($portal_title) ?>" />
                                <input type="hidden" name="portal_logo_url" value="<?= htmlspecialchars($portal_logo_url) ?>" />
                                <input type="hidden" name="portal_accent_color" value="<?= htmlspecialchars($portal_accent_color) ?>" />
                                <input type="hidden" name="portal_support_wa" value="<?= htmlspecialchars($portal_support_wa) ?>" />
                                <input type="hidden" name="portal_support_telegram" value="<?= htmlspecialchars($portal_support_telegram) ?>" />
                                <input type="hidden" name="portal_support_email" value="<?= htmlspecialchars($portal_support_email) ?>" />
                                <input type="hidden" name="portal_office_address" value="<?= htmlspecialchars($portal_office_address) ?>" />
                                <input type="hidden" name="portal_operational_hours" value="<?= htmlspecialchars($portal_operational_hours) ?>" />
                                <input type="hidden" name="log_retention_days" value="<?= $log_retention_days ?>" />

                                <div class="subtab-panel-title">Pengaturan Bot Notifikasi</div>

                                <div class="settings-form-grid">
                                    <div class="form-group">
                                        <label for="telegram_bot_token">Bot Token</label>
                                        <input class="form-control" type="text" id="telegram_bot_token" name="telegram_bot_token" value="<?= htmlspecialchars($telegram_bot_token) ?>" placeholder="123456789:ABCdef..."/>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="telegram_chat_id">Chat ID</label>
                                        <input class="form-control" type="text" id="telegram_chat_id" name="telegram_chat_id" value="<?= htmlspecialchars($telegram_chat_id) ?>" placeholder="987654321"/>
                                    </div>

                                    <div class="settings-form-full mt-hint" style="margin-top: -8px; margin-bottom: 16px;">
                                        Tips: Buat bot via <a href="https://t.me/BotFather" target="_blank" style="font-weight: 700; color: var(--primary);">@BotFather</a> · Dapatkan Chat ID Anda via <a href="https://t.me/userinfobot" target="_blank" style="font-weight: 700; color: var(--primary);">@userinfobot</a>
                                    </div>

                                    <div class="settings-form-full">
                                        <button type="submit" class="btn-modern-settings btn-save">
                                            <i class="fa fa-save"></i> Simpan Pengaturan Bot
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Test Telegram Notification -->
                            <?php if (!empty($telegram_bot_token) && !empty($telegram_chat_id)): ?>
                                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 28px 0;">
                                <div style="margin-bottom: 24px;">
                                    <div class="subtab-panel-title" style="color: #10b981 !important; border-bottom-color: rgba(16,185,129,0.15) !important;">Uji Coba Notifikasi</div>
                                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px; text-align: left;">Kirim pesan uji coba ke Telegram Anda untuk memastikan kredensial bot Telegram di atas sudah benar.</p>
                                    <button type="submit" form="testTelegramForm" class="btn-modern-settings btn-test">
                                        <i class="fa fa-paper-plane"></i> Kirim Test Notifikasi ke Telegram
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- Log Retention Form -->
                            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 28px 0;">
                            <form method="post" action="" style="margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="save_settings" />
                                <input type="hidden" name="telegram_bot_token" value="<?= htmlspecialchars($telegram_bot_token) ?>" />
                                <input type="hidden" name="telegram_chat_id" value="<?= htmlspecialchars($telegram_chat_id) ?>" />
                                <input type="hidden" name="portal_title" value="<?= htmlspecialchars($portal_title) ?>" />
                                <input type="hidden" name="portal_logo_url" value="<?= htmlspecialchars($portal_logo_url) ?>" />
                                <input type="hidden" name="portal_accent_color" value="<?= htmlspecialchars($portal_accent_color) ?>" />
                                <input type="hidden" name="portal_support_wa" value="<?= htmlspecialchars($portal_support_wa) ?>" />
                                <input type="hidden" name="portal_support_telegram" value="<?= htmlspecialchars($portal_support_telegram) ?>" />
                                <input type="hidden" name="portal_support_email" value="<?= htmlspecialchars($portal_support_email) ?>" />
                                <input type="hidden" name="portal_office_address" value="<?= htmlspecialchars($portal_office_address) ?>" />
                                <input type="hidden" name="portal_operational_hours" value="<?= htmlspecialchars($portal_operational_hours) ?>" />
                                
                                <div class="subtab-panel-title">Pengaturan Masa Simpan Log</div>
                                
                                <div class="settings-form-grid">
                                    <div class="form-group settings-form-full">
                                        <label for="log_retention_days">Masa Simpan Berkas Transaksi</label>
                                        <select class="form-control" id="log_retention_days" name="log_retention_days">
                                            <option value="1" <?= $log_retention_days === 1 ? 'selected' : '' ?>>1 Hari</option>
                                            <option value="2" <?= $log_retention_days === 2 ? 'selected' : '' ?>>2 Hari (Default)</option>
                                            <option value="7" <?= $log_retention_days === 7 ? 'selected' : '' ?>>7 Hari</option>
                                            <option value="30" <?= $log_retention_days === 30 ? 'selected' : '' ?>>30 Hari</option>
                                            <option value="0" <?= $log_retention_days === 0 ? 'selected' : '' ?>>Simpan Selamanya</option>
                                        </select>
                                        <div class="mt-hint">Berkas log lama di atas batas masa simpan akan dibersihkan sistem secara otomatis.</div>
                                    </div>
                                    
                                    <div class="settings-form-full">
                                        <button type="submit" class="btn-modern-settings btn-save">
                                            <i class="fa fa-save"></i> Simpan Retensi Log
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Subtab 3: Backup Data -->
                        <div id="subtab-backup" class="subtab-panel">
                            <div class="subtab-panel-title">MikhPay System Backup</div>
                            
                            <div class="mt-info-box">
                                <strong style="font-size: 14px;"><i class="fa fa-info-circle" style="color: var(--primary);"></i> Isi File Cadangan (Backup):</strong>
                                <ul style="margin-top: 10px;">
                                    <li style="margin-bottom: 6px;"><code>.env</code> / <code>.env.php</code> — Berkas konfigurasi kredensial API & Kunci sistem.</li>
                                    <li style="margin-bottom: 6px;"><code>data/database.php</code> — Kredensial Admin & Informasi sesi router MikroTik.</li>
                                    <li style="margin-bottom: 6px;"><code>voucher/*.json</code> — Log transaksi logis & arsip voucher billing.</li>
                                </ul>
                                <em style="display:block; margin-top:10px; font-weight: 600; color: var(--text-muted);">* Sistem akan menggunakan format cadangan TAR jika modul php-ZipArchive tidak aktif di server.</em>
                            </div>
                            
                            <button type="submit" form="backupForm" class="btn-modern-settings btn-save" style="max-width: 320px; margin-top: 10px;">
                                <i class="fa fa-file-archive-o"></i> Buat & Unduh Berkas Backup
                            </button>
                        </div>

                    </div>
                </div>
                
                <script type="text/javascript">
                    function openSubTab(tabId, btn) {
                        // Hide all subtab panels
                        var panels = document.querySelectorAll('.subtab-panel');
                        for (var i = 0; i < panels.length; i++) {
                            panels[i].style.display = 'none';
                        }
                        // Show target panel
                        document.getElementById(tabId).style.display = 'block';

                        // Deactivate all button highlights
                        var buttons = document.querySelectorAll('.subtab-btn');
                        for (var i = 0; i < buttons.length; i++) {
                            buttons[i].classList.remove('active');
                        }
                        // Highlight current button
                        btn.classList.add('active');
                    }
                </script>
            </div>
            </div>
        </div>

    </div>
</div>

<!-- Forms declared outside the main layout grid to avoid nesting forms -->
<form id="backupForm" method="post" action="" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="download_backup" />
</form>

<form id="testTelegramForm" method="post" action="" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_telegram" />
</form>

<?php if (!empty($ws_app_key)): ?>
    <script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<?php endif; ?>

<script>
var salesChartInstance = null;
var profileChartInstance = null;

function renderCharts() {
    var salesData = <?= json_encode($dailySales) ?>;
    var profileData = <?= json_encode($profileSales) ?>;
    
    // Get computed primary theme color dynamically
    var primaryColor = window.getComputedStyle(document.body).getPropertyValue('--primary').trim() || '<?= $portal_accent_color ?>';
    
    // Convert theme primary to rgba for line area fill background
    var primaryGlow = 'rgba(60, 80, 224, 0.1)';
    if (primaryColor.startsWith('#')) {
        var r = parseInt(primaryColor.slice(1, 3), 16);
        var g = parseInt(primaryColor.slice(3, 5), 16);
        var b = parseInt(primaryColor.slice(5, 7), 16);
        if (!isNaN(r) && !isNaN(g) && !isNaN(b)) {
            primaryGlow = `rgba(${r}, ${g}, ${b}, 0.1)`;
        }
    } else if (primaryColor.startsWith('rgb')) {
        primaryGlow = primaryColor.replace('rgb', 'rgba').replace(')', ', 0.1)');
    }
    
    // Setup Sales Chart
    var salesCtx = document.getElementById('dailySalesChart');
    if (salesCtx) {
        if (salesChartInstance) {
            salesChartInstance.destroy();
        }
        
        var labels = Object.keys(salesData);
        var values = Object.values(salesData);
        
        salesChartInstance = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Omzet (Rp)',
                    data: values,
                    borderColor: primaryColor,
                    backgroundColor: primaryGlow,
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Setup Profile Chart
    var profileCtx = document.getElementById('profileSalesChart');
    if (profileCtx) {
        if (profileChartInstance) {
            profileChartInstance.destroy();
        }
        
        var pLabels = [];
        var pCounts = [];
        for (var prof in profileData) {
            pLabels.push(prof);
            pCounts.push(profileData[prof].count);
        }
        
        if (pLabels.length === 0) {
            pLabels.push('Tidak Ada Transaksi');
            pCounts.push(0);
        }
        
        profileChartInstance = new Chart(profileCtx, {
            type: 'doughnut',
            data: {
                labels: pLabels,
                datasets: [{
                    data: pCounts,
                    backgroundColor: [
                        '#008BC9', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#6B7280'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12 }
                    }
                }
            }
        });
    }
}

function loadChartJsAndRender() {
    if (window.Chart) {
        renderCharts();
        return;
    }
    
    var timeout = setTimeout(function() {
        if (!window.Chart) {
            showFallback();
        }
    }, 5000);

    var script = document.createElement('script');
    script.src = "https://cdn.jsdelivr.net/npm/chart.js";
    script.onload = function() {
        clearTimeout(timeout);
        renderCharts();
    };
    script.onerror = function() {
        clearTimeout(timeout);
        showFallback();
    };
    document.head.appendChild(script);
}

function showFallback() {
    var salesCanvas = document.getElementById('dailySalesChart');
    var profileCanvas = document.getElementById('profileSalesChart');
    if (salesCanvas) salesCanvas.style.display = 'none';
    if (profileCanvas) profileCanvas.style.display = 'none';
    
    var salesMsg = document.getElementById('chart-offline-msg');
    var profileMsg = document.getElementById('doughnut-offline-msg');
    if (salesMsg) salesMsg.style.display = 'block';
    if (profileMsg) profileMsg.style.display = 'block';
}

function openTab(panelId, btnEl) {
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
    document.querySelectorAll('.tab-header-btn').forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(panelId).classList.add('active');
    btnEl.classList.add('active');
    
    // Store active tab in localStorage
    localStorage.setItem('mikhtrans_active_tab', panelId);
    
    if (panelId === 'tab-analytics') {
        loadChartJsAndRender();
    } else if (panelId === 'tab-logs') {
        fetchLatestLogs();
    }
}

// Filter History Table by Status
function filterHistoryTable() {
    var select = document.getElementById('historyStatusFilter');
    if (!select) return;
    var filterValue = select.value;
    var rows = document.querySelectorAll('#historyTableBody tr');
    
    rows.forEach(function(row) {
        var status = row.getAttribute('data-status');
        if (filterValue === 'all' || status === filterValue) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Restore active tab and initialize real-time features on load
document.addEventListener("DOMContentLoaded", function() {
    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get('tab');
    
    if (!activeTab) {
        activeTab = localStorage.getItem('mikhtrans_active_tab');
    }
    
    if (activeTab && document.getElementById(activeTab)) {
        var btn = Array.from(document.querySelectorAll('.tab-header-btn')).find(b => b.getAttribute('onclick').includes(activeTab));
        if (btn) {
            openTab(activeTab, btn);
        }
    } else {
        // Default to pending tab if nothing is set
        var defaultBtn = document.querySelector('.tab-header-btn');
        if (defaultBtn) {
            openTab('tab-pending', defaultBtn);
        }
    }

    // Initialize Real-time Dashboard Updates
    initDashboardRealtimeFeatures();
});

// Play audio chime using Web Audio API (Synthesized client-side)
function playChimeSound() {
    try {
        var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        var now = audioCtx.currentTime;
        
        // Tone 1: D5 (587.33 Hz)
        var osc1 = audioCtx.createOscillator();
        var gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now);
        gain1.gain.setValueAtTime(0.2, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);
        
        // Tone 2: A5 (880.00 Hz) - played slightly later
        var osc2 = audioCtx.createOscillator();
        var gain2 = audioCtx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880.00, now + 0.08);
        gain2.gain.setValueAtTime(0.2, now + 0.08);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.43);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(now + 0.08);
        osc2.stop(now + 0.43);
    } catch (e) {
        console.warn("Web Audio API blocked or not supported: ", e);
    }
}

// Show Toast Notification
function showToastNotification(title, message) {
    var container = document.getElementById("toastContainer");
    if (!container) return;
    
    var toast = document.createElement("div");
    toast.className = "toast-notification";
    toast.innerHTML = `
        <i class="fa fa-bell" style="color: #10b981; font-size: 20px;"></i>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-desc">${message}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Play sound notification
    playChimeSound();
    
    // Auto-remove toast after 6 seconds
    setTimeout(function() {
        toast.classList.add("fade-out");
        toast.addEventListener("animationend", function() {
            toast.remove();
        });
    }, 6000);
}

// Update UI dashboard statistics
function updateDashboardStats(data) {
    if (!data) return;
    
    // Welcome status banner stats update
    var welcomeBadges = document.querySelector('.dash-welcome-badges');
    if (welcomeBadges) {
        // Pending Queue count
        var antreanEl = Array.from(welcomeBadges.querySelectorAll('.welcome-badge')).find(el => el.innerHTML.includes('Antrean') || el.innerHTML.includes('Queue'));
        if (antreanEl) {
            var label = antreanEl.innerHTML.includes('Antrean') ? 'Antrean' : 'Queue';
            antreanEl.innerHTML = `<i class="fa fa-clock-o"></i> ${label}: ${data.pending_count}`;
        }
        // Success count
        var suksesEl = Array.from(welcomeBadges.querySelectorAll('.welcome-badge')).find(el => el.innerHTML.includes('Sukses') || el.innerHTML.includes('Success'));
        if (suksesEl) {
            var label = suksesEl.innerHTML.includes('Sukses') ? 'Sukses' : 'Success';
            suksesEl.innerHTML = `<i class="fa fa-check-circle"></i> ${label}: ${data.success_count}`;
        }
        // Revenue count
        var revenueEl = Array.from(welcomeBadges.querySelectorAll('.welcome-badge')).find(el => el.innerHTML.includes('Rp'));
        if (revenueEl) {
            revenueEl.innerHTML = `<i class="fa fa-money"></i> Rp ${data.total_revenue.toLocaleString('id-ID')}`;
        }
    }
    
    // Tab Analytics stats area update
    var statArea = document.querySelector('#tab-analytics');
    if (statArea) {
        var statVals = statArea.querySelectorAll('.stat-main-val');
        if (statVals.length >= 3) {
            statVals[0].textContent = `Rp ${data.total_revenue.toLocaleString('id-ID')}`;
            statVals[1].textContent = data.success_count;
            statVals[2].textContent = data.pending_count;
        }
    }
    
    // Tab Headers Queue Count update
    var pendingTabBtn = Array.from(document.querySelectorAll('.tab-header-btn')).find(b => b.innerHTML.includes('Antrean Tertunda') || b.innerHTML.includes('Pending Queue'));
    if (pendingTabBtn) {
        var label = pendingTabBtn.innerHTML.includes('Antrean/Queue') ? 'Antrean/Queue' : (pendingTabBtn.innerHTML.includes('Antrean Tertunda') ? 'Antrean Tertunda' : 'Pending Queue');
        pendingTabBtn.innerHTML = `<i class="fa fa-clock-o"></i> ${label} (${data.pending_count})`;
    }
}

// Append log line to console UI
function appendLogToConsole(log) {
    var consoleEl = document.getElementById("logsConsole");
    if (!consoleEl) return;
    
    // Remove loading indicator if present
    var loadingEl = consoleEl.querySelector(".fa-info-circle");
    if (loadingEl && loadingEl.parentElement) {
        loadingEl.parentElement.remove();
    }
    
    // Map log types to CSS classes
    var typeLower = log.type.toLowerCase();
    var levelClass = "info";
    if (typeLower.includes("success") || typeLower.includes("settlement") || typeLower.includes("capture")) {
        levelClass = "success";
    } else if (typeLower.includes("error") || typeLower.includes("fail")) {
        levelClass = "error";
    } else if (typeLower.includes("warn")) {
        levelClass = "warning";
    }
    
    var line = document.createElement("div");
    line.className = "console-line";
    line.setAttribute("data-level", levelClass);
    line.innerHTML = `
        <span class="log-time">[${log.time}]</span>
        <span class="log-type ${levelClass}">${log.type}</span>
        <span class="log-ip">[${log.ip}]</span>
        <span class="log-msg">${log.message}</span>
    `;
    
    consoleEl.appendChild(line);
    
    // Keep maximum 30 log lines
    var lines = consoleEl.querySelectorAll(".console-line");
    if (lines.length > 30) {
        lines[0].remove();
    }
    
    // Apply current filter settings
    filterConsoleLogs();
    
    // Auto-scroll to bottom
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

// Fetch latest logs from backend API
function fetchLatestLogs() {
    var consoleEl = document.getElementById("logsConsole");
    if (!consoleEl) return;
    
    fetch("process/admin_get_logs.php")
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                consoleEl.innerHTML = "";
                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach(function(log) {
                        appendLogToConsole(log);
                    });
                } else {
                    consoleEl.innerHTML = '<div class="console-line" style="color: #64748b;"><i class="fa fa-info-circle"></i> Belum ada catatan aktivitas log.</div>';
                }
            }
        })
        .catch(function(e) {
            console.error("Error loading console logs:", e);
            consoleEl.innerHTML = '<div class="console-line" style="color: #ef4444;"><i class="fa fa-exclamation-triangle"></i> Gagal memuat catatan log dari server.</div>';
        });
}

// Filter console logs based on selected level dropdown
function filterConsoleLogs() {
    var select = document.getElementById("logFilterLevel");
    if (!select) return;
    var filterValue = select.value;
    var lines = document.querySelectorAll("#logsConsole .console-line");
    
    lines.forEach(function(line) {
        var level = line.getAttribute("data-level");
        if (filterValue === "all" || level === filterValue) {
            line.style.display = "";
        } else {
            line.style.display = "none";
        }
    });
}

// Clear logs in the console UI
function clearConsoleLogUI() {
    var consoleEl = document.getElementById("logsConsole");
    if (consoleEl) {
        consoleEl.innerHTML = '<div class="console-line" style="color: #64748b;"><i class="fa fa-info-circle"></i> Tampilan log dibersihkan secara lokal.</div>';
    }
}

// Global configurations for updates
var AdminWSConfig = {
    enabled: <?= !empty($ws_app_key) ? 'true' : 'false' ?>,
    key: "<?= htmlspecialchars($ws_app_key) ?>",
    cluster: "<?= htmlspecialchars($ws_cluster) ?>",
    host: "<?= htmlspecialchars($ws_host) ?>",
    port: "<?= htmlspecialchars($ws_port) ?>",
    scheme: "<?= htmlspecialchars($ws_scheme) ?>",
    lastCheckTime: Math.floor(Date.now() / 1000)
};

// Initialize features on load
function initDashboardRealtimeFeatures() {
    var statusBadge = document.getElementById("wsStatusBadge");
    
    // Define helper to trigger UI reload if viewing pending or history queue
    function reloadQueueUiIfSafe() {
        var activeTab = localStorage.getItem('mikhtrans_active_tab') || 'tab-pending';
        var isEditing = document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA' || document.activeElement.tagName === 'SELECT');
        if ((activeTab === 'tab-pending' || activeTab === 'tab-history') && !isEditing) {
            setTimeout(function() {
                window.location.reload();
            }, 2500);
        }
    }

    if (AdminWSConfig.enabled && typeof Pusher !== 'undefined') {
        // Mode WebSocket: Pusher / Soketi
        var pusherConfig = {};
        if (AdminWSConfig.host) {
            var portVal = AdminWSConfig.port ? parseInt(AdminWSConfig.port, 10) : 6001;
            var isSecure = AdminWSConfig.scheme === 'https' || AdminWSConfig.scheme === 'wss';
            pusherConfig = {
                wsHost: AdminWSConfig.host,
                wsPort: portVal,
                wssPort: portVal,
                forceTLS: isSecure,
                disableStats: true,
                enabledTransports: ['ws', 'wss']
            };
        } else {
            pusherConfig = {
                cluster: AdminWSConfig.cluster
            };
        }
        
        try {
            var pusher = new Pusher(AdminWSConfig.key, pusherConfig);
            var channel = pusher.subscribe('admin-events');
            
            // Set status badge to WebSocket
            if (statusBadge) {
                statusBadge.textContent = "WS (Aktif)";
                statusBadge.style.backgroundColor = "#10b981"; // Green
            }
            
            channel.bind('new-payment', function(data) {
                // Tampilkan notifikasi toast dan suara bel
                showToastNotification(
                    "Pemasukan Baru!",
                    `Paket <b>${data.profile}</b> seharga <b>Rp ${data.price.toLocaleString('id-ID')}</b> baru saja dibayar.`
                );
                
                // Update dashboard statistics
                if (data.total_revenue && data.success_count) {
                    updateDashboardStats({
                        total_revenue: data.total_revenue,
                        success_count: data.success_count,
                        pending_count: data.pending_count
                    });
                }
                
                reloadQueueUiIfSafe();
            });
            
            channel.bind('new-log', function(data) {
                appendLogToConsole(data);
            });
            
            console.log("WebSocket Dashboard Listener connected.");
        } catch (e) {
            console.error("Failed to connect WebSocket on admin panel. Falling back to HTTP polling...", e);
            startAdminHttpPolling();
        }
    } else {
        // Fallback Mode: HTTP Polling (Setiap 15 detik)
        startAdminHttpPolling();
    }
    
    function startAdminHttpPolling() {
        console.log("HTTP Polling Dashboard Listener started (15s intervals).");
        if (statusBadge) {
            statusBadge.textContent = "Polling";
            statusBadge.style.backgroundColor = "#f59e0b"; // Orange/Yellow
        }
        
        setInterval(function() {
            fetch(`process/admin_check_updates.php?last_check_time=${AdminWSConfig.lastCheckTime}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Perbarui data timestamp pengecekan
                        AdminWSConfig.lastCheckTime = data.server_time;
                        
                        // Perbarui dashboard stats
                        updateDashboardStats(data);
                        
                        // Cek jika ada pembayaran baru
                        if (data.new_payments && data.new_payments.length > 0) {
                            data.new_payments.forEach(function(pay) {
                                showToastNotification(
                                    "Pemasukan Baru!",
                                    `Paket <b>${pay.profile}</b> seharga <b>Rp ${pay.price.toLocaleString('id-ID')}</b> baru saja dibayar.`
                                );
                            });
                            
                            reloadQueueUiIfSafe();
                        }
                    }
                })
                .catch(e => console.error("Error checking dashboard updates: ", e));
        }, 15000);

        // Poll for logs console updates
        setInterval(function() {
            var activeTab = localStorage.getItem('mikhtrans_active_tab') || 'tab-pending';
            if (activeTab === 'tab-logs') {
                fetchLatestLogs();
            }
        }, 15000);
    }
}
</script>
