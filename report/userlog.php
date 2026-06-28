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

	$idhr = $_GET['idhr'];
	$idbl = $_GET['idbl'];
	$remdata = ($_POST['remdata']);


	if (strlen($idhr) > "0") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$API->write('/system/script/print', false);
			$API->write('?=source=' . $idhr . '');
			$ARRAY = $API->read();
			$API->disconnect();
		}
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "text";
	} elseif (strlen($idbl) > "0") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$API->write('/system/script/print', false);
			$API->write('?=owner=' . $idbl . '');
			$ARRAY = $API->read();
			$API->disconnect();
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "text";
	} elseif ($idhr == "" || $idbl == "") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$API->write('/system/script/print', false);
			$API->write('?=comment=mikhmon');
			$ARRAY = $API->read();
			$API->disconnect();
		}
		$filedownload = "all";
		$shf = "text";
		$shd = "hidden";
	}
}
?>
		<script>
			function downloadCSV(csv, filename) {
			  var csvFile;
			  var downloadLink;
			  // CSV file
			  csvFile = new Blob([csv], {type: "text/csv"});
			  // Download link
			  downloadLink = document.createElement("a");
			  // File name
			  downloadLink.download = filename;
			  // Create a link to the file
			  downloadLink.href = window.URL.createObjectURL(csvFile);
			  // Hide download link
			  downloadLink.style.display = "none";
			  // Add the link to DOM
			  document.body.appendChild(downloadLink);
			  // Click download link
			  downloadLink.click();
			  }
			  
			  function exportTableToCSV(filename) {
			    var csv = [];
			    var rows = document.querySelectorAll("#dataTable tr");
			    
			   for (var i = 0; i < rows.length; i++) {
			      var row = [], cols = rows[i].querySelectorAll("td, th");
			   for (var j = 0; j < cols.length; j++)
            row.push(cols[j].innerText);
        csv.push(row.join(","));
        }
        // Download CSV file
        downloadCSV(csv.join("\n"), filename);
        }

		</script>
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
.filter-dates-group {
    display: flex !important;
    gap: 8px !important;
    align-items: center !important;
    flex-wrap: wrap !important;
}
.filter-control-modern {
    height: 38px !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    background: var(--bg-card, #ffffff) !important;
    color: var(--text-main) !important;
    padding: 0 12px !important;
    font-size: 13px !important;
    outline: none !important;
    transition: all 0.2s ease !important;
    box-sizing: border-box !important;
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
.btn-modern-filter-action.btn-secondary {
    background: rgba(148, 163, 184, 0.08) !important;
    color: #475569 !important;
    border: 1px solid rgba(148, 163, 184, 0.15) !important;
}
.btn-modern-filter-action.btn-secondary:hover {
    background: rgba(148, 163, 184, 0.15) !important;
    color: #1e293b !important;
}
</style>

<div class="row">
<div class="col-12">
<div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
<div class="card-header">
	<h3><i class=" fa fa-align-justify"></i> User Log <?= $idhr . $idbl; ?></h3>
</div>
<div class="card-body" style="padding: 24px !important;">
	<div class="filter-row-flex">
		<div class="filter-inputs-group">	   
		  <input id="filterTable" type="text" class="filter-control-modern" style="flex: 1; min-width: 120px;" placeholder="Search..">
		  <button class="btn-modern-filter-action btn-secondary" onclick="exportTableToCSV('user-log-mikhmon-<?= $filedownload; ?>.csv')" title="Download user log"><i class="fa fa-download"></i> CSV</button>
		  <button class="btn-modern-filter-action btn-secondary" onclick="location.href='./?report=userlog&session=<?= $session; ?>';" title="Reload all data"><i class="fa fa-search"></i> <?= $_all ?></button>
		</div>
		
		<div class="filter-dates-group">  
			<select class="filter-control-modern" style="width: 75px;" title="Day" id="D">
        			<?php
										$day = explode("/", $idhr)[1];
										if ($day != "") {
											echo "<option value='" . $day . "'>" . $day . "</option>";
										}
										echo "<option value=''>Day</option>";

										for ($x = 1; $x <= 31; $x++) {
											if (strlen($x) == 1) {
												$x = "0" . $x;
											} else {
												$x = $x;
											}
											echo "<option value='" . $x . "'>" . $x . "</option>";
										}
										?> 
    		</select>
			
			<select class="filter-control-modern" style="width: 120px;" title="Month" id="M">
        			<?php 
										$idbls = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
										$idblf = array(1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
										$month = explode("/", $idhr)[0];
										$month1 = substr($idbl, 0, 3);

										if ($month != "") {
											$fm = array_search($month, $idbls);
											echo "<option value='" . $month . "'>" . $idblf[$fm] . "</option>";
										} elseif ($month1 != "") {
											$fm = array_search($month1, $idbls);
											echo "<option value=" . $month1 . ">" . $idblf[$fm] . "</option>";
										} else {
											echo "<option value=" . $idbls[date("n")] . ">" . $idblf[date("n")] . "</option>";
										}
										for ($x = 1; $x <= 12; $x++) {
											echo "<option value='" . $idbls[$x] . "''>" . $idblf[$x] . "</option>";
										}
										?> 
    		</select>
			
			<select class="filter-control-modern" style="width: 90px;" title="Year" id="Y">
        			<?php 
										$year = explode("/", $idhr)[2];
										$year1 = substr($idbl, 3, 4);

										if ($year != "") {
											echo "<option>" . $year . "</option>";
										} elseif ($year1 != "") {
											echo "<option>" . $year1 . "</option>";
										} else {
											echo "<option>" . date("Y") . "</option>";
										}
										for ($Y = 2018; $Y <= date("Y"); $Y++) {
											if ($Y == date("Y")) {
											} else {
												echo "<option value='" . $Y . "''>" . $Y . "</option>";
											}
										}
										?> 
    		</select>
            
			<button class="btn-modern-filter-action btn-accent" onclick="filterR();" title="Filter"><i class="fa fa-search"></i> Filter</button>
			
			<script type="text/javascript">
				function filterR(){
					var D = document.getElementById('D').value;
					var M = document.getElementById('M').value;
					var Y = document.getElementById('Y').value;

					if(D !== ""){
						window.location='./?report=userlog&idhr='+M+'/'+D+'/'+Y+'&session=<?= $session; ?>';
					}else if(D === ""){
						window.location='./?report=userlog&idbl='+M+Y+'&session=<?= $session; ?>';
					}
				}
			</script>
		</div>
	</div>  
		  <div class="overflow box-bordered" style="max-height: 75vh;">
			<table id="dataTable" class="table table-bordered table-hover text-nowrap">
				<thead>
				<tr>
				  <th colspan=6 >User Log <?= $filedownload; ?></th>
				</tr>
				<tr>
					<th ><?= $_date ?></th>
					<th ><?= $_time ?></th>
					<th ><?= $_user_name ?></th>
					<th >address</th>
					<th >Mac Address</th>
					<th ><?= $_validity ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
			$TotalReg = count($ARRAY);

			for ($i = 0; $i < $TotalReg; $i++) {
				$regtable = $ARRAY[$i];
				echo "<tr>";
				echo "<td>";
				$getname = explode("-|-", $regtable['name']);
				$tgl = $getname[0];
				echo $tgl;
				echo "</td>";
				echo "<td>";
				$ltime = $getname[1];
				echo $ltime;
				echo "</td>";
				echo "<td>";
				$username = $getname[2];
				echo $username;
				echo "</td>";
				echo "<td>";
				$addr = $getname[4];
				echo $addr;
				echo "</td>";
				echo "<td>";
				$mac = $getname[5];
				echo $mac;
				echo "</td>";
				echo "<td>";
				$val = $getname[6];
				echo $val;
				echo "</td>";
				echo "</tr>";
			}
			?>
				</tbody>
			</table>
		</div>
</div>
</div>
</div>
</div>
