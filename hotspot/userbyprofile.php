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
  ?>
<style>
.voucher-grid-container {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)) !important;
    gap: 20px !important;
    width: 100% !important;
    box-sizing: border-box !important;
}
.voucher-card-premium {
    background: var(--bg-card, #ffffff) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 16px !important;
    padding: 20px !important;
    box-shadow: var(--shadow-card) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
    box-sizing: border-box !important;
}
.voucher-card-premium:hover {
    border-color: var(--primary) !important;
    box-shadow: 0 10px 25px rgba(0, 139, 201, 0.08) !important;
    transform: translateY(-3px) !important;
}
.voucher-avatar {
    width: 48px !important;
    height: 48px !important;
    border-radius: 14px !important;
    background: var(--primary-glow) !important;
    color: var(--primary) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 20px !important;
    flex-shrink: 0 !important;
    transition: all 0.3s ease !important;
}
.voucher-card-premium:hover .voucher-avatar {
    background: var(--primary) !important;
    color: #ffffff !important;
}
.voucher-details {
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    flex: 1 !important;
    min-width: 0 !important;
}
.voucher-title {
    font-size: 11px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    color: var(--text-muted) !important;
    margin-bottom: 2px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    width: 100% !important;
    text-align: left !important;
}
.voucher-count {
    font-size: 18px !important;
    font-weight: 800 !important;
    color: var(--text-bright) !important;
    margin-bottom: 8px !important;
    text-align: left !important;
}
.voucher-actions {
    display: flex !important;
    gap: 8px !important;
    width: 100% !important;
}
.voucher-actions .action-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 30px !important;
    padding: 0 10px !important;
    border-radius: 8px !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    gap: 6px !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
    border: 1px solid transparent !important;
    cursor: pointer !important;
}
.voucher-actions .action-btn.btn-open {
    background: rgba(60, 80, 224, 0.08) !important;
    color: var(--primary) !important;
}
.voucher-actions .action-btn.btn-open:hover {
    background: var(--primary) !important;
    color: #ffffff !important;
}
.voucher-actions .action-btn.btn-generate {
    background: rgba(33, 150, 83, 0.08) !important;
    color: #219653 !important;
}
.voucher-actions .action-btn.btn-generate:hover {
    background: #219653 !important;
    color: #ffffff !important;
}
</style>

<div class="row">
<div class="col-12">
<div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
<div class="card-header" style="padding: 14px 20px !important;">
	<h3 class="card-title" style="margin: 0;"><i class=" fa fa-users"></i> <?= $_vouchers ?> &nbsp;&nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i></h3>
</div>
<div class="card-body" style="padding: 24px !important;">
    
    <div class="voucher-grid-container">
        
      <!-- Profile: All Card -->
      <div class="voucher-card-premium">
        <div class="voucher-avatar">
          <i class="fa fa-ticket"></i>
        </div>
        <div class="voucher-details">
          <div class="voucher-title">Profile: all</div>
          <div class="voucher-count">
            <?php $countuser = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
            if ($countuser < 2) {
              echo $countuser . " Item";
            } elseif ($countuser > 1) {
              echo $countuser . " Items";
            }
            ?>
          </div>
          <div class="voucher-actions">
            <a class="action-btn btn-open" title="Open User by profile all" href="./?hotspot=users&profile=all&session=<?= $session; ?>"><i class="fa fa-external-link"></i> <?= $_open ?></a>
            <a class="action-btn btn-generate" title="Generate User by profile all" href="./?hotspot-user=generate&session=<?= $session; ?>"><i class="fa fa-users"></i> <?= $_generate ?></a>
          </div>
        </div>
      </div>

      <?php
      // get user profile
      $getprofile = $API->comm("/ip/hotspot/user/profile/print");
      $TotalReg = count($getprofile);
      for ($i = 0; $i < $TotalReg; $i++) {
        $profiledetalis = $getprofile[$i];
        $pname = $profiledetalis['name'];
        ?>
        <div class="voucher-card-premium">
          <div class="voucher-avatar">
            <i class="fa fa-ticket"></i>
          </div>
          <div class="voucher-details">
            <div class="voucher-title">Profile: <?= htmlspecialchars($pname); ?></div>
            <div class="voucher-count">
              <?php	$countuser = $API->comm("/ip/hotspot/user/print", array("count-only" => "", "?profile" => "$pname", ));
              if ($countuser < 2) {
                echo $countuser . " Item";
              } elseif ($countuser > 1) {
                echo $countuser . " Items";
              }
              ?>
            </div>
            <div class="voucher-actions">
              <a class="action-btn btn-open" title="Open User by profile <?= $pname; ?>" href="./?hotspot=users&profile=<?= $pname; ?>&session=<?= $session; ?>"><i class="fa fa-external-link"></i> <?= $_open ?></a>
              <a class="action-btn btn-generate" title="Generate User by profile <?= $pname; ?>" href="./?hotspot-user=generate&genprof=<?= $pname; ?>&session=<?= $session; ?>"><i class="fa fa-users"></i> <?= $_generate ?></a>
            </div>
          </div>
        </div>
      <?php 
      }
    }
      ?>
      
    </div>
</div>
</div>
</div>
</div>