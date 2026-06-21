# Changelog

Semua pembaruan penting pada modifikasi MikhTrans ini akan dicatat di dokumen ini.

## [MikhTrans v1.3] - 2026-06-21

### Ditambahkan
- **Notifikasi Telegram Bot (Admin Alert)**: Pengiriman notifikasi real-time instan ke admin via Telegram jika ada transaksi lunas Midtrans namun pembuatan voucher di MikroTik tertunda (`paid_pending_generate`) akibat router offline.
- **Sistem Backup Data Hibrida (ZIP & TAR)**: Fitur "Buat & Unduh Backup" pada halaman admin untuk mencadangkan berkas sensitif (`.env`, `data/database.php`, dan semua log transaksi `voucher/*.json`). Menggunakan kompresi `.zip` jika ekstensi PHP `ZipArchive` terpasang, dengan fallback otomatis ke arsip `.tar` menggunakan generator ustar murni (pure PHP) jika modul ZIP tidak tersedia di server.
- **Tab Pengaturan & Backup**: Panel baru di halaman Antrean Webhook (`settings/pending_transactions.php`) untuk mengelola kredensial Telegram Bot (Bot Token & Chat ID), melakukan pengujian pengiriman notifikasi, dan mengunduh cadangan data.

---

## [MikhTrans v1.2] - 2026-06-21

### Ditambahkan
- **Antrean Webhook (Resilience Queue)**: Menyimpan transaksi dengan status `paid_pending_generate` jika router offline sewaktu webhook Midtrans melacak pelunasan. Ditambahkan tombol "Generate/Retry" di admin panel dan informasi error yang ramah di portal frontpage.
- **REST API Rate Limiting**: Membatasi laju request ke `api.php` hingga 30 request/menit/IP menggunakan pembacaan file cache `data/rate_limit.json` yang terproteksi file lock.
- **Dashboard Ringkasan Multi-Router**: Mengubah halaman daftar sesi di `sessions.php` menjadi grid visual interaktif yang menampilkan status online, CPU, uptime, dan jumlah user aktif yang dimuat secara asinkron (AJAX) secara paralel untuk tiap router.

---

## [MikhTrans v1.1] - 2026-06-21

### Diubah
- **Pembersihan Kode Native PHP 8.2 (Depresiasi PHP 5.4)**: Menghapus seluruh polyfill kompatibilitas PHP versi lama (`hash_equals`, `password_hash`, `password_verify`) dan memfaktorkan ulang kode pada `include/csrf.php` serta `lib/routeros_api.class.php` agar memanfaatkan fungsi bawaan PHP 8.2+ secara langsung (seperti `random_bytes` untuk generate IV dan token CSRF).

### Dihapus
- **Binary PHP 5.4 Lama**: Menghapus folder cadangan `php_backup/` (PHP 5.4.17) secara permanen dari disk lokal untuk menyelesaikan migrasi penuh ke arsitektur PHP 8.2 modern.

---

## [MikhTrans v1.0] - 2026-06-21

### Ditambahkan
- **Proteksi CSRF (Cross-Site Request Forgery)**: Menambahkan helper `include/csrf.php` dengan token per-sesi yang memproteksi seluruh form POST kritis (login admin, pembelian voucher frontpage) dari serangan CSRF.
- **Session Timeout Auto-Logout (30 Menit)**: Sesi admin otomatis kedaluwarsa setelah 30 menit tanpa aktivitas, meningkatkan keamanan panel admin pada perangkat bersama.
- **Enkripsi Kredensial AES-256-CBC**: Mengupgrade metode enkripsi kredensial router MikroTik di `data/database.php` menggunakan OpenSSL AES-256-CBC dengan `ENCRYPTION_KEY` dinamis dari berkas `.env` (dilengkapi fallback otomatis backward-compatible ke skema XOR lama).
- **Indikator Live Sync Status**: Menambahkan status koneksi realtime (`Connected`, `Syncing...`, `Offline`) dengan indikator dot berwarna dan animasi denyut (*pulsing dot*) di welcome banner dashboard utama untuk memantau status sinkronisasi dengan API MikroTik secara langsung.
- **Tema Overrides Dark Mode Global**: Menambahkan stylesheet kustom `css/modern-override.css` yang secara paksa menerapkan desain bertema gelap (Dark Mode Only) yang modern, premium, dan konsisten di seluruh halaman admin panel Mikhmon (sidebar, navbar, tabel, form, input, tombol, font).
- **Penyimpanan Database JSON Terstruktur**: Mengganti penyimpanan `config.php` yang rentan rusak dengan file database JSON terenkripsi/terlindungi (`data/database.json`) menggunakan mekanisme lock file (`flock`) untuk mencegah korupsi data saat akses simultan.
- **Arsitektur MVC & Autoloading**: Implementasi model OOP (`RouterSession` dan `AppSettings` di bawah namespace `App\Models`) dengan PSR-4 autoloader internal di `include/autoload.php`.
- **Skeleton Loading & Sukses Payment (Portal Pelanggan)**: Skeleton loading `.skeleton-card` glassmorphic shimmer animation saat menunggu status transaksi, notifikasi copy toast `#copyToast` dengan CSS transition, dan pulse animation pada CTA button.
- **Areaspline Bandwidth Charts**: Modifikasi grafik Highcharts dashboard (`home.php` & `trafficmonitor.php`) menjadi smooth spline curve dengan fill gradient warna (indigo/emerald ke transparan).
- **Dark/Light Mode Toggle Switch**: Menambahkan tombol switch (sun & moon icon) di navbar kanan yang terintegrasi ke switch theme otomatis.
- **Status Dot, Checkout Stepper, & Layout Switcher (Portal Pelanggan)**: Menambahkan indikator status online/offline berdenyut pada logo header, progress bar checkout stepper wizard (Pilih -> Bayar -> Hubungkan), dan tombol view switcher (Grid vs Carousel) dengan CSS Scroll Snap dan penyimpanan state di `localStorage`.
- **Sparkline Canvas & Badge Log Berwarna (Admin Dashboard)**: Grafik sparkline real-time berbasis HTML5 Canvas untuk melacak penggunaan CPU & Memory secara visual saat polling data, serta pewarnaan badge otomatis (`getLogBadge()`) untuk memetakan level log hotspot (Info, Warning, Error, System, User, Login/Logout) dengan badge tag berwarna.
- **Anti-Crawler/Anti-Indexing (SEO)**: Menambahkan robots.txt global, meta tag noindex pada halaman depan dan admin, serta pengaturan header X-Robots-Tag pada `.htaccess` untuk mencegah perayapan dan pengindeksan oleh search engine.
- **Proteksi Keamanan (.htaccess Root)**: Menambahkan berkas `.htaccess` utama untuk memblokir akses HTTP langsung ke berkas `.env`, `.git`, dan `.gitignore` di server Apache.
- **Diagram Pendapatan Bulanan (12 Bulan)**: Menambahkan diagram batang interaktif kustom berbasis Highcharts pada dashboard admin (`home.php`) yang menyajikan total pendapatan dan volume voucher terjual secara historis selama 12 bulan terakhir melalui pemrosesan transaksi yang dimuat secara asinkron dari API endpoint `dashboard/income_chart.php`.
- **Integrasi WebSockets (Pusher & Soketi)**: Dukungan notifikasi real-time instan ketika pembayaran QRIS/VA lunas, sehingga pengguna langsung mendapatkan voucher tanpa delay.
- **Sistem Fallback Polling Hybrid**: Jika koneksi WebSocket gagal terhubung dalam 5 detik, sistem otomatis mendegradasi layanan ke HTTP Polling berkala (setiap 3 detik) agar transaksi tetap aman.
- **Pemisahan Berkas Lingkungan (.env)**: Migrasi kunci API eksternal (Midtrans, WebSocket) dari kode program utama ke berkas `.env` di root direktori untuk keamanan maksimal.
- **Isolasi Konfigurasi (`env_config.php`)**: Memindahkan deklarasi variabel dinamis dari `config.php` ke `include/env_config.php` untuk mencegah kerusakan parsing sesi router di Web Admin Panel Mikhmon.
- **Sistem Log Aplikasi Terstruktur**: Fungsi `writeAppLog()` ditambahkan untuk mencatat error/warning penting seputar transaksi dan otentikasi di file `logs/error.log`.
- **Keamanan Berkas Transaksi**: File `.htaccess` otomatis dibuat di folder `voucher/` dan `logs/` untuk memblokir akses HTTP langsung dari luar (`Deny from all`).

### Diubah
- **Hashing Password Admin (bcrypt)**: Migrasi penyimpanan password admin dari skema base64/XOR ke bcrypt (`password_hash()` / `password_verify()`). Password lama di-upgrade otomatis saat login sukses pertama kali tanpa intervensi manual.
- **Validasi Sesi & Perbaikan Redirect Loop**: Memperbaiki masalah loop redireksi tak terbatas pada halaman login admin dengan hanya melakukan validasi parameter `$_GET['session']` di `readcfg.php` ketika parameter sesi tersebut tidak kosong, serta menyembunyikan PHP notices saat sesi tidak valid.
- **Konsolidasi Koneksi Router (Selling Report)**: Mengurangi 5x panggilan `$API->connect()` menjadi 1x koneksi tunggal di `report/selling.php`, serta menerapkan filter `.proplist` pada query `/system/script/print` untuk memangkas payload data.
- **Cache Profil Hotspot (Frontpage)**: Menambahkan file-based cache dengan TTL 5 menit untuk daftar profil paket voucher di halaman pelanggan (`frontpage.php`), mengurangi koneksi TCP ke router MikroTik pada setiap kunjungan halaman publik.
- **Optimasi Query Resource & System Clock**: Menghapus query `/system/clock/print` dari pemroses AJAX reloads (`aload.php`) karena banner jam terupdate secara mandiri di sisi client via JavaScript, serta menerapkan filter `.proplist` pada query `/system/resource/print` di dashboard (`home.php` & `aload.php`) untuk membatasi payload data hanya pada field yang digunakan.
- **Caching Info Routerboard**: Mengimplementasikan session-level caching untuk query `/system/routerboard/print` di dashboard dan pemroses AJAX (`aload.php`), memangkas ribuan request API tak perlu ke router.
- **Desain Dropdown Select Modern**: Memperbarui class `.dropd` pada stylesheet kustom untuk memberikan desain dropdown pilihan interface (*select inputs*) yang modern, premium, dan serasi dengan tema gelap TailAdmin.
- **Pembersihan Query Redundan & Optimasi Payload MikroTik**: Menghilangkan query `count-only` tambahan yang tidak perlu pada menu User Aktif dan Users List (jumlah baris dihitung secara lokal di PHP via `count()`), serta menerapkan filter `.proplist` pada query logs and user lists ke router untuk hanya memuat properti data yang digunakan saja (mengurangi ukuran payload data router hingga 70% dan memangkas waktu load halaman).
- **Desain Premium Dashboard & Layout Admin (Dark Mode Only)**: Mengubah total layout visual panel admin dengan gaya gelap premium, font Plus Jakarta Sans, card bergaya glassmorphism, spasi baris tabel yang renggang, tombol minimalis bershadow, input field bulat dengan focus-ring, serta menyembunyikan tombol pemilih tema agar tampilan konsisten gelap.
- **Desain Premium Dashboard Admin**: Merombak tampilan dashboard admin (`home.php`) dengan desain card modern, border tipis, bayangan lembut, font Plus Jakarta Sans, ikon bergaya lingkaran minimalis, serta penyesuaian otomatis warna background, border, dan teks yang beradaptasi secara dinamis ke tema aktif (Dark, Light, dll.) menggunakan CSS Variables.
- **Desain Premium 2-Panel Login (Admin)**: Merombak total tampilan login panel admin (`login.php`) menggunakan struktur 2-panel modern (panel visual info/branding di sisi kiri dengan efek blob & panel form interaktif di sisi kanan), font premium Plus Jakarta Sans, tombol input minimalis dengan ikon, eye-toggle untuk visibilitas password, serta layout responsive-collapse di mobile.
- **De-obfuscation Total Kode JavaScript**: Refaktor dan konversi seluruh script inline yang sebelumnya disamarkan (obfuscated) dengan array hex menjadi kode JavaScript/jQuery modern yang bersih, efisien, dan mudah dipelihara.
- **Penghapusan Proteksi DRM Logo/Brand**: Menghapus script validasi paksa innerHTML `#brand` (`You destroy MIKHMON`) agar visual dashboard dapat disesuaikan secara bebas untuk branding modern MikhTrans.
- **Card-Based Log Feed**: Mengubah layout log hotspot dashboard dari tabel kaku menjadi daftar log card modern dengan shadow, margin lembut, dan transisi hover.
- **Floating Labels (Settings & Sessions)**: Form input settings dan sessions admin menggunakan pola floating label modern yang bergerak halus ke atas disertai focus-ring.
- **Penghapusan Data Sensitif Pribadi**: Mengganti data email, nomor WhatsApp, dan alamat kantor riil dalam berkas `frontpage.php` dan `composer.json` dengan data dummy demi menjaga privasi di repositori publik.
- **Pelacakan Berkas config.php**: Mengeluarkan `/include/config.php` dari daftar `.gitignore` dan menyertakannya di git agar sistem langsung dapat membaca database sesaat setelah dideploy tanpa manual rename berkas `.example`.
- **Rombak Tampilan Hotspot Log**: Merombak total daftar log hotspot dari tampilan bergaya card kaku menjadi daftar linier bertema *activity feed* yang bersih, borderless, dan hemat ruang. Mengubah tampilan waktu secara bertumpuk (Stacked Date & Time) dan mengelompokkan detail pengguna serta alamat IP secara vertikal agar tidak terpotong pada layar kecil.
- **Standardisasi Kartu Profil Voucher**: Merombak tampilan menu Vouchers (`userbyprofile.php`) dari warna latar belakang acak (*random color flash*) yang mengganggu menjadi kartu-kartu premium yang seragam menggunakan CSS Variable, lengkap dengan transisi hover bersinar (*primary glow*) dan ikon tiket modern yang disesuaikan secara dinamis di semua variasi tema.
- **Desain Premium Light Theme**: Antarmuka halaman depan diubah sepenuhnya dengan tema terang premium berbasis HSL (Soft whites, slate text, indigo & emerald accents).
- **Mobile Navigation Bar**: Ditambahkan bilah navigasi statis di bagian bawah (Bottom Nav) pada perangkat mobile dengan highlight otomatis tab aktif berdasarkan posisi scroll.
- **Peningkatan Idempotensi Webhook**: Refaktor file `notification.php` agar dapat mendeteksi pembayaran ganda (idempoten) dan langsung mengembalikan HTTP 200 tanpa memproses ulang/menerbitkan voucher baru.
- **Auto Cleanup Log**: Penghapusan otomatis file log transaksi usang (> 2 hari) dengan mekanisme acak (probabilitas 5% saat halaman dimuat).

### Diperbaiki
- **Penanganan Inaktivitas Tab Browser (Visibility API)**: Menghubungkan Page Visibility API pada dashboard utama dan halaman pemantau traffic real-time (`traffic/trafficmonitor.php`) agar otomatis menjeda (*pause*) polling/refresh ke router saat tab browser berada di background, lalu melanjutkan (*resume*) secara instan ketika tab kembali aktif, menghemat beban CPU MikroTik dan server.
- **Eror Disappearing & Invisible Cards**: Memperbaiki layout flexbox untuk kartu pendapatan di HP agar tidak tergencet/hilang, serta memulihkan visibilitas kartu sesi aktif di Router List dengan menstandardisasi warna teks dan latar belakang kartu sesuai variabel tema aktif.
- **Visual Flicker Polling Dashboard**: Sinkronisasi template markup AJAX `dashboard/aload.php` untuk widget status resource (`#r_1`) dan overview hotspot (`#r_2`) agar identik dengan `dashboard/home.php`, sehingga mencegah kedipan (flicker) visual ke template lama saat polling data otomatis berjalan.
- **Akses Forbidden Cetak/Lihat Voucher**: Memperbaiki masalah `403 Forbidden` saat mencoba mengakses cetak/lihat voucher hotspot (`print.php` & `vpreview.php`) di halaman settings, dengan memperbarui aturan keamanan di `voucher/.htaccess` agar hanya memblokir file transaksi `*.json` dan tetap mengizinkan eksekusi file PHP.
- **Eror Dropdown Sesi (Voucher Hotspot)**: Memperbaiki error pada dropdown pemilih sesi di navbar (`menu.php`) yang menampilkan variabel internal PHP dari file `config.php` alih-alih nama sesi router yang terdaftar, dengan mengalihkan loop untuk langsung membaca key dari array `$data` hasil query database.
- **Eror Daftar Sesi (Router List)**: Memperbaiki kesalahan parsing naif di `sessions.php` yang sebelumnya membaca parameter konfigurasi eksternal sebagai sesi router aktif dengan merutekan manajemen sesi langsung via database model.
- **Kerentanan Keamanan XSS/DOM Injection**: Memperbaiki alert code scanning GitHub Advanced Security pada `mikhmon.js` dengan mengubah fungsi manipulasi DOM `.html(n)` menjadi `.text(n)` yang lebih aman dari potensi manipulasi HTML tak tepercaya.
- **Kompatibilitas putenv() Nonaktif**: Penyesuaian pemuatan berkas `.env` di `env_config.php` dan `config.php.example` dengan mengecek ketersediaan fungsi `putenv()` serta membuat fungsi pembantu `mikhmonEnv()` sebagai fallback ke `$_ENV`/`$_SERVER` agar kompatibel di semua VPS.
- **Infinite Redirect Loop (Settings)**: Memperbaiki loop redirect tanpa henti di `settings/settings.php` dengan menambahkan fungsi `exit;` setelah redirect JavaScript serta menetapkan nilai mata uang default jika kosong.
- **Disappearing Income Card Bug**: Menyelesaikan bug di mana kartu pendapatan atas (Income card) mendadak hilang dari layar setiap 10 detik saat AJAX polling system resource terpicu. Solusi dilakukan dengan memisahkan penampung target update `#r_1` menggunakan CSS `display: contents;` sehingga kartu Pendapatan (`#r_4`) tetap menjadi sibling sejajar tanpa ikut terhapus atau tertimpa oleh response `aload.php`.
- **Theme Toggle Skewed Background Bug**: Memperbaiki distorsi latar belakang miring (jajaran genjang) pada tombol pemilih tema di navbar saat kursor diarahkan (hover), dengan mengisolasi transformasi rotasi 15 derajat secara khusus pada elemen ikon `<i>` alih-alih seluruh kontainer tombol `<a>`.
- **Teks Input & Ikon Mata Tumpang Tindih (Login Page)**: Memperbaiki masalah di mana ikon pengguna/gembok bertumpang tindih dengan teks placeholder akibat konflik spesifisitas CSS padding, serta menengahkan posisi ikon mata kata sandi secara vertikal (`top: 50%`, `transform: translateY(-50%)`) agar sejajar simetris di sisi kanan input.
- **Layout Overflow di HP**: Perbaikan CSS grid layout 1 kolom pada mobile view agar card paket internet tidak melebar melebihi layar.
- **Tag PHP Hilang**: Memulihkan tag pembuka `<?php` pada file `include/config.php` yang sempat terhapus.
- **Kompatibilitas Tanggal RouterOS v7.10+**: Penanganan perubahan format tanggal RouterOS dari `mmm/dd/yyyy` ke `yyyy-mm-dd` pada skrip profile hotspot `on-login` dan monitoring scheduler kedaluwarsa agar masa aktif voucher berfungsi sempurna di versi RouterOS baru maupun lama.

### Dihapus
- **Berkas Migrasi SQL/JSON Usang**: Menghapus file migrasi lawas yang tidak digunakan lagi (`system/migrate.php`, `system/migrate_v2.php`, `system/migrate_v3.php`) untuk merapikan struktur berkas proyek.
- **Berkas Sisa Tidak Terpakai**: Menghapus berkas `.profile` (chmod script yang tidak sesuai), `_config.yml` (konfigurasi Jekyll GitHub Pages milik penulis asli), dan `verson.txt` (berkas teks versi duplikat dengan nama typo) yang tidak dibaca oleh aplikasi dan tidak berkaitan dengan fungsionalitas MikhTrans.
