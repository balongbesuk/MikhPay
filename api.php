<?php
/**
 * Mikhmon REST API
 * Untuk integrasi sistem pembelian voucher otomatis.
 */

// Format output selalu JSON
header('Content-Type: application/json');

// Mematikan tampilan error mentah, diganti dengan respons JSON terstruktur
error_reporting(0);
ini_set('display_errors', 0);

// Load Config Utama
if (!file_exists(__DIR__ . '/include/config.php')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Configuration file config.php not found.']);
    exit;
}
include_once(__DIR__ . '/include/config.php');

// Verifikasi API Key
$headers = getallheaders();
$apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : (isset($_REQUEST['api_key']) ? $_REQUEST['api_key'] : '');

if (empty($mikhmon_api_key) || $apiKey !== $mikhmon_api_key) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Invalid or missing API Key.']);
    exit;
}

// Validasi Parameter Router Session
$session = isset($_REQUEST['session']) ? $_REQUEST['session'] : '';
if (empty($session) || !isset($data[$session])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing router session name.']);
    exit;
}

// Load routeros_api.class.php dan dependencies
include_once(__DIR__ . '/lib/routeros_api.class.php');
include_once(__DIR__ . '/lib/formatbytesbites.php');

// Parse data koneksi router dari data session
$iphost = explode('!', $data[$session][1])[1];
$userhost = explode('@|@', $data[$session][2])[1];
$passwdhost = explode('#|#', $data[$session][3])[1];
$currency = explode('&', $data[$session][6])[1];

$API = new RouterosAPI();
$API->debug = false;

// Konek ke MikroTik
if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to MikroTik router. Check router status and API configuration.']);
    exit;
}

// Handle Aksi
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'profiles':
        // Menampilkan daftar paket/profil hotspot
        $profiles = $API->comm("/ip/hotspot/user/profile/print");
        $resultList = [];

        foreach ($profiles as $prof) {
            if ($prof['name'] === 'default') continue;

            $onLogin = isset($prof['on-login']) ? $prof['on-login'] : '';
            $exploded = explode(',', $onLogin);
            
            $price = isset($exploded[2]) ? (float)$exploded[2] : 0;
            $validity = isset($exploded[3]) ? $exploded[3] : '';
            
            $resultList[] = [
                'name' => $prof['name'],
                'shared_users' => isset($prof['shared-users']) ? $prof['shared-users'] : '',
                'rate_limit' => isset($prof['rate-limit']) ? $prof['rate-limit'] : 'Unlimited',
                'price' => $price,
                'validity' => $validity,
                'currency' => $currency
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $resultList]);
        break;

    case 'generate':
        // Generate voucher baru
        $profileName = isset($_REQUEST['profile']) ? $_REQUEST['profile'] : '';
        if (empty($profileName)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Profile name is required.']);
            exit;
        }

        // Ambil info profil untuk mendapatkan validitas dan harga
        $getProfile = $API->comm("/ip/hotspot/user/profile/print", [
            "?name" => $profileName
        ]);

        if (empty($getProfile)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Profile not found on router.']);
            exit;
        }

        $onLogin = isset($getProfile[0]['on-login']) ? $getProfile[0]['on-login'] : '';
        $exploded = explode(',', $onLogin);
        $price = isset($exploded[2]) ? (float)$exploded[2] : 0;
        $validity = isset($exploded[3]) ? $exploded[3] : '';

        // Parameter Kustom Pembuatan Voucher
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        $qty = ($qty > 100) ? 100 : (($qty < 1) ? 1 : $qty); // Batasan qty 1-100 per request
        
        $userMode = isset($_REQUEST['user_mode']) ? $_REQUEST['user_mode'] : 'vc'; // vc (user=pass) atau up (user & pass)
        $userLength = isset($_REQUEST['user_length']) ? (int)$_REQUEST['user_length'] : 5;
        $userLength = ($userLength < 3 || $userLength > 12) ? 5 : $userLength;
        
        $charSet = isset($_REQUEST['char_set']) ? $_REQUEST['char_set'] : 'mix'; // mix, lower, upper, num, mix1, mix2
        $prefix = isset($_REQUEST['prefix']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_REQUEST['prefix']) : '';
        $commentInput = isset($_REQUEST['comment']) ? $_REQUEST['comment'] : 'API Auto';
        
        $timelimit = isset($_REQUEST['timelimit']) ? $_REQUEST['timelimit'] : '';
        $datalimit = isset($_REQUEST['datalimit']) ? (float)$_REQUEST['datalimit'] : 0; // dalam bytes
        
        $server = isset($_REQUEST['server']) ? $_REQUEST['server'] : 'all';

        // Set Comment Format
        $comment = "API-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $commentInput;
        
        $generatedVouchers = [];

        // Loop pembuatan voucher
        for ($i = 0; $i < $qty; $i++) {
            $username = '';
            $password = '';

            // Generate Username & Password berdasarkan charSet
            if ($userMode === 'up') {
                // User & Password terpisah
                switch ($charSet) {
                    case 'lower': $username = randLC($userLength); break;
                    case 'upper': $username = randUC($userLength); break;
                    case 'upplow': $username = randULC($userLength); break;
                    case 'mix1': $username = randNUC($userLength); break;
                    case 'mix2': $username = randNULC($userLength); break;
                    case 'num': $username = randN($userLength); break;
                    case 'mix':
                    default: $username = randNLC($userLength); break;
                }
                $password = randN($userLength);
            } else {
                // User = Password
                $shuf = $userLength;
                if ($charSet !== 'num' && $charSet !== 'mix' && $charSet !== 'mix1' && $charSet !== 'mix2') {
                    $a = ["1" => "", "", 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6];
                    $shuf = $userLength - (isset($a[$userLength]) ? (int)$a[$userLength] : 2);
                }

                switch ($charSet) {
                    case 'lower': $username = randLC($shuf) . randN($userLength - $shuf); break;
                    case 'upper': $username = randUC($shuf) . randN($userLength - $shuf); break;
                    case 'upplow': $username = randULC($shuf) . randN($userLength - $shuf); break;
                    case 'num': $username = randN($userLength); break;
                    case 'mix1': $username = randNUC($userLength); break;
                    case 'mix2': $username = randNULC($userLength); break;
                    case 'mix':
                    default: $username = randNLC($userLength); break;
                }
                $password = $username;
            }

            $username = $prefix . $username;

            // Parameter tambah user ke RouterOS
            $addParams = [
                "server" => $server,
                "name" => $username,
                "password" => $password,
                "profile" => $profileName,
                "comment" => $comment
            ];

            if (!empty($timelimit)) {
                $addParams["limit-uptime"] = $timelimit;
            }
            if ($datalimit > 0) {
                $addParams["limit-bytes-total"] = (string)$datalimit;
            }

            // Daftarkan ke MikroTik
            $API->comm("/ip/hotspot/user/add", $addParams);

            $generatedVouchers[] = [
                'username' => $username,
                'password' => $password,
                'profile' => $profileName,
                'price' => $price,
                'validity' => $validity,
                'timelimit' => $timelimit ? $timelimit : 'Unlimited',
                'datalimit' => $datalimit ? formatBytes($datalimit, 2) : 'Unlimited',
                'comment' => $comment
            ];
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Successfully generated $qty voucher(s).",
            'data' => $generatedVouchers
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action. Supported actions: profiles, generate.']);
        break;
}

$API->disconnect();
