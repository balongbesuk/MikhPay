<?php
/**
 * MikhTrans Custom PSR-4 Autoloader
 * Menyediakan autoloading otomatis untuk namespace 'App\' yang mengarah ke folder 'src/'.
 * Berfungsi sebagai fallback mandiri jika Composer tidak terpasang di sistem pengguna.
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
