<?php
namespace App\Models;

use App\Database;

/**
 * Model AppSettings untuk mengelola konfigurasi key-value global menggunakan JSON database
 * Ditulis agar kompatibel dengan PHP 5.x ke atas.
 */
class AppSettings {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function get($key, $default = null) {
        return $this->db->getData('app_settings', $key, $default);
    }

    public function set($key, $value) {
        $this->db->setData('app_settings', $key, $value);
        return true;
    }

    public function getAdminCredentials() {
        return array(
            'username' => $this->get('admin_username', 'mikhmon'),
            'password_hash' => $this->get('admin_password_hash', 'aWNlbA==')
        );
    }

    public function saveAdminCredentials($username, $passwordHash) {
        $this->set('admin_username', $username);
        return $this->set('admin_password_hash', $passwordHash);
    }
}
