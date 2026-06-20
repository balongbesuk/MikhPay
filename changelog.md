# Changelog

Semua pembaruan penting pada modifikasi MikhTrans ini akan dicatat di dokumen ini.

## [MikhTrans v2.0] - 2026-06-19

### Ditambahkan
- **Penyimpanan Database JSON Terstruktur**: Mengganti penyimpanan `config.php` yang rentan rusak dengan file database JSON terenkripsi/terlindungi (`data/database.json`) menggunakan mekanisme lock file (`flock`) untuk mencegah korupsi data saat akses simultan.
- **Arsitektur MVC & Autoloading**: Implementasi model OOP (`RouterSession` dan `AppSettings` di bawah namespace `App\Models`) dengan PSR-4 autoloader internal di `include/autoload.php`.
- **Skeleton Loading & Sukses Payment (Portal Pelanggan)**: Skeleton loading `.skeleton-card` glassmorphic shimmer animation saat menunggu status transaksi, notifikasi copy toast `#copyToast` dengan CSS transition, dan pulse animation pada CTA button.
- **Areaspline Bandwidth Charts**: Modifikasi grafik Highcharts dashboard (`home.php` & `trafficmonitor.php`) menjadi smooth spline curve dengan fill gradient warna (indigo/emerald ke transparan).
- **Dark/Light Mode Toggle Switch**: Menambahkan tombol switch (sun & moon icon) di navbar kanan yang terintegrasi ke switch theme otomatis.
- **Status Dot, Checkout Stepper, & Layout Switcher (Portal Pelanggan)**: Menambahkan indikator status online/offline berdenyut pada logo header, progress bar checkout stepper wizard (Pilih -> Bayar -> Hubungkan), dan tombol view switcher (Grid vs Carousel) dengan CSS Scroll Snap dan penyimpanan state di `localStorage`.
- **Sparkline Canvas & Badge Log Berwarna (Admin Dashboard)**: Grafik sparkline real-time berbasis HTML5 Canvas untuk melacak penggunaan CPU & Memory secara visual saat polling data, serta pewarnaan badge otomatis (`getLogBadge()`) untuk memetakan level log hotspot (Info, Warning, Error, System, User, Login/Logout) dengan badge tag berwarna.
- **Anti-Crawler/Anti-Indexing (SEO)**: Menambahkan robots.txt global, meta tag noindex pada halaman depan dan admin, serta pengaturan header X-Robots-Tag pada `.htaccess` untuk mencegah perayapan dan pengindeksan oleh search engine.
- **Proteksi Keamanan (.htaccess Root)**: Menambahkan berkas `.htaccess` utama untuk memblokir akses HTTP langsung ke berkas `.env`, `.git`, dan `.gitignore` di server Apache.

### Diubah
- **Desain Premium 2-Panel Login (Admin)**: Merombak total tampilan login panel admin ([login.php](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/include/login.php)) menggunakan struktur 2-panel modern (panel visual info/branding di sisi kiri dengan efek blob & panel form interaktif di sisi kanan), font premium Plus Jakarta Sans, tombol input minimalis dengan ikon, eye-toggle untuk visibilitas password, serta layout responsive-collapse di mobile.
- **De-obfuscation Total Kode JavaScript**: Refaktor dan konversi seluruh script inline yang sebelumnya disamarkan (obfuscated) dengan array hex menjadi kode JavaScript/jQuery modern yang bersih, efisien, dan mudah dipelihara.
- **Penghapusan Proteksi DRM Logo/Brand**: Menghapus script validasi paksa innerHTML `#brand` (`You destroy MIKHMON`) agar visual dashboard dapat disesuaikan secara bebas untuk branding modern MikhTrans.
- **Card-Based Log Feed**: Mengubah layout log hotspot dashboard dari tabel kaku menjadi daftar log card modern dengan shadow, margin lembut, dan transisi hover.
- **Floating Labels (Settings & Sessions)**: Form input settings dan sessions admin menggunakan pola floating label modern yang bergerak halus ke atas disertai focus-ring.
- **Penghapusan Data Sensitif Pribadi**: Mengganti data email, nomor WhatsApp, dan alamat kantor riil dalam berkas `frontpage.php` dan `composer.json` dengan data dummy demi menjaga privasi di repositori publik.
- **Pelacakan Berkas config.php**: Mengeluarkan `/include/config.php` dari daftar `.gitignore` dan menyertakannya di git agar sistem langsung dapat membaca database sesaat setelah dideploy tanpa manual rename berkas `.example`.

### Diperbaiki
- **Akses Forbidden Cetak/Lihat Voucher**: Memperbaiki masalah `403 Forbidden` saat mencoba mengakses cetak/lihat voucher hotspot ([print.php](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/voucher/print.php) & [vpreview.php](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/voucher/vpreview.php)) di halaman settings, dengan memperbarui aturan keamanan di [voucher/.htaccess](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/voucher/.htaccess) agar hanya memblokir file transaksi `*.json` dan tetap mengizinkan eksekusi file PHP.
- **Eror Dropdown Sesi (Voucher Hotspot)**: Memperbaiki error pada dropdown pemilih sesi di navbar ([menu.php](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/include/menu.php)) yang menampilkan variabel internal PHP dari file `config.php` alih-alih nama sesi router yang terdaftar, dengan mengalihkan loop untuk langsung membaca key dari array `$data` hasil query database.
- **Eror Daftar Sesi (Router List)**: Memperbaiki kesalahan parsing naif di `sessions.php` yang sebelumnya membaca parameter konfigurasi eksternal sebagai sesi router aktif dengan merutekan manajemen sesi langsung via database model.
- **Kerentanan Keamanan XSS/DOM Injection**: Memperbaiki alert code scanning GitHub Advanced Security pada [mikhmon.js](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/js/mikhmon.js) dengan mengubah fungsi manipulasi DOM `.html(n)` menjadi `.text(n)` yang lebih aman dari potensi manipulasi HTML tak tepercaya.
- **Kompatibilitas putenv() Nonaktif**: Penyesuaian pemuatan berkas `.env` di `env_config.php` dan `config.php.example` dengan mengecek ketersediaan fungsi `putenv()` serta membuat fungsi pembantu `mikhmonEnv()` sebagai fallback ke `$_ENV`/`$_SERVER` agar kompatibel di semua VPS.
- **Infinite Redirect Loop (Settings)**: Memperbaiki loop redirect tanpa henti di `settings/settings.php` dengan menambahkan fungsi `exit;` setelah redirect JavaScript serta menetapkan nilai mata uang default jika kosong.



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
