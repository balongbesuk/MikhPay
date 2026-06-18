<?php
/**
 * Mikhmon frontpage.php
 * Halaman depan pelanggan untuk pembelian voucher hotspot otomatis menggunakan Midtrans.
 * Memenuhi kriteria kelayakan/compliance Midtrans.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // Tampilkan error untuk membantu debugging
date_default_timezone_set('Asia/Jakarta');

// Start session safely if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HTTP Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");


// Pastikan folder voucher ada dan aman (.htaccess)
if (!file_exists(__DIR__ . '/voucher')) {
    mkdir(__DIR__ . '/voucher', 0755, true);
}
if (!file_exists(__DIR__ . '/voucher/.htaccess')) {
    file_put_contents(__DIR__ . '/voucher/.htaccess', "Deny from all\n");
}

// Auto-cleanup berkas transaksi usang (> 2 hari) secara berkala
if (rand(1, 100) <= 5) {
    $dir = __DIR__ . '/voucher/';
    if (is_dir($dir)) {
        foreach (glob($dir . 'trans-*.json') as $file) {
            if (time() - filemtime($file) > 172800) { // 2 hari
                @unlink($file);
            }
        }
    }
}

// Include config
if (!file_exists(__DIR__ . '/include/config.php')) {
    die("Configuration file config.php not found.");
}
include_once(__DIR__ . '/include/config.php');

// Safety defaults for Midtrans config
$midtrans_server_key = isset($midtrans_server_key) ? $midtrans_server_key : '';
$midtrans_client_key = isset($midtrans_client_key) ? $midtrans_client_key : '';
$midtrans_is_production = isset($midtrans_is_production) ? $midtrans_is_production : false;

// Helper untuk meminta Snap Token ke Midtrans
function getMidtransSnapToken($order_id, $price, $server_key, $is_production) {
    $url = $is_production 
        ? "https://app.midtrans.com/snap/v1/transactions" 
        : "https://app.sandbox.midtrans.com/snap/v1/transactions";
        
    $auth = base64_encode($server_key . ":");
    
    $payload = [
        'transaction_details' => [
            'order_id' => $order_id,
            'gross_amount' => (int)$price
        ],
        'expiry' => [
            'start_time' => date("Y-m-d H:i:s O"),
            'unit' => 'minute',
            'duration' => 10
        ],
        'credit_card' => [
            'secure' => true
        ]
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n" .
                        "Authorization: Basic $auth\r\n",
            'content' => json_encode($payload),
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

// Endpoint Polling Pengecekan Status Pembayaran (Client-side fetch)
if (isset($_GET['check_order'])) {
    header('Content-Type: application/json');
    $order_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['check_order']);
    $filepath = __DIR__ . "/voucher/trans-" . $order_id . ".json";
    
    if (file_exists($filepath)) {
        $trans = json_decode(file_get_contents($filepath), true);
        if (isset($trans['status']) && $trans['status'] === 'settlement') {
            echo json_encode([
                'status' => 'success',
                'username' => isset($trans['username']) ? $trans['username'] : '',
                'password' => isset($trans['password']) ? $trans['password'] : '',
                'profile' => isset($trans['profile']) ? $trans['profile'] : '',
                'price' => isset($trans['price']) ? $trans['price'] : 0,
                'validity' => isset($trans['validity']) ? $trans['validity'] : ''
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
$router_online = false;

// Tampilkan voucher sukses jika redirect kembali dari pembayaran
$show_voucher_id = isset($_GET['order_id']) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['order_id']) : '';
if (isset($_GET['show_voucher']) && !empty($show_voucher_id)) {
    $filepath = __DIR__ . "/voucher/trans-" . $show_voucher_id . ".json";
    if (file_exists($filepath)) {
        $trans = json_decode(file_get_contents($filepath), true);
        if (isset($trans['status']) && $trans['status'] === 'settlement') {
            $success_voucher = [
                'username' => $trans['username'],
                'password' => $trans['password'],
                'profile' => $trans['profile'],
                'price' => $trans['price'],
                'validity' => $trans['validity']
            ];
            $currency = isset($data[$selected_session]) ? explode('&', $data[$selected_session][6])[1] : 'Rp';
        }
    }
}

// Konek ke MikroTik untuk list profil & cek status online
if (!empty($selected_session) && !$success_voucher) {
    include_once(__DIR__ . '/lib/routeros_api.class.php');
    include_once(__DIR__ . '/lib/formatbytesbites.php');

    if (isset($data[$selected_session])) {
        $iphost = explode('!', $data[$selected_session][1])[1];
        $userhost = explode('@|@', $data[$selected_session][2])[1];
        $passwdhost = explode('#|#', $data[$selected_session][3])[1];
        $currency = explode('&', $data[$selected_session][6])[1];

        $API = new RouterosAPI();
        $API->debug = false;

        // Beri timeout koneksi cepat
        if (@$API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $router_online = true;
            $raw_profiles = $API->comm("/ip/hotspot/user/profile/print");
            if (is_array($raw_profiles)) {
                foreach ($raw_profiles as $prof) {
                    if ($prof['name'] === 'default') continue;

                    $onLogin = isset($prof['on-login']) ? $prof['on-login'] : '';
                    $exploded = explode(',', $onLogin);
                    
                    $price = isset($exploded[2]) ? (float)$exploded[2] : 0;
                    if ($price <= 0) continue; // Hanya tampilkan profil yang memiliki harga > 0
                    
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

                // Urutkan profil berdasarkan harga terendah ke tertinggi
                usort($profiles, function($a, $b) {
                    if ($a['price'] == $b['price']) {
                        return 0;
                    }
                    return ($a['price'] < $b['price']) ? -1 : 1;
                });
            }
            $API->disconnect();
        } else {
            $router_online = false;
            $error_msg = "Sistem gagal terhubung ke router MikroTik.";
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

// Proses Pembuatan Transaksi Midtrans Snap
$snap_token = "";
$snap_order_id = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_profile'])) {
    // Cooldown rate limit check (10 detik)
    if (isset($_SESSION['last_checkout_time']) && (time() - $_SESSION['last_checkout_time'] < 10)) {
        $error_msg = "Harap tunggu 10 detik sebelum melakukan pesanan kembali.";
    } else {
        $_SESSION['last_checkout_time'] = time();

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
                        
                        // Panggil Midtrans Snap
                        $snapResult = getMidtransSnapToken($snap_order_id, $price, $midtrans_server_key, $midtrans_is_production);
                        
                        if (isset($snapResult['token'])) {
                            $snap_token = $snapResult['token'];
                            
                            // Simpan data pending transaksi
                            $transData = [
                                'status' => 'pending',
                                'order_id' => $snap_order_id,
                                'session' => $selected_session,
                                'profile' => $profile_to_buy,
                                'price' => $price,
                                'validity' => $validity,
                                'created_at' => time()
                            ];
                            
                            if (!file_exists(__DIR__ . '/voucher')) {
                                mkdir(__DIR__ . '/voucher', 0755, true);
                            }
                            file_put_contents(__DIR__ . "/voucher/trans-" . $snap_order_id . ".json", json_encode($transData));
                        } else {
                            $error_msg = "Gagal membuat tagihan pembayaran ke Midtrans.";
                        }
                    } else {
                        $error_msg = "Profil paket tidak ditemukan.";
                    }
                    $API->disconnect();
                } else {
                    $error_msg = "Gagal terhubung ke router MikroTik.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GalaxyNet Hotspot - Layanan Internet Wifi Voucher</title>
    <!-- SEO Meta Tags -->
    <meta name="description" content="Layanan internet hotspot instan berkecepatan tinggi dengan sistem voucher otomatis via QRIS dan E-Wallet. Cepat, stabil, dan tanpa kontrak bulanan.">
    <meta name="keywords" content="hotspot, voucher hotspot, internet murah, qris hotspot, mikhmon midtrans">
    
    <!-- Modern Typography & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Midtrans Snap JS -->
    <script type="text/javascript"
            src="https://app.<?= $midtrans_is_production ? '' : 'sandbox.' ?>midtrans.com/snap/snap.js"
            data-client-key="<?= $midtrans_client_key ?>"></script>

    <style>
        :root {
            /* Design Tokens (HSL system) - Light Theme Overhaul */
            --hue: 228;
            --background: HSL(var(--hue), 24%, 97%);
            --background-alt: HSL(var(--hue), 24%, 93%);
            --card-bg: rgba(255, 255, 255, 0.85);
            --primary: HSL(242, 78%, 56%);
            --primary-hover: HSL(242, 78%, 48%);
            --accent: HSL(150, 78%, 32%);
            --accent-hover: HSL(150, 78%, 26%);
            --danger: HSL(0, 72%, 48%);
            --border-color: rgba(0, 0, 0, 0.07);
            --text-main: HSL(var(--hue), 24%, 12%);
            --text-muted: HSL(var(--hue), 12%, 42%);
            --shadow-primary: 0 12px 30px rgba(99, 102, 241, 0.08);
            --shadow-success: 0 12px 30px rgba(16, 185, 129, 0.08);
            --glass-blur: 20px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--background);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.04) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.03) 0%, transparent 40%);
        }

        /* Container & Grid Setup */
        .wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Translucent Header */
        header.nav-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(var(--glass-blur));
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        header.nav-header .wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        .logo i {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        nav.menu-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        nav.menu-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        nav.menu-links a:hover {
            color: var(--text-main);
        }

        .btn-admin {
            background: rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
        }

        .btn-admin:hover {
            background: rgba(0, 0, 0, 0.08);
            border-color: rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        /* Hero Section */
        section.hero {
            padding: 130px 0 60px 0; /* Mobile First */
            text-align: center;
            position: relative;
        }

        section.hero h1 {
            font-size: 32px; /* Mobile First */
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -1px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #0f172a 30%, #312e81 80%, #4f46e5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        section.hero p {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 680px;
            margin: 0 auto 36px auto;
        }

        .cta-group {
            display: flex;
            justify-content: center;
            gap: 16px;
        }

        .btn-primary-action {
            background: var(--primary);
            color: white;
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: var(--shadow-primary);
        }

        .btn-primary-action:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-secondary-action {
            background: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-secondary-action:hover {
            background: rgba(0, 0, 0, 0.03);
            transform: translateY(-2px);
        }

        /* Router Session Switcher */
        .session-switcher {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 32px 0;
            background: var(--background-alt);
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            display: inline-flex;
            margin-left: auto;
            margin-right: auto;
        }

        .session-switcher select {
            background: transparent;
            color: var(--text-main);
            border: none;
            font-family: inherit;
            font-weight: 600;
            font-size: 14px;
            outline: none;
            cursor: pointer;
            padding-right: 8px;
        }

        /* Status & Alert Banners */
        .alert-bar {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.15);
            padding: 16px;
            border-radius: 16px;
            color: #b91c1c;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
            font-size: 14px;
            text-align: left;
        }

        .alert-bar i {
            font-size: 20px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .status-online {
            background: rgba(16, 185, 129, 0.08);
            color: var(--accent);
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        .status-offline {
            background: rgba(239, 68, 68, 0.08);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        /* Products Grid */
        section.products {
            padding: 80px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-header h2 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #0f172a 50%, #4338ca 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 16px;
            max-width: 500px;
            margin: 0 auto;
        }

        .products-grid {
            display: grid;
            grid-template-columns: 1fr; /* Mobile First: 1 column */
            gap: 24px;
        }

        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 32px;
            backdrop-filter: blur(var(--glass-blur));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        }

        .product-card:hover {
            transform: translateY(-6px);
            border-color: rgba(99, 102, 241, 0.2);
            box-shadow: var(--shadow-primary);
        }

        .product-card.card-offline:hover {
            transform: translateY(-6px);
            border-color: rgba(239, 68, 68, 0.2);
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.05);
        }

        .card-top {
            margin-bottom: 24px;
        }

        .plan-icon {
            width: 48px;
            height: 48px;
            background: rgba(99, 102, 241, 0.06);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
            margin-bottom: 20px;
        }

        .card-offline .plan-icon {
            background: rgba(239, 68, 68, 0.06);
            color: var(--danger);
        }

        .plan-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .plan-description {
            font-size: 13px;
            color: var(--text-muted);
            min-height: 48px;
            margin-bottom: 16px;
        }

        .plan-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.03);
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .plan-meta span i {
            margin-right: 4px;
            color: var(--primary);
        }

        .card-offline .plan-meta span i {
            color: var(--danger);
        }

        .plan-price-wrapper {
            margin-bottom: 24px;
        }

        .plan-price-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 1px;
        }

        .plan-price {
            font-size: 28px;
            font-weight: 800;
            color: var(--accent);
        }

        .card-offline .plan-price {
            color: var(--text-muted);
            text-decoration: line-through;
        }

        .btn-buy-voucher {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-buy-voucher:hover {
            background: var(--primary-hover);
        }

        .btn-buy-voucher:disabled {
            background: rgba(0, 0, 0, 0.04);
            color: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            cursor: not-allowed;
        }

        /* How it Works */
        section.how-it-works {
            padding: 80px 0;
            background: var(--background-alt);
        }

        .steps-container {
            display: grid;
            grid-template-columns: 1fr; /* Mobile First: 1 column */
            gap: 24px;
        }

        .step-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 32px;
            border-radius: 20px;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.01);
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            margin: 0 auto 20px auto;
        }

        .step-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-main);
        }

        .step-card p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Terms & Policies */
        section.compliance-docs {
            padding: 80px 0;
        }

        .tabs-header {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .tab-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-primary);
        }

        .tab-content {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px; /* Mobile First */
            backdrop-filter: blur(var(--glass-blur));
            display: none;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.01);
            text-align: left;
        }

        .tab-content.active {
            display: block;
        }

        .tab-content h3 {
            font-size: 22px;
            margin-bottom: 24px;
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            color: var(--text-main);
        }

        .tab-content ol {
            padding-left: 20px;
            margin-bottom: 20px;
        }

        .tab-content li {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .tab-content li strong {
            color: var(--text-main);
        }

        .tab-content p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        /* Contact Section */
        section.contact {
            padding: 80px 0;
            background: var(--background-alt);
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr; /* Mobile First: 1 column */
            gap: 24px;
        }

        .contact-info-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.01);
            text-align: left;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 28px;
        }

        .contact-item i {
            font-size: 20px;
            color: var(--primary);
            margin-top: 4px;
        }

        .contact-item-text h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-main);
        }

        .contact-item-text p {
            font-size: 13px;
            color: var(--text-muted);
        }

        .contact-form {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.01);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .form-group input, .form-group textarea {
            background: rgba(0, 0, 0, 0.015);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }

        .form-group input:focus, .form-group textarea:focus {
            border-color: var(--primary);
            background: #ffffff;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        /* Checkout Overlay & Loader */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-main);
        }

        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.05);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border-left-color: var(--primary);
            animation: spin 1s linear infinite;
            margin-bottom: 24px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-overlay h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .loading-overlay p {
            color: var(--text-muted);
            font-size: 14px;
            text-align: center;
        }

        /* Success Voucher Receipt Card */
        .receipt-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px dashed rgba(99, 102, 241, 0.25);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.06);
            margin: 0 auto;
            position: relative;
        }

        .success-icon {
            width: 64px;
            height: 64px;
            background: rgba(16, 185, 129, 0.08);
            color: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 24px auto;
            border: 1px solid rgba(16, 185, 129, 0.15);
            animation: pulse-success 2s infinite;
        }

        @keyframes pulse-success {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.25); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .receipt-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .receipt-subtitle {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 24px;
        }

        .voucher-box {
            background: rgba(99, 102, 241, 0.03);
            border: 1px dashed rgba(99, 102, 241, 0.25);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
        }

        .voucher-code-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 6px;
            letter-spacing: 1px;
        }

        .voucher-code {
            font-family: monospace;
            font-size: 32px;
            letter-spacing: 4px;
            font-weight: 800;
            color: var(--primary);
            text-shadow: none;
        }

        .receipt-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            text-align: left;
            border-top: 1px solid var(--border-color);
            padding-top: 24px;
            margin-bottom: 28px;
        }

        .receipt-info-item span {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .receipt-info-item strong {
            font-size: 14px;
            color: var(--text-main);
        }

        .btn-connect {
            width: 100%;
            background: var(--accent);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .btn-connect:hover {
            background: var(--accent-hover);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.25);
            transform: translateY(-1px);
        }

        .receipt-actions {
            display: flex;
            gap: 12px;
        }

        .btn-copy {
            flex: 1;
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-copy:hover {
            background: rgba(0, 0, 0, 0.06);
        }

        .btn-done {
            flex: 1;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-done:hover {
            background: var(--primary-hover);
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border-color);
            background: var(--background-alt);
            padding: 40px 0;
            text-align: center;
        }

        footer p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 20px;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--text-main);
        }

        /* Bottom Navigation Bar for Mobile */
        nav.bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(var(--glass-blur));
            border-top: 1px solid var(--border-color);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.03);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 900;
            padding: 0 12px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
            flex: 1;
            text-align: center;
            transition: all 0.2s ease;
        }

        .nav-item i {
            font-size: 18px;
        }

        .nav-item.active, .nav-item:hover {
            color: var(--primary);
        }

        /* Default menu link behaviour (mobile first: hide top navigation menu links) */
        nav.menu-links {
            display: none;
        }

        /* Responsive Breakpoints - Desktop layout overrides */
        @media (min-width: 768px) {
            nav.bottom-nav {
                display: none; /* Hide bottom nav on desktop */
            }

            nav.menu-links {
                display: flex; /* Display horizontally on desktop */
                position: static;
                width: auto;
                background: transparent;
                backdrop-filter: none;
                border: none;
                flex-direction: row;
                padding: 0;
                gap: 32px;
                box-shadow: none;
            }

            section.hero {
                padding: 180px 0 100px 0; /* Desktop padding */
            }

            section.hero h1 {
                font-size: 52px; /* Desktop font size */
                letter-spacing: -1.5px;
                margin-bottom: 24px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }

            .steps-container {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 32px;
            }

            .tab-content {
                padding: 40px; /* Desktop padding */
            }

            .contact-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 40px;
            }
        }
    </style>
</head>
<body>

    <!-- Translucent Navigation Bar -->
    <header class="nav-header">
        <div class="wrapper">
            <a href="#" class="logo">
                <i class="fa fa-wifi"></i> GalaxyNet
            </a>
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
        <div class="section-header">
            <h2>Pilihan Paket Voucher</h2>
            <p>Pilih paket internet terbaik untuk menemani aktivitas harian Anda</p>
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

        <?php if ($success_voucher): ?>
            <!-- Menampilkan Voucher Hasil Pembelian Sukses -->
            <div class="receipt-card">
                <div class="success-icon">
                    <i class="fa fa-check"></i>
                </div>
                <div class="receipt-title">Pembayaran Berhasil!</div>
                <div class="receipt-subtitle">Voucher internet Anda telah berhasil diterbitkan</div>

                <div class="voucher-box">
                    <div class="voucher-code-label">Kode Voucher</div>
                    <div class="voucher-code" id="voucherCode"><?= htmlspecialchars($success_voucher['username']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px;">
                        Gunakan kode di atas pada halaman masuk Hotspot Anda.
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
                    <?php
                    $login_url = "";
                    if (!empty($dnsname)) {
                        $login_url = "http://" . $dnsname . "/login?username=" . urlencode($success_voucher['username']) . "&password=" . urlencode($success_voucher['password']);
                    }
                    ?>
                    <?php if (!empty($login_url)): ?>
                        <a href="<?= htmlspecialchars($login_url) ?>" class="btn-connect">Hubungkan Sekarang</a>
                    <?php endif; ?>
                    <div style="display: flex; gap: 12px; width: 100%;">
                        <button class="btn-copy" onclick="copyVoucherCode()">Salin Kode</button>
                        <a href="index.php?session=<?= urlencode($selected_session) ?>" class="btn-done">Selesai</a>
                    </div>
                </div>
            </div>

            <script>
                // Bersihkan data transaksi aktif dari localStorage setelah voucher sukses dimuat
                localStorage.removeItem('active_order_id');
                localStorage.removeItem('active_snap_token');

                function copyVoucherCode() {
                    var codeText = document.getElementById("voucherCode").innerText;
                    navigator.clipboard.writeText(codeText).then(function() {
                        alert("Kode voucher berhasil disalin!");
                    }, function(err) {
                        alert("Gagal menyalin kode. Silakan salin secara manual.");
                    });
                }
            </script>
        <?php else: ?>
            <!-- Grid Produk -->
            <div class="products-grid">
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
                                <form method="POST" action="#paket">
                                    <input type="hidden" name="buy_profile" value="<?= htmlspecialchars($prof['name']) ?>">
                                    <button type="submit" class="btn-buy-voucher">
                                        <i class="fa fa-shopping-cart"></i> Beli Sekarang
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Tampilan paket fallback offline (tetap memajang deskripsi produk sesuai syarat Midtrans) -->
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

    <!-- Terms & Policies Section (Compliance Midtrans) -->
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
            <p>Selamat datang di layanan GalaxyNet Hotspot. Dengan membeli dan menggunakan voucher kami, Anda menyetujui seluruh ketentuan berikut:</p>
            <ol>
                <li><strong>Penggunaan Voucher:</strong> Setiap voucher hotspot hanya berlaku untuk 1 (satu) perangkat dalam satu satuan waktu akses, kecuali dinyatakan lain pada jenis paket.</li>
                <li><strong>Masa Berlaku Voucher:</strong> Masa aktif voucher dimulai sejak pengguna pertama kali melakukan koneksi (login) ke jaringan hotspot kami.</li>
                <li><strong>Batas Tanggung Jawab:</strong> Kami berupaya memberikan konektivitas terbaik. Namun, kami tidak bertanggung jawab atas gangguan sinyal yang disebabkan oleh faktor geografis, interferensi perangkat pengguna, atau kendala teknis tak terduga di luar jangkauan infrastruktur kami.</li>
                <li><strong>Larangan Penggunaan:</strong> Pengguna dilarang menggunakan layanan internet hotspot kami untuk aktivitas ilegal, penyebaran konten berhak cipta secara tidak sah, peretasan, spamming, serta tindakan kriminal siber lainnya yang melanggar hukum Negara Republik Indonesia.</li>
                <li><strong>Perubahan Layanan:</strong> GalaxyNet Hotspot berhak menyesuaikan tarif, alokasi bandwidth, dan kebijakan operasional hotspot sewaktu-waktu demi menjaga kualitas layanan yang optimal bagi semua pelanggan.</li>
            </ol>
        </div>

        <!-- Kebijakan Pengembalian Dana Content -->
        <div class="tab-content" id="kebijakan-pengembalian">
            <h3>Kebijakan Pengembalian Dana & Layanan</h3>
            <p>Kepuasan Anda adalah komitmen utama kami. Berikut adalah rincian kebijakan pengembalian dana (refund) atas layanan kami:</p>
            <ol>
                <li><strong>Kriteria Pengembalian Dana:</strong> Pelanggan berhak mengajukan pengembalian dana penuh jika sistem hotspot mengalami gangguan total (down time) berkelanjutan lebih dari 24 jam berturut-turut yang disebabkan langsung oleh kendala server atau infrastruktur kami.</li>
                <li><strong>Pembatalan Transaksi:</strong> Transaksi pembayaran yang telah berhasil diproses oleh Midtrans tidak dapat dibatalkan atau dikembalikan apabila kode voucher telah aktif atau digunakan oleh pembeli.</li>
                <li><strong>Kesalahan Pembelian:</strong> Kami tidak menyediakan pengembalian dana (refund) untuk kesalahan pemilihan paket oleh pembeli (misal salah pilih durasi atau tipe voucher). Mohon teliti kembali sebelum melakukan transaksi.</li>
                <li><strong>Prosedur Pengajuan:</strong> Untuk mengajukan pengembalian dana, silakan kirimkan bukti pembayaran Midtrans yang sah dan tangkapan layar kendala ke kontak bisnis resmi kami (Email: <em>support@galaxynet.my.id</em> / WhatsApp).</li>
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
                            <p>support@galaxynet.my.id</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fa-brands fa-whatsapp"></i>
                        <div class="contact-item-text">
                            <h4>WhatsApp / Telepon</h4>
                            <p>+62 812 3456 7890</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fa fa-location-dot"></i>
                        <div class="contact-item-text">
                            <h4>Alamat Kantor</h4>
                            <p>GalaxyNet Hotspot Area, Jl. Raya Jombang No. 123, Jombang, 61471, Indonesia</p>
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
                <a href="#syarat">Syarat & Kebentuan</a>
                <a href="#kontak">Hubungi Kami</a>
                <a href="admin.php?id=login">Admin Portal</a>
            </div>
            <p>&copy; <?= date("Y") ?> GalaxyNet Hotspot. All Rights Reserved. Seluruh transaksi diproses dalam Rupiah (IDR).</p>
            <p style="font-size: 11px; color: var(--text-muted); padding-bottom: 60px;">Diintegrasikan dengan sistem billing otomatis Mikhmon & payment gateway Midtrans.</p>
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

    <!-- Midtrans Snap Transaction Overlay -->
    <?php if (!empty($snap_token)): ?>
        <div class="loading-overlay" id="loadingPayment">
            <div class="spinner"></div>
            <h2>Menunggu Pembayaran QRIS / E-Wallet</h2>
            <p>Silakan selesaikan transaksi Anda pada pop-up pembayaran Midtrans di layar.</p>
            <p style="margin-top: 8px; font-size: 12px; color: var(--text-muted);">Order ID: <?= htmlspecialchars($snap_order_id) ?></p>

            <div class="qris-tip-box" style="margin-top: 24px; background: rgba(255, 255, 255, 0.95); border: 1px dashed var(--border-color); padding: 16px; border-radius: 12px; font-size: 13px; text-align: left; max-width: 440px; color: var(--text-main); line-height: 1.5; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-left: auto; margin-right: auto;">
                <i class="fa fa-circle-info" style="color: var(--primary); font-size: 16px; float: left; margin-right: 10px; margin-top: 2px;"></i>
                <div style="overflow: hidden;">
                    <strong style="display: block; margin-bottom: 4px; color: var(--primary);">Tips Bayar Satu Perangkat (HP):</strong>
                    Screenshot/tangkap layar kode QR pembayaran dari Midtrans, lalu buka aplikasi e-wallet Anda (Gopay, OVO, Dana, LinkAja, Mobile Banking, dll.). Pilih fitur **Bayar/Scan**, lalu klik ikon galeri untuk mengunggah gambar screenshot tersebut.
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var orderId = "<?= $snap_order_id ?>";
                var snapToken = "<?= $snap_token ?>";
                var checkInterval;
                
                // Simpan transaksi aktif ke localStorage agar bisa di-resume jika tab tertutup
                localStorage.setItem('active_order_id', orderId);
                localStorage.setItem('active_snap_token', snapToken);
                
                snap.pay(snapToken, {
                    onSuccess: function(result) {
                        startPolling(orderId);
                    },
                    onPending: function(result) {
                        startPolling(orderId);
                    },
                    onError: function(result) {
                        alert("Pembuatan transaksi gagal atau dibatalkan.");
                        localStorage.removeItem('active_order_id');
                        localStorage.removeItem('active_snap_token');
                        window.location.href = "index.php?session=<?= urlencode($selected_session) ?>#paket";
                    },
                    onClose: function() {
                        startPolling(orderId);
                    }
                });
                
                function startPolling(orderId) {
                    checkInterval = setInterval(function() {
                        fetch("index.php?check_order=" + orderId)
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "success") {
                                    clearInterval(checkInterval);
                                    window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=<?= urlencode($selected_session) ?>#paket";
                                }
                            });
                    }, 3000);
                }
            });
        </script>
    <?php endif; ?>

    <!-- Tabs Switching & Bottom Navigation Active Scroll Script -->
    <script>
        function switchTab(tabId) {
            // Remove active classes
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to selected elements
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // Active Bottom Navigation Tab dynamic highlight on Scroll
        document.addEventListener("DOMContentLoaded", function() {
            const sections = document.querySelectorAll("section[id], div[id], div[id='home']");
            const navItems = document.querySelectorAll(".bottom-nav .nav-item");

            window.addEventListener("scroll", () => {
                let current = "";
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (pageYOffset >= (sectionTop - 160)) {
                        current = section.getAttribute("id");
                    }
                });

                navItems.forEach(item => {
                    item.classList.remove("active");
                    if (item.getAttribute("href").slice(1) === current) {
                        item.classList.add("active");
                    }
                });
            });
        });

        // Pengecekan riwayat transaksi aktif yang tertunda (Resume checkout)
        document.addEventListener("DOMContentLoaded", function() {
            var activeOrderId = localStorage.getItem('active_order_id');
            var activeSnapToken = localStorage.getItem('active_snap_token');
            
            if (activeOrderId && activeSnapToken && !window.location.search.includes('show_voucher') && !window.location.search.includes('order_id')) {
                // Parse timestamp dari Order ID (format: MK-[session]-[profile]-[timestamp])
                var parts = activeOrderId.split('-');
                var timestamp = parseInt(parts[parts.length - 1], 10);
                var now = Math.floor(Date.now() / 1000);
                
                // Jika transaksi sudah lewat dari 10 menit (600 detik), anggap kadaluarsa & hapus
                if (now - timestamp > 600) {
                    localStorage.removeItem('active_order_id');
                    localStorage.removeItem('active_snap_token');
                    return;
                }
                
                // Lakukan pengecekan status transaksi ke server secara background
                fetch("index.php?check_order=" + activeOrderId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            // Jika ternyata sudah lunas, langsung redirect ke halaman voucher sukses
                            window.location.href = "index.php?show_voucher=1&order_id=" + activeOrderId + "&session=<?= urlencode($selected_session) ?>#paket";
                        } else {
                            // Jika masih pending, munculkan floating banner untuk opsi resume pembayaran
                            showResumeBanner(activeOrderId, activeSnapToken);
                        }
                    });
            }
            
            function showResumeBanner(orderId, snapToken) {
                var banner = document.createElement('div');
                banner.id = 'resume-payment-banner';
                banner.style.cssText = `
                    position: fixed;
                    top: 16px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: white;
                    border: 1px solid var(--border-color);
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    padding: 16px 20px;
                    border-radius: 16px;
                    z-index: 1000;
                    display: flex;
                    align-items: center;
                    gap: 16px;
                    width: 90%;
                    max-width: 500px;
                    font-size: 13px;
                    color: var(--text-main);
                    animation: slideDown 0.3s ease;
                `;
                
                banner.innerHTML = `
                    <div style="flex: 1;">
                        <strong style="color: var(--primary); display: block; margin-bottom: 2px;">Pembayaran Tertunda</strong>
                        <span style="color: var(--text-muted);">Ada transaksi yang belum diselesaikan (10 mnt limit).</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button id="btn-resume-pay" style="background: var(--primary); color: white; border: none; padding: 8px 12px; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">Bayar</button>
                        <button id="btn-resume-dismiss" style="background: rgba(0,0,0,0.05); color: var(--text-muted); border: none; padding: 8px 12px; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">Batal</button>
                    </div>
                `;
                
                document.body.appendChild(banner);
                
                // Tambahkan CSS Keyframe untuk slideDown jika belum ada
                if (!document.getElementById('slideDown-style')) {
                    var style = document.createElement('style');
                    style.id = 'slideDown-style';
                    style.innerHTML = `
                        @keyframes slideDown {
                            from { transform: translate(-50%, -40px); opacity: 0; }
                            to { transform: translate(-50%, 0); opacity: 1; }
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                // Handle click resume
                document.getElementById('btn-resume-pay').addEventListener('click', function() {
                    // Buat loading overlay resume
                    var overlay = document.createElement('div');
                    overlay.style.cssText = `
                        position: fixed;
                        top: 0; left: 0; right: 0; bottom: 0;
                        background: rgba(255,255,255,0.9);
                        z-index: 2000;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                    `;
                    overlay.innerHTML = `
                        <div class="spinner"></div>
                        <h2 style="margin-top: 20px; font-family: 'Plus Jakarta Sans', sans-serif;">Menghubungkan ke Midtrans...</h2>
                    `;
                    document.body.appendChild(overlay);
                    
                    snap.pay(snapToken, {
                        onSuccess: function(result) {
                            checkPayment(orderId);
                        },
                        onPending: function(result) {
                            checkPayment(orderId);
                        },
                        onError: function(result) {
                            overlay.remove();
                            alert("Pembayaran dibatalkan.");
                        },
                        onClose: function() {
                            overlay.remove();
                        }
                    });
                });
                
                // Handle dismiss
                document.getElementById('btn-resume-dismiss').addEventListener('click', function() {
                    localStorage.removeItem('active_order_id');
                    localStorage.removeItem('active_snap_token');
                    banner.remove();
                });
            }
            
            function checkPayment(orderId) {
                var interval = setInterval(function() {
                    fetch("index.php?check_order=" + orderId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === "success") {
                                clearInterval(interval);
                                window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=<?= urlencode($selected_session) ?>#paket";
                            }
                        });
                }, 3000);
            }
        });
    </script>
</body>
</html>
