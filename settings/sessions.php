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
  	<div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
  		<div class="card-header">
  			<h3 class="card-title"><i class="fa fa-gear"></i> <?= $_admin_settings ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
  		</div>
      <div class="card-body" style="padding: 24px !important;">
        <div class="settings-row-flex">
          <div class="settings-col-flex">
            <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important;">
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
                        <div class="box box-bordered session-card" data-session="<?= $value; ?>" style="padding: 24px !important; border-radius: 16px !important; border: 1px solid var(--border-color) !important; background: var(--bg-card, #ffffff) !important; box-shadow: 0 4px 18px rgba(0, 0, 0, 0.02) !important;">
                            <div class="box-group" style="display: flex; gap: 20px; align-items: flex-start;">
                              
                              <div class="box-group-icon" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-glow) 100%) !important; color: #ffffff !important; width: 48px !important; height: 48px !important; border-radius: 12px !important; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0, 139, 201, 0.15) !important;">
                                <span class="connect pointer" id="<?= $value; ?>" style="color: #ffffff !important;">
                                  <i class="fa fa-server" style="font-size: 20px;"></i>
                                </span>
                              </div>
                            
                              <div class="box-group-area" style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                    <strong style="font-size: 16px; color: var(--text-main); font-weight: 800;"><?= explode('%', $data[$value][4])[1] ?: $value; ?></strong>
                                    
                                    <span class="status-indicator-pill" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; background: rgba(156, 163, 175, 0.08); color: #6b7280; transition: all 0.3s ease;">
                                        <span class="dot-pulse" style="width: 7px; height: 7px; border-radius: 50%; background: #9CA3AF; display: inline-block;"></span>
                                        <span class="status-text">Checking</span>
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px; text-align: left;">
                                  Sesi: <span style="font-family: monospace; font-weight: bold; background: rgba(148, 163, 184, 0.08); padding: 2px 6px; border-radius: 6px; color: var(--text-main);"><?= $value; ?></span>
                                </div>
                                
                                <!-- Metrics grid as mini cards -->
                                <div class="session-metrics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px;">
                                    <div style="font-size: 11px; text-align: center; background: rgba(148, 163, 184, 0.04); border: 1px solid var(--border-color); padding: 10px 6px; border-radius: 10px;">
                                        <span style="display: block; color: var(--text-muted); margin-bottom: 4px; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">CPU Load</span>
                                        <strong class="metric-cpu" style="color: var(--text-main); font-size: 13px;">-</strong>
                                    </div>
                                    <div style="font-size: 11px; text-align: center; background: rgba(148, 163, 184, 0.04); border: 1px solid var(--border-color); padding: 10px 6px; border-radius: 10px;">
                                        <span style="display: block; color: var(--text-muted); margin-bottom: 4px; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Active Users</span>
                                        <strong class="metric-active" style="color: var(--text-main); font-size: 13px;">-</strong>
                                    </div>
                                    <div style="font-size: 11px; text-align: center; background: rgba(148, 163, 184, 0.04); border: 1px solid var(--border-color); padding: 10px 6px; border-radius: 10px;">
                                        <span style="display: block; color: var(--text-muted); margin-bottom: 4px; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Uptime</span>
                                        <strong class="metric-uptime" style="color: var(--text-main); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">-</strong>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; font-size: 12px; font-weight: bold; text-align: center;">
                                  <div>
                                    <span class="connect pointer action-link-btn btn-open" id="<?= $value; ?>" style="width: 100%;"><i class="fa fa-external-link"></i> <?= $_open ?></span>
                                  </div>
                                  <div>
                                    <a class="action-link-btn btn-edit" href="./admin.php?id=settings&session=<?= $value; ?>" style="width: 100%;"><i class="fa fa-edit"></i> <?= $_edit ?></a>
                                  </div>
                                  <div>
                                    <a class="action-link-btn btn-delete" href="javascript:void(0)" onclick="if(confirm('Are you sure to delete data <?= $value; ?>?')){loadpage('./admin.php?id=remove-session&session=<?= $value; ?>')}" style="width: 100%;"><i class="fa fa-remove"></i> <?= $_delete ?></a>
                                  </div>
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
        <div class="settings-col-flex">
          <form autocomplete="off" method="post" action="" style="display: flex; flex-direction: column; flex: 1;">
            <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important; height: 100%; display: flex; flex-direction: column;">
              <div class="card-header" style="padding: 14px 20px !important;">
                <h3 class="card-title"><i class="fa fa-user-circle"></i> <?= $_admin ?></h3>
              </div>
            <div class="card-body" style="padding: 24px !important; flex: 1;">
            <style>
            .settings-row-flex {
                display: flex !important;
                gap: 24px !important;
                width: 100% !important;
                flex-wrap: wrap !important;
                box-sizing: border-box !important;
            }
            .settings-col-flex {
                flex: 1 1 calc(50% - 12px) !important;
                min-width: 320px !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 20px !important;
            }
            
            /* Form Styles matching premium theme */
            .form-field-group {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
                margin-bottom: 18px !important;
                width: 100% !important;
                box-sizing: border-box;
            }
            .form-field-group label {
                font-size: 13px !important;
                font-weight: 700 !important;
                color: var(--text-muted) !important;
                text-align: left !important;
            }
            .form-field-group input.form-control,
            .form-field-group select.form-control {
                height: 48px !important;
                border: 1px solid var(--border-color) !important;
                border-radius: 12px !important;
                background: var(--bg-card, #ffffff) !important;
                color: var(--text-main) !important;
                padding: 0 16px !important;
                font-size: 14px !important;
                outline: none !important;
                transition: all 0.25s ease !important;
                box-sizing: border-box !important;
                width: 100% !important;
            }
            .form-field-group input.form-control:focus,
            .form-field-group select.form-control:focus {
                border-color: var(--primary) !important;
                box-shadow: 0 0 0 3.5px var(--primary-glow) !important;
            }
            
            /* Password Toggle Wrapper */
            .modern-password-wrapper {
                position: relative !important;
                width: 100% !important;
                display: flex !important;
                align-items: center !important;
                box-sizing: border-box;
            }
            .modern-password-wrapper input {
                padding-right: 48px !important;
            }
            .password-toggle-btn {
                position: absolute !important;
                right: 4px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                background: transparent !important;
                border: none !important;
                color: var(--text-muted) !important;
                width: 40px !important;
                height: 40px !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 50% !important;
                transition: all 0.2s !important;
            }
            .password-toggle-btn:hover {
                color: var(--primary) !important;
                background: var(--primary-glow) !important;
            }

            /* Buttons style */
            .btn-modern-action {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                height: 40px !important;
                padding: 0 16px !important;
                border-radius: 10px !important;
                font-size: 13px !important;
                font-weight: 700 !important;
                gap: 8px !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                border: 1px solid transparent !important;
                cursor: pointer !important;
                text-decoration: none !important;
            }
            .btn-modern-action.btn-save {
                background: var(--primary) !important;
                color: #ffffff !important;
                box-shadow: 0 4px 12px rgba(0, 139, 201, 0.2) !important;
            }
            .btn-modern-action.btn-save:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(0, 139, 201, 0.3) !important;
            }
            .btn-modern-action.btn-reload {
                background: rgba(148, 163, 184, 0.08) !important;
                color: #475569 !important;
                border-color: rgba(148, 163, 184, 0.15) !important;
            }
            .btn-modern-action.btn-reload:hover {
                background: rgba(148, 163, 184, 0.15) !important;
            }

            /* Action Buttons inside Session Card */
            .action-link-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 6px 12px !important;
                border-radius: 8px !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                gap: 6px !important;
                text-decoration: none !important;
                transition: all 0.2s ease !important;
                border: 1px solid transparent !important;
            }
            .action-link-btn.btn-open {
                background: rgba(16, 185, 129, 0.08) !important;
                color: #10b981 !important;
                border-color: rgba(16, 185, 129, 0.15) !important;
            }
            .action-link-btn.btn-open:hover {
                background: #10b981 !important;
                color: #ffffff !important;
            }
            .action-link-btn.btn-edit {
                background: rgba(14, 165, 233, 0.08) !important;
                color: #0ea5e9 !important;
                border-color: rgba(14, 165, 233, 0.15) !important;
            }
            .action-link-btn.btn-edit:hover {
                background: #0ea5e9 !important;
                color: #ffffff !important;
            }
            .action-link-btn.btn-delete {
                background: rgba(239, 68, 68, 0.08) !important;
                color: #ef4444 !important;
                border-color: rgba(239, 68, 68, 0.15) !important;
            }
            .action-link-btn.btn-delete:hover {
                background: #ef4444 !important;
                color: #ffffff !important;
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
            </style>
            
            <div style="padding: 10px 0;">
              
              <div class="form-field-group">
                <label for="useradm"><?= $_user_name ?></label>
                <input class="form-control" id="useradm" type="text" name="useradm" placeholder="Username Admin" value="<?= $useradm; ?>" required="1"/>
              </div>
              
              <div class="form-field-group">
                <label for="passadm"><?= $_password ?> <?= ($langid == 'id') ? '(Kosongkan jika tidak diubah)' : '(Leave blank if unchanged)' ?></label>
                <div class="modern-password-wrapper">
                  <input class="form-control" id="passadm" type="password" name="passadm" placeholder="Password Admin baru"/>
                  <button type="button" class="password-toggle-btn" onclick="togglePass();" title="Tampilkan Password">
                    <i id="pass-icon" class="fa fa-eye"></i>
                  </button>
                </div>
              </div>

              <div class="form-field-group">
                <label for="qrbt"><?= $_quick_print ?> QR</label>
                <select class="form-control" name="qrbt" id="qrbt">
                  <option value="<?= $qrbt ?>"><?= $qrbt ?></option>
                  <option value="enable">enable</option>
                  <option value="disable">disable</option>
                </select>
              </div>

              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
                <div id="loadV" style="font-size: 12px; color: var(--text-muted);">v<?= $_SESSION['v']; ?> </div>
                <div style="display: flex; gap: 8px;">
                  <button type="submit" name="save" class="btn-modern-action btn-save"><?= $_save ?></button>
                  <div class="btn-modern-action btn-reload pointer" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
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
        var pill = card.querySelector('.status-indicator-pill');
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
                    pill.style.background = "rgba(16, 185, 129, 0.08)";
                    pill.style.color = "#10B981";
                    dot.style.background = "#10B981";
                    statusText.innerText = "Online";
                    
                    metricCpu.innerText = data.cpu;
                    metricActive.innerText = data.active_users + " / " + data.total_users;
                    metricUptime.innerText = data.uptime;
                    metricUptime.title = data.uptime;
                } else {
                    pill.style.background = "rgba(239, 68, 68, 0.08)";
                    pill.style.color = "#EF4444";
                    dot.style.background = "#EF4444";
                    statusText.innerText = "Offline";
                    
                    metricCpu.innerText = "Offline";
                    metricActive.innerText = "Offline";
                    metricUptime.innerText = "Offline";
                }
            })
            .catch(err => {
                dot.style.animation = "none";
                pill.style.background = "rgba(239, 68, 68, 0.08)";
                pill.style.color = "#EF4444";
                dot.style.background = "#EF4444";
                statusText.innerText = "Offline";
                
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









