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
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

// Parse local changelog.md safely to display in the about page
$changelogHtml = '';
$changelogPath = __DIR__ . '/../changelog.md';
if (file_exists($changelogPath)) {
    $rawContent = file_get_contents($changelogPath);
    // Basic Markdown parser for HTML rendering
    $htmlContent = htmlspecialchars($rawContent, ENT_QUOTES, 'UTF-8');
    
    // Convert headers
    $htmlContent = preg_replace('/^# (.*)$/m', '<h2 style="font-weight: 800; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; margin-bottom: 20px; color: var(--text-bright);">$1</h2>', $htmlContent);
    $htmlContent = preg_replace('/^## (.*)$/m', '<h3 style="font-weight: 700; margin-top: 24px; margin-bottom: 12px; color: var(--text-bright);">$1</h3>', $htmlContent);
    $htmlContent = preg_replace('/^### (.*)$/m', '<h4 style="font-weight: 700; margin-top: 14px; margin-bottom: 8px; color: var(--primary);">$1</h4>', $htmlContent);
    
    // Convert links: [text](url) -> <a href="url" target="_blank">text</a>
    $htmlContent = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: none;">$1</a>', $htmlContent);
    
    // Convert bold: **text** -> <strong>text</strong>
    $htmlContent = preg_replace('/\*\*(.*?)\*\*/', '<strong style="color: var(--text-bright);">$1</strong>', $htmlContent);
    
    // Convert lists: - item -> <li>item</li>
    // Note: To properly wrap them in <ul>, we do simple replacement or handle them list-style
    $htmlContent = preg_replace('/^- (.*)$/m', '<li style="margin-bottom: 6px; line-height: 1.6; color: var(--text-main);">$1</li>', $htmlContent);
    
    // Wrap consecutive list items in ul if needed, but since browser handles <li> nicely inside div, we can just apply padding/margin
    $htmlContent = preg_replace('/((?:<li.*?>.*?<\/li>\s*)+)/s', '<ul style="padding-left: 20px; margin: 10px 0;">$1</ul>', $htmlContent);
    
    // Convert horizontal line: --- -> <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
    $htmlContent = preg_replace('/^---$/m', '<hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">', $htmlContent);
    
    // Replace double newlines with single linebreaks
    $htmlContent = nl2br($htmlContent);
    
    // Strip empty lines and extra brs next to tags to clean up rendering
    $htmlContent = preg_replace('/<(ul|li|h2|h3|h4|hr|p)(.*?)><br \/>/i', '<$1$2>', $htmlContent);
    $htmlContent = preg_replace('/<\/li><br \/>/i', '</li>', $htmlContent);
    $htmlContent = preg_replace('/<\/ul><br \/>/i', '</ul>', $htmlContent);
    
    $changelogHtml = $htmlContent;
} else {
    $changelogHtml = '<p style="color: var(--text-muted);">Berkas changelog.md tidak ditemukan di root direktori.</p>';
}
?>
<style>
.about-card {
    background: var(--bg-card) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 20px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important;
    padding: 28px !important;
    margin-bottom: 24px !important;
}
.about-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: var(--primary-glow) !important;
    color: var(--primary) !important;
    border: 1px solid var(--border-color) !important;
    margin-bottom: 16px;
}
.changelog-container {
    max-height: 480px;
    overflow-y: auto;
    background: var(--bg-body) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 14px;
    padding: 24px;
    font-size: 13.5px;
    box-sizing: border-box;
}
.about-list {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}
.about-list li {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: var(--text-main);
}
.about-list li strong {
    color: var(--text-muted);
    font-weight: 500;
}
.about-list li a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 700;
}
</style>

<div class="row">
  <div class="col-6">
    <div class="about-card">
      <div class="about-logo">
        <i class="fa fa-credit-card"></i>
      </div>
      <h2 style="font-weight: 800; color: var(--text-bright); margin: 0 0 6px 0; letter-spacing: -0.5px;">MikhPay v2.0</h2>
      <p style="color: var(--text-muted); font-size: 13.5px; line-height: 1.6; margin: 0;">
        Billing Hotspot & Router Manager dengan sistem pembayaran QRIS Dinamis Mandiri. Aplikasi ini merupakan modifikasi modern dari basis MIKHMON v3 untuk mendukung transaksi asinkron bebas biaya gateway admin.
      </p>
      
      <ul class="about-list">
        <li>
          <strong>Author Asli (Mikhmon)</strong>
          <span>Laksamadi Guko</span>
        </li>
        <li>
          <strong>Developer (MikhPay)</strong>
          <span><a href="https://github.com/balongbesuk" target="_blank">Balongbesuk Dev</a></span>
        </li>
        <li>
          <strong>Repository Resmi</strong>
          <span><a href="https://github.com/balongbesuk/MikhPay" target="_blank">GitHub</a></span>
        </li>
        <li>
          <strong>Licence</strong>
          <span><a href="https://github.com/balongbesuk/MikhPay/blob/main/LICENSE" target="_blank">GPLv2</a></span>
        </li>
        <li>
          <strong>API RouterOS Class</strong>
          <span><a href="https://github.com/BenMenking/routeros-api" target="_blank">BenMenking Class</a></span>
        </li>
      </ul>
      
      <p style="font-size: 12.5px; color: var(--text-muted); line-height: 1.5; margin-top: 18px;">
        Terima kasih yang sebesar-besarnya untuk Laksamadi Guko atas pengembangan framework dasar Mikhmon, dan seluruh komunitas yang terus mendukung perbaikan aplikasi ini.
      </p>
      
      <div style="font-size: 12px; color: var(--text-muted); margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 14px;">
        Copyright &copy; 2018 Laksamadi Guko &bull; MikhPay modifications in 2026.
      </div>
    </div>
  </div>
  
  <div class="col-6">
    <div class="about-card" style="height: calc(100% - 24px);">
      <h3 style="font-weight: 800; color: var(--text-bright); margin: 0 0 16px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;"><i class="fa fa-history" style="color: var(--primary);"></i> Pembaruan Sistem (Changelog)</h3>
      <div class="changelog-container">
        <?= $changelogHtml; ?>
      </div>
    </div>
  </div>
</div>
