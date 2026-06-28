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
// check url

ob_start("ob_gzhandler");


$url = $_SERVER['REQUEST_URI'];

// load session MikroTik

$session = $_GET['session'];

if (!isset($_SESSION["mikhmon"])) {
  include_once(__DIR__ . '/frontpage.php');
  exit;
} elseif (empty($session)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} else {
  $_SESSION["$session"] = $session;
  $setsession = $_SESSION["$session"];

  $_SESSION["connect"] = "";

// time zone
  date_default_timezone_set($_SESSION['timezone']);

// lang
  include('./include/lang.php');
  include('./lang/'.$langid.'.php');

// quick bt
  include('./include/quickbt.php');

// load config
  include('./include/config.php');
  include('./include/readcfg.php');

// theme  
  include('./include/theme.php');
  include('./settings/settheme.php');
  if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
  }

// routeros api
  include_once('./lib/routeros_api.class.php');
  include_once('./lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, decrypt($passwdhost));

  $getidentity = $API->comm("/system/identity/print");
  $identity = $getidentity[0]['name'];
  

// get variable
  $hotspot = $_GET['hotspot'];
  $hotspotuser = $_GET['hotspot-user'];
  $userbyname = $_GET['hotspot-user'];
  $removeuseractive = $_GET['remove-user-active'];
  $removehost = $_GET['remove-host'];
  $removecookie = $_GET['remove-cookie'];
  $removeipbinding = $_GET['remove-ip-binding'];
  $removehotspotuser = $_GET['remove-hotspot-user'];
  $removehotspotusers = $_GET['remove-hotspot-users'];
  $removeuserprofile = $_GET['remove-user-profile'];
  $resethotspotuser = $_GET['reset-hotspot-user'];
  $removehotspotuserbycomment = $_GET['remove-hotspot-user-by-comment'];
  $removeexpiredhotspotuser = $_GET['remove-hotspot-user-expired'];
  $enablehotspotuser = $_GET['enable-hotspot-user'];
  $disablehotspotuser = $_GET['disable-hotspot-user'];
  $enableipbinding = $_GET['enable-ip-binding'];
  $disableipbinding = $_GET['disable-ip-binding'];
  $userprofile = $_GET['user-profile'];
  $userprofilebyname = $_GET['user-profile'];
  $sys = $_GET['system'];
  $enablesch = $_GET['enable-scheduler'];
  $disablesch = $_GET['disable-scheduler'];
  $removesch = $_GET['remove-scheduler'];
  $macbinding = $_GET['mac'];
  $ipbinding = $_GET['addr'];
  $ppp = $_GET['ppp'];
  $secretbyname = $_GET['secret'];
  $enablesecr = $_GET['enable-pppsecret'];
  $disablesecr = $_GET['disable-pppsecret'];
  $removesecr = $_GET['remove-pppsecret'];
  $removepprofile = $_GET['remove-pprofile'];
  $removepactive = $_GET['remove-pactive'];
  $srv = $_GET['srv'];
  $prof = $_GET['profile'];
  $comm = $_GET['comment'];
  $serveractive = $_GET['server'];
  $report = $_GET['report'];
  $removereport = $_GET['remove-report'];
  $minterface = $_GET['interface'];


  $pagehotspot = array('users','hosts','ipbinding','cookies','log','dhcp-leases');
  $pageppp = array('secrets','profiles','active',);
  $pagereport = array('userlog','selling');

  include_once('./include/headhtml.php');

  include_once('./include/menu.php');

  $disable_sci = '<script>
  document.getElementById("comment").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,.~".indexOf(chr) >= 0)
        return false;
};
</script>';


// logout
  if ($hotspot == "logout") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";

    session_destroy();
    echo "<script>sessionStorage.clear();</script>";
    echo "<script>window.location='./admin.php?id=login'</script>";
  }
// redirect to home
  elseif (substr(explode("=", $url)[0],-9) == "/?session") {

    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";
  }

// redirect to home
  elseif ($hotspot == "dashboard") {
    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";

  }

// hotspot log
  elseif ($hotspot == "log") {
    include_once('./hotspot/log.php');
  }

// hotspot log
  elseif ($report == "userlog") {
    include_once('./report/userlog.php');
  }

// about
  elseif ($hotspot == "about") {
    include_once('./include/about.php');
  }

// bad request
  elseif (substr($url, -1) == "=") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Bad request! redirect to Home......</b>";

    echo "<script>window.location='./'</script>";
  }

// hotspot add users
  elseif ($hotspot == "add-user") {
    $_SESSION['hua'] = "";
    include_once('./hotspot/adduser.php');
  }

// hotspot users
  elseif ($hotspot == "users" && $prof == "all") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by profile
  elseif ($hotspot == "users" && $prof != "") {
    $_SESSION['ubp'] = $prof;
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by comment
  elseif ($hotspot == "users" && $comm != "") {
    $_SESSION['ubc'] = $comm;
    $_SESSION['hua'] = "";
    $_SESSION['ubp'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot by profile
  elseif ($hotspot == "users-by-profile") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "active";
    include_once('./hotspot/userbyprofile.php');
  }
// export hotspot users
  elseif ($hotspot == "export-users") {
    include_once('./hotspot/exportusers.php');
  }

// quick print
  elseif ($hotspot == "quick-print") {
    include_once('./hotspot/quickprint.php');
  }

// quick print
elseif ($hotspot == "list-quick-print") {
  include_once('./hotspot/listquickprint.php');
}  

// add hotspot user
  elseif ($hotspotuser == "add") {
    include_once('./hotspot/adduser.php');
    echo $disable_sci;
  }

// add hotspot user
  elseif ($hotspotuser == "generate") {
    include_once('./hotspot/generateuser.php');
    echo $disable_sci;
  }

// hotspot users filter by name
  elseif (substr($hotspotuser, 0, 1) == "*") {
    $_SESSION['ubn'] = $hotspotuser;
    $_SESSION['hua'] = "";
    include_once('./hotspot/userbyname.php');
  } elseif ($hotspotuser != "") {
    $_SESSION['ubn'] = $hotspotuser;
    include_once('./hotspot/userbyname.php');
  }

// remove hotspot user
  elseif ($removehotspotuser != "" || $removehotspotusers != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuser.php');
  }

// remove hotspot user by comment
  elseif ($removehotspotuserbycomment != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuserbycomment.php');
  }

// remove expired hotspot user
elseif ($removeexpiredhotspotuser != "") {
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

  include_once('./process/removeexpiredhotspotuser.php');
}  

// reset hotspot user
  elseif ($resethotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/resethotspotuser.php');
  }

// enable hotspot user
  elseif ($enablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/enablehotspotuser.php');
  }

// disable hotspot user
  elseif ($disablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/disablehotspotuser.php');
  }

// user profile
  elseif ($hotspot == "user-profiles") {
    include_once('./hotspot/userprofile.php');
  }

// add  user profile
  elseif ($userprofile == "add") {
    include_once('./hotspot/adduserprofile.php');
  }

// User profile by name
  elseif (substr($userprofile, 0, 1) == "*") {
    include_once('./hotspot/userprofilebyname.php');
  } elseif ($userprofile != "") {
    include_once('./hotspot/userprofilebyname.php');
  }


// remove user profile
  elseif ($removeuserprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuserprofile.php');
  }

// hotspot active
  elseif ($hotspot == "active") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "hotspotactive";
    $_SESSION['ubc'] = "";
    include_once('./hotspot/hotspotactive.php');
  }

// dhcp leases
  elseif ($hotspot == "dhcp-leases") {
    include_once('./dhcp/dhcpleases.php');
  }

// traffic monitor
  elseif ($minterface == "traffic-monitor") {
  include_once('./traffic/trafficmonitor.php');
}

// hotspot hosts
  elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta") {
    include_once('./hotspot/hosts.php');
  }

// hotspot bindings
  elseif ($hotspot == "binding") {
    include_once('./hotspot/binding.php');
  }

// template editor
  elseif ($hotspot == "template-editor") {
    include_once('./settings/vouchereditor.php');
  }

// upload logo
  elseif ($hotspot == "uplogo") {
    include_once('./settings/uplogo.php');
  }

// hotspot Cookies
  elseif ($hotspot == "cookies") {
    include_once('./hotspot/cookies.php');
  }

// remove hotspot Cookies
  elseif ($removecookie != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removecookie.php');
  }

// hotspot Ip Bindings
  elseif ($hotspot == "ipbinding") {
    include_once('./hotspot/ipbinding.php');
  }

// remove enable disable ipbinding
  elseif ($removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pipbinding.php');
  }


// remove user active
  elseif ($removeuseractive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuseractive.php');
  }

// remove host
  elseif ($removehost != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehost.php');
  }


// makebinding
  elseif ($macbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/makebinding.php');
  }

// selling
  elseif ($report == "selling") {
    include_once('./report/selling.php');
  }

// selling
elseif ($report == "resume-report") {
  include_once('./report/resumereport.php');
}

// selling
elseif ($report == "export") {
  include_once('./report/export.php');
}

// selling
  elseif ($removereport != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removereport.php');
  }

// ppp secret
  elseif ($ppp == "secrets") {
    include_once('./ppp/pppsecrets.php');
  }

// ppp addsecret
  elseif ($ppp == "addsecret") {
    include_once('./ppp/addsecret.php');
  }

// ppp secretbyname
  elseif ($secretbyname != "") {
    include_once('./ppp/secretbyname.php');
  }

// remove enable disable secret
  elseif ($removesecr != "" || $enablesecr != "" || $disablesecr != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/psecret.php');
  }


// ppp profile
  elseif ($ppp == "profiles") {
    include_once('./ppp/pppprofile.php');
  }

// add ppp profile
  elseif ($ppp == "add-profile") {
    include_once('./ppp/addpppprofile.php');
  }

// add ppp profile
elseif ($ppp == "edit-profile") {
  include_once('./ppp/profilebyname.php');
}
// remove enable disable profile
  elseif ($removepprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepprofile.php');
  }

// ppp active connection
  elseif ($ppp == "active") {
    include_once('./ppp/pppactive.php');
  }

// remove ppp active connection
  elseif ($removepactive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepactive.php');
  }

// sys scheduler
  elseif ($sys == "scheduler") {
    include_once('./system/scheduler.php');
  }
// remove enable disable scheduler
  elseif ($removesch != "" || $enablesch != "" || $disablesch != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pscheduler.php');
  }

  ?>

</div>
</div>
</div>
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= $theme; ?>.js"></script>
<script src="./js/mikhmon-ui.<?= $theme; ?>.min.js?t=<?= time(); ?>"></script>
<script src="./js/mikhmon.js?t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>

<script>
    function setSyncStatus(status) {
        var $badge = $("#sync-status-badge");
        if (!$badge.length) return;
        if (status === "connected") {
            $badge.html("<span class=\"status-dot connected\"></span> Connected").attr("class", "sync-status connected");
        } else if (status === "syncing") {
            $badge.html("<span class=\"status-dot syncing\"></span> Syncing...").attr("class", "sync-status syncing");
        } else if (status === "offline") {
            $badge.html("<span class=\"status-dot offline\"></span> Offline").attr("class", "sync-status offline");
        }
    }

    // Safe AJAX loader: only update DOM when response is valid & non-empty
    function safeLoad(targetSel, url, fragmentSel, callback) {
        setSyncStatus("syncing");
        
        // Simpan posisi scroll target, window, dan elemen scrollable di dalam target
        var scrolls = [];
        $(targetSel).find('*').each(function() {
            if (this.scrollTop > 0 || this.scrollLeft > 0) {
                var selector = '';
                if (this.id) {
                    selector = '#' + this.id;
                } else if (this.className) {
                    selector = '.' + $.trim(this.className).split(/\s+/).join('.');
                } else {
                    selector = this.tagName.toLowerCase();
                }
                scrolls.push({
                    selector: selector,
                    top: this.scrollTop,
                    left: this.scrollLeft
                });
            }
        });
        
        var targetTop = $(targetSel).scrollTop();
        var targetLeft = $(targetSel).scrollLeft();
        var windowTop = $(window).scrollTop();
        var windowLeft = $(window).scrollLeft();

        $.ajax({
            url: url,
            dataType: "html",
            timeout: 15000,
            success: function(data) {
                if (!data || $.trim(data).length === 0) {
                    setSyncStatus("offline");
                    return; // skip empty
                }
                var $resp;
                if (fragmentSel) {
                    $resp = $("<div>").append($.parseHTML(data)).find(fragmentSel);
                    if ($resp.length === 0) {
                        setSyncStatus("offline");
                        return; // fragment not found, skip
                    }
                    $(targetSel).html($resp.html());
                } else {
                    $(targetSel).html(data);
                }
                
                // Kembalikan posisi scroll target dan window
                $(targetSel).scrollTop(targetTop);
                $(targetSel).scrollLeft(targetLeft);
                $(window).scrollTop(windowTop);
                $(window).scrollLeft(windowLeft);
                
                // Kembalikan posisi scroll elemen-elemen di dalamnya (misal .overflow)
                scrolls.forEach(function(s) {
                    if (s.selector) {
                        $(targetSel).find(s.selector).scrollTop(s.top).scrollLeft(s.left);
                    }
                });

                if (typeof callback === "function") callback();
                setSyncStatus("connected");
            },
            error: function() {
                setSyncStatus("offline");
            }
        });
    }
</script>

<?php
if ($hotspot == "dashboard" || substr(end(explode("/", $url)), 0, 8) == "?session") {
  echo '<script>
    window.cpuHistory = window.cpuHistory || [];
    window.memHistory = window.memHistory || [];
    
    function drawSparkline(canvasId, data, color) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (data.length < 2) return;
        
        var max = Math.max.apply(Math, data);
        var min = Math.min.apply(Math, data);
        if (max === min) { max += 1; min -= 1; }
        var range = max - min;
        
        ctx.beginPath();
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.5;
        ctx.lineJoin = "round";
        
        for (var i = 0; i < data.length; i++) {
            var x = (i / (data.length - 1)) * canvas.width;
            var y = canvas.height - ((data[i] - min) / range) * (canvas.height - 4) - 2;
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();
    }
    
    function updateSparklines() {
        var cpuEl = document.getElementById("cpuVal");
        var memEl = document.getElementById("memVal");
        if (cpuEl) {
            var cpuVal = parseFloat(cpuEl.getAttribute("data-val") || 0);
            window.cpuHistory.push(cpuVal);
            if (window.cpuHistory.length > 15) window.cpuHistory.shift();
            drawSparkline("cpuSparkline", window.cpuHistory, "#818cf8");
        }
        if (memEl) {
            var memVal = parseFloat(memEl.getAttribute("data-val") || 0);
            window.memHistory.push(memVal);
            if (window.memHistory.length > 15) window.memHistory.shift();
            drawSparkline("memorySparkline", window.memHistory, "#34d399");
        }
    }




    var interval1 = "' . ($areload * 1000) . '";
    var interval2 = "65432";
    var dashboard = null;
    var livereport = null;

    function loadDashboardData() {
        setSyncStatus("syncing");
        $.ajax({
            url: "./dashboard/aload.php?session=' . $session . '&load=all&_t=" + new Date().getTime(),
            dataType: "html",
            timeout: 15000,
            success: function(data) {
                if (!data || $.trim(data).length === 0) {
                    setSyncStatus("offline");
                    return;
                }
                
                var $html = $("<div>").append($.parseHTML(data));
                
                var $r1 = $html.find("#r_1_inner");
                if ($r1.length > 0) {
                    $("#r_1").html($r1.html());
                }
                
                var $r2 = $html.find("#r_2");
                if ($r2.length > 0) {
                    $("#r_2").html($r2.html());
                }
                
                var $r3 = $html.find("#r_3");
                if ($r3.length > 0) {
                    $("#r_3").html($r3.html());
                }
                
                updateSparklines();
                setSyncStatus("connected");
            },
            error: function() {
                setSyncStatus("offline");
            }
        });
    }

    function startIntervals() {
        if (!dashboard) {
            dashboard = setInterval(loadDashboardData, interval1);
        }
        ';
        if ($livereport == "enable" || $livereport == "") {
            echo '
            if (!livereport) {
                livereport = setInterval(function() {
                    safeLoad("#r_4", "./report/livereport.php?session=' . $session . '&_t=" + new Date().getTime(), "#r_4_inner"); 
                }, interval2);
            }';
        }
    echo '
    }

    function stopIntervals() {
        if (dashboard) { clearInterval(dashboard); dashboard = null; }
        if (livereport) { clearInterval(livereport); livereport = null; }
    }

    // Initial load and start
    loadDashboardData();
    startIntervals();

    // Visibility Listener: pause when backgrounded
    document.addEventListener("visibilitychange", function() {
        if (document.hidden) {
            stopIntervals();
        } else {
            loadDashboardData();
            ';
            if ($livereport == "enable" || $livereport == "") {
                echo 'safeLoad("#r_4", "./report/livereport.php?session=' . $session . '&_t=" + new Date().getTime(), "#r_4_inner");';
            }
            echo '
            startIntervals();
        }
    });

';
if ($livereport == "enable" || $livereport == "") {
  if($_SESSION[$session.'sdate'] != $_SESSION[$session.'idhr']){
    $_SESSION[$session.'totalHr'] = "0";
    echo 'safeLoad("#r_4", "./report/livereport.php?session=' . $session . '&_t=" + new Date().getTime(), "#r_4_inner");';
    }else if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){  
    }else{
      echo 'safeLoad("#r_4", "./report/livereport.php?session=' . $session . '&_t=" + new Date().getTime(), "#r_4_inner");';
    }
}
echo ' 
  function cancelPage(){
    window.stop();
    stopIntervals();
  }
</script>';

} elseif ($hotspot == "active" && $serveractive != "") {
  echo '<script>
  $(document).ready(function(){
    var interval = "' . ($areload * 1000) . '";
    setInterval(function() {
    safeLoad("#reloadHotspotActive", "./hotspot/hotspotactive.php?server=' . $serveractive . '&session=' . $session . '&_t=" + new Date().getTime(), "#reloadHotspotActive"); }, interval);})
</script>
';
} elseif ($hotspot == "active" && $serveractive == "") {
  echo '<script>
  $(document).ready(function(){
    var interval = "' . ($areload * 1000) . '";
    setInterval(function() {
    safeLoad("#reloadHotspotActive", "./hotspot/hotspotactive.php?session=' . $session . '&_t=" + new Date().getTime(), "#reloadHotspotActive"); }, interval);})
</script>
';
} elseif ($userprofile == "add" || substr($userprofile, 0, 1) == "*" || $userprofile != "") {
  echo "<script>
  //enable disable input on ready
$(document).ready(function(){
    var exp = document.getElementById('expmode').value;
    var val = document.getElementById('validity').style;
    var vali = document.getElementById('validi');
    if (exp === 'rem' || exp === 'remc') {
      val.display= 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else if (exp === 'ntf' || exp === 'ntfc') {
      val.display = 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else {
      val.display = 'none';
      vali.type = 'hidden';
    }
});
</script>";

} elseif (in_array($hotspot, $pagehotspot) || in_array($ppp, $pageppp) || in_array($report, $pagereport) || $sys == "scheduler") {
echo '
<script>
$(document).ready(function(){
  makeAllSortable();
  $("#filterTable").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dataTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});

</script>
';
}
}
?>
</body>
</html>

