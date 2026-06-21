<?php
/**
 * Webhook Pending Transactions & History Manager
 * MikhTrans v1.1
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
        $order_id = isset($_POST['order_id']) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $_POST['order_id']) : '';
        $filepath = __DIR__ . '/../voucher/trans-' . $order_id . '.json';
        
        if (!empty($order_id) && file_exists($filepath)) {
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
        } else {
            $error_msg = "Berkas data transaksi tidak ditemukan.";
        }
    } elseif ($_POST['action'] === 'save_settings') {
        $bot_token = isset($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : '';
        $chat_id = isset($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : '';
        
        $settingsModel->set('telegram_bot_token', $bot_token);
        $settingsModel->set('telegram_chat_id', $chat_id);
        
        $success_msg = ($langid == 'id') 
            ? "Sukses! Pengaturan Telegram berhasil disimpan." 
            : "Success! Telegram settings successfully saved.";
    } elseif ($_POST['action'] === 'test_telegram') {
        $testMsg = "🔔 <b>[MikhTrans] Test Notifikasi Sukses!</b>\n\nIntegrasi Telegram Bot Anda telah berhasil dikonfigurasi.";
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

// Load Telegram credentials
$telegram_bot_token = $settingsModel->get('telegram_bot_token', mikhmonEnv('TELEGRAM_BOT_TOKEN', ''));
$telegram_chat_id = $settingsModel->get('telegram_chat_id', mikhmonEnv('TELEGRAM_CHAT_ID', ''));

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
?>

<style>
.tab-container {
    margin-bottom: 24px;
}
.tab-headers {
    display: flex;
    gap: 8px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 8px;
    margin-bottom: 16px;
}
.tab-header-btn {
    background: transparent;
    border: none;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
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
}
.trans-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    font-size: 13px;
    text-align: left;
    color: var(--text-main);
}
.trans-table th {
    padding: 12px 16px;
    background: var(--background-alt, #F5F6F7);
    font-weight: 600;
    border-bottom: 1px solid var(--border-color);
}
.trans-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
}
.trans-table tr:hover {
    background: rgba(0, 0, 0, 0.02);
}
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
.btn-action-retry {
    background: #EF4444;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background-color 0.2s ease;
}
.btn-action-retry:hover {
    background: #DC2626;
}
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
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-exchange"></i> <?= ($langid == 'id') ? 'Antrean Webhook & Transaksi' : 'Webhook Queue & Transactions' ?>
                </h3>
            </div>
            <div class="card-body">
                
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
                            <?= ($langid == 'id') ? 'Antrean Tertunda' : 'Pending Queue' ?> (<?= count($pendingTransactions) ?>)
                        </button>
                        <button class="tab-header-btn" onclick="openTab('tab-history', this)">
                            <?= ($langid == 'id') ? 'Riwayat Transaksi' : 'Transaction History' ?>
                        </button>
                        <button class="tab-header-btn" onclick="openTab('tab-settings', this)">
                            <?= ($langid == 'id') ? 'Pengaturan & Backup' : 'Settings & Backup' ?>
                        </button>
                    </div>

                    <!-- Panel Pending Transactions -->
                    <div id="tab-pending" class="tab-panel active">
                        <?php if (empty($pendingTransactions)): ?>
                            <div style="text-align: center; padding: 40px 20px;">
                                <i class="fa fa-circle-check" style="font-size: 48px; color: #10B981; margin-bottom: 12px; display: block;"></i>
                                <strong style="color: var(--text-main); font-size: 15px; display: block; margin-bottom: 4px;">
                                    <?= ($langid == 'id') ? 'Antrean Bersih' : 'Queue is Clean' ?>
                                </strong>
                                <span style="color: var(--text-muted); font-size: 13px;">
                                    <?= ($langid == 'id') ? 'Tidak ada transaksi tertunda. Semua voucher sukses diterbitkan ke router.' : 'No pending voucher generation tasks found.' ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="trans-table">
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
                                                        <button type="submit" class="btn-action-retry">
                                                            <i class="fa fa-refresh"></i> <?= ($langid == 'id') ? 'Generate' : 'Retry' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Transaction History -->
                    <div id="tab-history" class="tab-panel">
                        <?php if (empty($historyTransactions)): ?>
                            <div style="text-align: center; padding: 40px 20px;">
                                <i class="fa fa-folder-open" style="font-size: 48px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                                <span style="color: var(--text-muted); font-size: 13px;">
                                    <?= ($langid == 'id') ? 'Belum ada riwayat transaksi.' : 'No transaction history available.' ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="trans-table">
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
                                    <tbody>
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
                                            <tr>
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
                        <?php endif; ?>
                    </div>

                    <!-- Panel Settings & Backup -->
                    <div id="tab-settings" class="tab-panel">
                        <div class="row">
                            <!-- Kolom Kiri: Telegram Bot Settings -->
                            <div class="col-6">
                                <div class="card" style="border: 1px solid var(--border-color, #c1c1c1); border-radius: 8px; margin-bottom: 20px; box-shadow: none;">
                                    <div class="card-header" style="background: var(--background-alt, #F5F6F7); border-bottom: 1px solid var(--border-color, #c1c1c1); padding: 12px 16px;">
                                        <h3 class="card-title" style="font-size: 14px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 8px;">
                                            <i class="fa fa-paper-plane" style="color: #0088cc;"></i> Telegram Notifikasi Bot
                                        </h3>
                                    </div>
                                    <div class="card-body" style="padding: 16px;">
                                        <form method="post" action="" autocomplete="off" style="margin-bottom: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="save_settings" />
                                            
                                            <div class="form-group-floating">
                                                <input class="form-control" id="telegram_bot_token" type="text" name="telegram_bot_token" placeholder=" " value="<?= htmlspecialchars($telegram_bot_token) ?>"/>
                                                <label for="telegram_bot_token">Telegram Bot Token</label>
                                            </div>
                                            
                                            <div class="form-group-floating">
                                                <input class="form-control" id="telegram_chat_id" type="text" name="telegram_chat_id" placeholder=" " value="<?= htmlspecialchars($telegram_chat_id) ?>"/>
                                                <label for="telegram_chat_id">Telegram Chat ID</label>
                                            </div>
                                            
                                            <div style="font-size: 11px; color: var(--text-muted, #73818f); margin-bottom: 20px; line-height: 1.4; text-align: left;">
                                                * Buat bot via <a href="https://t.me/BotFather" target="_blank" style="color: var(--primary, #008BC9); font-weight: 600;">@BotFather</a> untuk mendapatkan Token.<br>
                                                * Chat <a href="https://t.me/userinfobot" target="_blank" style="color: var(--primary, #008BC9); font-weight: 600;">@userinfobot</a> untuk mendapatkan Chat ID Anda.<br>
                                                * Pastikan Anda sudah klik <code>/start</code> pada bot Anda sebelum mengirim test.
                                            </div>
                                            
                                            <button type="submit" class="btn-modern-save" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                <i class="fa fa-save"></i> <?= ($langid == 'id') ? 'Simpan Pengaturan' : 'Save Settings' ?>
                                            </button>
                                        </form>
                                        
                                        <?php if (!empty($telegram_bot_token) && !empty($telegram_chat_id)): ?>
                                            <form method="post" action="" style="margin-top: 12px; margin-bottom: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="test_telegram" />
                                                <button type="submit" class="btn-modern-save" style="background: var(--accent, #4dbd74) !important; color: white !important; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                    <i class="fa fa-send"></i> <?= ($langid == 'id') ? 'Kirim Test Notifikasi' : 'Send Test Notification' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Kolom Ranan: Backup & Restore -->
                            <div class="col-6">
                                <div class="card" style="border: 1px solid var(--border-color, #c1c1c1); border-radius: 8px; margin-bottom: 20px; box-shadow: none;">
                                    <div class="card-header" style="background: var(--background-alt, #F5F6F7); border-bottom: 1px solid var(--border-color, #c1c1c1); padding: 12px 16px;">
                                        <h3 class="card-title" style="font-size: 14px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 8px;">
                                            <i class="fa fa-download" style="color: var(--accent, #4dbd74);"></i> Backup Data MikhTrans
                                        </h3>
                                    </div>
                                    <div class="card-body" style="padding: 16px;">
                                        <p style="font-size: 12px; color: var(--text-main); margin-bottom: 16px; line-height: 1.5; text-align: left;">
                                            Unduh seluruh konfigurasi MikhTrans Anda, termasuk kredensial pembayaran Midtrans (<code>.env</code>), berkas sesi MikroTik/Admin (<code>database.php</code>), serta riwayat log voucher (<code>trans-*.json</code>) dalam satu paket file ZIP.
                                        </p>
                                        
                                        <div style="background: var(--background-alt, #F5F6F7); border: 1px dashed var(--border-color, #c1c1c1); border-radius: 6px; padding: 12px; margin-bottom: 20px; font-size: 11px; color: var(--text-muted, #73818f); text-align: left;">
                                            <strong>Isi File Backup ZIP:</strong>
                                            <ul style="margin: 6px 0 0 16px; padding: 0;">
                                                <li><code>.env</code> (Kredensial API & WebSocket)</li>
                                                <li><code>data/database.php</code> (Konfigurasi Admin & Sesi)</li>
                                                <li><code>voucher/*.json</code> (Logs & Antrean Transaksi)</li>
                                            </ul>
                                        </div>
                                        
                                        <form method="post" action="" style="margin-bottom: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="download_backup" />
                                            <button type="submit" class="btn-modern-save" style="background: var(--accent, #4dbd74) !important; color: white !important; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; height: 44px;">
                                                <i class="fa fa-file-archive-o"></i> <?= ($langid == 'id') ? 'Buat & Unduh Backup' : 'Create & Download Backup' ?>
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
    </div>
</div>

<script>
function openTab(panelId, btnEl) {
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
    document.querySelectorAll('.tab-header-btn').forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(panelId).classList.add('active');
    btnEl.classList.add('active');
    
    // Store active tab in localStorage
    localStorage.setItem('mikhtrans_active_tab', panelId);
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
