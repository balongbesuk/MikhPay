# Changelog

Semua pembaruan penting pada modifikasi MikhPay ini akan dicatat di dokumen ini.

## [MikhPay v2.0] - 2026-06-27

### Ditambahkan
- **Sistem QRIS Dinamis Mandiri**: Merombak total metode pembayaran dengan menghilangkan dependensi ke pihak ketiga (Midtrans), digantikan dengan mesin pembuat QRIS dinamis mandiri (berjalan secara offline/lokal) yang secara otomatis menyisipkan kode nominal unik (1-99) dan meregenerasi checksum CRC16 pada kode QR statis GoPay pengguna. 
- **Endpoint Verifikasi Otomatis (qris_verify.php)**: Menambahkan antarmuka API khusus yang mendukung verifikasi otomatis berbasis nominal unik via webhook pihak ketiga atau MacroDroid.
- **Auto Cleanup Transaksi (cron_qris)**: Menambahkan *cron job* untuk membersihkan riwayat tagihan *pending* yang sudah berusia lebih dari 15 menit agar kode unik terbebaskan kembali untuk transaksi baru.
- **Batasi Limit Transaksi Pending per Pelanggan**: Mengintegrasikan sistem proteksi spamming di mana 1 IP pelanggan dibatasi hanya bisa memiliki maksimal 1 transaksi pending aktif. Jika mencoba memesan paket kembali, sistem secara cerdas akan langsung memulihkan (*restore*) modal QRIS transaksi aktif yang sama untuk mengamankan slot kode unik.
- **Sistem Pembatalan Pesanan Aktif**: Memperbarui tombol "Batalkan Pesanan" pada halaman utama agar langsung menghapus berkas transaksi pending terkait secara aman di server, membebaskan kode unik agar pelanggan bisa berganti pilihan paket internet secara instan.
- **Validasi Ketersediaan Router Real-Time**: Mengintegrasikan sistem verifikasi koneksi API MikroTik secara *real-time* sesaat sebelum transaksi QRIS dibuat, mencegah pembuatan tagihan baru jika router dalam kondisi offline/mati lampu demi menghindari transaksi gantung.
- **Panduan Screenshot untuk Browser Wi-Fi**: Menambahkan petunjuk alternatif bagi pelanggan jika tombol unduh QRIS gagal mengeksekusi penyimpanan berkas akibat batasan keamanan mini-browser Captive Portal bawaan HP.

### Dihapus
- **Midtrans Payment Gateway**: Menghapus total seluruh integrasi SDK Snap JavaScript, antarmuka checkout Midtrans UI overlay, pemanggil webhook (`notification.php`), serta konfigurasi kredensial *server key* Midtrans dari dalam basis kode proyek.

### Diperbaiki
- **Batalkan Transaksi QRIS (Sticky Overlay)**: Memperbaiki *bug* pada halaman *frontpage* di mana *overlay* pop-up pembayaran QRIS tidak mau tertutup ketika tombol "Batalkan" ditekan. Tombol telah direstrukturisasi agar melakukan muat ulang halaman penuh (*hard reload*) alih-alih sekadar lompat *hash fragment*, sekaligus mencabut sistem pengecekan riwayat usang (*Resume Flow*) peninggalan Midtrans.
- **Penyelamatan Variabel Global `$data` (`qris_verify.php`)**: Memperbaiki konflik variabel kritis di mana `$data` hasil pembacaan JSON transaksi menimpa variabel global `$data` sesi router dari database, menyebabkan error "Session tidak ditemukan". Variabel internal diubah menjadi `$transData`.
- **Pembersihan PHP Warning (`qris_verify.php`)**: Menghapus pemanggilan file non-eksisten `include/functions.php` yang memicu peringatan PHP. Peringatan tersebut sebelumnya mengotori respons API sehingga peramban gagal melakukan *parsing* JSON (*SyntaxError*). Pembuatan username acak dialihkan menggunakan fungsi bawaan `randNLC` jika tersedia.
- **Pemasangan Script `qrious.min.js` (`frontpage.php`)**: Memperbaiki masalah QR Code auto-login voucher yang kosong (putih polos) di halaman struk sukses karena library *qrious* tidak dimuat.
- **Pengecekan Status Router Real-Time di Halaman Sukses**: Memperbaiki status router yang keliru tampil "Offline" pada halaman struk voucher sukses dengan tetap menjalankan fungsi cek koneksi MikroTik secara *real-time*.
- **Penyelarasan Versi Admin Dashboard**: Memperbarui teks penanda versi hardcoded pada banner utama dashboard admin (`settings/pending_transactions.php`) agar sesuai dengan rilis MikhPay v2.0 terbaru.
- **Optimalisasi Pop-up QRIS Seluler**: Mengubah desain overlay pop-up modal QRIS menjadi berbasis *block layout* dengan *auto margin* dan *overflow scrolling* agar tidak terpotong di layar HP ber-viewport kecil (CNA).
- **Resolusi Konflik Transaksi NAT (Satu IP Publik)**: Mengubah parameter pendeteksi transaksi aktif unik dari basis alamat IP menjadi basis Browser Session ID (`session_id()`). Perbaikan ini mencegah perangkat berbeda (seperti PC dan HP) di bawah jaringan Wi-Fi lokal yang sama saling menimpa pesanan QRIS yang sama.

### Keamanan
- **Proteksi Berkas Transaksi via PHP Encapsulation**: Mengubah ekstensi penyimpanan berkas transaksi dari `.json` menjadi `.php` (`trans-*.php`) yang dilindungi header `403 Forbidden` di baris pertama. Ini mencegah unduhan langsung berkas transaksi berisi kode voucher secara ilegal pada web server Nginx/Apache.
- **Pencegahan Directory Listing Nginx**: Menambahkan berkas `index.php` berproteksi 403 di dalam direktori sensitif (`voucher/`, `data/`, dan `logs/`) untuk menutup celah keamanan perayapan folder (*folder traversal*).


## [MikhPay v2.0] - 2026-06-25

### Rebranding
- **Rebranding Global**: Mengubah seluruh nama aplikasi dan referensi teks dari "MikhTrans" menjadi "MikhPay" di seluruh kode sumber, pengaturan, dan dokumentasi.

---

## [MikhPay v1.8] - 2026-06-21

### Keamanan / Pengerasan Sistem
- **Proteksi Brute-Force Login Admin**: Membatasi kegagalan percobaan login administrator maksimal 5 kali dalam waktu 10 menit per alamat IP di `admin.php` menggunakan database rate-limit terisolasi untuk melindungi sistem dari serangan menebak kata sandi.
- **Konfigurasi Lingkungan Aman `.env.php`**: Mengamankan penyimpanan variabel lingkungan dengan memigrasikan `.env` ke `.env.php` lengkap dengan tag PHP penolak akses (`exit;`), sehingga melindungi kunci rahasia dari kebocoran teks polos pada web server non-Apache (seperti Nginx, IIS, dan Caddy).
- **Verifikasi Status Ganda Midtrans**: Webhook handler (`notification.php`) kini melakukan verifikasi ganda (Dual-Check Verification) dengan menanyakan langsung status transaksi secara *asynchronous server-to-server* ke API Midtrans sebelum memproses pembuatan voucher di MikroTik.
- **Pembersihan Cache Logout**: Menambahkan pembersihan total cache peramban (`localStorage` & `sessionStorage` terkait data transaksi dan sesi) saat administrator menekan tombol logout.

### Diperbaiki
- **Layout Tabel Mobile**: Memperbaiki pemotongan tampilan baris pada tabel data standar (`table-bordered`) dan tabel log aktivitas Hotspot (`card-table-modern`) di perangkat seluler dengan mengecualikan tabel data dari skema penumpukan paksa (form stacking) dan menambahkan dukungan scroll horizontal.
- **ReferenceError safeLoad()**: Memperbaiki masalah gagalnya sistem memuat ulang (auto-refresh) tabel data secara asinkron di luar halaman Dashboard akibat deklarasi fungsi pembantu `safeLoad()` yang sebelumnya secara keliru terisolasi di dalam blok kondisional halaman utama. Fungsi kini telah ditarik ke scope global `index.php` dan tersedia di seluruh penjuru aplikasi.
- **Layout Welcome Banner Mobile**: Menyelaraskan teks waktu dan tanggal pada *banner* selamat datang (Dashboard) agar rata kiri dengan menyetel ulang aturan flexbox (`align-items: flex-start`) pada tampilan seluler, mencegah teks waktu tampil mengambang di tengah layar.
- **SyntaxError JSON.parse (Traffic Monitor)**: Memperbaiki kemunculan error konsol merah beruntun (`Unexpected token '<'`) pada grafik pantauan lalu lintas (Traffic Monitor) dan dasbor yang terjadi ketika sesi login admin telah kedaluwarsa. Sistem kini menangkap kegagalan *parsing* secara halus (`try-catch`) saat AJAX secara tak sengaja memuat halaman login HTML, mencegah *crash* pada *script* di peramban pengguna.
- **TypeError DOM Reference (Halaman Login)**: Memperbaiki *error javascript* (`Cannot read properties of null`) yang muncul di halaman *login* karena *script* UI (`mikhmon-ui.min.js` dan `mikhmon.js`) mencoba mengakses elemen antarmuka (*sidebar toggle*, penanda waktu sesi) yang tidak ada di halaman tersebut. Pengecekan elemen (*null check*) telah ditambahkan.
- **Error 404 GitHub Update Checker**: Menghapus *script* bawaan pengecek pembaruan (Update Checker) di halaman pengaturan Sesi (Sessions). *Script* lama tersebut mencoba memuat data `version.txt` dari repositori GitHub Mikhmonv3 versi orisinal yang saat ini sudah tidak tersedia, sehingga selalu menyebabkan *error* 404 *Not Found* di konsol peramban. Fitur ini tidak lagi relevan untuk versi *fork* MikhPay.
- **Toggle Tema Macet di Dashboard**: Memperbaiki isu di mana mengubah tema (gelap/terang) atau bahasa saat berada di halaman *Dashboard* menyebabkan antarmuka macet dengan pesan `Loading theme...` yang tidak berkesudahan. Ini disebabkan karena proses *background* (AJAX) secara tak sengaja ikut memuat seluruh halaman *Dashboard* ke dalam *buffer*, sehingga menyebabkan konflik *script*. Mekanisme penggantian tema (`settheme.php`) dan bahasa (`setlang.php`) kini secara eksplisit menghentikan *rendering* halaman setelah tema berhasil diubah (`exit;`), memastikan peramban selalu berhasil di-*refresh*.

---

## [MikhPay v1.7] - 2026-06-21

### Keamanan / Pengerasan Sistem
- **Session Fixation Protection**: Mengintegrasikan `session_regenerate_id(true)` secara dinamis pada saat login admin berhasil untuk mencegah pembajakan sesi.
- **Session Cookie Hardening**: Mengamankan parameter `session_start()` di semua entry point (`admin.php`, `frontpage.php`, `index.php`, `include/csrf.php`) dengan mengonfigurasi `HttpOnly`, `SameSite=Lax`, `use_only_cookies`, dan `Secure` dinamis yang menyesuaikan diri otomatis pada server lokal (HTTP non-SSL).
- **Otentikasi Header API Key**: Menambahkan dukungan otentikasi via header HTTP `X-API-Key` dan `x-api-key` pada modul cron web (`process/cron_retry.php`) dengan tetap menyediakan fallback aman ke query parameter `api_key` untuk kompatibilitas ke belakang.
- **Webhook Rate Limiting**: Menerapkan rate limiter tangguh (maksimal 60 request per menit per alamat IP) menggunakan pencatatan terenkripsi dengan *file locking* (`flock`) pada Midtrans webhook handler (`notification.php`) guna menangkal serangan Denial of Service (DoS).

---

## [MikhPay v1.6] - 2026-06-21

### Ditambahkan
- **Riwayat Pembelian Lokal (localStorage)**: Menambahkan kontainer riwayat voucher pembelian terakhir pelanggan yang terintegrasi di atas daftar paket produk, lengkap dengan tombol salin andal, status paket, dan tombol auto-connect.
- **Tombol Hubungi WhatsApp Admin**: Tombol bantuan berwarna hijau khas WhatsApp dengan pesan template otomatis terisi Order ID dan detail transaksi ketika pelanggan mengalami kendala router offline selama pembayaran.
- **Keandalan Tombol Salin Fallback**: Menyediakan fungsi salin teks fallback menggunakan elemen temporary textarea di frontpage untuk menjamin tombol salin 100% berfungsi di in-app browser HP pelanggan di bawah protokol non-HTTPS (HTTP).
- **Checkout Loading State (Spinner)**: Memberikan umpan balik loading dan melumpuhkan (disable) tombol beli seketika setelah formulir disubmit untuk mencegah checkout ganda.
- **Panduan QR Code Sukses**: Keterangan instruksi berbahasa Indonesia untuk scan QR Code di halaman sukses.

---

## [MikhPay v1.5] - 2026-06-21

### Ditambahkan
- **QR Code Voucher Offline (QRious)**: Integrasi pustaka Javascript QRious (`js/qrious.min.js`) pada halaman sukses portal pelanggan (`frontpage.php`). QR Code secara otomatis di-render secara *client-side* (offline-compatible) mengarah ke URL auto-login hotspot (jika DNS name aktif) atau ke kode voucher, diposisikan secara berdampingan (*side-by-side flex layout*) dengan kode voucher teks biasa.
- **Filter Status Riwayat Transaksi**: Ditambahkan dropdown filter status transaksi pada tab Riwayat Transaksi admin panel (`pending_transactions.php`) untuk menyaring baris tabel secara instan berdasarkan status (Semua, Lunas/Success, Pending, Gagal/Expired) menggunakan filter DOM Javascript *client-side* tanpa reload halaman.

### Diperbaiki / Keamanan
- **SSL Peer Verification**: Mengaktifkan kembali verifikasi sertifikat SSL (`verify_peer => true` dan `verify_peer_name => true`) pada koneksi API Midtrans dan integrasi Telegram Bot untuk mencegah serangan *Man-in-the-Middle* (MITM).
- **display_errors Dimatikan**: Mengubah `ini_set('display_errors', 0)` pada portal pelanggan (`frontpage.php`) dan webhook handler (`notification.php`) untuk menghentikan kebocoran stack trace/path internal server pada environment produksi.
- **Verifikasi IP Webhook Midtrans**: Menambahkan validasi alamat IP pengirim webhook Midtrans (`REMOTE_ADDR`) berbasis whitelist prefix IP resmi Midtrans di `notification.php` untuk environment produksi.
- **Validasi Parameter Retry**: Menerapkan validasi strict regex (`/^[a-zA-Z0-9\-]+$/`) dan validasi realpath pada parameter POST `order_id` di `pending_transactions.php` untuk mencegah serangan directory traversal.

---

## [MikhPay v1.4] - 2026-06-21

### Ditambahkan
- **Welcome Status Banner & Stats**: Banner `.dash-welcome` bernuansa gradient biru-ungu di bagian atas menu Antrean Webhook untuk menyajikan status bot Telegram, jumlah antrean, jumlah transaksi sukses, dan total omzet nominal secara dinamis.
- **Desain Tab Modern & Ikonik**: Navigasi tab panel dilengkapi dengan ikon FontAwesome pada masing-masing tombol dan aksen warna active/hover adaptif sesuai CSS variable (`--primary`, `--primary-glow`).
- **Visualisasi Grafik Penjualan Dinamis**: Integrasi Chart.js di tab Analitik Penjualan yang adaptif membaca computed CSS variable `--primary` untuk sinkronisasi warna chart otomatis saat tema admin diubah.
- **Pemisahan Tombol Simpan Pengaturan**: Menambahkan tombol simpan spesifik di masing-masing form pengaturan (Telegram Bot, Portal Pelanggan, Retensi Log) untuk fungsionalitas simpan terpisah yang lebih intuitif.

### Diubah
- **Penyelarasan Tata Letak Berdampingan (Side-by-Side Flex Layout)**: Menggunakan custom flexbox (`.row-flex` dan `.col-flex-6`) berbasis `box-sizing: border-box` di tab Pengaturan & Backup agar kolom konfigurasi seimbang, terbagi merata 50/50 secara sejajar di desktop, dan responsif menumpuk di perangkat seluler.

### Diperbaiki
- **Kebocoran Background Abu-Abu Tab Panel**: Menambahkan aturan override `.tab-panel.active { background: transparent !important; border-radius: 0 !important; }` guna mematikan pewarisan gaya latar belakang abu-abu dan border-radius dari kelas `.active` global bawaan tema Mikhmon.
- **Penyelarasan Tabel**: Membungkus tabel antrean tertunda dan riwayat transaksi sukses menggunakan container `.overflow` dengan border radius `12px` dan scrollbar kustom.
- **Tampilan Log Hotspot Mobile**: Menghidupkan kembali visibilitas kolom nama pengguna/kode voucher hotspot serta kolom status aktivitas pada tampilan mobile (mencegah kolom mengecil ke 0px akibat flexbox shrink dengan `min-width: 70px`, serta menolak pewarisan lebar 100% dari layout form-tabel default dengan `width: auto` yang sebelumnya mendorong kolom status keluar dari batas kanan layar).
- **Tata Letak Responsif Antrean Webhook**: Menyelaraskan tab navigasi pada halaman Antrean Webhook agar dapat di-scroll secara horizontal (`overflow-x: auto; white-space: nowrap;`) pada perangkat seluler alih-alih berantakan/wrap vertikal, serta mengoptimalkan padding dan arah flexbox welcome banner untuk layar kecil.

---

## [MikhPay v1.3] - 2026-06-21

### Ditambahkan
- **Notifikasi Telegram Bot (Admin Alert)**: Pengiriman notifikasi real-time instan ke admin via Telegram jika ada transaksi lunas Midtrans namun pembuatan voucher di MikroTik tertunda (`paid_pending_generate`) akibat router offline.
- **Sistem Backup Data Hibrida (ZIP & TAR)**: Fitur "Buat & Unduh Backup" pada halaman admin untuk mencadangkan berkas sensitif (`.env`, `data/database.php`, dan semua log transaksi `voucher/*.json`). Menggunakan kompresi `.zip` jika ekstensi PHP `ZipArchive` terpasang, dengan fallback otomatis ke arsip `.tar` menggunakan generator ustar murni (pure PHP) jika modul ZIP tidak tersedia di server.
- **Tab Pengaturan & Backup**: Panel baru di halaman Antrean Webhook (`settings/pending_transactions.php`) untuk mengelola kredensial Telegram Bot (Bot Token & Chat ID), melakukan pengujian pengiriman notifikasi, dan mengunduh cadangan data.

---

## [MikhPay v1.2] - 2026-06-21

### Ditambahkan
- **Antrean Webhook (Resilience Queue)**: Menyimpan transaksi dengan status `paid_pending_generate` jika router offline sewaktu webhook Midtrans melacak pelunasan. Ditambahkan tombol "Generate/Retry" di admin panel dan informasi error yang ramah di portal frontpage.
- **REST API Rate Limiting**: Membatasi laju request ke `api.php` hingga 30 request/menit/IP menggunakan pembacaan file cache `data/rate_limit.json` yang terproteksi file lock.
- **Dashboard Ringkasan Multi-Router**: Mengubah halaman daftar sesi di `sessions.php` menjadi grid visual interaktif yang menampilkan status online, CPU, uptime, dan jumlah user aktif yang dimuat secara asinkron (AJAX) secara paralel untuk tiap router.

---

## [MikhPay v1.1] - 2026-06-21

### Diubah
- **Pembersihan Kode Native PHP 8.2 (Depresiasi PHP 5.4)**: Menghapus seluruh polyfill kompatibilitas PHP versi lama (`hash_equals`, `password_hash`, `password_verify`) dan memfaktorkan ulang kode pada `include/csrf.php` serta `lib/routeros_api.class.php` agar memanfaatkan fungsi bawaan PHP 8.2+ secara langsung (seperti `random_bytes` untuk generate IV dan token CSRF).

### Dihapus
- **Binary PHP 5.4 Lama**: Menghapus folder cadangan `php_backup/` (PHP 5.4.17) secara permanen dari disk lokal untuk menyelesaikan migrasi penuh ke arsitektur PHP 8.2 modern.

---

## [MikhPay v1.0] - 2026-06-21

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
- **Penghapusan Proteksi DRM Logo/Brand**: Menghapus script validasi paksa innerHTML `#brand` (`You destroy MIKHMON`) agar visual dashboard dapat disesuaikan secara bebas untuk branding modern MikhPay.
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
- **Berkas Sisa Tidak Terpakai**: Menghapus berkas `.profile` (chmod script yang tidak sesuai), `_config.yml` (konfigurasi Jekyll GitHub Pages milik penulis asli), dan `verson.txt` (berkas teks versi duplikat dengan nama typo) yang tidak dibaca oleh aplikasi dan tidak berkaitan dengan fungsionalitas MikhPay.
