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

  // get MikroTik system clock
  $getclock = $API->comm("/system/clock/print");
  $clock = is_array($getclock) ? $getclock[0] : null;
  $timezone = isset($clock['time-zone-name']) ? $clock['time-zone-name'] : (isset($_SESSION['timezone']) ? $_SESSION['timezone'] : '');
  if (!empty($timezone)) {
    $_SESSION['timezone'] = $timezone;
    date_default_timezone_set($timezone);
  }
  $_SESSION[$session.'sdate'] = isset($clock['date']) ? $clock['date'] : '';

  // get system resource MikroTik
  $getresource = $API->comm("/system/resource/print", array(
    ".proplist" => "uptime,version,free-memory,total-memory,cpu-load,free-hdd-space,total-hdd-space,board-name"
  ));
  $resource = is_array($getresource) ? $getresource[0] : null;

  // get routeboard info
  if (isset($_SESSION[$session.'_routerboard'])) {
    $routerboard = $_SESSION[$session.'_routerboard'];
  } else {
    $getrouterboard = $API->comm("/system/routerboard/print");
    $routerboard = is_array($getrouterboard) ? $getrouterboard[0] : null;
    if (is_array($routerboard)) {
      $_SESSION[$session.'_routerboard'] = $routerboard;
    }
  }

  // get & counting hotspot users
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if (!is_numeric($countallusers)) {
    $countallusers = '-';
    $uunit = "items";
  } elseif ($countallusers < 2) {
    $uunit = "item";
  } else {
    $uunit = "items";
  }

  // get & counting hotspot active
  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if (!is_numeric($counthotspotactive)) {
    $counthotspotactive = '-';
    $hunit = "items";
  } elseif ($counthotspotactive < 2) {
    $hunit = "item";
  } else {
    $hunit = "items";
  }

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
    $col_class = "col-6";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
    $col_class = "col-4";
  }

  // Calculate resource percentages & define UI variables
  $board_name = isset($resource['board-name']) ? $resource['board-name'] : '-';
  $model = isset($routerboard['model']) ? $routerboard['model'] : '-';
  $version = isset($resource['version']) ? $resource['version'] : '-';
  $uptime = (isset($resource['uptime']) && !empty($resource['uptime'])) ? formatDTM($resource['uptime']) : '-';
  $time = isset($clock['time']) ? $clock['time'] : '-';
  $date = isset($clock['date']) ? $clock['date'] : '-';

  $cpu_load = (isset($resource['cpu-load']) && is_numeric($resource['cpu-load'])) ? $resource['cpu-load'] : '-';

  $total_mem = isset($resource['total-memory']) ? $resource['total-memory'] : 1;
  if ($total_mem <= 0) $total_mem = 1;
  $mem_pct = (isset($resource['free-memory']) && isset($resource['total-memory'])) ? round((($total_mem - $resource['free-memory']) / $total_mem) * 100) : '-';
  $free_mem_bytes = isset($resource['free-memory']) ? formatBytes($resource['free-memory'], 1) : '-';
  
  $total_hdd = isset($resource['total-hdd-space']) ? $resource['total-hdd-space'] : 1;
  if ($total_hdd <= 0) $total_hdd = 1;
  $hdd_pct = (isset($resource['free-hdd-space']) && isset($resource['total-hdd-space'])) ? round((($total_hdd - $resource['free-hdd-space']) / $total_hdd) * 100) : '-';
  $free_hdd_bytes = isset($resource['free-hdd-space']) ? formatBytes($resource['free-hdd-space'], 1) : '-';
}
?>
    
<style>
/* ═══════════════════════════════════════════════════════════════
   MIKHTRANS DASHBOARD — FRESH DARK MODERN DESIGN
   ═══════════════════════════════════════════════════════════════ */

#reloadHome {
    font-family: 'Plus Jakarta Sans', -apple-system, sans-serif !important;
}

/* ─── Welcome Banner ─── */
.dash-welcome {
    background: var(--welcome-bg) !important;
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-color) !important;
}
.dash-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.dash-welcome::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: 10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.dash-welcome-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dash-welcome h2 {
    font-size: 22px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.dash-welcome p {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.65);
    margin: 0;
    font-weight: 500;
}
.dash-welcome-time {
    text-align: right;
}
.dash-welcome-time .time-big {
    font-size: 36px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -1px;
    line-height: 1;
}
.dash-welcome-time .time-date {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.55);
    font-weight: 500;
    margin-top: 4px;
}

/* ─── System Resource Cards (r_1) ─── */
#r_1 {
    display: contents !important;
}
#r_1 > .col-4,
#r_1 > .col-3,
.row > .col-4,
.row > .col-3 {
    display: flex !important;
    flex-direction: column !important;
}
#r_1 .box.box-bordered {
    background: var(--bg-card) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: var(--radius) !important;
    padding: 24px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-sizing: border-box;
    height: 100% !important;
    box-shadow: var(--shadow-card) !important;
    flex: 1 !important;
}
#r_1 .box.box-bordered:hover {
    border-color: var(--border-hover) !important;
    box-shadow: 0px 8px 24px rgba(0, 0, 0, 0.05) !important;
    transform: translateY(-1px);
}

#r_1 .box-group {
    display: flex !important;
    align-items: flex-start !important;
    gap: 14px !important;
    height: 100% !important;
}

#r_1 .box-group-icon {
    font-size: 18px !important;
    width: 44px !important;
    height: 44px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    margin: 0 !important;
    float: none !important;
    transition: all 0.3s ease;
}
.icon-indigo { background: rgba(60, 80, 224, 0.1) !important; color: var(--primary) !important; }
.icon-cyan { background: rgba(33, 150, 83, 0.1) !important; color: #219653 !important; }
.icon-violet { background: rgba(245, 158, 11, 0.1) !important; color: #ffa70b !important; }

#r_1 .box-group-area {
    flex: 1;
    float: none !important;
    padding: 0 !important;
    margin: 0 !important;
    text-align: left !important;
}

.stat-title {
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #6b7280 !important;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 4px;
}
.stat-main-val {
    font-size: 20px !important;
    font-weight: 800 !important;
    color: var(--text-bright) !important;
    margin-bottom: 3px;
    letter-spacing: -0.5px;
    line-height: 1.2;
}
.stat-sub-val {
    font-size: 11px !important;
    color: #6b7280 !important;
    font-weight: 500;
}

/* ─── Progress Bars (CPU/Memory) ─── */
.sys-metric { display: flex; flex-direction: column; width: 100%; }
.metric-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: var(--text-main);
    margin-bottom: 5px;
    font-weight: 600;
}
.metric-bar {
    width: 100%;
    height: 5px;
    background: var(--border-color);
    border-radius: 10px;
    overflow: hidden;
}
.metric-bar-fill {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, #6366f1, #818cf8);
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.metric-bar-fill.bg-emerald {
    background: linear-gradient(90deg, #10b981, #34d399);
}
.metric-bar-fill.bg-amber {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

/* ─── Hotspot Overview Panel (r_2) ─── */
.card-hotspot-panel {
    background: var(--bg-card) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: var(--radius) !important;
    box-shadow: var(--shadow-card) !important;
    margin-bottom: 24px !important;
    overflow: hidden !important;
}
.card-hotspot-panel .card-header {
    background: transparent !important;
    border-bottom: 1px solid var(--border-color) !important;
    padding: 18px 24px !important;
}
.card-hotspot-panel .card-header h3 {
    font-size: 15px !important;
    font-weight: 700 !important;
    color: var(--text-main) !important;
    margin: 0 !important;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-hotspot-panel .card-body {
    padding: 20px 24px !important;
}

/* Action cards inside hotspot panel */
#r_2 .box {
    border-radius: var(--radius) !important;
    padding: 24px 14px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    border: 1px solid transparent !important;
    overflow: hidden;
    height: auto !important;
    box-sizing: border-box;
    text-align: center;
    margin: 4px !important;
}
#r_2 .box:hover {
    transform: translateY(-3px);
}
#r_2 .box a { text-decoration: none !important; }

.bg-blue-modern { background: rgba(14, 165, 233, 0.08) !important; border-color: rgba(14, 165, 233, 0.12) !important; }
.bg-blue-modern:hover { background: rgba(14, 165, 233, 0.14) !important; box-shadow: 0 8px 24px rgba(14, 165, 233, 0.12); }
.bg-blue-modern .action-card-icon { background: rgba(14, 165, 233, 0.15) !important; color: #38bdf8 !important; }
.bg-blue-modern .action-card-val { color: #38bdf8 !important; }

.bg-green-modern { background: rgba(16, 185, 129, 0.08) !important; border-color: rgba(16, 185, 129, 0.12) !important; }
.bg-green-modern:hover { background: rgba(16, 185, 129, 0.14) !important; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.12); }
.bg-green-modern .action-card-icon { background: rgba(16, 185, 129, 0.15) !important; color: #34d399 !important; }
.bg-green-modern .action-card-val { color: #34d399 !important; }

.bg-yellow-modern { background: rgba(245, 158, 11, 0.08) !important; border-color: rgba(245, 158, 11, 0.12) !important; }
.bg-yellow-modern:hover { background: rgba(245, 158, 11, 0.14) !important; box-shadow: 0 8px 24px rgba(245, 158, 11, 0.12); }
.bg-yellow-modern .action-card-icon { background: rgba(245, 158, 11, 0.15) !important; color: #fbbf24 !important; }
.bg-yellow-modern .action-card-val { color: #fbbf24 !important; }

.bg-red-modern { background: rgba(239, 68, 68, 0.08) !important; border-color: rgba(239, 68, 68, 0.12) !important; }
.bg-red-modern:hover { background: rgba(239, 68, 68, 0.14) !important; box-shadow: 0 8px 24px rgba(239, 68, 68, 0.12); }
.bg-red-modern .action-card-icon { background: rgba(239, 68, 68, 0.15) !important; color: #f87171 !important; }
.bg-red-modern .action-card-val { color: #f87171 !important; }

.action-card-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 17px; margin-bottom: 12px;
}
.action-card-val {
    font-size: 26px; font-weight: 800; margin-bottom: 4px;
    letter-spacing: -0.5px; line-height: 1.2;
}
.action-card-label {
    font-size: 11px; font-weight: 700; color: #6b7280;
    text-transform: uppercase; letter-spacing: 0.3px;
}

/* ─── Generic Card & Traffic ─── */
.card {
    background: var(--bg-card) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: var(--radius) !important;
    box-shadow: var(--shadow-card) !important;
    margin-bottom: 24px !important;
    overflow: hidden;
}
.card-header {
    background: transparent !important;
    border-bottom: 1px solid var(--border-color) !important;
    padding: 18px 24px !important;
}
.card-header h3 {
    font-size: 15px !important;
    font-weight: 700 !important;
    color: var(--text-main) !important;
    margin: 0 !important;
}
.card-header h3 a {
    color: var(--primary) !important;
    font-weight: 700 !important;
    font-size: 15px !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.card-body { padding: 20px 24px !important; }

/* Traffic chart */
#trafficMonitor {
    border-radius: var(--radius) !important; overflow: hidden;
    background: transparent !important;
}
.highcharts-background { fill: transparent !important; }
.highcharts-plot-background { fill: transparent !important; }
.highcharts-grid-line { stroke: var(--border-color) !important; }
.highcharts-axis-line { stroke: var(--border-color) !important; }
.highcharts-tick { stroke: var(--border-color) !important; }
.highcharts-title { fill: var(--text-main) !important; font-weight: 700 !important; font-family: inherit !important; font-size: 14px !important; }
.highcharts-legend-item text { fill: var(--text-muted) !important; }
.highcharts-axis-labels text { fill: var(--text-muted) !important; }
.highcharts-tooltip-box { fill: var(--bg-card) !important; stroke: var(--border-color) !important; }

/* ─── Income Card (r_4) ─── */
#r_4 .box.box-bordered {
    background: var(--bg-card) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: var(--radius) !important;
    box-shadow: var(--shadow-card) !important;
    padding: 24px !important;
    transition: all 0.3s ease;
}
#r_4 .box.box-bordered:hover {
    border-color: rgba(33, 150, 83, 0.3) !important;
    box-shadow: 0px 8px 24px rgba(0, 0, 0, 0.05) !important;
    transform: translateY(-1px);
}
#r_4 .box-group { display: flex !important; align-items: center !important; }
#r_4 .box-group-icon {
    font-size: 18px !important;
    color: #10b981 !important;
    background: rgba(16, 185, 129, 0.12) !important;
    width: 44px !important; height: 44px !important;
    border-radius: 50% !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    float: none !important; margin-right: 14px !important; flex-shrink: 0;
}
#reloadLreport {
    font-size: 13px !important; line-height: 1.7 !important; color: var(--text-main) !important;
}

/* ─── Log Table Styling ─── */
.card-table-modern {
    border-collapse: separate !important;
    background: transparent !important;
    width: 100% !important;
}
.card-table-modern thead { display: none !important; }
#r_3 .card-table-modern tbody {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
    padding: 0 !important;
}
#r_3 .card-table-modern tr {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    background: transparent !important;
    border: none !important;
    border-bottom: 1px solid var(--border-color) !important;
    border-radius: 0 !important;
    padding: 10px 4px !important;
    transition: all 0.2s ease !important;
    box-shadow: none !important;
    gap: 14px !important;
}
#r_3 .card-table-modern tr:last-child {
    border-bottom: none !important;
}
#r_3 .card-table-modern tr:hover {
    background: var(--hover-bg) !important;
}
#r_3 .card-table-modern td {
    display: block !important;
    width: auto !important;
    border: none !important;
    padding: 0 !important;
    background: transparent !important;
    color: var(--text-main) !important;
}
#r_3 .card-table-modern td:first-child {
    width: auto !important;
    white-space: nowrap !important;
    flex-shrink: 0;
}
#r_3 .card-table-modern td:nth-child(2) {
    flex: 1 !important;
    min-width: 70px !important;
    text-align: left !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
#r_3 .card-table-modern td:last-child {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    min-width: 90px !important;
    text-align: right !important;
    flex-shrink: 0;
}

/* Status Indicators */
.log-status {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}
.log-status i {
    font-size: 13px !important;
}
.log-status.success { color: var(--success) !important; }
.log-status.danger { color: var(--danger) !important; }
.log-status.warning { color: var(--warning) !important; }
.log-status.info { color: var(--info) !important; }
.log-status.dark { color: var(--text-muted) !important; }
.log-status.default { color: var(--text-muted) !important; }

/* ─── Animations ─── */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
#reloadHome > * {
    animation: fadeInUp 0.5s ease forwards;
}
#reloadHome > *:nth-child(2) { animation-delay: 0.08s; }
#reloadHome > *:nth-child(3) { animation-delay: 0.15s; }

/* ─── Responsive ─── */
@media (max-width: 750px) {
    .dash-welcome { padding: 24px 20px; }
    .dash-welcome h2 { font-size: 18px; }
    .dash-welcome-time .time-big { font-size: 28px; }
    .dash-welcome-content { flex-direction: column; align-items: flex-start; gap: 12px; }
    .dash-welcome-time { text-align: left; width: 100%; }
}
</style>

<div id="reloadHome">

    <!-- ═══ Welcome Banner ═══ -->
    <div class="dash-welcome">
      <div class="dash-welcome-content">
        <div>
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
            <div class="dash-welcome-tag" style="margin-bottom: 0 !important;">Active Connection</div>
            <div id="sync-status-badge" class="sync-status connected"><span class="status-dot connected"></span> Connected</div>
          </div>
          <h2><?= $board_name ?></h2>
          <div class="dash-welcome-badges">
            <span class="welcome-badge"><i class="fa fa-microchip"></i> <?= $model ?></span>
            <span class="welcome-badge"><i class="fa fa-code-fork"></i> v<?= $version ?></span>
            <span class="welcome-badge"><i class="fa fa-clock-o"></i> <?= $uptime ?></span>
          </div>
        </div>
        <div class="dash-welcome-time">
          <div class="time-big"><?= $time ?></div>
          <div class="time-date"><i class="fa fa-calendar"></i> <?= ucfirst($date) ?> &bull; <?= $timezone ?></div>
        </div>
      </div>
    </div>

    <!-- ═══ System Resource Row (r_1) ═══ -->
    <div class="row" style="display: flex !important; flex-wrap: wrap !important;">
      <div id="r_1" style="display: contents;">
        <!-- Card 1: System Resources (CPU & RAM) -->
        <div class="<?= $col_class ?>">
          <div class="box bmh-75 box-bordered">
            <div class="box-group">
              <div class="box-group-icon icon-cyan"><i class="fa fa-microchip"></i></div>
              <div class="box-group-area">
                <div class="stat-title">System Resources</div>
                <div class="sys-metric">
                  <div class="metric-info">
                    <span>CPU <strong id="cpuVal" data-val="<?= $cpu_load ?>"><?= $cpu_load ?><?= is_numeric($cpu_load) ? '%' : '' ?></strong></span>
                    <canvas id="cpuSparkline" width="60" height="14"></canvas>
                  </div>
                  <div class="metric-bar"><div class="metric-bar-fill" style="width: <?= is_numeric($cpu_load) ? $cpu_load : 0 ?>%;"></div></div>
                </div>
                <div class="sys-metric" style="margin-top: 8px;">
                  <div class="metric-info">
                    <span>RAM <strong id="memVal" data-val="<?= $mem_pct ?>"><?= $mem_pct ?><?= is_numeric($mem_pct) ? '%' : '' ?></strong></span>
                    <canvas id="memorySparkline" width="60" height="14"></canvas>
                  </div>
                  <div class="metric-bar"><div class="metric-bar-fill bg-emerald" style="width: <?= is_numeric($mem_pct) ? $mem_pct : 0 ?>%;"></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Card 2: Storage & Memory -->
        <div class="<?= $col_class ?>">
          <div class="box bmh-75 box-bordered">
            <div class="box-group">
              <div class="box-group-icon icon-violet"><i class="fa fa-hdd-o"></i></div>
              <div class="box-group-area">
                <div class="stat-title">Storage & Memory</div>
                <div class="stat-main-val"><?= $free_mem_bytes ?> <span style="font-size:12px;color:#6b7280;font-weight:600;">free RAM</span></div>
                <div class="sys-metric" style="margin-top: 6px;">
                  <div class="metric-info">
                    <span>HDD <?= $hdd_pct ?><?= is_numeric($hdd_pct) ? '%' : '' ?></span>
                    <span style="color:#6b7280;"><?= $free_hdd_bytes ?> free</span>
                  </div>
                  <div class="metric-bar"><div class="metric-bar-fill bg-amber" style="width: <?= is_numeric($hdd_pct) ? $hdd_pct : 0 ?>%;"></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Card 4: Income (Moved from Sidebar) -->
      <div id="r_4" class="<?= $col_class ?>" <?= $lreport; ?>>
        <div class="box bmh-75 box-bordered" style="height: 100%;">
          <div class="box-group">
            <div class="box-group-icon icon-emerald"><i class="fa fa-money"></i></div>
            <div class="box-group-area" style="width: 100%;">
              <div class="stat-title"><?= $_income ?></div>
              <div id="reloadLreport">
                <?php 
                if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){
                ?>
                  <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 4px;">
                    <!-- Today -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">
                      <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);"><?= $_today ?></span>
                      <span style="font-size: 12px; font-weight: 700; color: var(--text-bright);"><?= $currency ?> <?= $_SESSION[$session.'dincome'] ?> <span style="font-size: 10px; font-weight: 600; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 1px 5px; border-radius: 10px; margin-left: 4px;"><?= $_SESSION[$session.'totalHr'] ?> vcr</span></span>
                    </div>
                    <!-- This Month -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 2px;">
                      <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);"><?= $_this_month ?></span>
                      <span style="font-size: 12px; font-weight: 700; color: var(--primary);"><?= $currency ?> <?= $_SESSION[$session.'mincome'] ?> <span style="font-size: 10px; font-weight: 600; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 1px 5px; border-radius: 10px; margin-left: 4px;"><?= $_SESSION[$session.'totalBl'] ?> vcr</span></span>
                    </div>
                  </div>
                <?php 
                } else {
                  echo "<div id='loader'><i><span><i class='fa fa-circle-o-notch fa-spin'></i> " . $_processing . "</span></i></div>";
                }
                ?>                       
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Main Content Row ═══ -->
    <div class="row">
      <div class="col-8">
        <!-- Hotspot Overview (r_2) -->
        <div id="r_2" class="card card-hotspot-panel">
          <div class="card-header">
            <h3><i class="fa fa-wifi"></i> Hotspot Overview</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-3 col-box-6">
                <div class="box bg-blue-modern">
                  <a onclick="cancelPage()" href="./?hotspot=active&session=<?= $session; ?>">
                    <div class="action-card-icon"><i class="fa fa-laptop"></i></div>
                    <div class="action-card-val"><?= $counthotspotactive; ?></div>
                    <div class="action-card-label"><?= $_hotspot_active ?></div>
                  </a>
                </div>
              </div>
              <div class="col-3 col-box-6">
                <div class="box bg-green-modern">
                  <a onclick="cancelPage()" href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                    <div class="action-card-icon"><i class="fa fa-users"></i></div>
                    <div class="action-card-val"><?= $countallusers; ?></div>
                    <div class="action-card-label"><?= $_hotspot_users ?></div>
                  </a>
                </div>
              </div>
              <div class="col-3 col-box-6">
                <div class="box bg-yellow-modern">
                  <a onclick="cancelPage()" href="./?hotspot-user=add&session=<?= $session; ?>">
                    <div class="action-card-icon"><i class="fa fa-user-plus"></i></div>
                    <div class="action-card-val"><i class="fa fa-plus"></i></div>
                    <div class="action-card-label">Add User</div>
                  </a>
                </div>
              </div>
              <div class="col-3 col-box-6">
                <div class="box bg-red-modern">
                  <a onclick="cancelPage()" href="./?hotspot-user=generate&session=<?= $session; ?>">
                    <div class="action-card-icon"><i class="fa fa-ticket"></i></div>
                    <div class="action-card-val"><i class="fa fa-magic"></i></div>
                    <div class="action-card-label">Generate</div>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Traffic Monitor -->
        <div class="card" id="trafficCard">
          <div class="card-header"><h3><i class="fa fa-area-chart"></i> <?= $_traffic ?></h3></div>
          <div class="card-body">
              <?php $getinterface = $API->comm("/interface/print");
              $interface = $getinterface[$iface - 1]['name']; ?>
              
              <script type="text/javascript"> 
                var chart;
                var sessiondata = "<?= $session ?>";
                var interface = "<?= $interface ?>";
                var n = 3000;
                function requestDatta(session,iface) {
                  if (document.hidden) return; // Pause traffic requests when tab is hidden
                  $.ajax({
                    url: './traffic/traffic.php?session='+session+'&iface='+iface,
                    datatype: "json",
                    success: function(data) {
                      try {
                        var midata = JSON.parse(data);
                        if( midata.length > 0 ) {
                          var TX=parseInt(midata[0].data);
                          var RX=parseInt(midata[1].data);
                          var x = (new Date()).getTime(); 
                          shift=chart.series[0].data.length > 19;
                          chart.series[0].addPoint([x, TX], true, shift);
                          chart.series[1].addPoint([x, RX], true, shift);
                        }
                      } catch (e) {}
                    },
                    error: function(XMLHttpRequest, textStatus, errorThrown) { 
                      console.error("Status: " + textStatus + " request: " + XMLHttpRequest); console.error("Error: " + errorThrown); 
                    }       
                  });
                }	

                $(document).ready(function() {
                    Highcharts.setOptions({
                      global: {
                        useUTC: false
                      }
                    });

                    Highcharts.addEvent(Highcharts.Series, 'afterInit', function () {
                        this.symbolUnicode = {
                        circle: '●',
                      diamond: '♦',
                      square: '■',
                      triangle: '▲',
                      'triangle-down': '▼'
                      }[this.symbol] || '●';
                    });

                      chart = new Highcharts.Chart({
                      chart: {
                      renderTo: 'trafficMonitor',
                      animation: Highcharts.svg,
                      type: 'areaspline',
                      events: {
                        load: function () {
                          setInterval(function () {
                            requestDatta(sessiondata,interface);
                          }, 8000);
                        }				
                      }
                    },
                    title: {
                      text: '<?= $_interface ?> ' + interface
                    },
                    
                    xAxis: {
                      type: 'datetime',
                      tickPixelInterval: 150,
                      maxZoom: 20 * 1000,
                    },
                    yAxis: {
                        minPadding: 0.2,
                        maxPadding: 0.2,
                        title: {
                          text: null
                        },
                        labels: {
                          formatter: function () {      
                            var bytes = this.value;                          
                            var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                            if (bytes == 0) return '0 bps';
                            var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                            return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];                    
                          },
                        },       
                    },
                    
                    series: [{
                      name: 'Tx',
                      data: [],
                      color: '#818cf8',
                      fillColor: {
                        linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                        stops: [
                          [0, 'rgba(129, 140, 248, 0.25)'],
                          [1, 'rgba(129, 140, 248, 0)']
                        ]
                      },
                      marker: { symbol: 'circle' }
                    }, {
                      name: 'Rx',
                      data: [],
                      color: '#34d399',
                      fillColor: {
                        linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                        stops: [
                          [0, 'rgba(52, 211, 153, 0.25)'],
                          [1, 'rgba(52, 211, 153, 0)']
                        ]
                      },
                      marker: { symbol: 'circle' }
                    }],

                    tooltip: {
                      formatter: function () { 
                        var s = [];
                        $.each(this.points, function(i, point) {
                          var y = point.y;
                          var units = ["bps", "kbps", "Mbps", "Gbps", "Tbps"];
                          if (y == 0) {
                            s.push("<span style=\"color:" + this.series.color + "; font-size: 1.5em;\">" + this.series.symbolUnicode + "</span><b>" + this.series.name + ":</b> 0 bps");
                          } else {
                            var unitIndex = parseInt(Math.floor(Math.log(y) / Math.log(1024)));
                            s.push("<span style=\"color:" + this.series.color + "; font-size: 1.5em;\">" + this.series.symbolUnicode + "</span><b>" + this.series.name + ":</b> " + parseFloat((y / Math.pow(1024, unitIndex)).toFixed(2)) + " " + units[unitIndex]);
                          }
                        });
                        return "<b>MikhPay Traffic Monitor</b><br /><br /><b>Time: </b>" + Highcharts.dateFormat("%H:%M:%S", new Date(this.x)) + "<br />" + s.join(" <br/> ");
                      },
                      shared: true                                                      
                    },
                  });
                });
              </script>
              <div id="trafficMonitor"></div>
          </div> 
        </div>

        <!-- Monthly Sales Performance Chart -->
        <div class="card" id="salesCard">
          <div class="card-header">
            <h3><i class="fa fa-line-chart"></i> Monthly Sales Performance (12 Months)</h3>
          </div>
          <div class="card-body">
            <div id="incomeChartLoader" class="text-center" style="padding: 40px 0; color: var(--text-muted);">
              <i class="fa fa-circle-o-notch fa-spin" style="font-size: 24px;"></i><br/>
              <span style="font-size: 12px; margin-top: 8px; display: inline-block;">Loading chart data...</span>
            </div>
            <div id="incomeChartContainer" style="display: none;"></div>
          </div>
        </div>

        <script type="text/javascript">
          $(document).ready(function() {
            // Live clock for welcome banner
            var welcomeTime = $('.dash-welcome-time .time-big');
            if (welcomeTime.length > 0) {
              var timeParts = welcomeTime.text().split(':');
              if (timeParts.length === 3) {
                var hours = parseInt(timeParts[0]);
                var minutes = parseInt(timeParts[1]);
                var seconds = parseInt(timeParts[2]);
                
                setInterval(function() {
                  seconds++;
                  if (seconds >= 60) {
                    seconds = 0;
                    minutes++;
                    if (minutes >= 60) {
                      minutes = 0;
                      hours++;
                      if (hours >= 24) {
                        hours = 0;
                      }
                    }
                  }
                  var hStr = (hours < 10 ? '0' : '') + hours;
                  var mStr = (minutes < 10 ? '0' : '') + minutes;
                  var sStr = (seconds < 10 ? '0' : '') + seconds;
                  welcomeTime.text(hStr + ':' + mStr + ':' + sStr);
                }, 1000);
              }
            }

            $.getJSON('./dashboard/income_chart.php?session=<?= $session ?>', function(data) {
              if (data.error) {
                $('#incomeChartLoader').html('<span class="text-danger"><i class="fa fa-warning"></i> ' + data.error + '</span>');
                return;
              }
              
              $('#incomeChartLoader').hide();
              $('#incomeChartContainer').show();
              
              Highcharts.chart('incomeChartContainer', {
                chart: {
                  type: 'column',
                  backgroundColor: 'transparent',
                  height: 300,
                  spacingLeft: window.innerWidth < 500 ? 5 : 10,
                  spacingRight: window.innerWidth < 500 ? 5 : 10,
                  style: {
                    fontFamily: "'Plus Jakarta Sans', sans-serif"
                  }
                },
                title: {
                  text: null
                },
                xAxis: {
                  categories: data.labels,
                  crosshair: true,
                  labels: {
                    style: {
                      color: 'var(--text-muted)'
                    }
                  },
                  lineColor: 'var(--border-color)',
                  tickColor: 'var(--border-color)'
                },
                yAxis: [{
                  title: {
                    text: window.innerWidth < 500 ? null : 'Total Income',
                    style: {
                      color: 'var(--text-muted)'
                    }
                  },
                  labels: {
                    formatter: function() {
                      var val = this.value;
                      if (window.innerWidth < 500) {
                        if (val >= 1000000) {
                          return 'Rp ' + (val / 1000000).toFixed(1).replace('.0', '') + 'M';
                        } else if (val >= 1000) {
                          return 'Rp ' + (val / 1000) + 'k';
                        }
                        return 'Rp ' + val;
                      }
                      return 'Rp ' + Highcharts.numberFormat(val, 0, ',', '.');
                    },
                    style: {
                      color: 'var(--text-muted)'
                    }
                  },
                  gridLineColor: 'var(--border-color)'
                }, {
                  title: {
                    text: window.innerWidth < 500 ? null : 'Vouchers Sold',
                    style: {
                      color: 'var(--text-muted)'
                    }
                  },
                  labels: {
                    style: {
                      color: 'var(--text-muted)'
                    }
                  },
                  opposite: true,
                  gridLineColor: 'transparent'
                }],
                tooltip: {
                  shared: true,
                  useHTML: true,
                  backgroundColor: 'var(--bg-card)',
                  borderColor: 'var(--border-color)',
                  style: {
                    color: 'var(--text-main)'
                  },
                  headerFormat: '<small style="font-weight: 700; color: var(--text-muted);">{point.key}</small><table style="margin-top: 5px;">',
                  pointFormat: '<tr><td style="color: {series.color}; padding-right: 10px;">{series.name}: </td>' +
                               '<td style="text-align: right;"><b>{point.y}</b></td></tr>',
                  footerFormat: '</table>',
                  valueDecimals: 0
                },
                plotOptions: {
                  column: {
                    pointPadding: 0.2,
                    borderWidth: 0,
                    borderRadius: 4
                  }
                },
                legend: {
                  itemStyle: {
                    color: 'var(--text-muted)'
                  },
                  itemHoverStyle: {
                    color: 'var(--text-main)'
                  }
                },
                series: [{
                  name: 'Income (Rupiah)',
                  data: data.income,
                  color: 'var(--primary)',
                  tooltip: {
                    valuePrefix: 'Rp '
                  }
                }, {
                  name: 'Vouchers',
                  data: data.vouchers,
                  color: '#34d399',
                  yAxis: 1,
                  type: 'spline',
                  marker: {
                    enabled: true,
                    radius: 4
                  }
                }]
              });
            }).fail(function() {
              $('#incomeChartLoader').html('<span class="text-danger"><i class="fa fa-warning"></i> Failed to load chart data</span>');
            });
          });
        </script>
      </div>  

      <!-- ═══ Right Sidebar Column ═══ -->
      <div class="col-4">

        <!-- Hotspot Log (r_3) -->
        <div id="r_3" class="row">
          <div class="card">
            <div class="card-header">
              <h3><a onclick="cancelPage()" href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log"><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3>
            </div>
            <div class="card-body">
              <div style="padding: 0; height: <?= $logh; ?>;" class="mr-t-10 overflow" style="border:none !important; box-shadow:none !important;">
                <table class="table table-sm table-hover card-table-modern" style="font-size: 12px;">
                  <thead>
                    <tr>
                      <th><?= $_time ?></th>
                      <th><?= $_users ?> (IP)</th>
                      <th><?= $_messages ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="3" class="text-center">
                        <div id="loader"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>
