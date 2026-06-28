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
// load session MikroTik
  $session = $_GET['session'];
// set  timezone
date_default_timezone_set($_SESSION['timezone']);

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');


// load config
  include('../include/config.php');
  include('../include/readcfg.php');

// routeros api
  include_once('../lib/routeros_api.class.php');
  include_once('../lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $connected = $API->connect($iphost, $userhost, decrypt($passwdhost));
  
  // If connection failed, exit silently — safeLoad() will skip the empty response
  if (!$connected) {
    exit;
  }

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
    $col_class = "col-6";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
    $col_class = "col-4";
// get selling report
    $thisD = date("d");
    $thisM = strtolower(date("M"));
    $thisY = date("Y");

    if (strlen($thisD) == 1) {
      $thisD = "0" . $thisD;
    } else {
      $thisD = $thisD;
    }

    $idhr = $thisM . "/" . $thisD . "/" . $thisY;
    $idbl = $thisM . $thisY;

    $_SESSION[$session.'idhr'] = $idhr;

   /* $getSRHr = $API->comm("/system/script/print", array(
      "?source" => "$idhr",
    ));
    $TotalRHr = count($getSRHr);
    $_SESSION[$session.'totalHr'] = $TotalRHr;*/
    $getSRBl = $API->comm("/system/script/print", array(
      "?owner" => "$idbl",
    ));
    $TotalRBl = count($getSRBl);
    $_SESSION[$session.'totalBl'] = $TotalRBl;
/*
    for ($i = 0; $i < $TotalRHr; $i++) {

      $tHr += explode("-|-", $getSRHr[$i]['name'])[3];

    }*/
    foreach($getSRBl as $row){
    
      if((explode("-|-", $row['name'])[0]) == $idhr){
         $tHr += explode("-|-", $row['name'])[3];
         $TotalRHr += count((array)$row['source']); /*Modif line add (array) by github https://github.com/MasKawer*/
 
       }
       $tBl += explode("-|-", $row['name'])[3];

      if($TotalRHr == ""){
        $TotalRHr = "0";
        $_SESSION[$session.'totalHr'] = "0";
      }else{
        $_SESSION[$session.'totalHr'] = $TotalRHr;
      }
      
    }
  }
}
?>
            <div id="r_4_inner" style="height: 100%;">
              <div class="box bmh-75 box-bordered" style="height: 100%;">
                <div class="box-group">
                  <div class="box-group-icon icon-emerald"><i class="fa fa-money"></i></div>
                  <div class="box-group-area" style="width: 100%;">
                    <div class="stat-title"><?= $_income ?></div>
                    <div id="reloadLreport">
                      <?php 
                        if ($currency == in_array($currency, $cekindo['indo'])) {
                          $dincome = number_format((float)$tHr, 0, ",", ".");
                          $mincome = number_format((float)$tBl, 0, ",", ".");
                          $_SESSION[$session.'dincome'] = $dincome;
                          $_SESSION[$session.'mincome'] = $mincome;
                        }else{
                          $dincome = number_format((float)$tHr, 2);
                          $mincome = number_format((float)$tBl, 2);
                          $_SESSION[$session.'dincome'] = $dincome;
                          $_SESSION[$session.'mincome'] = $mincome;
                        }
                      ?>
                      <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 4px;">
                        <!-- Today -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">
                          <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);"><?= $_today ?></span>
                          <span style="font-size: 12px; font-weight: 700; color: var(--text-bright);"><?= $currency ?> <?= $dincome ?> <span style="font-size: 10px; font-weight: 600; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 1px 5px; border-radius: 10px; margin-left: 4px;"><?= $TotalRHr ?> vcr</span></span>
                        </div>
                        <!-- This Month -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 2px;">
                          <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);"><?= $_this_month ?></span>
                          <span style="font-size: 12px; font-weight: 700; color: var(--primary);"><?= $currency ?> <?= $mincome ?> <span style="font-size: 10px; font-weight: 600; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 1px 5px; border-radius: 10px; margin-left: 4px;"><?= $TotalRBl ?> vcr</span></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            