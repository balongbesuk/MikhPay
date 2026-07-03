<?php
namespace App;

/**
 * JSON Database Manager untuk MikhPay
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

        $this->dbFile = $dbDir . '/database.php';
        $oldDbFile = $dbDir . '/database.json';
        
        // Auto-migration from database.json to database.php
        if (file_exists($oldDbFile) && !file_exists($this->dbFile)) {
            $jsonContent = file_get_contents($oldDbFile);
            $decoded = json_decode($jsonContent, true);
            if (is_array($decoded)) {
                $this->data = $decoded;
                $this->saveAll();
                @unlink($oldDbFile);
            }
        } else {
            $this->load();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Memuat data dari file PHP/JSON dengan penguncian berkas (shared lock)
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

        @chmod($this->dbFile, 0666);
        $fp = @fopen($this->dbFile, 'rb');
        if ($fp) {
            $locked = flock($fp, LOCK_SH);
            $content = '';
            while (!feof($fp)) {
                $content .= fread($fp, 8192);
            }
            if ($locked) {
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            
            // Strip the protective PHP opening header
            $jsonContent = preg_replace('/^<\?php.*?\?>\s*/s', '', $content);
            $decoded = json_decode($jsonContent, true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }
        }
    }

    /**
     * Menyimpan seluruh data ke file PHP/JSON dengan penguncian berkas (exclusive lock)
     */
    public function saveAll() {
        @chmod($this->dbFile, 0666);
        $fp = @fopen($this->dbFile, 'cb');
        if ($fp) {
            $locked = flock($fp, LOCK_EX);
            ftruncate($fp, 0);
            $prefix = "<?php header('HTTP/1.0 403 Forbidden'); exit; ?>\n";
            fwrite($fp, $prefix . json_encode($this->data));
            fflush($fp);
            if ($locked) {
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            return true;
        } else {
            if (function_exists('writeAppLog')) {
                writeAppLog("DATABASE_ERROR", "Gagal membuka database file untuk ditulis: " . $this->dbFile);
            }
            return false;
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
