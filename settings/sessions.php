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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  if (isset($_POST['save'])) {

    $suseradm = ($_POST['useradm']);
    $spassadm = ($_POST['passadm']);
    $qrbt = ($_POST['qrbt']);

    $dbSettings = new \App\Models\AppSettings();
    if (!empty($spassadm)) {
        $spassadm_hash = password_hash($spassadm, PASSWORD_BCRYPT);
        $dbSettings->saveAdminCredentials($suseradm, $spassadm_hash);
    } else {
        $adminCreds = $dbSettings->getAdminCredentials();
        $dbSettings->saveAdminCredentials($suseradm, $adminCreds['password_hash']);
    }
    $dbSettings->set('quick_print_qr', $qrbt);
  
    $gen = '<?php $qrbt="' . $qrbt . '";?>';
    $key = './include/quickbt.php';
    $handle = fopen($key, 'w') or die('Cannot open file:  ' . $key);
    $data = $gen;
    fwrite($handle, $data);
    fclose($handle);
    echo "<script>window.location='./admin.php?id=sessions'</script>";
  }

}
?>
<script>
  function Pass(id){
    var x = document.getElementById(id);
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
</script>

<div class="row">
	<div class="col-12">
  	<div class="card">
  		<div class="card-header">
  			<h3 class="card-title"><i class="fa fa-gear"></i> <?= $_admin_settings ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
  		</div>
      <div class="card-body">
        <div class="row">
          <div class="col-6">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-server"></i> <?= $_router_list ?></h3>
              </div>
            <div class="card-body">
            <div class="row">
              <?php
              $dbSessions = new \App\Models\RouterSession();
              $sessionsList = $dbSessions->getAll();
              foreach ($sessionsList as $sessionData) {
                $value = $sessionData['session_name'];
                ?>
                    <div class="col-12">
                        <div class="box box-bordered session-card" data-session="<?= $value; ?>">
                            <div class="box-group">
                              
                              <div class="box-group-icon">
                                <span class="connect pointer" id="<?= $value; ?>">
                                  <i class="fa fa-server"></i>
                                </span>
                              </div>
                            
                              <div class="box-group-area">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <strong style="font-size: 15px; color: var(--text-main);"><?= explode('%', $data[$value][4])[1] ?: $value; ?></strong>
                                    <span class="status-indicator" style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: bold; color: var(--text-muted);">
                                        <span class="dot-pulse" style="width: 8px; height: 8px; border-radius: 50%; background: #9CA3AF; display: inline-block;"></span>
                                        <span class="status-text">Checking...</span>
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-align: left;">
                                  Sesi: <span style="font-family: monospace; font-weight: bold;"><?= $value; ?></span>
                                </div>
                                
                                <!-- Metrics grid -->
                                <div class="session-metrics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                                    <div style="font-size: 11px; text-align: center;">
                                        <span style="display: block; color: var(--text-muted); margin-bottom: 2px;">CPU Load</span>
                                        <strong class="metric-cpu" style="color: var(--text-main);">-</strong>
                                    </div>
                                    <div style="font-size: 11px; text-align: center; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color);">
                                        <span style="display: block; color: var(--text-muted); margin-bottom: 2px;">Active Users</span>
                                        <strong class="metric-active" style="color: var(--text-main);">-</strong>
                                    </div>
                                    <div style="font-size: 11px; text-align: center;">
                                        <span style="display: block; color: var(--text-muted); margin-bottom: 2px;">Uptime</span>
                                        <strong class="metric-uptime" style="color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">-</strong>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 12px; font-size: 12px; font-weight: bold; justify-content: flex-start;">
                                  <span class="connect pointer" id="<?= $value; ?>"><i class="fa fa-external-link"></i> <?= $_open ?></span>
                                  <a href="./admin.php?id=settings&session=<?= $value; ?>"><i class="fa fa-edit"></i> <?= $_edit ?></a>
                                  <a href="javascript:void(0)" onclick="if(confirm('Are you sure to delete data <?= $value; ?>?')){loadpage('./admin.php?id=remove-session&session=<?= $value; ?>')}"><i class="fa fa-remove"></i> <?= $_delete ?></a>
                                </div>
                              </div>
                            </div>
                        </div>
                    </div>
              <?php
              }
          ?>
              </div>
            </div>
          </div>
        </div>
			    <div class="col-6">
          <form autocomplete="off" method="post" action="">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-user-circle"></i> <?= $_admin ?></h3>
              </div>
            <div class="card-body">
            <style>
            .form-group-floating {
                position: relative;
                margin-bottom: 16px;
                width: 100%;
            }
            .form-group-floating .form-control {
                width: 100% !important;
                height: 48px !important;
                padding: 18px 16px 6px 16px !important;
                font-size: 14px !important;
                border: 1px solid var(--border-color, #c1c1c1) !important;
                border-radius: 8px !important;
                background: var(--card-bg, #fff) !important;
                color: var(--text-main, #3E3E3E) !important;
                outline: none !important;
                transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
                box-sizing: border-box !important;
            }
            .form-group-floating .form-control::placeholder {
                color: transparent !important;
                opacity: 0 !important;
            }
            .form-group-floating label {
                position: absolute;
                left: 16px;
                top: 14px;
                font-size: 14px;
                color: var(--text-muted, #73818f);
                pointer-events: none;
                transition: all 0.2s ease;
                margin: 0;
            }
            .form-group-floating .form-control:focus ~ label,
            .form-group-floating .form-control:not(:placeholder-shown) ~ label {
                top: 4px;
                left: 16px;
                font-size: 11px;
                color: var(--primary, #008BC9);
                font-weight: 600;
            }
            .form-group-floating .form-control:focus {
                border-color: var(--primary, #008BC9) !important;
                box-shadow: 0 0 0 3px rgba(0, 139, 201, 0.15) !important;
            }
            .btn-modern-save {
                height: 40px;
                border-radius: 8px !important;
                padding: 0 20px !important;
                font-weight: 600 !important;
                background: var(--primary, #008BC9) !important;
                color: #fff !important;
                border: none !important;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            .btn-modern-save:hover {
                background: var(--primary-hover, #007bb0) !important;
            }
            .box.box-bordered {
                background: var(--bg-card) !important;
                border: 1px solid var(--border-color) !important;
                border-radius: 12px !important;
                padding: 18px 20px !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: var(--shadow-card) !important;
                height: auto !important;
                box-sizing: border-box;
                margin: 8px 0 !important;
            }
            .box.box-bordered:hover {
                border-color: var(--border-hover) !important;
                box-shadow: 0px 8px 24px rgba(0, 0, 0, 0.08) !important;
                transform: translateY(-2px);
            }
            .box-group {
                display: flex !important;
                align-items: center !important;
                gap: 16px !important;
                height: 100% !important;
            }
            .box-group-icon {
                font-size: 16px !important;
                width: 40px !important;
                height: 40px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
                margin: 0 !important;
                float: none !important;
                background: var(--primary-glow) !important;
                color: var(--primary) !important;
                transition: all 0.3s ease;
            }
            .box-group-icon span {
                color: inherit !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100%;
                height: 100%;
            }
            .box-group-area {
                flex: 1;
                padding: 0 !important;
                margin: 0 !important;
                text-align: left !important;
                font-size: 13px !important;
                color: var(--text-main) !important;
            }
            .box-group-area span {
                color: inherit !important;
            }
            .box-group-area a, .box-group-area .connect {
                font-size: 12px !important;
                font-weight: 600 !important;
                color: var(--primary) !important;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                margin-top: 6px;
            }
            .box-group-area a:hover, .box-group-area .connect:hover {
                color: var(--primary-hover-text) !important;
            }
            </style>
            <div style="padding: 10px 0;">
              <div class="form-group-floating">
                <input class="form-control" id="useradm" type="text" name="useradm" placeholder=" " value="<?= $useradm; ?>" required="1"/>
                <label for="useradm"><?= $_user_name ?></label>
              </div>
              
              <div class="form-group-floating">
                <div class="input-group" style="display: flex; width: 100%;">
                  <div class="input-group-11 col-box-10" style="flex: 1; position: relative; float: none;">
                    <input class="group-item form-control" id="passadm" type="password" name="passadm" placeholder=" " style="border-radius: 8px 0 0 8px !important; width: 100% !important; height: 48px !important; border: 1px solid var(--border-color, #c1c1c1) !important; border-right: none !important; padding: 18px 16px 6px 16px !important;"/>
                    <label for="passadm"><?= $_password ?> <?= ($langid == 'id') ? '(Kosongkan jika tidak diubah)' : '(Leave blank if unchanged)' ?></label>
                  </div>
                  <div class="input-group-1 col-box-2" style="width: 48px; float: none;">
                    <div class="group-item group-item-r pd-2p5 text-center align-middle" style="border-radius: 0 8px 8px 0 !important; height: 48px; border: 1px solid var(--border-color, #c1c1c1); background: var(--background-alt, #F5F6F7); display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
                      <input title="Show/Hide Password" type="checkbox" onclick="Pass('passadm')">
                    </div>
                  </div>
                </div>
              </div>

              <div style="margin-bottom: 20px;">
                <label style="font-size: 12px; color: var(--text-muted, #73818f); display: block; margin-bottom: 6px; font-weight: 600; text-align: left;"><?= $_quick_print ?> QR</label>
                <select class="form-control" name="qrbt" style="height: 48px !important; border-radius: 8px !important; border: 1px solid var(--border-color, #c1c1c1) !important; background: var(--card-bg, #fff) !important; color: var(--text-main, #3E3E3E) !important; padding: 0 16px !important; width: 100% !important;">
                  <option value="<?= $qrbt ?>"><?= $qrbt ?></option>
                  <option value="enable">enable</option>
                  <option value="disable">disable</option>
                </select>
              </div>

              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
                <div id="loadV" style="font-size: 12px; color: var(--text-muted, #73818f);">v<?= $_SESSION['v']; ?> </div>
                <div style="display: flex; gap: 8px;">
                  <button type="submit" name="save" class="btn-modern-save"><?= $_save ?></button>
                  <div style="cursor: pointer; height: 40px; width: 40px; border-radius: 8px; border: 1px solid var(--border-color, #c1c1c1); display: flex; align-items: center; justify-content: center; background: var(--card-bg, #fff);" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
                </div>
              </div>
              <div><b id="newVer" class="text-green"></b></div>
            </div>
          </div>
    </div>
    </form>
  </div>
</div>
</div>
</div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Perform status checking for each session card
    document.querySelectorAll('.session-card').forEach(function(card) {
        var sessionName = card.getAttribute('data-session');
        var dot = card.querySelector('.dot-pulse');
        var statusText = card.querySelector('.status-text');
        var metricCpu = card.querySelector('.metric-cpu');
        var metricActive = card.querySelector('.metric-active');
        var metricUptime = card.querySelector('.metric-uptime');
        
        // Add pulse animation class to dot during loading
        dot.style.animation = "pulse-dot-checking 1.2s infinite ease-in-out";
        
        fetch('./dashboard/session_status.php?session=' + encodeURIComponent(sessionName))
            .then(response => response.json())
            .then(data => {
                dot.style.animation = "none";
                if (data.status === "online") {
                    dot.style.background = "#10B981"; // Emerald green
                    statusText.innerText = "Online";
                    statusText.style.color = "#10B981";
                    
                    metricCpu.innerText = data.cpu;
                    metricActive.innerText = data.active_users + " / " + data.total_users;
                    metricUptime.innerText = data.uptime;
                    metricUptime.title = data.uptime;
                } else {
                    dot.style.background = "#EF4444"; // Red
                    statusText.innerText = "Offline";
                    statusText.style.color = "#EF4444";
                    
                    metricCpu.innerText = "Offline";
                    metricActive.innerText = "Offline";
                    metricUptime.innerText = "Offline";
                }
            })
            .catch(err => {
                dot.style.animation = "none";
                dot.style.background = "#EF4444";
                statusText.innerText = "Offline";
                statusText.style.color = "#EF4444";
                
                metricCpu.innerText = "Error";
                metricActive.innerText = "Error";
                metricUptime.innerText = "Error";
            });
    });
});
</script>
<style>
@keyframes pulse-dot-checking {
    0% { transform: scale(0.85); opacity: 0.5; }
    50% { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(0.85); opacity: 0.5; }
}
</style>









