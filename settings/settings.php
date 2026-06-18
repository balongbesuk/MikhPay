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
    $dbSessions = new \App\Models\RouterSession();
    $dbSessions->save(array(
      'session_name' => $router,
      'ip_address' => '',
      'username' => '',
      'password' => '',
      'hotspot_name' => '',
      'dns_name' => '',
      'currency' => 'Rp',
      'auto_reload' => 10,
      'traffic_interface' => '1',
      'idle_timeout' => '10',
      'live_report' => 'disable'
    ));
    echo "<script>window.location='./admin.php?id=settings&session=" . $router . "'</script>";
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
  }
  if ($currency == "") {
    echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
  }
}
?>
<script>
  function PassMk(){
    var x = document.getElementById('passmk');
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
    function PassAdm(){
    var x = document.getElementById('passadm');
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
  }}
  
</script>

<form autocomplete="off" method="post" action="" name="settings">  
<div class="row">
	<div class="col-12">
  		<div class="card" >
  			<div class="card-header">
  				<h3 class="card-title"><i class="fa fa-gear"></i> <?= $_session_settings ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
  			</div>
        <div class="card-body">
    	   <div class="row">
			     <div class="col-6">
            <div class="col-12">
              <div class="card">
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
                </style>
                <div class="card-header">
                  <h3 class="card-title"><?= $_session ?></h3>
                </div>
                <div class="card-body">
                  <div class="form-group-floating">
                    <input class="form-control" id="sessname" type="text" name="sessname" placeholder=" " value="<?php if (explode("-",$session)[0] == "new") {
                                                                                                                          echo "";
                                                                                                                        } else {
                                                                                                                          echo $session;
                                                                                                                        } ?>" required="1"/>
                    <label for="sessname"><?= $_session_name ?></label>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12">
				      <div class="card">
        	     <div class="card-header">
            	   <h3 class="card-title">MikroTik <?= $_SESSION["connect"]; ?></h3>
        	     </div>
        	     <div class="card-body">
                 <div style="padding: 10px 0;">
                   <div class="form-group-floating">
                     <input class="form-control" id="ipmik" type="text" name="ipmik" placeholder=" " value="<?= $iphost; ?>" required="1"/>
                     <label for="ipmik">IP MikroTik / IP Cloud</label>
                   </div>
                   
                   <div class="form-group-floating">
                     <input class="form-control" id="usermk" type="text" name="usermik" placeholder=" " value="<?= $userhost; ?>" required="1"/>
                     <label for="usermk">Username</label>
                   </div>

                   <div class="form-group-floating">
                     <div class="input-group" style="display: flex; width: 100%;">
                       <div class="input-group-11 col-box-10" style="flex: 1; position: relative; float: none;">
                         <input class="group-item form-control" id="passmk" type="password" name="passmik" placeholder=" " value="<?= decrypt($passwdhost); ?>" required="1" style="border-radius: 8px 0 0 8px !important; width: 100% !important; height: 48px !important; border: 1px solid var(--border-color, #c1c1c1) !important; border-right: none !important; padding: 18px 16px 6px 16px !important;"/>
                         <label for="passmk">Password</label>
                       </div>
                       <div class="input-group-1 col-box-2" style="width: 48px; float: none;">
                         <div class="group-item group-item-r pd-2p5 text-center align-middle" style="border-radius: 0 8px 8px 0 !important; height: 48px; border: 1px solid var(--border-color, #c1c1c1); background: var(--background-alt, #F5F6F7); display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
                           <input title="Show/Hide Password" type="checkbox" onclick="PassMk()">
                         </div>
                       </div>
                     </div>
                   </div>

                   <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                     <button type="submit" name="save" class="btn-modern-save">Save</button>
                     <span class="connect pointer btn-modern-save" id="<?= $session; ?>&c=settings" style="display: flex; align-items: center; justify-content: center;">Connect</span>
                     <span class="pointer btn-modern-save" id="ping_test" style="display: flex; align-items: center; justify-content: center; background: var(--accent, #4dbd74) !important;">Ping</span>
                     <div style="cursor: pointer; height: 40px; width: 40px; border-radius: 8px; border: 1px solid var(--border-color, #c1c1c1); display: flex; align-items: center; justify-content: center; background: var(--card-bg, #fff);" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
                   </div>
                 </div>
			</div>
    </div>  	
    <div id="ping">
    </div>	
	</div>
</div>
<div class="col-6">
<div class="col-12">
	<div class="card">
        <div class="card-header">
            <h3 class="card-title">Mikhmon Data</h3>
        </div>
    <div class="card-body">    
      <div style="padding: 10px 0;">
        <div class="form-group-floating">
          <input class="form-control" type="text" name="hotspotname" placeholder=" " value="<?= $hotspotname; ?>" required="1" id="hotspotname"/>
          <label for="hotspotname"><?= $_hotspot_name ?></label>
        </div>

        <div class="form-group-floating">
          <input class="form-control" type="text" name="dnsname" placeholder=" " value="<?= $dnsname; ?>" required="1" id="dnsname"/>
          <label for="dnsname"><?= $_dns_name ?></label>
        </div>

        <div class="form-group-floating">
          <input class="form-control" type="text" name="currency" placeholder=" " value="<?= $currency; ?>" required="1" id="currency"/>
          <label for="currency"><?= $_currency ?></label>
        </div>

        <div class="form-group-floating">
          <div class="input-group" style="display: flex; width: 100%;">
            <div class="input-group-10" style="flex: 1; position: relative; float: none;">
              <input class="group-item form-control" type="number" min="10" max="3600" name="areload" placeholder=" " value="<?= $areload; ?>" required="1" id="areload" style="border-radius: 8px 0 0 8px !important; width: 100% !important; height: 48px !important; border: 1px solid var(--border-color, #c1c1c1) !important; border-right: none !important; padding: 18px 16px 6px 16px !important;"/>
              <label for="areload"><?= $_auto_reload ?></label>
            </div>
            <div class="input-group-2" style="width: 60px; float: none;">
              <span class="group-item group-item-r pd-2p5 text-center align-middle" style="border-radius: 0 8px 8px 0 !important; height: 48px; border: 1px solid var(--border-color, #c1c1c1); background: var(--background-alt, #F5F6F7); display: flex; align-items: center; justify-content: center; box-sizing: border-box; font-size: 13px; font-weight: 600; color: var(--text-muted, #73818f);"><?= $_sec ?></span>
            </div>
          </div>
        </div>

        <div style="margin-bottom: 16px;">
          <label style="font-size: 12px; color: var(--text-muted, #73818f); display: block; margin-bottom: 6px; font-weight: 600; text-align: left;"><?= $_idle_timeout ?></label>
          <div class="input-group" style="display: flex; width: 100%;">
            <div class="input-group-9" style="flex: 1; float: none;">
              <select class="group-item form-control" name="idleto" required="1" style="height: 48px !important; border-radius: 8px 0 0 8px !important; border: 1px solid var(--border-color, #c1c1c1) !important; border-right: none !important; background: var(--card-bg, #fff) !important; color: var(--text-main, #3E3E3E) !important; padding: 0 16px !important; width: 100% !important;">
                <option value="<?= $idleto; ?>"><?= $idleto; ?></option>
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="30">30</option>
                <option value="60">60</option>
                <option value="disable">disable</option>
              </select>
            </div>
            <div class="input-group-3" style="width: 60px; float: none;">
              <span class="group-item group-item-r pd-3p5 text-center align-middle" style="border-radius: 0 8px 8px 0 !important; height: 48px; border: 1px solid var(--border-color, #c1c1c1); background: var(--background-alt, #F5F6F7); display: flex; align-items: center; justify-content: center; box-sizing: border-box; font-size: 13px; font-weight: 600; color: var(--text-muted, #73818f);"><?= $_min ?></span>
            </div>
          </div>
        </div>

        <div class="form-group-floating">
          <input class="form-control" type="number" min="1" max="99" name="iface" placeholder=" " value="<?= $iface; ?>" required="1" id="iface"/>
          <label for="iface"><?= $_traffic_interface ?></label>
        </div>

        <?php if (!empty($livereport)): ?>
        <div style="margin-bottom: 16px;">
          <label style="font-size: 12px; color: var(--text-muted, #73818f); display: block; margin-bottom: 6px; font-weight: 600; text-align: left;"><?= $_live_report ?></label>
          <select class="form-control" name="livereport" style="height: 48px !important; border-radius: 8px !important; border: 1px solid var(--border-color, #c1c1c1) !important; background: var(--card-bg, #fff) !important; color: var(--text-main, #3E3E3E) !important; padding: 0 16px !important; width: 100% !important;">
            <option value="<?= $livereport; ?>"><?= ucfirst($livereport); ?></option>
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
