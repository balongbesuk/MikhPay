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
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
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
    }else{
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
  }
}
?>
<script>
  function PassUser(){
    var x = document.getElementById('passUser');
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
</script>
<div class="gen-row-flex">
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
	
	/* Modern Action Buttons Style */
	.btn-modern-action {
	    display: inline-flex !important;
	    align-items: center !important;
	    justify-content: center !important;
	    height: 40px !important;
	    padding: 0 18px !important;
	    border-radius: 10px !important;
	    font-size: 13.5px !important;
	    font-weight: 700 !important;
	    gap: 8px !important;
	    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
	    border: 1px solid transparent !important;
	    cursor: pointer !important;
	    text-decoration: none !important;
	    box-shadow: 0 2px 6px rgba(0,0,0,0.02) !important;
	}
	
	.btn-modern-action.btn-close {
	    background: rgba(239, 68, 68, 0.08) !important;
	    color: #ef4444 !important;
	    border-color: rgba(239, 68, 68, 0.15) !important;
	}
	.btn-modern-action.btn-close:hover {
	    background: #ef4444 !important;
	    color: #ffffff !important;
	}
	
	.btn-modern-action.btn-submit {
	    background: var(--primary) !important;
	    color: #ffffff !important;
	    box-shadow: 0 4px 12px rgba(0, 139, 201, 0.2) !important;
	}
	.btn-modern-action.btn-submit:hover {
	    transform: translateY(-1px);
	    box-shadow: 0 6px 16px rgba(0, 139, 201, 0.3) !important;
	}

	/* Form Table Modern Overhaul */
	.card-body table.table {
	    width: 100% !important;
	    border-collapse: collapse !important;
	    margin-top: 10px !important;
	}
	.card-body table.table tr {
	    border-bottom: 1px solid var(--border-color) !important;
	}
	.card-body table.table tr:last-child {
	    border-bottom: none !important;
	}
	.card-body table.table td {
	    padding: 16px 8px !important;
	    border: none !important;
	    font-size: 14px !important;
	    color: var(--text-main) !important;
	}
	.card-body table.table td:first-child {
	    font-weight: 700 !important;
	    color: var(--text-muted) !important;
	    width: 25% !important;
	    vertical-align: middle !important;
	}
	.card-body table.table td select.form-control, 
	.card-body table.table td input.form-control {
	    width: 100% !important;
	    height: 46px !important;
	    border: 1px solid var(--border-color) !important;
	    border-radius: 10px !important;
	    background: var(--input-bg, #ffffff) !important;
	    color: var(--text-main) !important;
	    padding: 0 16px !important;
	    font-size: 14px !important;
	    outline: none !important;
	    transition: all 0.2s ease !important;
	    box-sizing: border-box !important;
	}
	.card-body table.table td select.form-control:focus, 
	.card-body table.table td input.form-control:focus {
	    border-color: var(--primary) !important;
	    box-shadow: 0 0 0 3px var(--primary-glow) !important;
	}
	#GetValidPrice b {
	    display: inline-block;
	    background: var(--primary-glow) !important;
	    color: var(--primary) !important;
	    padding: 8px 16px !important;
	    border-radius: 8px !important;
	    font-size: 12.5px !important;
	    font-weight: 700 !important;
	    margin-top: 8px !important;
	}
	</style>
<div class="gen-col-left">
<div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important;">
  <div class="card-header">
  <h3><i class="fa fa-user-plus"></i> <?= $_add_user ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
  </div>
  <div class="card-body" style="padding: 24px !important;">
<form autocomplete="off" method="post" action="">  
  <div style="margin-bottom: 24px; display: flex; gap: 8px; flex-wrap: wrap;">
  <?php if ($_SESSION['ubp'] != "") {
    echo "    <a class='btn-modern-action btn-close' href='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
  } else {
    echo "    <a class='btn-modern-action btn-close' href='./?hotspot=users&profile=all&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
  }
  ?>
    <button type="submit" onclick="loader()" class="btn-modern-action btn-submit" name="save"><i class="fa fa-save"></i> <?= $_save ?></button>
  </div>

<table class="table">
  <tr>
    <td class="align-middle" >Server</td>
    <td>
			<select class="form-control" name="server" required="1">
				<option>all</option>
				<?php $TotalReg = count($srvlist);
    for ($i = 0; $i < $TotalReg; $i++) {
      echo "<option>" . $srvlist[$i]['name'] . "</option>";
    }
    ?>
			</select>
		</td>
	</tr>
  <tr>
    <td class="align-middle"><?= $_name ?></td><td><input class="form-control" type="text" autocomplete="off" name="name" value="" required="1" autofocus style="height: 46px !important;"></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_password ?></td><td>
        <div class="input-group" style="display: flex; width: 100%;">
          <div class="input-group-11 col-box-10" style="flex: 1; float: none; position: relative;">
            <input class="group-item group-item-l form-control" id="passUser" type="password" name="pass" autocomplete="new-password" value="" required="1" style="border-radius: 10px 0 0 10px !important; width: 100% !important; height: 46px !important;">
          </div>
            <div class="input-group-1 col-box-2" style="width: 48px; float: none;">
              <div class="group-item group-item-r pd-2p5 text-center align-middle" style="border-radius: 0 10px 10px 0 !important; height: 46px; border: 1px solid var(--border-color, #c1c1c1); background: var(--background-alt, #F5F6F7); display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
              <input title="Show/Hide Password" type="checkbox" onclick="PassUser()">
            </div>
            </div>
        </div>
		</td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_profile ?></td><td>
			<select class="form-control" onchange="GetVP();"  id="uprof" name="profile" required="1">
				<?php $TotalReg = count($getprofile);
    for ($i = 0; $i < $TotalReg; $i++) {
      echo "<option>" . $getprofile[$i]['name'] . "</option>";
    }
    ?>
			</select>
		</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_time_limit ?></td><td><input class="form-control" type="text"  autocomplete="off" name="timelimit" value="" style="height: 46px !important;"></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_data_limit ?></td><td>
      <div class="input-group" style="display: flex; width: 100%;">
        <div class="input-group-10 col-box-9" style="flex: 1; float: none;">
          <input class="group-item group-item-l" type="number" min="0" max="9999" name="datalimit" value="<?= $udatalimit; ?>" style="border-radius: 10px 0 0 10px !important; width: 100% !important; height: 46px !important;">
        </div>
          <div class="input-group-2 col-box-3" style="width: 70px; float: none;">
              <select style="padding:4.2px; height: 46px !important; border-radius: 0 10px 10px 0 !important; border-left: none !important; width: 100% !important;" class="group-item group-item-r form-control" name="mbgb" required="1">
				        <option value=1048576>MB</option>
				        <option value=1073741824>GB</option>
			        </select>
          </div>
      </div>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_comment ?></td><td><input class="form-control" type="text" title="No special characters" id="comment" autocomplete="off" name="comment" value="" style="height: 46px !important;"></td>
  </tr>
  <tr >
    <td  colspan="4" class="align-middle"  id="GetValidPrice"></td>
  </tr>
</table>
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
<table style="width: 100%; border-collapse: collapse;">
   <tr>
    <td colspan="2" style="padding: 0 !important;">
    <p style="padding: 0; margin-bottom: 12px; color: var(--text-main); font-size: 14px; line-height: 1.5;">
      <?= $_format_time_limit ?>
    </p>
    <p style="padding: 0; color: var(--text-main); font-size: 14px; line-height: 1.5;">
      <?= $_details_add_user ?>
    </p>
    </td>
  </tr>
</table>
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
</div>