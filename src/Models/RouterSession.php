<?php
namespace App\Models;

use App\Database;

/**
 * Model RouterSession untuk pengelolaan sesi router menggunakan JSON database
 * Ditulis agar kompatibel dengan PHP 5.x ke atas.
 */
class RouterSession {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        $sessions = $this->db->getData('router_sessions', null, array());
        ksort($sessions);
        return array_values($sessions);
    }

    public function getBySessionName($name) {
        return $this->db->getData('router_sessions', $name, null);
    }

    public function save($data) {
        $name = $data['session_name'];
        $sessionData = array(
            'session_name' => $name,
            'ip_address' => $data['ip_address'],
            'username' => $data['username'],
            'password' => $data['password'],
            'hotspot_name' => isset($data['hotspot_name']) ? $data['hotspot_name'] : '',
            'dns_name' => isset($data['dns_name']) ? $data['dns_name'] : '',
            'currency' => isset($data['currency']) ? $data['currency'] : 'Rp',
            'auto_reload' => (int)(isset($data['auto_reload']) ? $data['auto_reload'] : 10),
            'traffic_interface' => isset($data['traffic_interface']) ? $data['traffic_interface'] : '1',
            'idle_timeout' => isset($data['idle_timeout']) ? $data['idle_timeout'] : '10',
            'live_report' => isset($data['live_report']) ? $data['live_report'] : 'enable'
        );
        $this->db->setData('router_sessions', $name, $sessionData);
        return true;
    }

    public function delete($name) {
        $this->db->removeData('router_sessions', $name);
        return true;
    }
}
