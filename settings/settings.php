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

  if ($id == "settings" && explode("-",$router)[0] == "new") {
    echo "<script>window.location='./admin.php?id=settings&session=" . $router . "'</script>";
    exit;
  }

  // Initialize default form values for new sessions
  if (explode("-", $session)[0] == "new") {
    $iphost = '';
    $userhost = '';
    $passwdhost = '';
    $hotspotname = '';
    $dnsname = '';
    $currency = 'Rp';
    $areload = 10;
    $iface = '1';
    $idleto = '10';
    $livereport = 'disable';
  }

  if (isset($_POST['save'])) {

    $siphost = (preg_replace('/\s+/', '', $_POST['ipmik']));
    $suserhost = ($_POST['usermik']);
    $spasswdhost = encrypt($_POST['passmik']);
    $shotspotname = str_replace("'","",$_POST['hotspotname']);
    $sdnsname = ($_POST['dnsname']);
    $scurrency = ($_POST['currency']);
    $sreload = ($_POST['areload']);
    if ($sreload < 10) {
      $sreload = 10;
    } else {
      $sreload = $sreload;
    }
    $siface = ($_POST['iface']);
    $sidleto = ($_POST['idleto']);

    $sesname = (preg_replace('/\s+/', '-', $_POST['sessname']));
    $slivereport = ($_POST['livereport']);

    $dbSessions = new \App\Models\RouterSession();
    
    // Jika nama sesi diubah, hapus sesi lama dari database
    if ($session !== $sesname) {
      $dbSessions->delete($session);
    }
    
    $dbSessions->save(array(
      'session_name' => $sesname,
      'ip_address' => $siphost,
      'username' => $suserhost,
      'password' => $spasswdhost,
      'hotspot_name' => $shotspotname,
      'dns_name' => $sdnsname,
      'currency' => $scurrency,
      'auto_reload' => $sreload,
      'traffic_interface' => $siface,
      'idle_timeout' => $sidleto,
      'live_report' => $slivereport
    ));
    
    $_SESSION["connect"] = "";
    echo "<script>window.location='./admin.php?id=settings&session=" . $sesname . "'</script>";
    exit;
  }
  if ($currency == "") {
    $currency = "Rp";
  }
  
  // Sanitize variables for HTML attributes
  $s_session = htmlspecialchars($session, ENT_QUOTES, 'UTF-8');
  $s_iphost = htmlspecialchars($iphost, ENT_QUOTES, 'UTF-8');
  $s_userhost = htmlspecialchars($userhost, ENT_QUOTES, 'UTF-8');
  $s_passwdhost = htmlspecialchars(decrypt($passwdhost), ENT_QUOTES, 'UTF-8');
  $s_hotspotname = htmlspecialchars($hotspotname, ENT_QUOTES, 'UTF-8');
  $s_dnsname = htmlspecialchars($dnsname, ENT_QUOTES, 'UTF-8');
  $s_currency = htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
  $s_areload = htmlspecialchars($areload, ENT_QUOTES, 'UTF-8');
  $s_idleto = htmlspecialchars($idleto, ENT_QUOTES, 'UTF-8');
  $s_iface = htmlspecialchars($iface, ENT_QUOTES, 'UTF-8');
  $s_livereport = htmlspecialchars($livereport, ENT_QUOTES, 'UTF-8');
}
?>
<script>
  function togglePass() {
    var x = document.getElementById('passmk');
    var icon = document.getElementById('pass-icon');
    if (x.type === 'password') {
      x.type = 'text';
      icon.className = 'fa fa-eye-slash';
    } else {
      x.type = 'password';
      icon.className = 'fa fa-eye';
    }
  }
</script>

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

/* Modern Input Groups */
.modern-input-group-wrapper {
    display: flex !important;
    width: 100% !important;
    box-sizing: border-box;
}
.modern-input-group-wrapper input.form-control {
    border-radius: 12px 0 0 12px !important;
    border-right: none !important;
    flex: 1 !important;
}
.modern-input-group-wrapper input.form-control:focus {
    border-right: 1px solid var(--primary) !important;
}
.modern-input-group-wrapper select.form-control {
    border-radius: 12px 0 0 12px !important;
    border-right: none !important;
    flex: 1 !important;
}
.modern-input-group-wrapper select.form-control:focus {
    border-right: 1px solid var(--primary) !important;
}
.modern-input-group-addon {
    border-radius: 0 12px 12px 0 !important;
    border: 1px solid var(--border-color) !important;
    background: var(--background-alt, #F5F6F7) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 60px !important;
    height: 48px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    color: var(--text-muted) !important;
    box-sizing: border-box !important;
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
.btn-modern-action.btn-connect {
    background: rgba(16, 185, 129, 0.08) !important;
    color: #10b981 !important;
    border-color: rgba(16, 185, 129, 0.15) !important;
}
.btn-modern-action.btn-connect:hover {
    background: #10b981 !important;
    color: #ffffff !important;
}
.btn-modern-action.btn-ping {
    background: rgba(245, 158, 11, 0.08) !important;
    color: #f59e0b !important;
    border-color: rgba(245, 158, 11, 0.15) !important;
}
.btn-modern-action.btn-ping:hover {
    background: #f59e0b !important;
    color: #ffffff !important;
}
.btn-modern-action.btn-reload {
    background: rgba(148, 163, 184, 0.08) !important;
    color: #475569 !important;
    border-color: rgba(148, 163, 184, 0.15) !important;
}
.btn-modern-action.btn-reload:hover {
    background: rgba(148, 163, 184, 0.15) !important;
}
</style>

<form autocomplete="off" method="post" action="" name="settings">  
<div class="row">
	<div class="col-12">
  		<div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
  			<div class="card-header" style="padding: 16px 24px !important;">
  				<h3 class="card-title" style="margin: 0;"><i class="fa fa-gear"></i> <?= $_session_settings ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
  			</div>
        <div class="card-body" style="padding: 24px !important;">
    	   <div class="settings-row-flex">
			     <div class="settings-col-flex">
              
              <!-- Session Card -->
              <div class="card" style="border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); margin: 0 !important; overflow: hidden;">
                <div class="card-header" style="border-bottom: 1px solid var(--border-color); background: transparent; padding: 14px 20px !important;">
                  <h3 class="card-title"><?= $_session ?></h3>
                </div>
                <div class="card-body" style="padding: 20px !important;">
                  <div class="form-field-group" style="margin-bottom: 0 !important;">
                    <label for="sessname"><?= $_session_name ?></label>
                    <input class="form-control" id="sessname" type="text" name="sessname" placeholder="Masukkan nama sesi" value="<?php if (explode("-",$session)[0] == "new") { echo ""; } else { echo $s_session; } ?>" required="1"/>
                  </div>
                </div>
              </div>
              
              <!-- MikroTik Connection Card -->
				      <div class="card" style="border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); margin: 0 !important; overflow: hidden;">
        	     <div class="card-header" style="border-bottom: 1px solid var(--border-color); background: transparent; padding: 14px 20px !important;">
            	   <h3 class="card-title">MikroTik <?= $_SESSION["connect"]; ?></h3>
        	     </div>
        	     <div class="card-body" style="padding: 20px !important;">
                   
                   <div class="form-field-group">
                     <label for="ipmik">IP MikroTik / IP Cloud</label>
                     <input class="form-control" id="ipmik" type="text" name="ipmik" placeholder="Contoh: 192.168.1.1 atau cloud.mikrotik.com" value="<?= $s_iphost; ?>" required="1"/>
                   </div>
                   
                   <div class="form-field-group">
                     <label for="usermk">Username</label>
                     <input class="form-control" id="usermk" type="text" name="usermik" placeholder="Username Mikrotik" value="<?= $s_userhost; ?>" required="1"/>
                   </div>

                   <div class="form-field-group">
                     <label for="passmk">Password</label>
                     <div class="modern-password-wrapper">
                       <input class="form-control" id="passmk" type="password" name="passmik" placeholder="Password Mikrotik" value="<?= $s_passwdhost; ?>" required="1"/>
                       <button type="button" class="password-toggle-btn" onclick="togglePass();" title="Tampilkan Password">
                         <i id="pass-icon" class="fa fa-eye"></i>
                       </button>
                     </div>
                   </div>

                   <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; flex-wrap: wrap;">
                     <button type="submit" name="save" class="btn-modern-action btn-save"><i class="fa fa-save"></i> Save</button>
                     <span class="connect pointer btn-modern-action btn-connect" id="<?= $session; ?>&c=settings"><i class="fa fa-link"></i> Connect</span>
                     <span class="pointer btn-modern-action btn-ping" id="ping_test"><i class="fa fa-bolt"></i> Ping</span>
                     <div class="btn-modern-action btn-reload pointer" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
                   </div>
                 
			     </div>
          </div>  	
          <div id="ping">
          </div>	
        </div>
        
        <div class="settings-col-flex">
          
          <!-- Mikhmon Data Card -->
          <div class="card" style="border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); margin: 0 !important; overflow: hidden;">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); background: transparent; padding: 14px 20px !important;">
                <h3 class="card-title">Mikhmon Data</h3>
            </div>
            <div class="card-body" style="padding: 20px !important;">    
              
              <div class="form-field-group">
                <label for="hotspotname"><?= $_hotspot_name ?></label>
                <input class="form-control" type="text" name="hotspotname" placeholder="Nama Hotspot Anda" value="<?= $s_hotspotname; ?>" required="1" id="hotspotname"/>
              </div>

              <div class="form-field-group">
                <label for="dnsname"><?= $_dns_name ?></label>
                <input class="form-control" type="text" name="dnsname" placeholder="Contoh: mywifi.id" value="<?= $s_dnsname; ?>" required="1" id="dnsname"/>
              </div>

              <div class="form-field-group">
                <label for="currency"><?= $_currency ?></label>
                <input class="form-control" type="text" name="currency" placeholder="Mata uang (Contoh: Rp)" value="<?= $s_currency; ?>" required="1" id="currency"/>
              </div>

              <div class="form-field-group">
                <label for="areload"><?= $_auto_reload ?></label>
                <div class="modern-input-group-wrapper">
                  <input class="form-control" type="number" min="10" max="3600" name="areload" placeholder="10" value="<?= $s_areload; ?>" required="1" id="areload"/>
                  <span class="modern-input-group-addon"><?= $_sec ?></span>
                </div>
              </div>

              <div class="form-field-group">
                <label for="idleto"><?= $_idle_timeout ?></label>
                <div class="modern-input-group-wrapper">
                  <select class="form-control" name="idleto" required="1" id="idleto">
                    <option value="<?= $s_idleto; ?>"><?= $s_idleto; ?></option>
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="30">30</option>
                    <option value="60">60</option>
                    <option value="disable">disable</option>
                  </select>
                  <span class="modern-input-group-addon"><?= $_min ?></span>
                </div>
              </div>

              <div class="form-field-group">
                <label for="iface"><?= $_traffic_interface ?></label>
                <input class="form-control" type="number" min="1" max="99" name="iface" placeholder="1" value="<?= $s_iface; ?>" required="1" id="iface"/>
              </div>

              <?php if (!empty($livereport)): ?>
              <div class="form-field-group" style="margin-bottom: 0 !important;">
                <label for="livereport"><?= $_live_report ?></label>
                <select class="form-control" name="livereport" id="livereport">
                  <option value="<?= $s_livereport; ?>"><?= ucfirst($s_livereport); ?></option>
                  <option value="enable">Enable</option>
                  <option value="disable">Disable</option>
                </select>
              </div>
              <?php endif; ?>
              
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </div>
</div>
</form>
</div>
</div>
</div>
</div>
</div>
</form>
<script type="text/javascript">
  function pingTest(sessname_value) {
    $("#ping").load("./status/ping-test.php?ping&session=" + sessname_value);
  }

  document.getElementById("ping_test").onclick = function() {
    var sessX = document.getElementById("sessname").value;
    pingTest(sessX);
  }

  function closeX() {
    $("#pingX").hide();
  }

  var sesname = document.settings.sessname;
  function chksname() {
    var val = sesname.value;
    if (val === "mikhmon" || val === "MIKHMON" || val === "Mikhmon") {
      alert("You cannot use " + val + " as a session name.");
      sesname.value = "";
      window.location.reload();
    }
  }
  sesname.onkeyup = chksname;
  sesname.onchange = chksname;
</script>
