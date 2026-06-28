<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified for MikhPay in 2026.
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
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
} else {
  $getprofile = $API->comm("/ip/hotspot/user/profile/print");
  $srvlist = $API->comm("/ip/hotspot/print");

  if (isset($_POST['name'])) {
    $server = ($_POST['server']);
    $name = ($_POST['name']);
    $password = ($_POST['pass']);
    $profile = ($_POST['profile']);
    $disabled = ($_POST['disabled']);
    $timelimit = ($_POST['timelimit']);
    $datalimit = ($_POST['datalimit']);
    $comment = ($_POST['comment']);
    $chkvalid = ($_POST['valid']);
    $mbgb = ($_POST['mbgb']);
    if ($timelimit == "") {
      $timelimit = "0";
    } else {
      $timelimit = $timelimit;
    }
    if ($datalimit == "") {
      $datalimit = "0";
    } else {
      $datalimit = $datalimit * $mbgb;
    }
    if ($name == $password) {
      $usermode = "vc-";
    } else {
      $usermode = "up-";
    }
    $comment = $usermode.$comment;
    
    $API->comm("/ip/hotspot/user/add", array(
      "server" => "$server",
      "name" => "$name",
      "password" => "$password",
      "profile" => "$profile",
      "disabled" => "no",
      "limit-uptime" => "$timelimit",
      "limit-bytes-total" => "$datalimit",
      "comment" => "$comment",
    ));
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?name" => "$name",
    ));
    $uid = $getuser[0]['.id'];
    echo "<script>window.location='./?hotspot-user=" . $uid . "&session=" . $session . "'</script>";
    exit;
  }
}
?>

<script>
  function PassUser(btn){
    var x = document.getElementById('passUser');
    var icon = btn.querySelector('i');
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
.gen-row-flex {
    display: flex !important;
    gap: 24px !important;
    width: 100% !important;
    flex-wrap: wrap !important;
    box-sizing: border-box !important;
}
.gen-col-left {
    flex: 1 1 calc(66.666% - 12px) !important;
    min-width: 400px !important;
    box-sizing: border-box !important;
}
.gen-col-right {
    flex: 1 1 calc(33.333% - 12px) !important;
    min-width: 250px !important;
    box-sizing: border-box !important;
}

/* Section Titles */
.form-section-title {
    font-size: 12px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    color: var(--primary) !important;
    margin-bottom: 18px !important;
    border-bottom: 1px solid var(--primary-glow) !important;
    padding-bottom: 8px !important;
}

/* Row Grid */
.form-row-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 20px !important;
    margin-bottom: 20px !important;
    width: 100% !important;
    box-sizing: border-box;
}

@media (max-width: 768px) {
    .form-row-grid {
        grid-template-columns: 1fr !important;
        gap: 16px !important;
    }
}

/* Individual Form Fields */
.form-field-group {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
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

/* Password Wrapper */
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

/* Data Limit Wrapper */
.modern-data-limit-wrapper {
    display: flex !important;
    width: 100% !important;
    box-sizing: border-box;
}

.modern-data-limit-wrapper input {
    border-radius: 12px 0 0 12px !important;
    flex: 1 !important;
}

.modern-data-limit-wrapper select.data-unit-select {
    border-radius: 0 12px 12px 0 !important;
    border-left: none !important;
    width: 80px !important;
    background: var(--background-alt, #F5F6F7) !important;
    font-weight: 700 !important;
}

/* Form Footer Actions */
.form-actions-footer {
    display: flex !important;
    justify-content: flex-end !important;
    gap: 12px !important;
    margin-top: 32px !important;
    border-top: 1px solid var(--border-color) !important;
    padding-top: 24px !important;
    width: 100% !important;
}

.btn-modern-primary {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 46px !important;
    padding: 0 24px !important;
    background: var(--primary) !important;
    color: #ffffff !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    gap: 8px !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.25s ease !important;
    box-shadow: 0 4px 14px rgba(0, 139, 201, 0.2) !important;
}

.btn-modern-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 139, 201, 0.3) !important;
    opacity: 0.95;
}

.btn-modern-secondary {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 46px !important;
    padding: 0 20px !important;
    background: rgba(148, 163, 184, 0.08) !important;
    color: #475569 !important;
    border: 1px solid rgba(148, 163, 184, 0.15) !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    gap: 8px !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
}

.btn-modern-secondary:hover {
    background: rgba(148, 163, 184, 0.15) !important;
    color: #1e293b !important;
}

/* Readme Card Info Alert Box */
.readme-info-alert {
    background: var(--primary-glow) !important;
    border: 1px solid rgba(99, 102, 241, 0.15) !important;
    border-radius: 14px !important;
    padding: 16px 20px !important;
    display: flex !important;
    gap: 14px !important;
    margin-bottom: 16px !important;
    box-sizing: border-box;
}

.readme-info-alert-icon {
    font-size: 20px !important;
    color: var(--primary) !important;
    margin-top: 2px !important;
}

.readme-info-alert-content {
    text-align: left !important;
}

.readme-info-alert-content h4 {
    margin: 0 0 6px 0 !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    color: var(--primary) !important;
}

.readme-info-alert-content p {
    margin: 0 !important;
    font-size: 12.5px !important;
    color: var(--text-main) !important;
    line-height: 1.6 !important;
}
</style>

<div class="gen-row-flex">
  <div class="gen-col-left">
    <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important;">
      <div class="card-header">
        <h3><i class="fa fa-user-plus"></i> <?= $_add_user ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
      </div>
      <div class="card-body" style="padding: 28px !important;">
        <form autocomplete="off" method="post" action="">  
          
          <div class="form-section-title">Detail Pengguna</div>
          
          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="server">Hotspot Server</label>
              <select class="form-control" name="server" required="1">
                <option>all</option>
                <?php $TotalReg = count($srvlist);
                for ($i = 0; $i < $TotalReg; $i++) {
                  echo "<option>" . $srvlist[$i]['name'] . "</option>";
                }
                ?>
              </select>
            </div>
            
            <div class="form-field-group">
              <label for="profile">User Profile</label>
              <select class="form-control" onchange="GetVP();" id="uprof" name="profile" required="1">
                <?php $TotalReg = count($getprofile);
                for ($i = 0; $i < $TotalReg; $i++) {
                  echo "<option>" . $getprofile[$i]['name'] . "</option>";
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="name"><?= $_name ?></label>
              <input class="form-control" type="text" autocomplete="off" name="name" value="" required="1" autofocus placeholder="Masukkan username">
            </div>
            
            <div class="form-field-group">
              <label for="pass"><?= $_password ?></label>
              <div class="modern-password-wrapper">
                <input class="form-control" id="passUser" type="password" name="pass" autocomplete="new-password" value="" required="1" placeholder="Masukkan password">
                <button type="button" class="password-toggle-btn" onclick="PassUser(this)">
                  <i class="fa fa-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="form-section-title" style="margin-top: 24px;">Batasan & Catatan</div>

          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="timelimit"><?= $_time_limit ?></label>
              <input class="form-control" type="text" autocomplete="off" name="timelimit" value="" placeholder="Contoh: 30d, 12h, 4w3d">
            </div>
            
            <div class="form-field-group">
              <label for="datalimit"><?= $_data_limit ?></label>
              <div class="modern-data-limit-wrapper">
                <input class="form-control" type="number" min="0" max="9999" name="datalimit" value="<?= $udatalimit; ?>" placeholder="0">
                <select class="form-control data-unit-select" name="mbgb" required="1">
                  <option value=1048576>MB</option>
                  <option value=1073741824>GB</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-field-group" style="width: 100%;">
            <label for="comment"><?= $_comment ?></label>
            <input class="form-control" type="text" title="No special characters" id="comment" autocomplete="off" name="comment" value="" placeholder="Komentar atau catatan opsional">
          </div>

          <div id="GetValidPrice" style="text-align: left;"></div>

          <!-- Form Footer Actions -->
          <div class="form-actions-footer">
            <?php if ($_SESSION['ubp'] != "") {
              echo "<a class='btn-modern-secondary' href='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'><i class='fa fa-close'></i> Batal</a>";
            } else {
              echo "<a class='btn-modern-secondary' href='./?hotspot=users&profile=all&session=" . $session . "'><i class='fa fa-close'></i> Batal</a>";
            }
            ?>
            <button type="submit" onclick="loader()" class="btn-modern-primary" name="save"><i class="fa fa-save"></i> <?= $_save ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <div class="gen-col-right">
    <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important;">
      <div class="card-header">
        <h3><i class="fa fa-book"></i> <?= $_readme ?></h3>
      </div>
      <div class="card-body" style="padding: 24px !important;">
        <div class="readme-info-alert">
          <div class="readme-info-alert-icon"><i class="fa fa-info-circle"></i></div>
          <div class="readme-info-alert-content">
            <p style="margin: 0 !important; font-size: 13px !important; line-height: 1.5 !important; color: var(--text-main) !important;"><?= $_format_time_limit ?></p>
          </div>
        </div>
        
        <div class="readme-info-alert" style="background: rgba(16, 185, 129, 0.05) !important; border-color: rgba(16, 185, 129, 0.15) !important;">
          <div class="readme-info-alert-icon" style="color: #10b981 !important;"><i class="fa fa-check-circle"></i></div>
          <div class="readme-info-alert-content">
            <p style="margin: 0 !important; font-size: 13px !important; line-height: 1.5 !important; color: var(--text-main) !important;"><?= $_details_add_user ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// get valid $ price
function GetVP(){
  var prof = document.getElementById('uprof').value;
  $("#GetValidPrice").load("./process/getvalidprice.php?name="+prof+"&session=<?= $session; ?> #getdata");
}  
</script>