<?php
/**
 * Mikhmon frontpage.php
 * Halaman depan pelanggan untuk pembelian voucher hotspot otomatis menggunakan QRIS Mandiri.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Matikan display_errors di produksi untuk keamanan
date_default_timezone_set('Asia/Jakarta');

// Start session safely if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
        'use_only_cookies' => true
    ]);
}

// HTTP Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");


// Pastikan folder voucher ada dan aman (.htaccess)
if (!file_exists(__DIR__ . '/voucher')) {
    mkdir(__DIR__ . '/voucher', 0755, true);
}
$htaccess_content = "<Files \"*.json\">\n    Order Deny,Allow\n    Deny from all\n</Files>\n";
if (!file_exists(__DIR__ . '/voucher/.htaccess') || file_get_contents(__DIR__ . '/voucher/.htaccess') !== $htaccess_content) {
    file_put_contents(__DIR__ . '/voucher/.htaccess', $htaccess_content);
}

// Include config
if (!file_exists(__DIR__ . '/include/config.php')) {
    die("Configuration file config.php not found.");
}
include_once(__DIR__ . '/include/config.php');
include_once(__DIR__ . '/include/env_config.php');
include_once(__DIR__ . '/include/csrf.php');

// Auto-cleanup berkas transaksi usang secara berkala berbasis konfigurasi retensi
if (rand(1, 100) <= 5) {
    $dir = __DIR__ . '/voucher/';
    if (is_dir($dir)) {
        $retention_days = isset($dbSettings) ? (int)$dbSettings->get('log_retention_days', 2) : 2;
        $retention_seconds = $retention_days * 86400;
        $files = array_merge(
            glob($dir . 'trans-*.json'),
            glob($dir . 'trans-*.php')
        );
        foreach ($files as $file) {
            if (time() - filemtime($file) > $retention_seconds) {
                @unlink($file);
            }
        }
    }
}

// Safety defaults for QRIS config
$qris_mode = isset($qris_mode) ? filter_var($qris_mode, FILTER_VALIDATE_BOOLEAN) : false;
$qris_static_string = isset($qris_static_string) ? $qris_static_string : '';

// Batalkan transaksi aktif dari pengguna
if (isset($_GET['cancel_order'])) {
    $cancel_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['cancel_order']);
    $filepath = __DIR__ . "/voucher/trans-" . $cancel_id . ".php";
    if (!file_exists($filepath)) {
        $filepath = __DIR__ . "/voucher/trans-" . $cancel_id . ".json";
    }
    if (file_exists($filepath)) {
        $tData = readTransactionFile($filepath);
        if ($tData && isset($tData['status']) && $tData['status'] === 'pending') {
            $ip_ok = !isset($tData['client_ip']) || $tData['client_ip'] === $_SERVER['REMOTE_ADDR'];
            if ($ip_ok) {
                @unlink($filepath);
                writeAppLog("TRANSACTION_CANCELLED", "Order ID: " . $cancel_id . " dibatalkan oleh pengguna.");
            }
        }
    }
    unset($_SESSION['active_order_id']);
    unset($_SESSION['active_snap_token']);
    
    header("Location: index.php?session=" . urlencode($selected_session));
    exit;
}

// QRIS helper functions (empty, handled by lib/qris.php)

// Endpoint Polling Pengecekan Status Pembayaran (Client-side fetch)
if (isset($_GET['check_order'])) {
    header('Content-Type: application/json');
    $order_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['check_order']);
    $filepath = __DIR__ . "/voucher/trans-" . $order_id . ".php";
    if (!file_exists($filepath)) {
        $filepath = __DIR__ . "/voucher/trans-" . $order_id . ".json";
    }
    
    if (file_exists($filepath)) {
        $trans = readTransactionFile($filepath);
        if (isset($trans['status']) && $trans['status'] === 'settlement' && !empty($trans['username'])) {
            echo json_encode([
                'status' => 'success',
                'username' => $trans['username'],
                'password' => $trans['password'],
                'profile' => isset($trans['profile']) ? $trans['profile'] : '',
                'price' => isset($trans['price']) ? $trans['price'] : 0,
                'validity' => isset($trans['validity']) ? $trans['validity'] : ''
            ]);
            exit;
        } elseif (isset($trans['status']) && ($trans['status'] === 'paid_pending_generate' || ($trans['status'] === 'settlement' && empty($trans['username'])))) {
            echo json_encode([
                'status' => 'paid_pending_generate',
                'order_id' => $order_id
            ]);
            exit;
        }
    }
    echo json_encode(['status' => 'pending']);
    exit;
}

// Tentukan session router default (ambil dari sesi pertama yang tersedia di config)
$available_sessions = [];
foreach ($data as $key => $val) {
    if ($key !== 'mikhmon') {
        $available_sessions[] = $key;
    }
}

$selected_session = isset($_GET['session']) ? $_GET['session'] : (isset($available_sessions[0]) ? $available_sessions[0] : '');

// Validasi ketat keamanan (Parameter Tampering Protection)
if ($selected_session === 'mikhmon' || !in_array($selected_session, $available_sessions)) {
    $selected_session = isset($available_sessions[0]) ? $available_sessions[0] : '';
}

// Ambil DNS name untuk auto-login hotspot
$dnsname = "";
if (isset($data[$selected_session])) {
    $dnsname = explode('^', $data[$selected_session][5])[1];
}

// Ambil Profil Paket
$profiles = [];
$error_msg = "";
$success_voucher = null;
$pending_voucher = null;
$router_online = false;

// Tampilkan voucher sukses jika redirect kembali dari pembayaran
$show_voucher_id = isset($_GET['order_id']) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['order_id']) : '';
if (isset($_GET['show_voucher']) && !empty($show_voucher_id)) {
    $filepath = __DIR__ . "/voucher/trans-" . $show_voucher_id . ".php";
    if (!file_exists($filepath)) {
        $filepath = __DIR__ . "/voucher/trans-" . $show_voucher_id . ".json";
    }
    if (file_exists($filepath)) {
        $trans = readTransactionFile($filepath);
        if (isset($trans['status']) && $trans['status'] === 'settlement' && !empty($trans['username'])) {
            $success_voucher = [
                'username' => $trans['username'],
                'password' => $trans['password'],
                'profile' => $trans['profile'],
                'price' => $trans['price'],
                'validity' => $trans['validity']
            ];
            $currency = isset($data[$selected_session]) ? explode('&', $data[$selected_session][6])[1] : 'Rp';
        } elseif (isset($trans['status']) && ($trans['status'] === 'paid_pending_generate' || ($trans['status'] === 'settlement' && empty($trans['username'])))) {
            $pending_voucher = [
                'order_id' => $show_voucher_id,
                'profile' => isset($trans['profile']) ? $trans['profile'] : '',
                'price' => isset($trans['price']) ? $trans['price'] : 0,
                'validity' => isset($trans['validity']) ? $trans['validity'] : ''
            ];
            $currency = isset($data[$selected_session]) ? explode('&', $data[$selected_session][6])[1] : 'Rp';
        }
    }
}

// Konek ke MikroTik untuk list profil & cek status online (dengan cache 5 menit)
if (!empty($selected_session)) {
    include_once(__DIR__ . '/lib/routeros_api.class.php');
    include_once(__DIR__ . '/lib/formatbytesbites.php');

    if (isset($data[$selected_session])) {
        $iphost = explode('!', $data[$selected_session][1])[1];
        $userhost = explode('@|@', $data[$selected_session][2])[1];
        $passwdhost = explode('#|#', $data[$selected_session][3])[1];
        $currency = explode('&', $data[$selected_session][6])[1];

        // File-based profile cache (TTL: 5 menit)
        $cache_dir = __DIR__ . '/data';
        $cache_file = $cache_dir . '/profile_cache_' . preg_replace('/[^a-zA-Z0-9]/', '', $selected_session) . '.json';
        $cache_ttl = 300; // 5 menit
        $use_cache = false;

        if (file_exists($cache_file)) {
            $cache_age = time() - filemtime($cache_file);
            if ($cache_age < $cache_ttl) {
                $cached = json_decode(file_get_contents($cache_file), true);
                if (is_array($cached) && isset($cached['profiles'])) {
                    $profiles = $cached['profiles'];
                    $router_online = true;
                    $use_cache = true;
                }
            }
        }

        if (!$use_cache) {
            // Cache expired atau belum ada, query router
            $API = new RouterosAPI();
            $API->debug = false;

            if (@$API->connect($iphost, $userhost, decrypt($passwdhost))) {
                $router_online = true;
                $raw_profiles = $API->comm("/ip/hotspot/user/profile/print", array(
                    ".proplist" => "name,shared-users,rate-limit,on-login"
                ));
                if (is_array($raw_profiles)) {
                    foreach ($raw_profiles as $prof) {
                        if ($prof['name'] === 'default') continue;

                        $onLogin = isset($prof['on-login']) ? $prof['on-login'] : '';
                        $exploded = explode(',', $onLogin);
                        
                        $price = isset($exploded[2]) ? (float)$exploded[2] : 0;
                        if ($price <= 0) continue;
                        
                        $validity = isset($exploded[3]) ? $exploded[3] : '';
                        
                        $profiles[] = [
                            'name' => $prof['name'],
                            'shared_users' => isset($prof['shared-users']) ? $prof['shared-users'] : '1',
                            'rate_limit' => isset($prof['rate-limit']) ? $prof['rate-limit'] : 'Unlimited',
                            'price' => $price,
                            'validity' => $validity,
                            'currency' => $currency
                        ];
                    }

                    usort($profiles, function($a, $b) {
                        if ($a['price'] == $b['price']) return 0;
                        return ($a['price'] < $b['price']) ? -1 : 1;
                    });

                    // Simpan cache
                    @file_put_contents($cache_file, json_encode(['profiles' => $profiles, 'cached_at' => time()]));
                }
                $API->disconnect();
            } else {
                $router_online = false;
                $error_msg = "Sistem gagal terhubung ke router MikroTik.";
                writeAppLog("MIKROTIK_ERROR", "Gagal koneksi API MikroTik untuk sesi: " . $selected_session);
            }
        }
    } else {
        $router_online = false;
        $error_msg = "Sesi router belum dikonfigurasi.";
    }
}

// Fallback Paket Mockup (selalu didefinisikan agar ada deskripsi produk jika router offline)
$fallback_profiles = [
    [
        'name' => 'Paket Hemat 2 Jam',
        'rate_limit' => 'Up to 3 Mbps',
        'price' => 2000,
        'validity' => '2 Jam',
        'desc' => 'Cocok untuk browsing ringan, chatting, dan cek media sosial singkat.',
        'icon' => 'fa-bolt'
    ],
    [
        'name' => 'Paket Harian 24 Jam',
        'rate_limit' => 'Up to 5 Mbps',
        'price' => 5000,
        'validity' => '24 Jam',
        'desc' => 'Paket terpopuler untuk harian! Streaming video lancar dan bebas browsing seharian.',
        'icon' => 'fa-wifi'
    ],
    [
        'name' => 'Paket Mingguan 7 Hari',
        'rate_limit' => 'Up to 8 Mbps',
        'price' => 25000,
        'validity' => '7 Hari',
        'desc' => 'Lebih hemat untuk kebutuhan kerja atau sekolah dari rumah selama seminggu penuh.',
        'icon' => 'fa-calendar-week'
    ],
    [
        'name' => 'Paket Bulanan 30 Hari',
        'rate_limit' => 'Up to 15 Mbps',
        'price' => 90000,
        'validity' => '30 Hari',
        'desc' => 'Masa aktif terlama! Kecepatan prioritas, bebas kuota FUP, aktif sebulan penuh.',
        'icon' => 'fa-rocket'
    ]
];

// Proses Pembuatan Transaksi QRIS Snap
$snap_token = "";
$snap_order_id = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_profile'])) {
    // CSRF verification
    csrf_verify();
    // Cooldown rate limit check (10 detik)
    if (isset($_SESSION['last_checkout_time']) && (time() - $_SESSION['last_checkout_time'] < 10)) {
        $error_msg = "Harap tunggu 10 detik sebelum melakukan pesanan kembali.";
    } else {
        $_SESSION['last_checkout_time'] = time();

        // Pastikan tidak ada transaksi pending aktif milik IP klien ini
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $found_existing = null;
        if (!empty($client_ip)) {
            $dir = __DIR__ . '/voucher/';
            if (is_dir($dir)) {
                $files = array_merge(
                    glob($dir . 'trans-*.json'),
                    glob($dir . 'trans-*.php')
                );
                $now = time();
                foreach ($files as $file) {
                    $tData = readTransactionFile($file);
                    if ($tData && isset($tData['status']) && $tData['status'] === 'pending'
                        && isset($tData['client_ip']) && $tData['client_ip'] === $client_ip
                        && ($now - $tData['created_at']) < 900) {
                        $found_existing = $tData;
                        break;
                    }
                }
            }
        }

        if ($found_existing) {
            // Restore transaksi pending yang sudah ada secara otomatis
            $snap_order_id = $found_existing['order_id'];
            $snap_token = isset($found_existing['qris_string']) ? $found_existing['qris_string'] : '';
            $transData = $found_existing;
            $_SESSION['active_order_id'] = $snap_order_id;
            $_SESSION['active_snap_token'] = $snap_token;
        } else {
            // Pastikan router harus online untuk membeli
            if (!$router_online) {
                $error_msg = "Gagal memproses transaksi: MikroTik sedang offline.";
            } else {
            $profile_to_buy = $_POST['buy_profile'];
            
            if (!empty($selected_session)) {
                include_once(__DIR__ . '/lib/routeros_api.class.php');
                include_once(__DIR__ . '/lib/formatbytesbites.php');

                $iphost = explode('!', $data[$selected_session][1])[1];
                $userhost = explode('@|@', $data[$selected_session][2])[1];
                $passwdhost = explode('#|#', $data[$selected_session][3])[1];
                $currency = explode('&', $data[$selected_session][6])[1];

                $API = new RouterosAPI();
                $API->debug = false;

                if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
                    $getProfile = $API->comm("/ip/hotspot/user/profile/print", [
                        "?name" => $profile_to_buy
                    ]);

                    if (!empty($getProfile)) {
                        $onLogin = isset($getProfile[0]['on-login']) ? $getProfile[0]['on-login'] : '';
                        $exploded = explode(',', $onLogin);
                        $price = isset($exploded[2]) ? (float)$exploded[2] : 0;
                        $validity = isset($exploded[3]) ? $exploded[3] : '';

                        // Generate Order ID unik
                        $snap_order_id = "MK-" . preg_replace('/[^a-zA-Z0-9]/', '', $selected_session) . "-" . preg_replace('/[^a-zA-Z0-9]/', '', $profile_to_buy) . "-" . time();
                        
                        if ($qris_mode && !empty($qris_static_string)) {
                            // MODE QRIS MANDIRI
                            include_once(__DIR__ . '/lib/qris.php');
                            
                            // Cari kode unik (1 - 99) yang tidak sedang aktif (pending) di folder voucher
                            $active_codes = [];
                            $dir = __DIR__ . '/voucher/';
                            if (is_dir($dir)) {
                                $files = array_merge(
                                    glob($dir . 'trans-*.json'),
                                    glob($dir . 'trans-*.php')
                                );
                                foreach ($files as $file) {
                                    $tData = readTransactionFile($file);
                                    if ($tData && isset($tData['status']) && $tData['status'] === 'pending' && isset($tData['price']) && isset($tData['base_price'])) {
                                        $diff = (int)$tData['price'] - (int)$tData['base_price'];
                                        if ($diff >= 1 && $diff <= 99) {
                                            $active_codes[] = $diff;
                                        }
                                    }
                                }
                            }
                            // Dapatkan angka 1-99 yang tidak ada di $active_codes
                            $available_codes = array_diff(range(1, 99), $active_codes);
                            if (empty($available_codes)) {
                                // Fallback jika semua slot 99 kode unik penuh (sangat jarang terjadi)
                                $unique_code = rand(1, 99);
                            } else {
                                // Ambil satu secara acak dari kode yang masih kosong
                                $unique_code = $available_codes[array_rand($available_codes)];
                            }
                            
                            $total_price = $price + $unique_code;
                            
                            $qrisGen = new QrisGenerator($qris_static_string);
                            $dynamic_qris_string = $qrisGen->generateDynamic($total_price);
                            
                            $snap_token = $dynamic_qris_string; // Kita pinjam variabel snap_token untuk menyimpan string qris
                            
                            // Simpan data pending transaksi
                            $transData = [
                                'status' => 'pending',
                                'order_id' => $snap_order_id,
                                'session' => $selected_session,
                                'profile' => $profile_to_buy,
                                'price' => $total_price, // Harga sudah termasuk kode unik
                                'base_price' => $price,
                                'validity' => $validity,
                                'created_at' => time(),
                                'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                                'qris_string' => $dynamic_qris_string
                            ];
                            
                            if (!file_exists(__DIR__ . '/voucher')) {
                                mkdir(__DIR__ . '/voucher', 0755, true);
                            }
                            writeTransactionFile(__DIR__ . "/voucher/trans-" . $snap_order_id . ".php", $transData);
                        } else {
                            $error_msg = "Sistem QRIS Mandiri belum dikonfigurasi.";
                            writeAppLog("QRIS_ERROR", "Gagal membuat qris untuk Order ID: " . $snap_order_id . ". QRIS Mode mati atau string kosong.");
                        }
                    } else {
                        $error_msg = "Profil paket tidak ditemukan.";
                        writeAppLog("CHECKOUT_ERROR", "Profil paket '" . $profile_to_buy . "' tidak ditemukan di router.");
                    }
                    $API->disconnect();
                } else {
                    $error_msg = "Gagal terhubung ke router MikroTik.";
                    writeAppLog("MIKROTIK_ERROR", "Gagal koneksi API MikroTik saat checkout profil: " . $profile_to_buy);
                }
            }
        }
        }
    }
}
// Load portal customization settings
$portal_title = isset($dbSettings) ? $dbSettings->get('portal_title', 'GalaxyNet') : 'GalaxyNet';
$portal_logo_url = isset($dbSettings) ? $dbSettings->get('portal_logo_url', '') : '';
$portal_accent_color = isset($dbSettings) ? $dbSettings->get('portal_accent_color', '#008BC9') : '#008BC9';
$portal_support_wa = isset($dbSettings) ? $dbSettings->get('portal_support_wa', '') : '';
$portal_support_telegram = isset($dbSettings) ? $dbSettings->get('portal_support_telegram', '') : '';

// Safety defaults for env config
$qris_mode = isset($qris_mode) ? filter_var($qris_mode, FILTER_VALIDATE_BOOLEAN) : false;?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($portal_title) ?> - Layanan Internet Wifi Voucher</title>
    <!-- SEO Meta Tags -->
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="description" content="Layanan internet hotspot instan berkecepatan tinggi dengan sistem voucher otomatis via QRIS dan E-Wallet. Cepat, stabil, dan tanpa kontrak bulanan.">
    <meta name="keywords" content="hotspot, voucher hotspot, internet murah, qris hotspot, mikhmon qris">
    
    <!-- Modern Typography & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- QR Code Script is loaded dynamically when needed -->

    <link rel="stylesheet" href="css/frontpage.css">
    <style>
        :root {
            --primary: <?= $portal_accent_color ?>;
            --primary-glow: <?= $portal_accent_color ?>26;
        }
    </style>
</head>
<body>

    <!-- Translucent Navigation Bar -->
    <header class="nav-header">
        <div class="wrapper">
            <div style="display: flex; align-items: center; gap: 8px;">
                <a href="#" class="logo">
                    <?php if (!empty($portal_logo_url)): ?>
                        <img src="<?= htmlspecialchars($portal_logo_url) ?>" alt="Logo" style="height: 24px; vertical-align: middle; margin-right: 8px; border-radius: 4px;">
                    <?php else: ?>
                        <i class="fa fa-wifi"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($portal_title) ?>
                </a>
                <span class="status-indicator-dot <?= $router_online ? 'online' : 'offline' ?>" title="<?= htmlspecialchars($portal_title) ?> Server: <?= $router_online ? 'Online' : 'Offline' ?>"></span>
            </div>
            <nav class="menu-links">
                <a href="#home">Home</a>
                <a href="#paket">Paket Internet</a>
                <a href="#panduan">Cara Beli</a>
                <a href="#syarat">Syarat & Kebijakan</a>
                <a href="#kontak">Kontak</a>
            </nav>
            <a href="admin.php?id=login" class="btn-admin">
                <i class="fa fa-user-shield"></i>
            </a>
        </div>
    </header>

    <div id="home"></div>

    <!-- Hero Section -->
    <section class="hero wrapper">
        <span class="status-badge <?= $router_online ? 'status-online' : 'status-offline' ?>">
            <i class="fa <?= $router_online ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            Status Router: <?= $router_online ? 'Online' : 'Offline / Maintenance' ?>
        </span>
        <h1>Internet Hotspot Cepat & Stabil<br>Tanpa Kontrak Bulanan</h1>
        <p>Mulai dari Rp 2.000 saja. Pilih paket yang sesuai kebutuhan Anda, bayar instan menggunakan QRIS atau E-Wallet, dan langsung terhubung ke internet!</p>
        <div class="cta-group">
            <a href="#paket" class="btn-primary-action">Beli Voucher</a>
            <a href="#panduan" class="btn-secondary-action">Cara Penggunaan</a>
        </div>

        <?php if (count($available_sessions) > 1): ?>
            <div>
                <div class="session-switcher">
                    <span><i class="fa fa-map-marker-alt"></i> Lokasi Hotspot:</span>
                    <form method="GET" id="sessionForm">
                        <select name="session" onchange="document.getElementById('sessionForm').submit()">
                            <?php foreach ($available_sessions as $sess): ?>
                                <option value="<?= htmlspecialchars($sess) ?>" <?= ($selected_session === $sess) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sess) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Products Catalog Section -->
    <section class="products wrapper" id="paket">
        <?php
        $checkout_step = 1;
        if ($success_voucher) {
            $checkout_step = 3;
        } elseif (!empty($snap_token)) {
            $checkout_step = 2;
        }
        ?>
        <div class="section-header">
            <h2>Pilihan Paket Voucher</h2>
            <p>Pilih paket internet terbaik untuk menemani aktivitas harian Anda</p>
        </div>

        <!-- Checkout Stepper Tracker -->
        <div class="checkout-stepper">
            <div class="step <?= ($checkout_step === 1) ? 'active' : (($checkout_step > 1) ? 'completed' : '') ?>">
                <div class="step-num"><?= ($checkout_step > 1) ? '<i class="fa fa-check"></i>' : '1' ?></div>
                <span class="step-label">Pilih Paket</span>
            </div>
            <div class="step-line"></div>
            <div class="step <?= ($checkout_step === 2) ? 'active' : (($checkout_step > 2) ? 'completed' : '') ?>">
                <div class="step-num"><?= ($checkout_step > 2) ? '<i class="fa fa-check"></i>' : '2' ?></div>
                <span class="step-label">Pembayaran</span>
            </div>
            <div class="step-line"></div>
            <div class="step <?= ($checkout_step === 3) ? 'active' : '' ?>">
                <div class="step-num">3</div>
                <span class="step-label">Hubungkan</span>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-bar">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Pemberitahuan Sistem:</strong> <?= htmlspecialchars($error_msg) ?><br>
                    <small>Pembelian sementara dinonaktifkan hingga router terhubung kembali. Untuk detail harga & produk tetap dapat dilihat di bawah ini.</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Container Riwayat Pembelian (Dinamis via JS) -->
        <div id="voucherHistoryContainer" class="history-card" style="display: none; margin-bottom: 24px;">
            <div class="history-card-header">
                <h3><i class="fa fa-history"></i> Voucher Terakhir Anda</h3>
                <button type="button" onclick="clearVoucherHistory()" class="btn-clear-history">
                    <i class="fa fa-trash-can"></i> Hapus Riwayat
                </button>
            </div>
            <div class="history-card-body" id="voucherHistoryList">
                <!-- Diisi secara dinamis via JavaScript -->
            </div>
        </div>

        <?php if ($success_voucher): ?>
            <?php
            $login_url = "";
            if (!empty($dnsname)) {
                $login_url = "http://" . $dnsname . "/login?username=" . urlencode($success_voucher['username']) . "&password=" . urlencode($success_voucher['password']);
            }
            ?>
            <!-- Menampilkan Voucher Hasil Pembelian Sukses -->
            <div class="receipt-card">
                <div class="success-icon">
                    <i class="fa fa-check"></i>
                </div>
                <div class="receipt-title">Pembayaran Berhasil!</div>
                <div class="receipt-subtitle">Voucher internet Anda telah berhasil diterbitkan</div>

                <div class="voucher-box" style="display: flex; align-items: center; justify-content: space-around; gap: 20px; flex-wrap: wrap; text-align: left;">
                    <div style="flex: 1; min-width: 180px;">
                        <div class="voucher-code-label">Kode Voucher</div>
                        <div class="voucher-code" id="voucherCode" style="margin-bottom: 4px;"><?= htmlspecialchars($success_voucher['username']) ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); line-height: 1.4;">
                            Gunakan kode ini pada halaman login Hotspot Anda, atau gunakan QR Code di samping untuk terhubung secara otomatis.
                        </div>
                    </div>
                    <div class="voucher-qrcode-container" style="display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0; margin: 0 auto;">
                        <canvas id="voucherQrCanvas" style="background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 6px; width: 120px; height: 120px; box-shadow: 0 4px 10px rgba(0,0,0,0.03);"></canvas>
                        <span style="font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Pindai QR untuk Masuk Otomatis</span>
                    </div>
                </div>

                <div class="receipt-info-grid">
                    <div class="receipt-info-item">
                        <span>Nama Paket</span>
                        <strong><?= htmlspecialchars($success_voucher['profile']) ?></strong>
                    </div>
                    <div class="receipt-info-item">
                        <span>Masa Aktif</span>
                        <strong><?= htmlspecialchars($success_voucher['validity'] ?: '-') ?></strong>
                    </div>
                    <div class="receipt-info-item">
                        <span>Total Bayar</span>
                        <strong>
                            <?php
                            if ($currency === 'RP' || $currency === 'Rp') {
                                echo "Rp " . number_format($success_voucher['price'], 0, ',', '.');
                            } else {
                                echo htmlspecialchars($currency) . " " . number_format($success_voucher['price']);
                            }
                            ?>
                        </strong>
                    </div>
                    <div class="receipt-info-item">
                        <span>Status</span>
                        <strong style="color: var(--accent);">Lunas / Aktif</strong>
                    </div>
                </div>

                <div class="receipt-actions" style="flex-direction: column; width: 100%; gap: 12px;">
                    <?php if (!empty($login_url)): ?>
                        <a href="<?= htmlspecialchars($login_url) ?>" onclick="autoCopyBeforeConnect(event, '<?= htmlspecialchars($success_voucher['username']) ?>')" class="btn-connect btn-connect-pulse">Hubungkan Sekarang</a>
                    <?php endif; ?>
                    <div style="display: flex; gap: 12px; width: 100%;">
                        <button class="btn-copy" onclick="copyVoucherCode()">Salin Kode</button>
                        <a href="index.php?session=<?= urlencode($selected_session) ?>" class="btn-done">Selesai</a>
                    </div>
                </div>
            </div>

            <!-- Toast Copy Notification -->
            <div id="copyToast" class="toast-copy">
                <i class="fa fa-circle-check" style="color: #10b981; font-size: 16px;"></i>
                <span>Kode voucher berhasil disalin!</span>
            </div>

            <script src="js/qrious.min.js"></script>
            <script>
                function autoCopyBeforeConnect(event, codeText) {
                    event.preventDefault();
                    var targetUrl = event.currentTarget.getAttribute("href");
                    
                    if (typeof copyTextToClipboard === "function") {
                        copyTextToClipboard(codeText, function() {
                            window.location.href = targetUrl;
                        }, function() {
                            window.location.href = targetUrl;
                        });
                    } else {
                        window.location.href = targetUrl;
                    }
                }

                // Bersihkan data transaksi aktif dari localStorage setelah voucher sukses dimuat
                localStorage.removeItem('active_order_id');
                localStorage.removeItem('active_snap_token');

                // Simpan transaksi sukses ke riwayat pembelian lokal
                (function() {
                    try {
                        var history = JSON.parse(localStorage.getItem('mikhtrans_purchase_history') || '[]');
                        var orderId = <?= json_encode($show_voucher_id) ?>;
                        var username = <?= json_encode($success_voucher['username']) ?>;
                        var password = <?= json_encode($success_voucher['password']) ?>;
                        var profile = <?= json_encode($success_voucher['profile']) ?>;
                        var price = <?= json_encode($success_voucher['price']) ?>;
                        var validity = <?= json_encode($success_voucher['validity']) ?>;
                        var loginUrl = <?= json_encode($login_url) ?>;
                        var dateStr = new Date().toLocaleString('id-ID');
                        
                        var exists = history.some(function(item) { return item.order_id === orderId; });
                        if (!exists && orderId && username) {
                            history.unshift({
                                order_id: orderId,
                                username: username,
                                password: password,
                                profile: profile,
                                price: price,
                                validity: validity,
                                login_url: loginUrl,
                                date: dateStr
                            });
                            if (history.length > 5) {
                                history.pop();
                            }
                            localStorage.setItem('mikhtrans_purchase_history', JSON.stringify(history));
                        }
                    } catch (e) {
                        console.error('Error saving history:', e);
                    }
                })();

                // Inisialisasi QR Code
                var qr = new QRious({
                    element: document.getElementById('voucherQrCanvas'),
                    value: <?= json_encode($login_url ?: $success_voucher['username']) ?>,
                    size: 150
                });

                function copyVoucherCode() {
                    var codeText = document.getElementById("voucherCode").innerText.trim();
                    copyTextToClipboard(codeText, function() {
                        var toast = document.getElementById("copyToast");
                        if (toast) {
                            toast.classList.add("show");
                            setTimeout(function() {
                                toast.classList.remove("show");
                            }, 3000);
                        }
                    }, function() {
                        alert("Kode voucher: " + codeText + "\n(Silakan salin secara manual)");
                    });
                }
            </script>
        <?php elseif ($pending_voucher): ?>
            <?php
            $wa_url = "";
            if (!empty($portal_support_wa)) {
                $clean_wa = preg_replace('/[^0-9]/', '', $portal_support_wa);
                if (strpos($clean_wa, '08') === 0) {
                    $clean_wa = '62' . substr($clean_wa, 1);
                }
                $wa_msg = "Halo Admin, saya baru saja membeli voucher di portal hotspot Anda.\n\n"
                        . "Detail Transaksi:\n"
                        . "- Order ID: " . $pending_voucher['order_id'] . "\n"
                        . "- Paket: " . $pending_voucher['profile'] . "\n"
                        . "- Nominal: Rp " . number_format($pending_voucher['price'], 0, ',', '.') . "\n\n"
                        . "Status transaksi lunas tetapi pembuatan voucher di router tertunda (router offline). Mohon bantuannya untuk diproses manual. Terima kasih.";
                $wa_url = "https://api.whatsapp.com/send?phone=" . urlencode($clean_wa) . "&text=" . urlencode($wa_msg);
            }
            ?>
            <!-- Menampilkan Info Transaksi Pending Karena Router Offline -->
            <div class="receipt-card" style="border-top: 5px solid #F59E0B;">
                <div class="success-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                    <i class="fa fa-exclamation-triangle"></i>
                </div>
                <div class="receipt-title">Pembayaran Diterima!</div>
                <div class="receipt-subtitle" style="margin-bottom: 24px;">Pembayaran Anda sukses, tetapi koneksi ke router hotspot sedang terganggu.</div>

                <div class="voucher-box" style="background: #FFFBEB; border: 1px dashed #FCD34D;">
                    <div style="font-size: 14px; color: #92400E; font-weight: bold; margin-bottom: 8px;">
                        Status: Pembuatan Voucher Tertunda
                    </div>
                    <div style="font-size: 12px; color: #B45309; line-height: 1.5; text-align: left;">
                        Sistem sedang mengantrekan pembuatan voucher Anda. Silakan hubungi pemilik hotspot dengan menunjukkan Order ID di bawah ini jika voucher tidak terbit secara otomatis dalam beberapa menit.
                    </div>
                </div>

                <div class="receipt-info-grid">
                    <div class="receipt-info-item">
                        <span>Order ID</span>
                        <strong id="pendingOrderId" style="font-family: monospace; display: inline-flex; align-items: center; gap: 6px;">
                            <?= htmlspecialchars($pending_voucher['order_id']) ?>
                            <i class="fa-regular fa-copy" style="cursor: pointer; color: var(--primary); font-size: 14px;" onclick="copyPendingOrderId()" title="Salin Order ID"></i>
                        </strong>
                    </div>
                    <div class="receipt-info-item">
                        <span>Nama Paket</span>
                        <strong><?= htmlspecialchars($pending_voucher['profile']) ?></strong>
                    </div>
                    <div class="receipt-info-item">
                        <span>Total Bayar</span>
                        <strong>
                            <?php
                            if ($currency === 'RP' || $currency === 'Rp') {
                                echo "Rp " . number_format($pending_voucher['price'], 0, ',', '.');
                            } else {
                                echo htmlspecialchars($currency) . " " . number_format($pending_voucher['price']);
                            }
                            ?>
                        </strong>
                    </div>
                </div>

                <div class="receipt-actions" style="flex-direction: column; width: 100%; gap: 12px;">
                    <?php if (!empty($wa_url)): ?>
                        <a href="<?= htmlspecialchars($wa_url) ?>" target="_blank" class="btn-wa-support" style="text-decoration: none; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fa-brands fa-whatsapp" style="font-size: 16px;"></i> Hubungi Admin via WhatsApp
                        </a>
                    <?php endif; ?>
                    <div style="display: flex; gap: 12px; width: 100%;">
                        <button class="btn-copy" onclick="copyPendingOrderId()" style="flex: 1;">Salin Order ID</button>
                        <a href="index.php?session=<?= urlencode($selected_session) ?>" class="btn-done" style="flex: 1;">Selesai</a>
                    </div>
                </div>
            </div>

            <!-- Toast Copy Notification -->
            <div id="copyToastPending" class="toast-copy">
                <i class="fa fa-circle-check" style="color: #10b981; font-size: 16px;"></i>
                <span>Order ID berhasil disalin!</span>
            </div>

            <script>
                // Bersihkan data transaksi aktif dari localStorage
                localStorage.removeItem('active_order_id');
                localStorage.removeItem('active_snap_token');

                function copyPendingOrderId() {
                    var orderId = document.getElementById("pendingOrderId").innerText.replace(/\s+/g, '').trim();
                    copyTextToClipboard(orderId, function() {
                        var toast = document.getElementById("copyToastPending");
                        if (toast) {
                            toast.classList.add("show");
                            setTimeout(function() {
                                toast.classList.remove("show");
                            }, 3000);
                        }
                    }, function() {
                        alert("Order ID: " + orderId + "\n(Silakan salin secara manual)");
                    });
                }
            </script>
        <?php else: ?>
            <!-- Layout Switcher Buttons -->
            <div class="layout-toggle-container">
                <button type="button" id="btnGridView" class="layout-toggle">
                    <i class="fa fa-th"></i> Grid View
                </button>
                <button type="button" id="btnCarouselView" class="layout-toggle">
                    <i class="fa fa-columns"></i> Carousel View
                </button>
            </div>
            <!-- Grid / Carousel Produk -->
            <div class="products-wrapper products-grid">
                <?php if ($router_online && !empty($profiles)): ?>
                    <!-- List paket dinamis dari router yang sedang online -->
                    <?php foreach ($profiles as $prof): ?>
                        <div class="product-card">
                            <div class="card-top">
                                <div class="plan-icon">
                                    <i class="fa fa-wifi"></i>
                                </div>
                                <h3 class="plan-title"><?= htmlspecialchars($prof['name']) ?></h3>
                                <p class="plan-description">Voucher internet kecepatan tinggi berbayar untuk 1 perangkat.</p>
                                
                                <div class="plan-meta">
                                    <span><i class="fa-regular fa-clock"></i> Aktif: <?= htmlspecialchars($prof['validity'] ?: '-') ?></span>
                                    <?php if ($prof['rate_limit'] !== 'Unlimited'): ?>
                                        <span><i class="fa-solid fa-gauge-high"></i> Speed: <?= htmlspecialchars($prof['rate_limit']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <div class="plan-price-wrapper">
                                    <div class="plan-price-label">Harga</div>
                                    <div class="plan-price">
                                        <?php
                                        if ($prof['currency'] === 'RP' || $prof['currency'] === 'Rp') {
                                            echo "Rp " . number_format($prof['price'], 0, ',', '.');
                                        } else {
                                            echo htmlspecialchars($prof['currency']) . " " . number_format($prof['price']);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <form method="POST" action="#paket" onsubmit="return handleCheckoutSubmit(this)">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="buy_profile" value="<?= htmlspecialchars($prof['name']) ?>">
                                    <button type="submit" class="btn-buy-voucher">
                                        <i class="fa fa-shopping-cart"></i> Beli Sekarang
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Tampilan paket fallback offline (tetap memajang deskripsi produk) -->
                    <?php foreach ($fallback_profiles as $mock): ?>
                        <div class="product-card card-offline">
                            <div class="card-top">
                                <span class="status-badge status-offline" style="margin-bottom: 12px;">
                                    <i class="fa fa-ban"></i> Offline
                                </span>
                                <div class="plan-icon">
                                    <i class="fa <?= $mock['icon'] ?>"></i>
                                </div>
                                <h3 class="plan-title"><?= htmlspecialchars($mock['name']) ?></h3>
                                <p class="plan-description"><?= htmlspecialchars($mock['desc']) ?></p>
                                
                                <div class="plan-meta">
                                    <span><i class="fa-regular fa-clock"></i> Aktif: <?= htmlspecialchars($mock['validity']) ?></span>
                                    <span><i class="fa-solid fa-gauge-high"></i> Speed: <?= htmlspecialchars($mock['rate_limit']) ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="plan-price-wrapper">
                                    <div class="plan-price-label">Harga</div>
                                    <div class="plan-price">Rp <?= number_format($mock['price'], 0, ',', '.') ?></div>
                                </div>
                                <button class="btn-buy-voucher" disabled>
                                    <i class="fa fa-shopping-cart"></i> Offline
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- How it Works Section -->
    <section class="how-it-works" id="panduan">
        <div class="wrapper">
            <div class="section-header">
                <h2>Langkah Mudah Menggunakan Hotspot</h2>
                <p>Ikuti 3 tahapan praktis berikut untuk memulai akses internet cepat Anda</p>
            </div>
            
            <div class="steps-container">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Pilih Paket Internet</h3>
                    <p>Pilih paket voucher yang paling cocok dengan kebutuhan durasi dan kecepatan internet Anda di atas.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Lakukan Pembayaran</h3>
                    <p>Klik tombol beli, lakukan pembayaran instan secara aman melalui QRIS (Gopay, OVO, Dana, LinkAja) atau transfer e-wallet.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Masukkan Kode Voucher</h3>
                    <p>Setelah pembayaran selesai, kode voucher akan otomatis muncul di layar Anda. Masukkan kode tersebut di halaman login hotspot kami.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Terms & Policies Section -->
    <section class="compliance-docs wrapper" id="syarat">
        <div class="section-header">
            <h2>Syarat, Ketentuan & Kebijakan</h2>
            <p>Harap luangkan waktu untuk membaca informasi kebijakan layanan kami secara transparan</p>
        </div>

        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('syarat-ketentuan')">Syarat & Ketentuan</button>
            <button class="tab-btn" onclick="switchTab('kebijakan-pengembalian')">Kebijakan Pengembalian Dana</button>
        </div>

        <!-- Syarat & Ketentuan Content -->
        <div class="tab-content active" id="syarat-ketentuan">
            <h3>Syarat & Ketentuan Layanan</h3>
            <p>Selamat datang di layanan <?= htmlspecialchars($portal_title) ?> Hotspot. Dengan membeli dan menggunakan voucher kami, Anda menyetujui seluruh ketentuan berikut:</p>
            <ol>
                <li><strong>Penggunaan Voucher:</strong> Setiap voucher hotspot hanya berlaku untuk 1 (satu) perangkat dalam satu satuan waktu akses, kecuali dinyatakan lain pada jenis paket.</li>
                <li><strong>Masa Berlaku Voucher:</strong> Masa aktif voucher dimulai sejak pengguna pertama kali melakukan koneksi (login) ke jaringan hotspot kami.</li>
                <li><strong>Batas Tanggung Jawab:</strong> Kami berupaya memberikan konektivitas terbaik. Namun, kami tidak bertanggung jawab atas gangguan sinyal yang disebabkan oleh faktor geografis, interferensi perangkat pengguna, atau kendala teknis tak terduga di luar jangkauan infrastruktur kami.</li>
                <li><strong>Larangan Penggunaan:</strong> Pengguna dilarang menggunakan layanan internet hotspot kami untuk aktivitas ilegal, penyebaran konten berhak cipta secara tidak sah, peretasan, spamming, serta tindakan kriminal siber lainnya yang melanggar hukum Negara Republik Indonesia.</li>
                <li><strong>Perubahan Layanan:</strong> <?= htmlspecialchars($portal_title) ?> Hotspot berhak menyesuaikan tarif, alokasi bandwidth, dan kebijakan operasional hotspot sewaktu-waktu demi menjaga kualitas layanan yang optimal bagi semua pelanggan.</li>
            </ol>
        </div>

        <!-- Kebijakan Pengembalian Dana Content -->
        <div class="tab-content" id="kebijakan-pengembalian">
            <h3>Kebijakan Pengembalian Dana & Layanan</h3>
            <p>Kepuasan Anda adalah komitmen utama kami. Berikut adalah rincian kebijakan pengembalian dana (refund) atas layanan kami:</p>
            <ol>
                <li><strong>Kriteria Pengembalian Dana:</strong> Pelanggan berhak mengajukan pengembalian dana penuh jika sistem hotspot mengalami gangguan total (down time) berkelanjutan lebih dari 24 jam berturut-turut yang disebabkan langsung oleh kendala server atau infrastruktur kami.</li>
                <li><strong>Pembatalan Transaksi:</strong> Transaksi pembayaran yang telah berhasil diproses tidak dapat dibatalkan atau dikembalikan apabila kode voucher telah aktif atau digunakan oleh pembeli.</li>
                <li><strong>Prosedur Pengajuan:</strong> Untuk mengajukan pengembalian dana, silakan kirimkan bukti mutasi pembayaran yang sah dan tangkapan layar kendala ke kontak bisnis resmi kami.</li>
                <li><strong>Waktu Proses:</strong> Dana pengembalian yang disetujui akan ditransfer kembali ke rekening bank atau e-wallet asal pelanggan dalam kurun waktu maksimal 3 (tiga) hari kerja sejak pengajuan disetujui.</li>
            </ol>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="kontak">
        <div class="wrapper">
            <div class="section-header">
                <h2>Hubungi Kontak Bisnis Kami</h2>
                <p>Ada kendala koneksi atau pertanyaan seputar voucher? Tim support kami siap membantu Anda</p>
            </div>

            <div class="contact-grid">
                <div class="contact-info-card">
                    <div class="contact-item">
                        <i class="fa fa-envelope"></i>
                        <div class="contact-item-text">
                            <h4>Email Resmi Support</h4>
                            <p>support@<?= strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $portal_title)) ?>.my.id</p>
                        </div>
                    </div>
                    
                    <?php if (!empty($portal_support_wa)): ?>
                    <div class="contact-item">
                        <i class="fa-brands fa-whatsapp" style="color: #25D366; font-size: 20px;"></i>
                        <div class="contact-item-text">
                            <h4>WhatsApp Support</h4>
                            <p><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $portal_support_wa) ?>" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: none;"><?= htmlspecialchars($portal_support_wa) ?></a></p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="contact-item">
                        <i class="fa-brands fa-whatsapp" style="color: #25D366; font-size: 20px;"></i>
                        <div class="contact-item-text">
                            <h4>WhatsApp / Telepon</h4>
                            <p>+62 812 3456 7890</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($portal_support_telegram)): ?>
                    <div class="contact-item">
                        <i class="fa-brands fa-telegram" style="color: #0088cc; font-size: 20px;"></i>
                        <div class="contact-item-text">
                            <h4>Telegram Support</h4>
                            <p><a href="https://t.me/<?= htmlspecialchars($portal_support_telegram) ?>" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: none;">@<?= htmlspecialchars($portal_support_telegram) ?></a></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="contact-item">
                        <i class="fa fa-location-dot"></i>
                        <div class="contact-item-text">
                            <h4>Alamat Kantor</h4>
                            <p><?= htmlspecialchars($portal_title) ?> Hotspot Area, Jl. Raya Jombang No. 123, Jombang, 61471, Indonesia</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fa fa-clock"></i>
                        <div class="contact-item-text">
                            <h4>Jam Operasional Support</h4>
                            <p>Setiap Hari: 08.00 WIB - 22.00 WIB</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Message Form -->
                <form class="contact-form" onsubmit="event.preventDefault(); alert('Pesan Anda berhasil terkirim! Tim kami akan menghubungi Anda segera.'); this.reset();">
                    <div class="form-group">
                        <label for="c_name">Nama Lengkap</label>
                        <input type="text" id="c_name" required placeholder="Masukkan nama Anda">
                    </div>
                    <div class="form-group">
                        <label for="c_email">Alamat Email</label>
                        <input type="email" id="c_email" required placeholder="name@example.com">
                    </div>
                    <div class="form-group">
                        <label for="c_msg">Pesan / Keluhan</label>
                        <textarea id="c_msg" rows="4" required placeholder="Tuliskan keluhan atau pertanyaan Anda di sini..."></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Kirim Pesan</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Translucent Footer -->
    <footer>
        <div class="wrapper">
            <div class="footer-links">
                <a href="#home">Beranda</a>
                <a href="#paket">Beli Voucher</a>
                <a href="#syarat">Syarat & Ketentuan</a>
                <a href="#kontak">Hubungi Kami</a>
                <a href="admin.php?id=login">Admin Portal</a>
            </div>
            <p>&copy; <?= date("Y") ?> <?= htmlspecialchars($portal_title) ?> Hotspot. All Rights Reserved. Seluruh transaksi diproses dalam Rupiah (IDR).</p>
            <p style="font-size: 11px; color: var(--text-muted); padding-bottom: 60px;">Diintegrasikan dengan sistem billing otomatis MikhPay.</p>
        </div>
    </footer>

    <!-- Fixed Bottom Navigation Bar for Mobile -->
    <nav class="bottom-nav">
        <a href="#home" class="nav-item active">
            <i class="fa fa-home"></i>
            <span>Beranda</span>
        </a>
        <a href="#paket" class="nav-item">
            <i class="fa fa-ticket-alt"></i>
            <span>Beli Voucher</span>
        </a>
        <a href="#syarat" class="nav-item">
            <i class="fa fa-file-shield"></i>
            <span>Kebijakan</span>
        </a>
        <a href="#kontak" class="nav-item">
            <i class="fa fa-comments"></i>
            <span>Kontak</span>
        </a>
    </nav>

    <!-- QRIS Transaction Overlay -->
    <?php if (!empty($snap_token)): ?>
        <style>
            .loading-overlay {
                background: rgba(15, 23, 42, 0.7) !important;
                backdrop-filter: blur(12px) !important;
                -webkit-backdrop-filter: blur(12px) !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
                padding: 16px;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
                overflow-y: auto;
                box-sizing: border-box;
            }
            .qris-modal-card {
                background: #ffffff;
                border-radius: 24px;
                padding: 24px;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                text-align: center;
                animation: qrisModalFade 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                color: #1e293b;
                box-sizing: border-box;
                margin: auto;
            }
            @keyframes qrisModalFade {
                from { opacity: 0; transform: scale(0.95) translateY(10px); }
                to { opacity: 1; transform: scale(1) translateY(0); }
            }
            .qris-header-title {
                font-size: 20px;
                font-weight: 800;
                color: #0f172a;
                margin: 0 0 6px 0;
                letter-spacing: -0.5px;
            }
            .qris-header-desc {
                font-size: 13px;
                color: #64748b;
                margin: 0 0 16px 0;
            }
            .timer-badge {
                background: #fef2f2;
                border: 1px solid #fca5a5;
                color: #ef4444;
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-bottom: 20px;
                animation: timerPulse 2s infinite;
            }
            @keyframes timerPulse {
                0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
                70% { transform: scale(1.02); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
                100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
            }
            .qris-qr-container {
                background: #ffffff;
                padding: 16px;
                border-radius: 20px;
                border: 1px solid #e2e8f0;
                display: inline-block;
                margin-bottom: 20px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            }
            .btn-download-qris-new {
                background: #10b981;
                color: white;
                border: none;
                padding: 10px 18px;
                border-radius: 12px;
                font-weight: 700;
                cursor: pointer;
                font-size: 13px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
                margin-top: 10px;
                text-decoration: none;
            }
            .btn-download-qris-new:hover {
                background: #059669;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
            }
            .qris-total-amount {
                font-size: 26px;
                color: #10b981;
                font-weight: 800;
                margin: 0 0 4px 0;
                letter-spacing: -0.5px;
            }
            .qris-warning-text {
                color: #ef4444;
                font-size: 13px;
                margin: 0 0 16px 0;
                font-weight: 700;
            }
            .qris-info-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 12px;
                font-size: 11px;
                line-height: 1.5;
                color: #475569;
                text-align: left;
                margin-bottom: 20px;
            }
            .qris-info-box i {
                color: #3b82f6;
                font-size: 14px;
                float: left;
                margin-right: 8px;
                margin-top: 2px;
            }
            .btn-cancel-qris-new {
                background: transparent;
                border: 1px solid #cbd5e1;
                color: #64748b;
                padding: 10px 24px;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.2s;
                width: 100%;
                display: block;
                box-sizing: border-box;
                text-decoration: none;
            }
            .btn-cancel-qris-new:hover {
                background: #f1f5f9;
                color: #334155;
                border-color: #94a3b8;
            }
        </style>
        <div class="loading-overlay" id="loadingPayment">
            <div class="qris-modal-card">
                <h3 class="qris-header-title">Selesaikan Pembayaran</h3>
                <p class="qris-header-desc">Scan atau unduh QRIS di bawah ini</p>
                
                <?php
                // Hitung sisa waktu pembayaran (15 menit = 900 detik)
                $remaining_seconds = 900;
                if (isset($transData['created_at'])) {
                    $elapsed = time() - $transData['created_at'];
                    $remaining_seconds = 900 - $elapsed;
                    if ($remaining_seconds < 0) $remaining_seconds = 0;
                }
                
                $total_harga_unik = 0;
                $kode_unik_harga = 0;
                if (isset($transData['price']) && isset($transData['base_price'])) {
                    $total_harga_unik = $transData['price'];
                    $kode_unik_harga = $transData['price'] - $transData['base_price'];
                }
                ?>
                
                <div class="timer-badge">
                    <i class="fa fa-clock"></i> Bayar sebelum: <span id="countdownTimer">15:00</span>
                </div>
                
                <div class="qris-qr-container">
                    <div id="qrisCanvas"></div>
                    <button type="button" id="btnDownloadQris" class="btn-download-qris-new">
                        <i class="fa fa-download"></i> Simpan QRIS ke Galeri
                    </button>
                </div>
                
                <div class="qris-total-amount">
                    Rp <?= number_format($total_harga_unik, 0, ',', '.') ?>
                </div>
                <div class="qris-warning-text">
                    (Mohon transfer TEPAT hingga 3 digit terakhir!)
                </div>
                
                <div class="qris-info-box">
                    <i class="fa fa-info-circle"></i> Angka unik sebesar <b>Rp <?= $kode_unik_harga ?></b> ditambahkan sebagai verifikasi otomatis oleh sistem. Jaminan 100% uang Anda tetap utuh untuk aktivasi voucher.
                </div>
                
                <div style="font-size: 11px; color: #94a3b8; margin-bottom: 20px;">
                    Order ID: <?= htmlspecialchars($snap_order_id) ?>
                </div>
                
                <button type="button" onclick="window.location.href='frontpage.php?cancel_order=<?= urlencode($snap_order_id) ?>&session=<?= urlencode($selected_session) ?>'" class="btn-cancel-qris-new">Batalkan Pesanan</button>
            </div>
        </div>

        <script src="js/qrcode.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var orderId = "<?= $snap_order_id ?>";
                var qrisString = "<?= $snap_token ?>";
                
                // Render QR Code
                var qrcode = new QRCode(document.getElementById("qrisCanvas"), {
                    text: qrisString,
                    width: 230,
                    height: 230,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });

                // Download QRIS Handler
                var btnDownload = document.getElementById("btnDownloadQris");
                if (btnDownload) {
                    btnDownload.addEventListener("click", function() {
                        var container = document.getElementById("qrisCanvas");
                        var img = container.querySelector("img");
                        var canvas = container.querySelector("canvas");
                        var dataUrl = "";
                        
                        if (img && img.src && img.src.indexOf("data:image") === 0) {
                            dataUrl = img.src;
                        } else if (canvas) {
                            dataUrl = canvas.toDataURL("image/png");
                        }
                        
                        if (dataUrl) {
                            var link = document.createElement("a");
                            link.href = dataUrl;
                            link.download = "qris_mikhpay_" + orderId + ".png";
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        } else {
                            alert("Gagal mengunduh QRIS. Silakan ambil tangkapan layar (screenshot) kode QR di atas.");
                        }
                    });
                }

                // Countdown Timer Logic
                var remainingSeconds = <?= $remaining_seconds ?>;
                var countdownEl = document.getElementById("countdownTimer");
                
                function updateTimer() {
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        countdownEl.innerHTML = "Kedaluwarsa!";
                        alert("Waktu batas pembayaran Anda telah habis. Silakan buat pesanan paket baru.");
                        window.location.href = 'index.php?session=<?= urlencode($selected_session) ?>';
                        return;
                    }
                    var minutes = Math.floor(remainingSeconds / 60);
                    var seconds = remainingSeconds % 60;
                    countdownEl.innerHTML = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                    remainingSeconds--;
                }
                
                updateTimer();
                var timerInterval = setInterval(updateTimer, 1000);
                
                // Polling transaksi
                var checkInterval = setInterval(function() {
                    fetch("frontpage.php?check_order=" + orderId)
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success' || data.status === 'paid_pending_generate') {
                                clearInterval(checkInterval);
                                clearInterval(timerInterval);
                                window.location.href = "frontpage.php?show_voucher=1&order_id=" + orderId + "&session=<?= urlencode($selected_session) ?>";
                            }
                        })
                        .catch(e => console.error(e));
                }, 3000);
            });
        </script>
    <?php endif; ?>

    <!-- Global Config and Javascript Controller -->
    <script>
        var FrontpageConfig = {
            session: "<?= urlencode($selected_session) ?>",
            ws: {
                enabled: <?= !empty($ws_app_key) ? 'true' : 'false' ?>,
                key: "<?= htmlspecialchars($ws_app_key) ?>",
                cluster: "<?= htmlspecialchars($ws_cluster) ?>",
                host: "<?= htmlspecialchars($ws_host) ?>",
                port: "<?= htmlspecialchars($ws_port) ?>",
                scheme: "<?= htmlspecialchars($ws_scheme) ?>"
            }
        };
    </script>
    
    <!-- Load Pusher script if WebSocket is enabled -->
    <?php if (!empty($ws_app_key)): ?>
        <script src="https://js.pusher.com/8.0/pusher.min.js"></script>
    <?php endif; ?>

    <script src="js/frontpage.js"></script>
    <!-- View Switcher Controller -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var btnGrid = document.getElementById("btnGridView");
            var btnCarousel = document.getElementById("btnCarouselView");
            var wrapper = document.querySelector(".products-wrapper");

            if (btnGrid && btnCarousel && wrapper) {
                function setGrid() {
                    wrapper.className = "products-wrapper products-grid";
                    btnGrid.classList.add("active");
                    btnCarousel.classList.remove("active");
                    localStorage.setItem("mikhmon_vcr_layout", "grid");
                }
                function setCarousel() {
                    wrapper.className = "products-wrapper products-carousel";
                    btnCarousel.classList.add("active");
                    btnGrid.classList.remove("active");
                    localStorage.setItem("mikhmon_vcr_layout", "carousel");
                }

                btnGrid.addEventListener("click", setGrid);
                btnCarousel.addEventListener("click", setCarousel);

                // Initialize layout (default carousel on mobile, grid on desktop)
                var stored = localStorage.getItem("mikhmon_vcr_layout");
                if (stored === "grid") {
                    setGrid();
                } else if (stored === "carousel") {
                    setCarousel();
                } else {
                    if (window.innerWidth <= 768) {
                        setCarousel();
                    } else {
                        setGrid();
                    }
                }
            }
        });
    </script>
</body>
</html>
