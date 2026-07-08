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
$is_group = (strpos($removehotspotuserbycomment, "QRIS-") !== false || strpos($removehotspotuserbycomment, "API-Retry") !== false);

if ($is_group) {
  $all_users = $API->comm("/ip/hotspot/user/print");
  $getuser = array();
  if (is_array($all_users)) {
    foreach ($all_users as $usr) {
      if (isset($usr['comment']) && strpos($usr['comment'], $removehotspotuserbycomment) === 0) {
        $getuser[] = $usr;
      }
    }
  }
} else {
  $getuser = $API->comm("/ip/hotspot/user/print", array(
    "?comment" => "$removehotspotuserbycomment"
  ));
}

if (is_array($getuser)) {
  $TotalReg = count($getuser);
  $_SESSION['ubp'] = ($TotalReg > 0 && isset($getuser[0]['profile'])) ? $getuser[0]['profile'] : "";
  $_SESSION['ubc'] = "";

  for ($i = 0; $i < $TotalReg; $i++) {
    $userdetails = $getuser[$i];
    $uid = $userdetails['.id'];

    $API->comm("/ip/hotspot/user/remove", array(
      ".id" => "$uid",
    ));
  }
} else {
  $_SESSION['ubp'] = "";
  $_SESSION['ubc'] = "";
}

if ($_SESSION['ubp'] != "") {
  echo "<script>window.location='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'</script>";
} else {
  echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
}

?>