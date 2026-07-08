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
ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  $proplist = array(".proplist" => ".id,server,name,password,profile,mac-address,uptime,bytes-in,bytes-out,comment");

  if ($prof == "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", $proplist);
    $TotalReg = count($getuser);
    $counttuser = $TotalReg;

  } elseif ($prof != "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array_merge(array(
      "?profile" => "$prof",
    ), $proplist));
    $TotalReg = count($getuser);
    $counttuser = $TotalReg;

  }
  if ($comm != "") {
    $is_group = (strpos($comm, "QRIS-") !== false || strpos($comm, "API-Retry") !== false);
    if ($is_group) {
      $all_users = $API->comm("/ip/hotspot/user/print", $proplist);
      $getuser = array();
      if (is_array($all_users)) {
        foreach ($all_users as $usr) {
          if (isset($usr['comment']) && strpos($usr['comment'], $comm) === 0) {
            $getuser[] = $usr;
          }
        }
      }
    } else {
      $getuser = $API->comm("/ip/hotspot/user/print", array_merge(array(
        "?comment" => "$comm",
      ), $proplist));
    }
    $TotalReg = count($getuser);
    $counttuser = $TotalReg;
  }
  $exp = $_GET['exp'];
  if ($exp != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array_merge(array(
      "?limit-uptime" => "1s",
    ), $proplist));
    $TotalReg = count($getuser);
    $counttuser = $TotalReg;
    
  }
  $getprofile = $API->comm("/ip/hotspot/user/profile/print");
  $TotalReg2 = count($getprofile);
}
?>

<style>
.filter-row-flex {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 16px !important;
    flex-wrap: wrap !important;
    margin-bottom: 20px !important;
    width: 100% !important;
}
.filter-inputs-group {
    display: flex !important;
    gap: 8px !important;
    flex: 1 1 auto !important;
    min-width: 300px !important;
}
.filter-actions-group {
    display: flex !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
}
.filter-control-modern {
    height: 38px !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    background: var(--bg-card, #ffffff) !important;
    color: var(--text-main) !important;
    padding: 8px 12px !important;
    font-size: 13px !important;
    outline: none !important;
    transition: all 0.2s ease !important;
    box-sizing: border-box !important;
    flex: 1 !important;
}
.filter-control-modern:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px var(--primary-glow) !important;
}
.btn-modern-filter-action {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 38px !important;
    padding: 0 14px !important;
    border-radius: 8px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    gap: 6px !important;
    transition: all 0.2s ease !important;
    border: none !important;
    cursor: pointer !important;
    text-decoration: none !important;
    box-sizing: border-box !important;
}
.btn-modern-filter-action.btn-accent {
    background: var(--primary) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(0, 139, 201, 0.15) !important;
}
.btn-modern-filter-action.btn-accent:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}
.btn-modern-filter-action.btn-danger {
    background: var(--danger) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15) !important;
}
.btn-modern-filter-action.btn-danger:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}
.btn-modern-filter-action.btn-secondary {
    background: rgba(148, 163, 184, 0.08) !important;
    color: #475569 !important;
    border: 1px solid rgba(148, 163, 184, 0.15) !important;
}
.btn-modern-filter-action.btn-secondary:hover {
    background: rgba(148, 163, 184, 0.15) !important;
    color: #1e293b !important;
}
.header-actions-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    flex-wrap: wrap;
    gap: 12px;
}
</style>

<div class="row">
<div class="col-12">
<div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
<div class="card-header" style="padding: 16px 24px !important;">
    <div class="header-actions-wrapper">
        <h3 style="margin: 0;"><i class="fa fa-users"></i> <?= $_users ?>
            <?php if ($counttuser == 0) {
              echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "</script>";
            } ?>
            <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
        </h3>
        
        <div class="gen-actions-wrapper" style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="./?hotspot-user=add&session=<?= $session; ?>" class="btn-modern-filter-action btn-accent"><i class="fa fa-user-plus"></i> <?= $_add ?></a>
          <a href="./?hotspot-user=generate&session=<?= $session; ?>" class="btn-modern-filter-action btn-accent"><i class="fa fa-users"></i> <?= $_generate ?></a>
          <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=script" class="btn-modern-filter-action btn-secondary" title="Script"><i class="fa fa-download"></i> Script</a>
          <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=csv" class="btn-modern-filter-action btn-secondary" title="CSV"><i class="fa fa-download"></i> CSV</a>
        </div>
    </div>
</div>
<div class="card-body" style="padding: 24px !important;">
  <div class="filter-row-flex">
    <div class="filter-inputs-group">
      <input id="filterTable" type="text" class="filter-control-modern" placeholder="<?= $_search ?>">
      
      <select class="filter-control-modern" onchange="location = this.value; loader()" title="Filter by Profile">
        <option><?= $_profile ?> </option>
        <option value="./?hotspot=users&profile=all&session=<?= $session; ?>"><?= $_show_all ?></option>
        <?php
        for ($i = 0; $i < $TotalReg2; $i++) {
          $profile = $getprofile[$i];
          echo "<option value='./?hotspot=users&profile=" . $profile['name'] . "&session=" . $session . "'>" . $profile['name'] . "</option>";
        }
        ?>
      </select>

      <select class="filter-control-modern" id="comment" name="comment" onchange="location = './?hotspot=users&comment='+ this.value +'&session=<?= $session;?>';">
        <?php
        if ($comm != "") {
        } else {
          echo "<option value=''>".$_comment."</option>";
        }
        $acomment_arr = array();
        $TotalReg = count($getuser);
        for ($i = 0; $i < $TotalReg; $i++) {
          $ucomment = $getuser[$i]['comment'];
          $uprofile = $getuser[$i]['profile'];
          
          $group_comment = $ucomment;
          if (strpos($ucomment, "QRIS-") !== false) {
             $parts = explode("-", $ucomment);
             if (count($parts) > 3) {
                 array_pop($parts); // remove date
                 array_pop($parts); // remove timestamp
                 $group_comment = implode("-", $parts);
             }
          } elseif (strpos($ucomment, "API-Retry-") !== false) {
             $group_comment = "vc-API-Retry";
          }
          
          $is_qris = (strpos($group_comment, "QRIS-") !== false || strpos($group_comment, "API-Retry") !== false);
          $is_standard = is_numeric(substr($group_comment, 3, 3));
          
          if ($is_standard || $is_qris) {
             $acomment_arr[] = $group_comment . "#" . $uprofile;
          }
        }

        $comments = array_count_values($acomment_arr);
        foreach ($comments as $tcomment => $value) {
          $clean_comment = explode("#", $tcomment)[0];
          $uprof = explode("#", $tcomment)[1];
          echo "<option value='" . $clean_comment . "' >". $clean_comment." ".$uprof. " [".$value. "]</option>";
        }
        ?>
      </select>
    </div>
 
    <div class="filter-actions-group">
      <?php if ($comm != "") { ?>
        <button class="btn-modern-filter-action btn-danger" onclick="if(confirm('Are you sure to delete username by comment (<?= addslashes($comm); ?>)?')){loadpage('./?remove-hotspot-user-by-comment=<?= urlencode($comm); ?>&session=<?= $session; ?>');loader();}else{}" title="Remove user by comment <?= htmlspecialchars($comm, ENT_QUOTES); ?>"><i class="fa fa-trash"></i> <?= $_by_comment ?></button>
      <?php } else if ($exp == "1") { ?>
        <button class="btn-modern-filter-action btn-danger" onclick="if(confirm('Are you sure to delete users?')){loadpage('./?remove-hotspot-user-expired=1&session=<?= $session; ?>');loader();}else{}" title="Remove user expired"><i class="fa fa-trash"></i> Expired Users</button>
      <?php } ?>

      <script>
        function printV(a,b){
          var comm = document.getElementById('comment').value;
          var url = "./voucher/print.php?id="+comm+"&"+a+"="+b+"&session=<?= $session; ?>";
          if (comm === "" ){
            <?php if ($currency == in_array($currency, $cekindo['indo'])) { ?>
              alert('Silakan pilih salah satu Comment terlebih dulu!');
            <?php } else { ?>
              alert('Please choose one of the Comments first!');
            <?php } ?>
          } else {
            var win = window.open(url, '_blank');
            win.focus();
          }
        }
      </script>
      <button class="btn-modern-filter-action btn-accent" title='Print' onclick="printV('qr','no');"><i class="fa fa-print"></i> <?= $_print_default ?></button>
      <button class="btn-modern-filter-action btn-accent" title='Print QR' onclick="printV('qr','yes');"><i class="fa fa-print"></i> <?= $_print_qr ?></button>
      <button class="btn-modern-filter-action btn-accent" title='Print Small' onclick="printV('small','yes');"><i class="fa fa-print"></i> <?= $_print_small ?></button>
    </div>
  </div>
<div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="min-width:50px;" class="align-middle text-center" id="cuser"><?= $counttuser; ?></th>
    <th style="min-width:50px;" class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th>Print</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?></th>
	  <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Mac Address</th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_uptime_user ?></th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Bytes In</th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Bytes Out</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
    </tr>
  </thead>
  <tbody id="tbody">
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $userdetails = $getuser[$i];
  $uid = $userdetails['.id'];
  $userver = $userdetails['server'];
  $uname = $userdetails['name'];
  $upass = $userdetails['password'];
  $uprofile = $userdetails['profile'];
  $umacadd = $userdetails['mac-address'];
  $uuptime = formatDTM($userdetails['uptime']);
  $ubytesi = formatBytes($userdetails['bytes-in'], 2);
  $ubyteso = formatBytes($userdetails['bytes-out'], 2);

  $ucomment = $userdetails['comment'];
  $udisabled = $userdetails['disabled'];
  $utimelimit = $userdetails['limit-uptime'];
  if ($utimelimit == '1s') {
    $utimelimit = ' expired';
  } else {
    $utimelimit = ' ' . $utimelimit;
  }
  $udatalimit = $userdetails['limit-bytes-total'];
  if ($udatalimit == '') {
    $udatalimit = '';
  } else {
    $udatalimit = ' ' . formatBytes($udatalimit, 2);
  }

  $s_uname = htmlspecialchars($uname, ENT_QUOTES, 'UTF-8');
  $js_uname = addslashes($uname);
  $s_userver = htmlspecialchars($userver, ENT_QUOTES, 'UTF-8');
  $s_uprofile = htmlspecialchars($uprofile, ENT_QUOTES, 'UTF-8');
  $s_umacadd = htmlspecialchars($umacadd, ENT_QUOTES, 'UTF-8');
  $s_uuptime = htmlspecialchars($uuptime, ENT_QUOTES, 'UTF-8');
  $s_ubytesi = htmlspecialchars($ubytesi, ENT_QUOTES, 'UTF-8');
  $s_ubyteso = htmlspecialchars($ubyteso, ENT_QUOTES, 'UTF-8');
  $s_ucomment = htmlspecialchars($ucomment, ENT_QUOTES, 'UTF-8');
  $s_udatalimit = htmlspecialchars($udatalimit, ENT_QUOTES, 'UTF-8');
  $s_utimelimit = htmlspecialchars($utimelimit, ENT_QUOTES, 'UTF-8');

  echo "<tr>";
  ?>
  <td style='text-align:center;'>  <i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('Are you sure to delete username (<?= $js_uname; ?>)?')){loadpage('./?remove-hotspot-user=<?= $uid; ?>&session=<?= $session; ?>')}else{}" title='Remove <?= $s_uname; ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  <?php
  if ($udisabled == "true") {
    $uriprocess = "'./?enable-hotspot-user=" . $uid . "&session=" . $session."'";
    echo '<span class="text-warning pointer" title="Enable User ' . $s_uname . '"  onclick="loadpage('.$uriprocess.')"><i class="fa fa-lock "></i></span></td>';
  } else {
    $uriprocess = "'./?disable-hotspot-user=" . $uid . "&session=" . $session."'";
    echo '<span class="pointer" title="Disable User ' . $s_uname . '"  onclick="loadpage('.$uriprocess.')"><i class="fa fa-unlock "></i></span></td>';
  }
  echo "<td>" . $s_userver . "</td>";
  if ($uname == $upass) {
    $usermode = "vc";
  } else {
    $usermode = "up";
  }
  $popup = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . urlencode($uname) . "&qr=no&session=" . $session . "','_blank','width=320,height=550').print();";
  $popupQR = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . urlencode($uname) . "&qr=yes&session=" . $session . "','_blank','width=320,height=550').print();";
  echo "<td><a title='Open User " . $s_uname . "' href=./?hotspot-user=" . $uid . "&session=" . $session . "><i class='fa fa-edit'></i> " . $s_uname . " </a>";
  echo '</td><td class="text-center"><a title="Print ' . $s_uname . '" href="' . $popup . '"><i class="fa fa-print"></i></a> &nbsp <a title="Print ' . $s_uname . '" href="' . $popupQR . '"><i class="fa fa-qrcode"></i> </a></td>';
  echo "<td>" . $s_uprofile . "</td>";
  echo "<td style=' text-align:left'>" . $s_umacadd . "</td>";
  echo "<td style=' text-align:right'>" . $s_uuptime . "</td>";
  echo "<td style=' text-align:right'>" . $s_ubytesi . "</td>";
  echo "<td style=' text-align:right'>" . $s_ubyteso . "</td>";
  echo "<td>";
  if ($uname == "default-trial") {
  } else if (substr($ucomment,0,3) == "vc-" || substr($ucomment,0,3) == "up-") {
    echo "<a href=./?hotspot=users&comment=" . urlencode($ucomment) . "&session=" . $session . " title='Filter by " . $s_ucomment . "'><i class='fa fa-search'></i> ". $s_ucomment." ". $s_udatalimit ." ".$s_utimelimit . "</a>";
  } else if ($utimelimit == ' expired') {
    echo "<a href=./?hotspot=users&profile=all&exp=1&session=" . $session . " title='Filter by expired'><i class='fa fa-search'></i> " . $s_ucomment." ". $s_udatalimit ." ".$s_utimelimit . "</a>";
  }else{
    echo $s_ucomment.' ';
  }
  echo  "</td>";


}
?>
  </tr>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>

	
	
