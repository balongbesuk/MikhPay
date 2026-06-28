<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
        'use_only_cookies' => true
    ]);
}
// hide all error
error_reporting(0);

// CSRF protection
include_once('./include/csrf.php');

// Session timeout (30 menit idle)
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['mikhmon']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $session_timeout) {
        session_unset();
        session_destroy();
        session_start();
    }
}
if (isset($_SESSION['mikhmon'])) {
    $_SESSION['last_activity'] = time();
}

ob_start("ob_gzhandler");

// check url
$url = $_SERVER['REQUEST_URI'];

// load session MikroTik
$session = $_GET['session'];
$id = $_GET['id'];
$c = $_GET['c'];
$router = $_GET['router'];
$logo = $_GET['logo'];

$ids = array(
  "editor",
  "uplogo",
  "settings",
);

// lang
include('./lang/isocodelang.php');
include('./include/lang.php');
include('./lang/'.$langid.'.php');

// quick bt
include('./include/quickbt.php');

// theme
include('./include/theme.php');
include('./settings/settheme.php');
include('./settings/setlang.php');
if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
}

// PRG: Intercept save_settings POST before any HTML output
// so accent color changes take effect immediately on redirect
if ($id == 'pending-transactions' && isset($_POST['action']) && $_POST['action'] === 'save_settings' && isset($_SESSION['mikhmon'])) {
    include_once('./include/autoload.php');
    $earlySettings = new \App\Models\AppSettings();
    $earlySettings->set('telegram_bot_token', isset($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : '');
    $earlySettings->set('telegram_chat_id', isset($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : '');
    $earlySettings->set('log_retention_days', isset($_POST['log_retention_days']) ? (int)$_POST['log_retention_days'] : 2);
    $earlySettings->set('portal_title', isset($_POST['portal_title']) ? trim($_POST['portal_title']) : '');
    $earlySettings->set('portal_logo_url', isset($_POST['portal_logo_url']) ? trim($_POST['portal_logo_url']) : '');
    $earlySettings->set('portal_accent_color', isset($_POST['portal_accent_color']) ? trim($_POST['portal_accent_color']) : '#008BC9');
    $earlySettings->set('portal_support_wa', isset($_POST['portal_support_wa']) ? trim($_POST['portal_support_wa']) : '');
    $earlySettings->set('portal_support_telegram', isset($_POST['portal_support_telegram']) ? trim($_POST['portal_support_telegram']) : '');
    $earlySettings->set('portal_support_email', isset($_POST['portal_support_email']) ? trim($_POST['portal_support_email']) : '');
    $earlySettings->set('portal_office_address', isset($_POST['portal_office_address']) ? trim($_POST['portal_office_address']) : '');
    $earlySettings->set('portal_operational_hours', isset($_POST['portal_operational_hours']) ? trim($_POST['portal_operational_hours']) : '');
    $_SESSION['mikhtrans_success_msg'] = 'Sukses! Pengaturan MikhPay berhasil disimpan.';
    header('Location: ./admin.php?id=pending-transactions&tab=tab-settings');
    exit;
}

// load config
include_once('./include/headhtml.php');
include('./include/config.php');
include('./include/readcfg.php');

include_once('./lib/routeros_api.class.php');
include_once('./lib/formatbytesbites.php');
?>
    
<?php
if ($id == "login" || substr($url, -1) == "p") {

  // Brute-force rate limiting check
  $ipAddress = isset($_SERVER['HTTP_CLIENT_IP']) 
      ? $_SERVER['HTTP_CLIENT_IP'] 
      : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) 
          ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] 
          : $_SERVER['REMOTE_ADDR']);
  
  $loginLimitFile = __DIR__ . '/data/login_rate_limit.json';
  $loginMaxAttempts = 5;
  $loginCooldownPeriod = 600; // 10 minutes
  
  $loginBlocked = false;
  $cooldownRemaining = 0;
  $now = time();
  
  if (file_exists($loginLimitFile)) {
      $loginData = json_decode(@file_get_contents($loginLimitFile), true);
      if (is_array($loginData) && isset($loginData[$ipAddress])) {
          $attempt = $loginData[$ipAddress];
          if ($attempt['count'] >= $loginMaxAttempts && $attempt['reset_time'] > $now) {
              $loginBlocked = true;
              $cooldownRemaining = $attempt['reset_time'] - $now;
          }
      }
  }

  if (isset($_POST['login'])) {
    csrf_verify();
    
    if ($loginBlocked) {
        $minutesLeft = ceil($cooldownRemaining / 60);
        $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>IP Anda diblokir sementara. Silakan tunggu ' . $minutesLeft . ' menit lagi.</div>';
    } else {
        $user = $_POST['user'];
        $pass = $_POST['pass'];
        
        // Verifikasi password: support bcrypt hash, legacy base64+XOR, dan plaintext fallback
        $password_valid = false;
        if ($user == $useradm) {
          // Cek apakah hash sudah bcrypt (dimulai dengan $2y$ atau $2a$)
          if (substr($passadm, 0, 4) === '$2y$' || substr($passadm, 0, 4) === '$2a$') {
            $password_valid = password_verify($pass, $passadm);
          } else {
            // Legacy: password disimpan sebagai base64-encoded XOR ciphertext
            $password_valid = ($pass == decrypt($passadm));
            
            // Auto-upgrade ke bcrypt jika login berhasil
            if ($password_valid) {
              $bcrypt_hash = password_hash($pass, PASSWORD_BCRYPT);
              $dbSettings = new \App\Models\AppSettings();
              $dbSettings->saveAdminCredentials($useradm, $bcrypt_hash);
            }
          }
        }
        
        if ($password_valid) {
          // Clear login attempts on success
          if (file_exists($loginLimitFile)) {
              $loginData = json_decode(@file_get_contents($loginLimitFile), true);
              if (is_array($loginData) && isset($loginData[$ipAddress])) {
                  unset($loginData[$ipAddress]);
                  @file_put_contents($loginLimitFile, json_encode($loginData));
              }
          }
          session_regenerate_id(true);
          $_SESSION["mikhmon"] = $user;
          $_SESSION['last_activity'] = time();
          echo "<script>window.location='./admin.php?id=sessions'</script>";
        } else {
          // Record/Increment login attempt on failure
          $loginData = [];
          if (file_exists($loginLimitFile)) {
              $loginData = json_decode(@file_get_contents($loginLimitFile), true);
          }
          if (!is_array($loginData)) $loginData = [];
          
          if (isset($loginData[$ipAddress])) {
              if ($loginData[$ipAddress]['reset_time'] > $now) {
                  $loginData[$ipAddress]['count']++;
              } else {
                  $loginData[$ipAddress] = [
                      'count' => 1,
                      'reset_time' => $now + $loginCooldownPeriod
                  ];
              }
          } else {
              $loginData[$ipAddress] = [
                  'count' => 1,
                  'reset_time' => $now + $loginCooldownPeriod
              ];
          }
          @file_put_contents($loginLimitFile, json_encode($loginData));
          
          $attemptsLeft = $loginMaxAttempts - $loginData[$ipAddress]['count'];
          if ($attemptsLeft > 0) {
              $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>Invalid username or password. Sisa percobaan: ' . $attemptsLeft . ' kali.</div>';
          } else {
              $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>IP Anda diblokir sementara karena terlalu banyak percobaan masuk. Silakan coba lagi dalam 10 menit.</div>';
          }
        }
    }
  }
  

  include_once('./include/login.php');
} elseif (!isset($_SESSION["mikhmon"])) {
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif (substr($url, -1) == "/" || substr($url, -4) == ".php") {
  echo "<script>window.location='./admin.php?id=sessions'</script>";

} elseif ($id == "sessions") {
  $_SESSION["connect"] = "";
  include_once('./include/menu.php');
  include_once('./settings/sessions.php');
  /*echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';*/
} elseif ($id == "settings" && !empty($session) || $id == "settings" && !empty($router)) {
  include_once('./include/menu.php');
  include_once('./settings/settings.php');
  echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';
} elseif ($id == "connect"  && !empty($session)) {
  ini_set("max_execution_time",5);  
  include_once('./include/menu.php');
  $API = new RouterosAPI();
  $API->debug = false;
  if ($API->connect($iphost, $userhost, decrypt($passwdhost))){
    $_SESSION["connect"] = "<b class='text-green'>Connected</b>";
    echo "<script>window.location='./?session=" . $session . "'</script>";
  } else {
    $_SESSION["connect"] = "<b class='text-red'>Not Connected</b>";
    $nl = '\n';
    if ($currency == in_array($currency, $cekindo['indo'])) {
      echo "<script>alert('Mikhmon not connected!".$nl."Silakan periksa kembali IP, User, Password dan port API harus enable.".$nl."Jika menggunakan koneksi VPN, pastikan VPN tersebut terkoneksi.')</script>";
    }else{
      echo "<script>alert('Mikhmon not connected!".$nl."Please check the IP, User, Password and port API must be enabled.')</script>";
    }
    if($c == "settings"){
      echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
    }else{
      echo "<script>window.location='./admin.php?id=sessions'</script>";
    }
  }
} elseif ($id == "uplogo"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/uplogo.php');
} elseif ($id == "reboot"  && !empty($session)) {
  include_once('./process/reboot.php');
} elseif ($id == "shutdown"  && !empty($session)) {
  include_once('./process/shutdown.php');
} elseif ($id == "remove-session" && $session != "") {
  include_once('./include/menu.php');
  $dbSessions = new \App\Models\RouterSession();
  $dbSessions->delete($session);
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif ($id == "pending-transactions") {
  include_once('./include/menu.php');
  include_once('./settings/pending_transactions.php');
} elseif ($id == "about") {
  include_once('./include/menu.php');
  include_once('./include/about.php');
} elseif ($id == "logout") {
  include_once('./include/menu.php');
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";
  session_destroy();
  echo "<script>
    sessionStorage.clear();
    localStorage.removeItem('active_order_id');
    localStorage.removeItem('active_snap_token');
    window.location='./admin.php?id=login';
  </script>";
} elseif ($id == "remove-logo" && $logo != ""  && !empty($session)) {
  include_once('./include/menu.php');
  $logopath = "./img/";
  $remlogo = $logopath . $logo;
  unlink("$remlogo");
  echo "<script>window.location='./admin.php?id=uplogo&session=" . $session . "'</script>";
} elseif ($id == "editor"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/vouchereditor.php');
} elseif (empty($id)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif(in_array($id, $ids) && empty($session)){
	echo "<script>window.location='./admin.php?id=sessions'</script>";
}
?>
<script src="js/mikhmon-ui.<?= $theme; ?>.min.js?t=<?= time(); ?>"></script>
<script src="js/mikhmon.js?t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>
<?php include('./include/info.php'); ?>
</body>
</html>

