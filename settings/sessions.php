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
    $spassadm = encrypt($_POST['passadm']);
    $qrbt = ($_POST['qrbt']);

    $dbSettings = new \App\Models\AppSettings();
    $dbSettings->saveAdminCredentials($suseradm, $spassadm);
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
                        <div class="box bmh-75 box-bordered <?= $color[rand(1, 11)]; ?>">
                                <div class="box-group">
                                  
                                  <div class="box-group-icon">
                                    <span class="connect pointer" id="<?= $value; ?>">
                                    <i class="fa fa-server"></i>
                                    </span>
                                  </div>
                                
                                  <div class="box-group-area">
                                    <span>
                                      <?= $_hotspot_name ?> : <?= explode('%', $data[$value][4])[1]; ?><br>
                                      <?= $_session_name ?> : <?= $value; ?><br>
                                      <span class="connect pointer"  id="<?= $value; ?>"><i class="fa fa-external-link"></i> <?= $_open ?></span>&nbsp;
                                      <a href="./admin.php?id=settings&session=<?= $value; ?>"><i class="fa fa-edit"></i> <?= $_edit ?></a>&nbsp;
                                      <a href="javascript:void(0)" onclick="if(confirm('Are you sure to delete data <?= $value;
                                      echo " (" . explode('%', $data[$value][4])[1] . ")"; ?>?')){loadpage('./admin.php?id=remove-session&session=<?= $value; ?>')}else{}"><i class="fa fa-remove"></i> <?= $_delete ?></a>
                                    </span>

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
            </style>
            <div style="padding: 10px 0;">
              <div class="form-group-floating">
                <input class="form-control" id="useradm" type="text" name="useradm" placeholder=" " value="<?= $useradm; ?>" required="1"/>
                <label for="useradm"><?= $_user_name ?></label>
              </div>
              
              <div class="form-group-floating">
                <div class="input-group" style="display: flex; width: 100%;">
                  <div class="input-group-11 col-box-10" style="flex: 1; position: relative; float: none;">
                    <input class="group-item form-control" id="passadm" type="password" name="passadm" placeholder=" " value="<?= decrypt($passadm); ?>" required="1" style="border-radius: 8px 0 0 8px !important; width: 100% !important; height: 48px !important; border: 1px solid var(--border-color, #c1c1c1) !important; border-right: none !important; padding: 18px 16px 6px 16px !important;"/>
                    <label for="passadm"><?= $_password ?></label>
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
  $(function() {
    $.getJSON("https://raw.githubusercontent.com/laksa19/mikhmonv3/master/version.txt?t=" + (Math.floor(Math.random() * 999999999) + 1) * 128, function(data) {
      try {
        var getNewVer = data.version.split("v")[1];
        var newVerNum = parseInt(getNewVer.replace(".", ""));
        var currentVerStr = document.getElementById("loadV").innerHTML;
        var currentVerSplit = currentVerStr.split(" ")[0].split("v")[1];
        var currentVerNum = parseInt(currentVerSplit.replace(".", ""));
        var verDiff = newVerNum - currentVerNum;

        var getNewD = data.updated.split(" ")[0];
        var newD = parseInt(getNewD.split("-")[2] + getNewD.split("-")[0] + getNewD.split("-")[1]);

        var currentUpdateStr = currentVerStr.split(" ")[1];
        var currentUpdateNum = parseInt(currentUpdateStr.split("-")[2] + currentUpdateStr.split("-")[0] + currentUpdateStr.split("-")[1]);
        var dateDiff = newD - currentUpdateNum;

        if (verDiff > 0 || dateDiff > 0) {
          $("#newVer").html("New Version " + data.version + " " + data.updated + "<br><span><i class='text-white fa fa-info-circle'></i> <a class='text-blue' href='./admin.php?id=about'>Check Update</a></span>");
        }
      } catch (e) {
        console.log("Error checking version: ", e);
      }
    });
  });
</script>









