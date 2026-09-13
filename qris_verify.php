<?php
/**
 * QRIS Verification Endpoint
 * Digunakan oleh MacroDroid atau admin secara manual untuk memverifikasi
 * pembayaran QRIS yang masuk, menggunakan pencocokan "nominal unik".
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone Asia/Jakarta agar catatan waktu lunas sinkron dengan MikroTik
date_default_timezone_set('Asia/Jakarta');

include_once(__DIR__ . '/include/config.php');
include_once(__DIR__ . '/include/env_config.php');

// Set header JSON
header('Content-Type: application/json');

// Ambil input GET atau POST
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
$nominal = isset($_REQUEST['nominal']) ? (int)preg_replace('/[^0-9]/', '', $_REQUEST['nominal']) : 0;

// Cek jika request berupa JSON (dari Aplikasi Android MikhPay Forwarder)
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strpos($contentType, 'application/json') !== false) {
    $rawData = file_get_contents('php://input');
    $jsonData = json_decode($rawData, true);
    if (is_array($jsonData)) {
        if (isset($jsonData['api_key'])) {
            $token = $jsonData['api_key'];
        }
        if (isset($jsonData['amount'])) {
            $nominal = (int)preg_replace('/[^0-9]/', '', $jsonData['amount']);
        }
    }
}

// Validasi Token
if (empty($qris_secret_token) || $token !== $qris_secret_token) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid.']);
    exit;
}

// Catat notifikasi masuk ke log audit untuk melacak sinkronisasi nominal
writeAppLog("QRIS_VERIFY_REQUEST", "Menerima request verifikasi pembayaran nominal: Rp " . number_format($nominal, 0, ',', '.'));

// Muat engine core QRIS
require_once(__DIR__ . '/include/qris_core.php');

// Jalankan pemrosesan settlement
$result = processQrisSettlement($nominal);

if (isset($result['code'])) {
    http_response_code($result['code']);
    unset($result['code']);
}

echo json_encode($result);
exit;

