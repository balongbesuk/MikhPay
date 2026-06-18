<?php
/**
 * MikhTrans JSON Migration Script v3
 * Membaca data konfigurasi lama dari include/config.php dan memigrasikannya ke database JSON.
 * Ditulis agar kompatibel dengan PHP 5.x ke atas.
 */

require_once dirname(__FILE__) . '/../include/autoload.php';

use App\Models\AppSettings;
use App\Models\RouterSession;

// Helper untuk menghindari null coalescing operator (PHP < 7.0)
function getExplodedVal($delimiter, $string, $index) {
    if (empty($string)) {
        return '';
    }
    $parts = explode($delimiter, $string);
    return isset($parts[$index]) ? $parts[$index] : '';
}

// 1. Baca isi config.php lama
$configFile = dirname(__FILE__) . '/../include/config.php';
if (!file_exists($configFile)) {
    die("config.php tidak ditemukan.\n");
}

// Gunakan include untuk mendapatkan variabel $data yang terdefinisi secara global
include $configFile;

if (!isset($data) || !is_array($data)) {
    die("Data konfigurasi tidak valid di config.php.\n");
}

$dbSettings = new AppSettings();
$dbSessions = new RouterSession();

// 2. Migrasikan Kredensial Admin
if (isset($data['mikhmon'])) {
    $adminUser = getExplodedVal('<|<', $data['mikhmon'][1], 1);
    $adminPassHash = getExplodedVal('>|>', $data['mikhmon'][2], 1);
    $dbSettings->saveAdminCredentials($adminUser, $adminPassHash);
    echo "Kredensial Admin berhasil dimigrasikan: $adminUser<br>\n";
}

// 3. Migrasikan Sesi Router
foreach ($data as $sessionName => $sessionArr) {
    if ($sessionName === 'mikhmon' || empty($sessionName)) {
        continue;
    }
    
    // Parsing manual sesuai format lama mikhmon
    $ip = getExplodedVal('!', $sessionArr[1], 1);
    $usr = getExplodedVal('@|@', $sessionArr[2], 1);
    $pwd = getExplodedVal('#|#', $sessionArr[3], 1);
    $hName = getExplodedVal('%', $sessionArr[4], 1);
    $dns = getExplodedVal('^', $sessionArr[5], 1);
    $curr = getExplodedVal('&', $sessionArr[6], 1);
    $reload = getExplodedVal('*', $sessionArr[7], 1);
    $iface = getExplodedVal('(', $sessionArr[8], 1);
    $idle = getExplodedVal('=', $sessionArr[10], 1);
    $live = getExplodedVal('@!@', $sessionArr[11], 1);
    
    // Definisikan default jika kosong
    if (empty($curr)) $curr = 'Rp';
    if (empty($reload)) $reload = 10;
    if (empty($iface)) $iface = '1';
    if (empty($idle)) $idle = '10';
    if (empty($live)) $live = 'enable';
    
    $dbSessions->save(array(
        'session_name' => $sessionName,
        'ip_address' => $ip,
        'username' => $usr,
        'password' => $pwd,
        'hotspot_name' => $hName,
        'dns_name' => $dns,
        'currency' => $curr,
        'auto_reload' => $reload,
        'traffic_interface' => $iface,
        'idle_timeout' => $idle,
        'live_report' => $live
    ));
    
    echo "Sesi Router '$sessionName' berhasil dimigrasikan.<br>\n";
}

echo "Migrasi ke JSON selesai dengan sukses!<br>\n";
