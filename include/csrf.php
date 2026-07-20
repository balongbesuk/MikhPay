<?php
/**
 * CSRF Protection Helper untuk MikhPay
 * Mencegah serangan Cross-Site Request Forgery pada semua form POST.
 * Dioptimalkan untuk PHP 8.2 ke atas.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
        'use_only_cookies' => true
    ]);
}

/**
 * Generate atau ambil token CSRF yang aktif.
 * Token berlaku selama sesi berlangsung.
 * @return string Token CSRF
 */
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output hidden input field berisi token CSRF.
 * Panggil di dalam <form> tag.
 * @return string HTML hidden input
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verifikasi token CSRF dari POST request.
 * Jika gagal, return HTTP 403 dan hentikan eksekusi.
 * @return bool true jika valid
 */
function csrf_verify() {
    $token = isset($_POST['_csrf_token']) ? $_POST['_csrf_token'] : '';
    if (empty($token) || !isset($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Invalid or missing CSRF token. Silakan muat ulang halaman dan coba lagi.</p>';
        exit;
    }
    // Regenerasi token setelah verifikasi berhasil untuk mencegah replay attack
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    return true;
}
