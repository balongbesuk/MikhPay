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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
} else {

  if (isset($_POST["submit"])) {
    $logo_dir = "./img/";
    $logo_file = $logo_dir . basename($_FILES["UploadLogo"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($logo_file, PATHINFO_EXTENSION));

    $check = getimagesize($_FILES["UploadLogo"]["tmp_name"]);
    if ($check !== false) {
      $uploadOk = 1;
    } else {
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>File bukan gambar.</span></div>';
      } else {
        $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>File is not an image.</span></div>';
      }
      $uploadOk = 0;
    }

    if ($_FILES["UploadLogo"]["size"] > 2000000) {
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>Ukuran file terlalu besar (Max 2MB).</span></div>';
      } else {
        $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>File is too large (Max 2MB).</span></div>';
      }
      $uploadOk = 0;
    }

    if (basename($_FILES["UploadLogo"]["name"] != "logo-" . $session . ".png")) {
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>Hanya bisa upload file bernama logo-' . $session . '.png.</span></div>';
      } else {
        $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>Only logo-' . $session . '.png is allowed.</span></div>';
      }
      $uploadOk = 0;
    }

    if ($uploadOk != 0) {
      if (move_uploaded_file($_FILES["UploadLogo"]["tmp_name"], $logo_file)) {
        if ($currency == in_array($currency, $cekindo['indo'])) {
          $galat = '<div class="bg-success" style="margin-top:0; margin-bottom:16px; background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.18); color:#10b981; padding:14px 18px; border-radius:16px; display:flex; align-items:center; gap:12px;"><i class="fa fa-check-circle" style="font-size:16px; color:#10b981;"></i> <span>Success! File ' . basename($_FILES["UploadLogo"]["name"]) . ' telah diupload.</span></div>';
        } else {
          $galat = '<div class="bg-success" style="margin-top:0; margin-bottom:16px; background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.18); color:#10b981; padding:14px 18px; border-radius:16px; display:flex; align-items:center; gap:12px;"><i class="fa fa-check-circle" style="font-size:16px; color:#10b981;"></i> <span>Success! File ' . basename($_FILES["UploadLogo"]["name"]) . ' has been uploaded.</span></div>';
        }
      } else {
        if ($currency == in_array($currency, $cekindo['indo'])) {
          $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>Terjadi kegagalan upload file.</span></div>';
        } else {
          $galat = '<div class="bg-danger" style="margin-top:0; margin-bottom:16px;"><i class="fa fa-warning"></i> <span>There was an error uploading your file.</span></div>';
        }
      }
    }
  }
}
?>

<style>
.upload-area-card {
    border: 2px dashed var(--border-color);
    border-radius: 16px;
    padding: 36px 20px;
    text-align: center;
    background: var(--bg-body);
    transition: all 0.25s ease;
    margin-bottom: 24px;
    box-sizing: border-box;
}
.upload-area-card:hover {
    border-color: var(--primary);
    background: var(--primary-glow);
}
.upload-icon-box {
    font-size: 36px;
    color: var(--primary);
    margin-bottom: 12px;
}
.custom-file-upload {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    color: var(--text-main);
    transition: all 0.2s;
    margin-top: 8px;
}
.custom-file-upload:hover {
    border-color: var(--primary);
    color: var(--primary);
}
.upload-submit-btn {
    height: 40px;
    border-radius: 10px !important;
    padding: 0 24px !important;
    font-weight: 700 !important;
    background: var(--primary) !important;
    color: #fff !important;
    border: none !important;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}
.upload-submit-btn:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}
.table-logo-preview {
    max-height: 40px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    padding: 4px;
    background: #ffffff; /* background putih untuk logo kontras */
}
.btn-delete-modern {
    background: rgba(211, 64, 83, 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(211, 64, 83, 0.2) !important;
    padding: 8px 16px !important;
    border-radius: 10px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    text-decoration: none !important;
    transition: all 0.2s ease;
    font-size: 13px !important;
}
.btn-delete-modern:hover {
    background: var(--danger) !important;
    color: #fff !important;
    border-color: var(--danger) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(211, 64, 83, 0.15);
}
</style>

<div class="row">
<div class="col-12">
  <div class="card" style="box-shadow: var(--shadow-card); border-radius: var(--radius); border: 1px solid var(--border-color);">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-upload"></i> <?= $_upload_logo ?></h3>
    </div>
    <div class="card-body" style="padding: 24px !important;">
    <?= $galat; ?>
    
      <form action="" method="post" enctype="multipart/form-data">
        <div class="upload-area-card">
          <div class="upload-icon-box">
            <i class="fa fa-cloud-upload"></i>
          </div>
          <div style="font-size: 14px; font-weight: 700; color: var(--text-bright); margin-bottom: 6px;">Format Nama File: <code style="color: var(--primary); background: var(--primary-glow); padding: 2px 8px; border-radius: 6px; font-weight: 700;">logo-<?= $session; ?>.png</code></div>
          <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Hanya mendukung file .png dengan ukuran maksimal 2MB.</div>
          
          <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
            <label class="custom-file-upload">
              <input type="file" name="UploadLogo" style="display: none;" onchange="$('#file-name-text').html('<i class=\'fa fa-file-image-o\'></i> ' + this.files[0].name)" required>
              <i class="fa fa-folder-open-o"></i> <span>Pilih Berkas Logo</span>
            </label>
            <span id="file-name-text" style="font-size: 13px; color: var(--primary); font-weight: 600;">Belum ada berkas terpilih</span>
            
            <button type="submit" name="submit" class="upload-submit-btn" style="margin-top: 12px;"><i class="fa fa-upload"></i> Unggah Berkas</button>
          </div>
        </div>
      </form>

      <div class="mr-t-10">
      <table class="table table-bordered table-hover">
        <thead>
        <tr>
          <th><?= $_list_logo ?></th>
          <th><?= $_action ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $dir = "./img";
        if (is_dir($dir)) {
          if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
              if ($file != "." && $file != "..") {
                if (substr($file, 0, 5) != "logo-" ||
                  substr($file, -5) == ".html" ||
                  substr($file, -4) == ".php" ||
                  substr($file, -4) == ".jpg" ||
                  substr($file, -4) == ".bak") {
                } else { ?>
              
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 14px;">
                    <a href="javascript:window.open('./img/<?= $file; ?>','_blank','width=300,height=300')">
                      <img class="table-logo-preview" src="./img/<?= $file; ?>" title="Open <?= $file; ?>">
                    </a>
                    <span style="font-size: 13.5px; font-weight: 600; color: var(--text-bright);"><?= $file; ?></span>
                  </div>
                </td>
                <td style="vertical-align: middle; text-align: center; width: 120px;">
                  <a class="btn-delete-modern" href="javascript:void(0)" onclick="if(confirm('Sure to delete <?= $file; ?> ?')){window.location='./admin.php?id=remove-logo&logo=<?= $file; ?>&session=<?= $session ?>'}else{}"><i class="fa fa-trash"></i> <?= $_delete ?></a>
                </td>
              </tr>
              
          <?php 
        }
      }
    }
    closedir($dh);
  }
}
?>
      </tbody>
    </table>
  </div>
  
  </div>
</div>
</div>
</div>