<?php
/**
 * Webhook Pending Transactions & History Manager
 * MikhPay v1.1
 */

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

// CSRF check
include_once('./include/csrf.php');

$dbSessions = new \App\Models\RouterSession();
$settingsModel = new \App\Models\AppSettings();
$success_msg = '';
$error_msg = '';

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
    
    // 1. Add .env (if exists)
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $zip->addFile($envFile, '.env');
    }
    
    // 2. Add data/database.php
    $dbFile = __DIR__ . '/../data/database.php';
    if (file_exists($dbFile)) {
        $zip->addFile($dbFile, 'data/database.php');
    }
    
    // 3. Add voucher/*.json files
    $voucherDir = __DIR__ . '/../voucher/';
    if (file_exists($voucherDir)) {
        $files = glob($voucherDir . 'trans-*.json');
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
    
    // Add .env
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $filesToArchive['.env'] = $envFile;
    }
    
    // Add data/database.php
    $dbFile = __DIR__ . '/../data/database.php';
    if (file_exists($dbFile)) {
        $filesToArchive['data/database.php'] = $dbFile;
    }
    
    // Add voucher/*.json
    $voucherDir = __DIR__ . '/../voucher/';
    if (file_exists($voucherDir)) {
        $files = glob($voucherDir . 'trans-*.json');
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
            $filepath = __DIR__ . '/../voucher/trans-' . $order_id . '.json';
            $real_path = realpath(dirname($filepath));
            $voucher_dir = realpath(__DIR__ . '/../voucher');
            
            if ($real_path !== $voucher_dir || !file_exists($filepath)) {
                $error_msg = "Berkas data transaksi tidak ditemukan.";
            } else {
                $trans = json_decode(file_get_contents($filepath), true);
                
                if (isset($trans['status']) && $trans['status'] === 'paid_pending_generate') {
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
                            
                            $comment = "API-Retry-" . rand(100, 999) . "-" . date("m.d.y") . "-Paid QRIS";
                            
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
                            
                            file_put_contents($filepath, json_encode($trans));
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
        
        $settingsModel->set('telegram_bot_token', $bot_token);
        $settingsModel->set('telegram_chat_id', $chat_id);
        $settingsModel->set('log_retention_days', $log_retention);
        $settingsModel->set('portal_title', $p_title);
        $settingsModel->set('portal_logo_url', $p_logo);
        $settingsModel->set('portal_accent_color', $p_accent);
        $settingsModel->set('portal_support_wa', $p_wa);
        $settingsModel->set('portal_support_telegram', $p_tele);
        
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

// Load and group transactions
$dir = __DIR__ . '/../voucher/';
$files = glob($dir . 'trans-*.json');

$pendingTransactions = [];
$historyTransactions = [];
$now = time();

if (is_array($files)) {
    // Sort files by last modified time descending (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($files as $file) {
        $dataTrans = json_decode(file_get_contents($file), true);
        if ($dataTrans) {
            $order = isset($dataTrans['order_id']) ? $dataTrans['order_id'] : basename($file, '.json');
            $status = isset($dataTrans['status']) ? $dataTrans['status'] : 'pending';
            
            // Add file details
            $dataTrans['order_id'] = $order;
            $dataTrans['file_time'] = filemtime($file);
            
            if ($status === 'paid_pending_generate') {
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
        $dataTrans = json_decode(file_get_contents($file), true);
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
/* Welcome Status Banner Styles (aligned with dashboard) */
.dash-welcome {
    background: var(--welcome-bg) !important;
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-color) !important;
}
@media (max-width: 750px) {
    .dash-welcome {
        padding: 18px 20px !important;
        border-radius: 12px !important;
    }
    .dash-welcome h2 {
        font-size: 18px !important;
    }
}
@media (max-width: 576px) {
    .dash-welcome-content {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 16px !important;
    }
    .dash-welcome-time {
        text-align: left !important;
    }
}
.dash-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.dash-welcome::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: 10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.dash-welcome-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dash-welcome h2 {
    font-size: 22px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.dash-welcome p {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.65);
    margin: 0;
    font-weight: 500;
}
.dash-welcome-time {
    text-align: right;
}
.dash-welcome-time .time-big {
    font-size: 36px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -1px;
    line-height: 1;
}
.dash-welcome-time .time-date {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.55);
    font-weight: 500;
    margin-top: 4px;
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
</style>

<div class="row">
    <div class="col-12">
        
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
                    <h2><?= ($langid == 'id') ? 'Antrean Webhook & Transaksi' : 'Webhook Queue & Transactions' ?></h2>
                    <div class="dash-welcome-badges">
                        <span class="welcome-badge"><i class="fa fa-clock-o"></i> <?= ($langid == 'id') ? 'Antrean' : 'Queue' ?>: <?= count($pendingTransactions) ?></span>
                        <span class="welcome-badge"><i class="fa fa-check-circle"></i> <?= ($langid == 'id') ? 'Sukses' : 'Success' ?>: <?= $successCount ?></span>
                        <span class="welcome-badge"><i class="fa fa-money"></i> Rp <?= number_format($totalRevenue, 0, ',', '.') ?></span>
                    </div>
                </div>
                <div class="dash-welcome-time">
                    <div class="time-big" style="font-size: 24px;"><i class="fa fa-exchange"></i></div>
                    <div class="time-date">MikhPay v1.4</div>
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
            <div class="tab-headers">
                <button class="tab-header-btn active" onclick="openTab('tab-pending', this)">
                    <i class="fa fa-clock-o"></i> <?= ($langid == 'id') ? 'Antrean Tertunda' : 'Pending Queue' ?> (<?= count($pendingTransactions) ?>)
                </button>
                <button class="tab-header-btn" onclick="openTab('tab-analytics', this)">
                    <i class="fa fa-line-chart"></i> <?= ($langid == 'id') ? 'Analitik Penjualan' : 'Sales Analytics' ?>
                </button>
                <button class="tab-header-btn" onclick="openTab('tab-history', this)">
                    <i class="fa fa-history"></i> <?= ($langid == 'id') ? 'Riwayat Transaksi' : 'Transaction History' ?>
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
                                            <th>Username Voucher</th>
                                            <th style="text-align: center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <?php foreach ($historyTransactions as $ht): ?>
                                            <?php
                                            $stVal = isset($ht['status']) ? $ht['status'] : 'pending';
                                            $badgeClass = 'pending';
                                            $badgeLabel = 'Pending';
                                            if ($stVal === 'settlement' || $stVal === 'capture') {
                                                $badgeClass = 'success';
                                                $badgeLabel = 'Lunas';
                                            } elseif ($stVal === 'failed') {
                                                $badgeClass = 'failed';
                                                $badgeLabel = 'Gagal';
                                            }
                                            ?>
                                            <tr data-status="<?= $badgeClass ?>">
                                                <td><?= date("Y-m-d H:i:s", isset($ht['paid_at']) ? $ht['paid_at'] : (isset($ht['created_at']) ? $ht['created_at'] : $ht['file_time'])) ?></td>
                                                <td style="font-family: monospace;"><?= htmlspecialchars($ht['order_id']) ?></td>
                                                <td><span class="badge bg-grey"><?= htmlspecialchars($ht['session']) ?></span></td>
                                                <td><?= htmlspecialchars($ht['profile']) ?></td>
                                                <td>Rp <?= number_format($ht['price'], 0, ',', '.') ?></td>
                                                <td style="font-family: monospace; font-weight: bold;"><?= isset($ht['username']) ? htmlspecialchars($ht['username']) : '-' ?></td>
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

            <!-- Panel Settings & Backup -->
            <div id="tab-settings" class="tab-panel">
                <style>
                    #tab-settings .form-group { margin-bottom: 16px; }
                    #tab-settings .form-group label {
                        display: block;
                        font-size: 11px;
                        font-weight: 700;
                        color: var(--text-muted);
                        margin-bottom: 6px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    #tab-settings .mt-hint {
                        font-size: 11px;
                        color: var(--text-muted);
                        line-height: 1.5;
                        margin-top: 6px;
                    }
                    #tab-settings .mt-color-row {
                        display: flex;
                        gap: 10px;
                        align-items: center;
                    }
                    #tab-settings .mt-color-row input[type="color"] {
                        width: 42px;
                        height: 42px;
                        border: 1px solid var(--border-color);
                        border-radius: var(--radius, 8px);
                        cursor: pointer;
                        padding: 3px;
                        background: var(--input-bg);
                    }
                    #tab-settings .mt-color-row span {
                        font-family: monospace;
                        font-size: 13px;
                        color: var(--text-muted);
                        font-weight: 600;
                    }
                    #tab-settings .mt-info-box {
                        background: var(--bg-card-hover, rgba(0,0,0,0.02));
                        border: 1px dashed var(--border-color);
                        border-radius: var(--radius, 8px);
                        padding: 14px;
                        margin-bottom: 16px;
                        font-size: 11px;
                        color: var(--text-muted);
                        line-height: 1.6;
                    }
                    #tab-settings .mt-info-box strong { color: var(--text-main); }
                    #tab-settings .mt-info-box code {
                        background: var(--primary-glow);
                        color: var(--primary);
                        padding: 1px 5px;
                        border-radius: 4px;
                        font-size: 11px;
                    }
                    #tab-settings .mt-info-box ul { margin: 6px 0 0 16px; padding: 0; }
                    
                    /* Custom Layout styles */
                    #tab-settings .row-flex {
                        display: flex !important;
                        flex-wrap: wrap !important;
                        margin: 0 -12px !important;
                    }
                    #tab-settings .col-flex-6 {
                        box-sizing: border-box !important;
                        width: 50% !important;
                        padding: 0 12px !important;
                        display: flex !important;
                        flex-direction: column !important;
                    }
                    @media (min-width: 751px) {
                        #tab-settings .col-left {
                            border-right: 1px solid var(--border-color) !important;
                            padding-right: 24px !important;
                        }
                        #tab-settings .col-right {
                            padding-left: 24px !important;
                        }
                    }
                    @media (max-width: 750px) {
                        #tab-settings .col-flex-6 {
                            width: 100% !important;
                        }
                        #tab-settings .col-left {
                            border-right: none !important;
                            padding-right: 12px !important;
                            margin-bottom: 24px;
                        }
                    }
                </style>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa fa-sliders"></i> <?= ($langid == 'id') ? 'Pengaturan & Backup' : 'Settings & Backup' ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row-flex">
                            <!-- Left Column: Bot Settings, Test Bot, and Backup Data -->
                            <div class="col-flex-6 col-left">
                                
                                <!-- Telegram Bot Settings -->
                                <form method="post" action="" autocomplete="off" style="margin-bottom: 24px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="save_settings" />
                                    <!-- Preserve other settings when saving telegram bot -->
                                    <input type="hidden" name="portal_title" value="<?= htmlspecialchars($portal_title) ?>" />
                                    <input type="hidden" name="portal_logo_url" value="<?= htmlspecialchars($portal_logo_url) ?>" />
                                    <input type="hidden" name="portal_accent_color" value="<?= htmlspecialchars($portal_accent_color) ?>" />
                                    <input type="hidden" name="portal_support_wa" value="<?= htmlspecialchars($portal_support_wa) ?>" />
                                    <input type="hidden" name="portal_support_telegram" value="<?= htmlspecialchars($portal_support_telegram) ?>" />
                                    <input type="hidden" name="log_retention_days" value="<?= $log_retention_days ?>" />
                                    
                                    <h4 style="margin-top: 0; margin-bottom: 16px; font-weight: 700; font-size: 14px; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-paper-plane" style="color: #0088cc;"></i> Telegram Bot
                                    </h4>
                                    <div class="form-group">
                                        <label for="telegram_bot_token">Bot Token</label>
                                        <input class="form-control" type="text" id="telegram_bot_token" name="telegram_bot_token" value="<?= htmlspecialchars($telegram_bot_token) ?>" placeholder="123456789:ABCdef..."/>
                                    </div>
                                    <div class="form-group">
                                        <label for="telegram_chat_id">Chat ID</label>
                                        <input class="form-control" type="text" id="telegram_chat_id" name="telegram_chat_id" value="<?= htmlspecialchars($telegram_chat_id) ?>" placeholder="987654321"/>
                                    </div>
                                    <div class="mt-hint" style="margin-bottom: 16px;">
                                        Buat bot via <a href="https://t.me/BotFather" target="_blank">@BotFather</a> · Chat ID via <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>
                                    </div>
                                    <button type="submit" class="btn bg-primary" style="margin: 0; width: 100%; height: 38px; justify-content: center;">
                                        <i class="fa fa-save"></i> Simpan Pengaturan Bot
                                    </button>
                                </form>
                                
                                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 24px 0;">
                                
                                <!-- Test Notification -->
                                <?php if (!empty($telegram_bot_token) && !empty($telegram_chat_id)): ?>
                                    <div style="margin-bottom: 24px;">
                                        <h4 style="margin-top: 0; margin-bottom: 12px; font-weight: 700; font-size: 14px; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                            <i class="fa fa-send" style="color: #219653;"></i> Test Notifikasi
                                        </h4>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">Kirim pesan uji coba ke Telegram Anda untuk memastikan kredensial bot sudah benar.</p>
                                        <button type="submit" form="testTelegramForm" class="btn bg-green" style="margin: 0; width: 100%; height: 38px; justify-content: center;">
                                            <i class="fa fa-paper-plane"></i> Kirim Test ke Telegram
                                        </button>
                                    </div>
                                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 24px 0;">
                                <?php endif; ?>
                                
                                <!-- Backup Data -->
                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <h4 style="margin-top: 0; margin-bottom: 12px; font-weight: 700; font-size: 14px; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-download" style="color: var(--primary);"></i> Backup Data
                                    </h4>
                                    <div class="mt-info-box" style="margin-bottom: 16px; flex: 1;">
                                        <strong>Isi File Backup:</strong>
                                        <ul>
                                            <li><code>.env</code> — Kredensial API</li>
                                            <li><code>data/database.php</code> — Sesi & Admin</li>
                                            <li><code>voucher/*.json</code> — Log Transaksi</li>
                                        </ul>
                                        <em style="display:block; margin-top:6px;">* Format TAR jika ZipArchive tidak aktif.</em>
                                    </div>
                                    <button type="submit" form="backupForm" class="btn bg-primary" style="margin: 0; width: 100%; height: 38px; justify-content: center;">
                                        <i class="fa fa-file-archive-o"></i> Buat & Unduh Backup
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Right Column: Portal Pelanggan & Log Retention -->
                            <div class="col-flex-6 col-right">
                                <!-- Portal Customization Settings -->
                                <form method="post" action="" autocomplete="off" style="margin-bottom: 24px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="save_settings" />
                                    <!-- Preserve other settings when saving portal settings -->
                                    <input type="hidden" name="telegram_bot_token" value="<?= htmlspecialchars($telegram_bot_token) ?>" />
                                    <input type="hidden" name="telegram_chat_id" value="<?= htmlspecialchars($telegram_chat_id) ?>" />
                                    <input type="hidden" name="log_retention_days" value="<?= $log_retention_days ?>" />
                                    
                                    <h4 style="margin-top: 0; margin-bottom: 16px; font-weight: 700; font-size: 14px; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-paint-brush" style="color: var(--primary);"></i> Portal Pelanggan
                                    </h4>
                                    <div class="form-group">
                                        <label for="portal_title">Judul Portal</label>
                                        <input class="form-control" type="text" id="portal_title" name="portal_title" value="<?= htmlspecialchars($portal_title) ?>" required/>
                                    </div>
                                    <div class="form-group">
                                        <label for="portal_logo_url">URL Logo (Opsional)</label>
                                        <input class="form-control" type="text" id="portal_logo_url" name="portal_logo_url" value="<?= htmlspecialchars($portal_logo_url) ?>" placeholder="https://..."/>
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
                                        <input class="form-control" type="text" id="portal_support_wa" name="portal_support_wa" value="<?= htmlspecialchars($portal_support_wa) ?>" placeholder="+628123456789"/>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 16px;">
                                        <label for="portal_support_telegram">Telegram Support</label>
                                        <input class="form-control" type="text" id="portal_support_telegram" name="portal_support_telegram" value="<?= htmlspecialchars($portal_support_telegram) ?>" placeholder="username (tanpa @)"/>
                                    </div>
                                    <button type="submit" class="btn bg-primary" style="margin: 0; width: 100%; height: 38px; justify-content: center;">
                                        <i class="fa fa-save"></i> Simpan Pengaturan Portal
                                    </button>
                                </form>
                                
                                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 24px 0;">
                                
                                <!-- Log Retention -->
                                <form method="post" action="" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="save_settings" />
                                    <!-- Preserve other settings when saving retention -->
                                    <input type="hidden" name="telegram_bot_token" value="<?= htmlspecialchars($telegram_bot_token) ?>" />
                                    <input type="hidden" name="telegram_chat_id" value="<?= htmlspecialchars($telegram_chat_id) ?>" />
                                    <input type="hidden" name="portal_title" value="<?= htmlspecialchars($portal_title) ?>" />
                                    <input type="hidden" name="portal_logo_url" value="<?= htmlspecialchars($portal_logo_url) ?>" />
                                    <input type="hidden" name="portal_accent_color" value="<?= htmlspecialchars($portal_accent_color) ?>" />
                                    <input type="hidden" name="portal_support_wa" value="<?= htmlspecialchars($portal_support_wa) ?>" />
                                    <input type="hidden" name="portal_support_telegram" value="<?= htmlspecialchars($portal_support_telegram) ?>" />
                                    
                                    <h4 style="margin-top: 0; margin-bottom: 16px; font-weight: 700; font-size: 14px; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-clock-o" style="color: #10b981;"></i> Retensi Log
                                    </h4>
                                    <div class="form-group">
                                        <label for="log_retention_days">Masa Simpan Berkas Transaksi</label>
                                        <select class="form-control" id="log_retention_days" name="log_retention_days" style="width: 100%;">
                                            <option value="1" <?= $log_retention_days === 1 ? 'selected' : '' ?>>1 Hari</option>
                                            <option value="2" <?= $log_retention_days === 2 ? 'selected' : '' ?>>2 Hari (Default)</option>
                                            <option value="7" <?= $log_retention_days === 7 ? 'selected' : '' ?>>7 Hari</option>
                                            <option value="30" <?= $log_retention_days === 30 ? 'selected' : '' ?>>30 Hari</option>
                                            <option value="0" <?= $log_retention_days === 0 ? 'selected' : '' ?>>Simpan Selamanya</option>
                                        </select>
                                    </div>
                                    <div class="mt-hint" style="margin-bottom: 16px;">Berkas lama dihapus otomatis berdasarkan durasi di atas.</div>
                                    <button type="submit" class="btn bg-primary" style="margin: 0; width: 100%; height: 38px; justify-content: center;">
                                        <i class="fa fa-save"></i> Simpan Retensi Log
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
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

// Restore active tab on load
document.addEventListener("DOMContentLoaded", function() {
    var activeTab = localStorage.getItem('mikhtrans_active_tab');
    if (activeTab && document.getElementById(activeTab)) {
        var btn = Array.from(document.querySelectorAll('.tab-header-btn')).find(b => b.getAttribute('onclick').includes(activeTab));
        if (btn) {
            openTab(activeTab, btn);
        }
    }
});
</script>
