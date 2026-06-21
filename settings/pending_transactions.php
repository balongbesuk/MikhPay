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
$success_msg = '';
$error_msg = '';

// Handle Actions (Retry Generate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retry') {
    csrf_verify();
    
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
}

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
}
</script>
