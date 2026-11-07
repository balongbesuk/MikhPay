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

// check url
$url2 = explode("&setlang", $url)[0];

$getlang = $_GET['setlang'];

if (empty($getlang)) {

} else {
    $safe_url2 = json_encode($url2);
    $safe_lang = htmlspecialchars($getlang, ENT_QUOTES, 'UTF-8');
    if (!empty($isocodelang[$getlang]) && preg_match('/^[a-zA-Z0-9_-]+$/', $getlang)) {
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if (!$is_ajax) {
            include_once('./include/headhtml.php');
        }
        if (isset($_SESSION["mikhmon"])) {
            $gen = '<?php $langid="' . $getlang . '";?>';
            $slang = './include/lang.php';
            $handle = @fopen($slang, 'w');
            if ($handle) {
                fwrite($handle, $gen);
                fclose($handle);
            }
        }
        $_SESSION['lang'] = $getlang;
        if (!$is_ajax) {
            echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>Load '.$safe_lang.' lang...</h3></center>';
        }
        echo "<script>window.location=" . $safe_url2 . "</script>";
        exit;
    } else {
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if (!$is_ajax) {
            include_once('./include/headhtml.php');
            echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>'.$safe_lang.' lang not found...</h3></center>';
        }
        echo "<script>window.location=" . $safe_url2 . "</script>";
        exit;
    }
}

?>