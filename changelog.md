# Changelog

Semua pembaruan penting pada modifikasi MikhTrans ini akan dicatat di dokumen ini.

## [MikhTrans v2.0] - 2026-06-18

### Ditambahkan
- **Penyimpanan Database JSON Terstruktur**: Mengganti penyimpanan `config.php` yang rentan rusak dengan file database JSON terenkripsi/terlindungi (`data/database.json`) menggunakan mekanisme lock file (`flock`) untuk mencegah korupsi data saat akses simultan.
- **Arsitektur MVC & Autoloading**: Implementasi model OOP (`RouterSession` dan `AppSettings` di bawah namespace `App\Models`) dengan PSR-4 autoloader internal di `include/autoload.php`.

### Diubah
- **De-obfuscation Total Kode JavaScript**: Refaktor dan konversi seluruh script inline yang sebelumnya disamarkan (obfuscated) dengan array hex (`_0x1d39`, `_0x8202`, `_0x7470`, `_0x2f7f`, `_0x5b73`, `_0x7baa`, dll.) menjadi kode JavaScript/jQuery modern yang bersih, efisien, dan mudah dipelihara.
- **Penghapusan Proteksi DRM Logo/Brand**: Menghapus script validasi paksa innerHTML `#brand` (`You destroy MIKHMON`) agar visual dashboard dapat disesuaikan secara bebas untuk branding modern MikhTrans.

### Diperbaiki
- **Eror Daftar Sesi (Router List)**: Memperbaiki kesalahan parsing naif di `sessions.php` yang sebelumnya membaca parameter konfigurasi eksternal sebagai sesi router aktif dengan merutekan manajemen sesi langsung via database model.

## [MikhTrans v1.1] - 2026-06-18

### Ditambahkan
- **Integrasi WebSockets (Pusher & Soketi)**: Dukungan notifikasi real-time instan ketika pembayaran QRIS/VA lunas, sehingga pengguna langsung mendapatkan voucher tanpa delay.
- **Sistem Fallback Polling Hybrid**: Jika koneksi WebSocket gagal terhubung dalam 5 detik, sistem otomatis mendegradasi layanan ke HTTP Polling berkala (setiap 3 detik) agar transaksi tetap aman.
- **Pemisahan Berkas Lingkungan (.env)**: Migrasi kunci API eksternal (Midtrans, WebSocket) dari kode program utama ke berkas `.env` di root direktori untuk keamanan maksimal.
- **Isolasi Konfigurasi (`env_config.php`)**: Memindahkan deklarasi variabel dinamis dari `config.php` ke `include/env_config.php` untuk mencegah kerusakan parsing sesi router di Web Admin Panel Mikhmon.
- **Sistem Log Aplikasi Terstruktur**: Fungsi `writeAppLog()` ditambahkan untuk mencatat error/warning penting seputar transaksi dan otentikasi di file `logs/error.log`.
- **Keamanan Berkas Transaksi**: File `.htaccess` otomatis dibuat di folder `voucher/` and `logs/` untuk memblokir akses HTTP langsung dari luar (`Deny from all`).

### Diubah
- **Desain Premium Light Theme**: Antarmuka halaman depan diubah sepenuhnya dengan tema terang premium berbasis HSL (Soft whites, slate text, indigo & emerald accents).
- **Mobile Navigation Bar**: Ditambahkan bilah navigasi statis di bagian bawah (Bottom Nav) pada perangkat mobile dengan highlight otomatis tab aktif berdasarkan posisi scroll.
- **Peningkatan Idempotensi Webhook**: Refaktor file `notification.php` agar dapat mendeteksi pembayaran ganda (idempoten) dan langsung mengembalikan HTTP 200 tanpa memproses ulang/menerbitkan voucher baru.
- **Auto Cleanup Log**: Penghapusan otomatis file log transaksi usang (> 2 hari) dengan mekanisme acak (probabilitas 5% saat halaman dimuat).

### Diperbaiki
- **Layout Overflow di HP**: Perbaikan CSS grid layout 1 kolom pada mobile view agar card paket internet tidak melebar melebihi layar.
- **Tag PHP Hilang**: Memulihkan tag pembuka `<?php` pada file `include/config.php` yang sempat terhapus.
- **Kompatibilitas Tanggal RouterOS v7.10+**: Penanganan perubahan format tanggal RouterOS dari `mmm/dd/yyyy` ke `yyyy-mm-dd` pada skrip profile hotspot `on-login` dan monitoring scheduler kedaluwarsa agar masa aktif voucher berfungsi sempurna di versi RouterOS baru maupun lama.
