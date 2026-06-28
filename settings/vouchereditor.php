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
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
	exit;
} else {
	$session = $_GET['session'];

    include('../include/config.php');
    include('../include/readcfg.php');

    $url = $_SERVER['REQUEST_URI'];
    $telplate = $_GET['template'];
    if ($telplate == "default" || $telplate == "rdefault") {
        $telplatet = "template";
        $popup = "javascript:window.open('./voucher/vpreview.php?usermode=up&qr=no&session=" . $session . "','_blank','width=310,height=310')";
        $popupQR = "javascript:window.open('./voucher/vpreview.php?usermode=up&qr=yes&session=" . $session . "','_blank','width=310,height=310')";
    } elseif ($telplate == "thermal" || $telplate == "rthermal") {
        $telplatet = "template-thermal";
        $popup = "javascript:window.open('./voucher/vpreview.php?usermode=up&user=m&qr=no&session=" . $session . "','_blank','width=310,height=310')";
        $popupQR = "javascript:window.open('./voucher/vpreview.php?usermode=up&user=m&qr=yes&session=" . $session . "','_blank','width=310,height=310')";
    } elseif ($telplate == "small" || $telplate == "rsmall") {
        $telplatet = "template-small";
        $popup = "javascript:window.open('./voucher/vpreview.php?usermode=up&small=yes&qr=no&session=" . $session . "','_blank','width=310,height=310')";
        $popupQR = "javascript:window.open('./voucher/vpreview.php?usermode=up&small=yes&qr=yes&session=" . $session . "','_blank','width=310,height=310')";
    }
    if (isset($_POST['save'])) {
        $template = './voucher/' . $telplatet . '.php';
        $handle = fopen($template, 'w') or die('Cannot open file:  ' . $template);
        $data = ($_POST['editor']);
        fwrite($handle, $data);
    }
}
?>
<!-- Create a simple CodeMirror instance -->
<link rel="stylesheet" href="./css/editor.min.css">
<script src="./js/editor.min.js"></script>	

<style>
.editor-row-flex {
    display: flex !important;
    gap: 24px !important;
    width: 100% !important;
    flex-wrap: wrap !important;
    box-sizing: border-box !important;
}
.editor-col-left {
    flex: 1 1 calc(75% - 12px) !important;
    min-width: 500px !important;
    box-sizing: border-box !important;
}
.editor-col-right {
    flex: 1 1 calc(25% - 12px) !important;
    min-width: 250px !important;
    box-sizing: border-box !important;
}
.CodeMirror {
  border: 1px solid var(--border-color) !important;
  height: 520px !important;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
}
textarea{
  font-size:12px;
  border: 1px solid var(--border-color) !important;
}
.btn-editor-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 38px;
    padding: 0 16px;
    border-radius: 8px !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none !important;
    text-decoration: none !important;
    color: #fff !important;
}
.btn-editor-action.bg-primary {
    background: var(--primary) !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}
.btn-editor-action.bg-primary:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}
.btn-editor-action.bg-success {
    background: #10b981 !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}
.btn-editor-action.bg-success:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}
.editor-select-modern {
    height: 38px !important;
    border-radius: 8px !important;
    border: 1px solid var(--border-color) !important;
    background: var(--card-bg, #fff) !important;
    color: var(--text-main) !important;
    padding: 0 12px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    outline: none !important;
    box-sizing: border-box;
    cursor: pointer;
}
.editor-select-modern:focus {
    border-color: var(--primary) !important;
}
.variable-box-modern {
    width: 100% !important;
    height: 520px !important;
    background: #0f172a !important;
    color: #e2e8f0 !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    padding: 18px !important;
    font-family: 'Fira Code', 'Courier New', monospace !important;
    font-size: 12px !important;
    line-height: 1.6 !important;
    overflow-y: auto !important;
    box-sizing: border-box !important;
    margin: 0 !important;
}
</style>

<div class="editor-row-flex">
    <div class="editor-col-left">
        <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
            <div class="card-header">
                <h3><i class="fa fa-edit"></i> <?= $_template_editor ?></h3>
            </div>
            <div class="card-body" style="padding: 24px !important;">
                <form autocomplete="off" method="post" action="">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" title="Save template" class="btn-editor-action bg-primary" name="save"><i class="fa fa-save"></i> <?= $_save ?></button>
                            <a class="btn-editor-action bg-success" href="<?= $popup?>" title="View voucher with Logo"><i class="fa fa-image"></i> Logo</a>
                            <a class="btn-editor-action bg-success" href="<?= $popupQR?>" title="View voucher with QR"><i class="fa fa-qrcode"></i> QR Code</a>
                        </div>
                        
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Template:</span>
                                <select class="editor-select-modern" onchange="window.location.href=this.value+'&session=<?= $session; ?>';">
                                    <option><?= ucfirst($telplate); ?></option>
                                    <option value="./admin.php?id=editor&template=default">Default</option>
                                    <option value="./admin.php?id=editor&template=thermal">Thermal</option>
                                    <option value="./admin.php?id=editor&template=small">Small</option>
                                </select>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Reset:</span>
                                <select class="editor-select-modern" onchange="window.location.href=this.value+'&session=<?= $session; ?>';">
                                    <option><?= ucfirst($telplate); ?></option>
                                    <option value="./admin.php?id=editor&template=rdefault">Default</option>
                                    <option value="./admin.php?id=editor&template=rthermal">Thermal</option>
                                    <option value="./admin.php?id=editor&template=rsmall">Small</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <textarea class="bg-dark" id="editorMikhmon" name="editor" style="width:100%" height="700">
                        <?php if ($telplate == "default") {
                            echo file_get_contents('./voucher/template.php');
                        } elseif ($telplate == "thermal") {
                            echo file_get_contents('./voucher/template-thermal.php');
                        } elseif ($telplate == "small") {
                            echo file_get_contents('./voucher/template-small.php');
                        } elseif ($telplate == "rdefault") {
                            echo file_get_contents('./voucher/default.php');
                        } elseif ($telplate == "rthermal") {
                            echo file_get_contents('./voucher/default-thermal.php');
                        } elseif ($telplate == "rsmall") {
                            echo file_get_contents('./voucher/default-small.php');
                        } ?>
                    </textarea>
                </form>
            </div>
        </div>
    </div>
    
    <div class="editor-col-right">
        <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color); height: 100%;">
            <div class="card-header">
                <h3>Variable</h3>
            </div>
            <div class="card-body" style="padding: 24px !important;">
                <textarea id="var" class="variable-box-modern" readonly disabled><?= file_get_contents('./voucher/variable.php'); ?></textarea>
            </div>
        </div>
    </div>
</div>

<script>
  if (typeof(Storage) !== "undefined") {
    var sessionElem = document.getElementById("MikhmonSession");
    if (sessionElem) {
      sessionStorage.setItem("MikhmonSession", sessionElem.innerHTML);
    }
  } else {
    alert("Please use Google Chrome");
  }

  var editor = CodeMirror.fromTextArea(document.getElementById("editorMikhmon"), {
    lineNumbers: true,
    matchBrackets: true,
    mode: "application/x-httpd-php",
    indentUnit: 4,
    indentWithTabs: true,
    lineWrapping: true,
    viewportMargin: Infinity,
    matchTags: { bothTags: true },
    extraKeys: { "Ctrl-J": "toMatchingTag" }
  });
  editor.setOption("theme", "material");
</script>
