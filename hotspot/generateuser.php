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
ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
	exit;
} else {
    date_default_timezone_set($_SESSION['timezone']);

	$genprof = $_GET['genprof'];
	if ($genprof != "") {
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array(
			"?name" => "$genprof",
		));
		$ponlogin = $getprofile[0]['on-login'];
		$getprice = explode(",", $ponlogin)[2];
		if ($getprice == "0") {
			$getprice = "";
		} else {
			$getprice = $getprice;
		}

		$getvalid = explode(",", $ponlogin)[3];
		$getlocku = explode(",", $ponlogin)[6];
		if ($getlocku == "") {
			$getprice = "Disable";
		} else {
			$getlocku = $getlocku;
		}

		if ($currency == in_array($currency, $cekindo['indo'])) {
			$getprice = $currency . " " . number_format((float)$getprice, 0, ",", ".");
		} else {
			$getprice = $currency . " " . number_format((float)$getprice);
		}
		$ValidPrice = "<b>Validity : " . $getvalid . " | Price : " . $getprice . " | Lock User : " . $getlocku . "</b>";
	}

	$srvlist = $API->comm("/ip/hotspot/print");

	if (isset($_POST['qty'])) {
		$qty = ($_POST['qty']);
		$server = ($_POST['server']);
		$user = ($_POST['user']);
		$userl = ($_POST['userl']);
		$prefix = ($_POST['prefix']);
		$char = ($_POST['char']);
		$profile = ($_POST['profile']);
		$timelimit = ($_POST['timelimit']);
		$datalimit = ($_POST['datalimit']);
		$adcomment = ($_POST['adcomment']);
		$mbgb = ($_POST['mbgb']);
		if ($timelimit == "") {
			$timelimit = "0";
		}
		if ($datalimit == "") {
			$datalimit = "0";
		} else {
			$datalimit = $datalimit * $mbgb;
		}
		if ($adcomment == "") {
			$adcomment = "";
		}
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile"));
		$ponlogin = $getprofile[0]['on-login'];
		$getvalid = explode(",", $ponlogin)[3];
		$getprice = explode(",", $ponlogin)[2];
		$getsprice = explode(",", $ponlogin)[4];
		$getlock = explode(",", $ponlogin)[6];
		$_SESSION['ubp'] = $profile;
		$commt = $user . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $adcomment;
		$gentemp = $commt . "|~" . $profile . "~" . $getvalid . "~" . $getprice . "!".$getsprice."~" . $timelimit . "~" . $datalimit . "~" . $getlock;
		
		// Store in session to prevent write-permission errors on the server
		$_SESSION['genu'] = encrypt($gentemp);
		
		// Silently write to temp.php as a backup if writable, without interrupting the flow
		$temp = './voucher/temp.php';
		$handle = @fopen($temp, 'w');
		if ($handle) {
			$gen = '<?php $genu="'.encrypt($gentemp).'";?>';
			@fwrite($handle, $gen);
			@fclose($handle);
		}

		$a = array("1" => "", "", 1, 2, 2, 3, 3, 4);

		if ($user == "up") {
			for ($i = 1; $i <= $qty; $i++) {
				if ($char == "lower") {
					$u[$i] = randLC($userl);
				} elseif ($char == "upper") {
					$u[$i] = randUC($userl);
				} elseif ($char == "upplow") {
					$u[$i] = randULC($userl);
				} elseif ($char == "mix") {
					$u[$i] = randNLC($userl);
				} elseif ($char == "mix1") {
					$u[$i] = randNUC($userl);
				} elseif ($char == "mix2") {
					$u[$i] = randNULC($userl);
				}
				if ($userl == 3) {
					$p[$i] = randN(3);
				} elseif ($userl == 4) {
					$p[$i] = randN(4);
				} elseif ($userl == 5) {
					$p[$i] = randN(5);
				} elseif ($userl == 6) {
					$p[$i] = randN(6);
				} elseif ($userl == 7) {
					$p[$i] = randN(7);
				} elseif ($userl == 8) {
					$p[$i] = randN(8);
				}
				$u[$i] = "$prefix$u[$i]";
			}
			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$p[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

		if ($user == "vc") {
			$shuf = ($userl - $a[$userl]);
			for ($i = 1; $i <= $qty; $i++) {
				if ($char == "lower") {
					$u[$i] = randLC($shuf);
				} elseif ($char == "upper") {
					$u[$i] = randUC($shuf);
				} elseif ($char == "upplow") {
					$u[$i] = randULC($shuf);
				}
				if ($userl == 3) {
					$p[$i] = randN(1);
				} elseif ($userl == 4 || $userl == 5) {
					$p[$i] = randN(2);
				} elseif ($userl == 6 || $userl == 7) {
					$p[$i] = randN(3);
				} elseif ($userl == 8) {
					$p[$i] = randN(4);
				}
				$u[$i] = "$prefix$u[$i]$p[$i]";

				if ($char == "num") {
					if ($userl == 3) {
						$p[$i] = randN(3);
					} elseif ($userl == 4) {
						$p[$i] = randN(4);
					} elseif ($userl == 5) {
						$p[$i] = randN(5);
					} elseif ($userl == 6) {
						$p[$i] = randN(6);
					} elseif ($userl == 7) {
						$p[$i] = randN(7);
					} elseif ($userl == 8) {
						$p[$i] = randN(8);
					}
					$u[$i] = "$prefix$p[$i]";
				}
				if ($char == "mix") {
					$p[$i] = randNLC($userl);
					$u[$i] = "$prefix$p[$i]";
				}
				if ($char == "mix1") {
					$p[$i] = randNUC($userl);
					$u[$i] = "$prefix$p[$i]";
				}
				if ($char == "mix2") {
					$p[$i] = randNULC($userl);
					$u[$i] = "$prefix$p[$i]";
				}
			}
			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$u[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

		if ($qty < 2) {
			echo "<script>window.location='./?hotspot-user=" . $u[1] . "&session=" . $session . "'</script>";
		} else {
			echo "<script>window.location='./?hotspot-user=generate&session=" . $session . "'</script>";
		}
        exit;
	}

	$getprofile = $API->comm("/ip/hotspot/user/profile/print");
	
	// Load from session or fallback to temp.php file if available
	$genu = isset($_SESSION['genu']) ? $_SESSION['genu'] : '';
	if (empty($genu) && file_exists('./voucher/temp.php')) {
		include_once('./voucher/temp.php');
	}
	
	$genuser = explode("-", decrypt($genu));
	$genuser1 = explode("~", decrypt($genu));
	$umode = $genuser[0];
	$ucode = $genuser[1];
	$udate = $genuser[2];
	$uprofile = $genuser1[1];
	$uvalid = $genuser1[2];
	$ucommt = $genuser[3];
	if ($uvalid == "") {
		$uvalid = "-";
	}
	$uprice = explode("!",$genuser1[3])[0];
	if ($uprice == "0" || $uprice == "") {
		$uprice = "-";
	}
	$suprice = explode("!",$genuser1[3])[1];
	if ($suprice == "0" || $suprice == "") {
		$suprice = "-";
	}
	$utlimit = $genuser1[4];
	if ($utlimit == "0" || $utlimit == "") {
		$utlimit = "-";
	}
	$udlimit = $genuser1[5];
	if ($udlimit == "0" || $udlimit == "") {
		$udlimit = "-";
	} else {
		$udlimit = formatBytes($udlimit, 2);
	}
	$ulock = $genuser1[6];
	$urlprint = explode("|", decrypt($genu))[0];
	if ($currency == in_array($currency, $cekindo['indo'])) {
        if ($uprice != "-") $uprice = $currency . " " . number_format((float)$uprice, 0, ",", ".");
        if ($suprice != "-") $suprice = $currency . " " . number_format((float)$suprice, 0, ",", ".");
	} else {
        if ($uprice != "-") $uprice = $currency . " " . number_format((float)$uprice);
        if ($suprice != "-") $suprice = $currency . " " . number_format((float)$suprice);
	}
}
?>

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

/* Data Limit Wrapper */
.modern-data-limit-wrapper {
    display: flex !important;
    width: 100% !important;
    box-sizing: border-box;
}

.modern-data-limit-wrapper input.form-control {
    border-radius: 12px 0 0 12px !important;
    border-right: none !important;
    flex: 1 !important;
}

.modern-data-limit-wrapper input.form-control:focus {
    border-right: 1px solid var(--primary) !important;
}

.modern-data-limit-wrapper select.data-unit-select.form-control {
    border-radius: 0 12px 12px 0 !important;
    width: 80px !important;
    background: var(--background-alt, #F5F6F7) !important;
    font-weight: 700 !important;
}

/* Modern Action Buttons Style */
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

.btn-modern-action.btn-list {
    background: rgba(236, 72, 153, 0.08) !important;
    color: #ec4899 !important;
    border-color: rgba(236, 72, 153, 0.15) !important;
}
.btn-modern-action.btn-list:hover {
    background: #ec4899 !important;
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

.btn-modern-action.btn-print {
    background: #1e293b !important;
    color: #ffffff !important;
}
.btn-modern-action.btn-print:hover {
    background: #0f172a !important;
    transform: translateY(-1px);
}

.btn-modern-action.btn-qr {
    background: #ef4444 !important;
    color: #ffffff !important;
}
.btn-modern-action.btn-qr:hover {
    background: #dc2626 !important;
    transform: translateY(-1px);
}

.btn-modern-action.btn-small {
    background: #0284c7 !important;
    color: #ffffff !important;
}
.btn-modern-action.btn-small:hover {
    background: #0369a1 !important;
    transform: translateY(-1px);
}

/* Detail Table styling for Last Generate */
.last-gen-table {
    width: 100% !important;
    border-collapse: collapse !important;
}
.last-gen-table tr {
    border-bottom: 1px solid var(--border-color) !important;
}
.last-gen-table tr:last-child {
    border-bottom: none !important;
}
.last-gen-table td {
    padding: 12px 8px !important;
    font-size: 13.5px !important;
    color: var(--text-main) !important;
    border: none !important;
    text-align: left !important;
}
.last-gen-table td:first-child {
    font-weight: 700 !important;
    color: var(--text-muted) !important;
    width: 45% !important;
}

/* Readme Card Info Alert Box */
.readme-info-alert {
    background: var(--primary-glow) !important;
    border: 1px solid rgba(99, 102, 241, 0.15) !important;
    border-radius: 12px !important;
    padding: 14px 18px !important;
    display: flex !important;
    gap: 12px !important;
    margin-top: 16px !important;
    box-sizing: border-box;
}
.readme-info-alert-icon {
    font-size: 18px !important;
    color: var(--primary) !important;
}
.readme-info-alert-content {
    text-align: left !important;
}
.readme-info-alert-content h4 {
    margin: 0 0 4px 0 !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    color: var(--primary) !important;
}
.readme-info-alert-content p {
    margin: 0 !important;
    font-size: 11.5px !important;
    color: var(--text-main) !important;
    line-height: 1.5 !important;
}
</style>

<div class="gen-row-flex">
  <div class="gen-col-left">
    <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important;">
      <div class="card-header">
        <h3><i class="fa fa-user-plus"></i> <?= $_generate_user ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
      </div>
      <div class="card-body" style="padding: 28px !important;">
        <form autocomplete="off" method="post" action="">
          
          <div class="gen-actions-wrapper" style="margin-bottom: 24px; display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($_SESSION['ubp'] != "") {
              echo "    <a class='btn-modern-action btn-close' href='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
            } elseif ($_SESSION['vcr'] = "active") {
              echo "    <a class='btn-modern-action btn-close' href='./?hotspot=users-by-profile&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
            } else {
              echo "    <a class='btn-modern-action btn-close' href='./?hotspot=users&profile=all&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
            }
            ?>
            
            <a class="btn-modern-action btn-list" title="Open User List by Profile" href="./?hotspot=users&profile=<?php echo ($_SESSION['ubp'] == "") ? "all" : $uprofile; ?>&session=<?= $session; ?>"> <i class="fa fa-users"></i> <?= $_user_list ?></a>
            <button type="submit" name="save" onclick="loader()" class="btn-modern-action btn-submit" title="Generate User"> <i class="fa fa-save"></i> <?= $_generate ?></button>
            <a class="btn-modern-action btn-print" title="Print Default" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=no&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print ?></a>
            <a class="btn-modern-action btn-qr" title="Print QR" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-qrcode"></i> <?= $_print_qr ?></a>
            <a class="btn-modern-action btn-small" title="Print Small" href="./voucher/print.php?id=<?= $urlprint; ?>&small=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print_small ?></a>
          </div>

          <div class="form-section-title">Detail Pembuatan</div>
          
          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="qty"><?= $_qty ?></label>
              <input class="form-control" id="qty" type="number" name="qty" min="1" max="500" value="1" required="1" placeholder="Jumlah voucher">
            </div>
            
            <div class="form-field-group">
              <label for="server">Hotspot Server</label>
              <select class="form-control" id="server" name="server" required="1">
                <option>all</option>
                <?php $TotalReg = count($srvlist);
                for ($i = 0; $i < $TotalReg; $i++) {
                  echo "<option>" . $srvlist[$i]['name'] . "</option>";
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="user"><?= $_user_mode ?></label>
              <select class="form-control" onchange="defUserl();" id="user" name="user" required="1">
                <option value="up"><?= $_user_pass ?></option>
                <option value="vc"><?= $_user_user ?></option>
              </select>
            </div>
            
            <div class="form-field-group">
              <label for="userl"><?= $_user_length ?></label>
              <select class="form-control" id="userl" name="userl" required="1">
                <option>4</option>
                <option>3</option>
                <option>4</option>
                <option>5</option>
                <option>6</option>
                <option>7</option>
                <option>8</option>
              </select>
            </div>
          </div>

          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="prefix"><?= $_prefix ?></label>
              <input class="form-control" id="prefix" type="text" size="6" maxlength="6" autocomplete="off" name="prefix" value="" placeholder="Awalan username (Opsional)">
            </div>
            
            <div class="form-field-group">
              <label for="char"><?= $_character ?></label>
              <select class="form-control" id="char" name="char" required="1">
                <option id="lower" style="display:block;" value="lower"><?= $_random ?> abcd</option>
                <option id="upper" style="display:block;" value="upper"><?= $_random ?> ABCD</option>
                <option id="upplow" style="display:block;" value="upplow"><?= $_random ?> aBcD</option>
                <option id="lower1" style="display:none;" value="lower"><?= $_random ?> abcd2345</option>
                <option id="upper1" style="display:none;" value="upper"><?= $_random ?> ABCD2345</option>
                <option id="upplow1" style="display:none;" value="upplow"><?= $_random ?> aBcD2345</option>
                <option id="mix" style="display:block;" value="mix"><?= $_random ?> 5ab2c34d</option>
                <option id="mix1" style="display:block;" value="mix1"><?= $_random ?> 5AB2C34D</option>
                <option id="mix2" style="display:block;" value="mix2"><?= $_random ?> 5aB2c34D</option>
                <option id="num" style="display:none;" value="num"><?= $_random ?> 1234</option>
              </select>
            </div>
          </div>

          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="uprof"><?= $_profile ?></label>
              <select class="form-control" onchange="GetVP();" id="uprof" name="profile" required="1">
                <?php if ($genprof != "") {
                  echo "<option>" . $genprof . "</option>";
                }
                $TotalReg = count($getprofile);
                for ($i = 0; $i < $TotalReg; $i++) {
                  echo "<option>" . $getprofile[$i]['name'] . "</option>";
                }
                ?>
              </select>
            </div>
            
            <div class="form-field-group">
              <label for="timelimit"><?= $_time_limit ?></label>
              <input class="form-control" id="timelimit" type="text" size="4" autocomplete="off" name="timelimit" value="" placeholder="Contoh: 30d, 12h">
            </div>
          </div>

          <div class="form-row-grid">
            <div class="form-field-group">
              <label for="datalimit"><?= $_data_limit ?></label>
              <div class="modern-data-limit-wrapper">
                <input class="form-control" id="datalimit" type="number" min="0" max="9999" name="datalimit" value="<?= $udatalimit; ?>" placeholder="0">
                <select class="form-control data-unit-select" name="mbgb" required="1">
                  <option value=1048576>MB</option>
                  <option value=1073741824>GB</option>
                </select>
              </div>
            </div>
            
            <div class="form-field-group">
              <label for="comment"><?= $_comment ?></label>
              <input class="form-control" type="text" title="No special characters" id="comment" autocomplete="off" name="adcomment" value="" placeholder="Komentar opsional">
            </div>
          </div>

          <div id="GetValidPrice" style="text-align: left;">
            <?php if ($genprof != "") {
              echo $ValidPrice;
            } ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <div class="gen-col-right">
    <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin: 0 !important;">
      <div class="card-header">
        <h3><i class="fa fa-ticket"></i> <?= $_last_generate ?></h3>
      </div>
      <div class="card-body" style="padding: 24px !important;">
        <table class="last-gen-table">
          <tr>
            <td><?= $_generate_code ?></td><td><?= $ucode ?></td>
          </tr>
          <tr>
            <td><?= $_date ?></td><td><?= $udate ?></td>
          </tr>
          <tr>
            <td><?= $_profile ?></td><td><?= $uprofile ?></td>
          </tr>
          <tr>
            <td><?= $_validity ?></td><td><?= $uvalid ?></td>
          </tr>
          <tr>
            <td><?= $_time_limit ?></td><td><?= $utlimit ?></td>
          </tr>
          <tr>
            <td><?= $_data_limit ?></td><td><?= $udlimit ?></td>
          </tr>
          <tr>
            <td><?= $_price ?></td><td><?= $uprice ?></td>
          </tr>
          <tr>
            <td><?= $_selling_price ?></td><td><?= $suprice ?></td>
          </tr>
          <tr>
            <td><?= $_lock_user ?></td><td><?= $ulock ?></td>
          </tr>
        </table>
        
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
