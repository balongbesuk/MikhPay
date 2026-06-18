<?php
namespace App;

/**
 * JSON Database Manager untuk MikhTrans
 * Mengelola penyimpanan terstruktur dalam file JSON dengan penguncian berkas (file locking)
 * untuk menghindari korupsi data tanpa ketergantungan pada ekstensi pdo_sqlite.
 * Ditulis agar kompatibel dengan PHP 5.x ke atas.
 */
class Database {
    private static $instance = null;
    private $dbFile;
    private $data = array();

    private function __construct() {
        $dbDir = dirname(__FILE__) . '/../data';
        if (!file_exists($dbDir)) {
            mkdir($dbDir, 0755, true);
            file_put_contents($dbDir . '/.htaccess', "Deny from all\n");
        }

        $this->dbFile = $dbDir . '/database.json';
        $this->load();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Memuat data dari file JSON dengan penguncian berkas (shared lock)
     */
    public function load() {
        if (!file_exists($this->dbFile)) {
            $this->data = array(
                'app_settings' => array(
                    'admin_username' => 'mikhmon',
                    'admin_password_hash' => 'aWNlbA==',
                    'quick_print_qr' => 'disable'
                ),
                'router_sessions' => array()
            );
            $this->saveAll();
            return;
        }

        $fp = fopen($this->dbFile, 'rb');
        if ($fp) {
            if (flock($fp, LOCK_SH)) {
                $content = '';
                while (!feof($fp)) {
                    $content .= fread($fp, 8192);
                }
                flock($fp, LOCK_UN);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->data = $decoded;
                }
            }
            fclose($fp);
        }
    }

    /**
     * Menyimpan seluruh data ke file JSON dengan penguncian berkas (exclusive lock)
     */
    public function saveAll() {
        $fp = fopen($this->dbFile, 'cb');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                fwrite($fp, json_encode($this->data));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    public function getData($section, $key = null, $default = null) {
        if (!isset($this->data[$section])) {
            return $default;
        }
        if ($key === null) {
            return $this->data[$section];
        }
        return isset($this->data[$section][$key]) ? $this->data[$section][$key] : $default;
    }

    public function setData($section, $key, $value) {
        if (!isset($this->data[$section])) {
            $this->data[$section] = array();
        }
        $this->data[$section][$key] = $value;
        $this->saveAll();
    }

    public function removeData($section, $key) {
        if (isset($this->data[$section][$key])) {
            unset($this->data[$section][$key]);
            $this->saveAll();
        }
    }
}
