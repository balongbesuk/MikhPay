# MikhTrans v1.1 - Modernized with REST API & Midtrans QRIS

MikhTrans v1.1 (Mikrotik Hotspot Monitor & Transaction System) adalah aplikasi web berbasis PHP untuk mengelola dan memantau Hotspot MikroTik, khususnya untuk pembuatan voucher otomatis. Versi ini telah dimodernisasi dengan penambahan **REST API** untuk integrasi eksternal dan **Portal Pembelian Voucher Mandiri** terintegrasi dengan **Midtrans Payment Gateway**.

Aplikasi ini dikembangkan dan dimodifikasi dari kode sumber asli [Mikhmon v3 oleh Laksamadi Guko](https://github.com/laksa19/mikhmonv3).

---

## 🚀 Fitur Baru

1.  **Portal Pembelian Mandiri (`buy.php`)**:
    *   Tampilan modern (Glassmorphism & Dark Mode).
    *   Integrasi **Midtrans Snap SDK** untuk pembayaran instan via **QRIS**, Virtual Account (BCA, Mandiri, BNI, BRI), dan e-Wallet.
    *   *Real-time Polling* otomatis saat pembayaran lunas untuk langsung menampilkan kode voucher.
    *   Tombol salin kode voucher sekali klik.
2.  **REST API Endpoint (`api.php`)**:
    *   Akses aman dengan **Static API Key**.
    *   Mendapatkan daftar profil hotspot beserta detail harga.
    *   Membuat/generate voucher baru secara programatis dari sistem luar (misal: Website BUMDes atau sistem POS).
3.  **Webhook Notification Handler (`notification.php`)**:
    *   Verifikasi keamanan data notifikasi menggunakan **Signature Key** Midtrans SHA512.
    *   Pemrosesan otomatis pembuatan voucher di MikroTik secara *background* setelah pembayaran diverifikasi lunas.
4.  **PHP 8+ Compatibility**:
    *   Perbaikan sintaks dan penanganan enkripsi/dekripsi agar kompatibel dengan PHP versi 8.x.

---

## 🛠️ Panduan Konfigurasi & Instalasi

### 1. Salin Berkas Lingkungan (.env)
Buat file baru bernama `.env` di root direktori Mikhmon (sejajar dengan `index.php`). Salin dan sesuaikan parameter berikut:
```env
# API Key untuk mengamankan REST API (api.php)
MIKHMON_API_KEY=mikhmon_api_key_12345

# Kredensial Midtrans (Dapatkan dari Dashboard Midtrans Anda)
MIDTRANS_SERVER_KEY=SB-Mid-server-YOUR_SANDBOX_SERVER_KEY
MIDTRANS_CLIENT_KEY=SB-Mid-client-YOUR_SANDBOX_CLIENT_KEY
MIDTRANS_IS_PRODUCTION=false # Ubah ke true jika sudah live/produksi

# WebSocket (Pusher / Soketi) - Opsional untuk status lunas real-time instan
WS_APP_ID=YOUR_PUSHER_APP_ID
WS_APP_KEY=YOUR_PUSHER_KEY
WS_APP_SECRET=YOUR_PUSHER_SECRET
WS_CLUSTER=ap1

# Konfigurasi Tambahan Soketi (Hanya jika menggunakan server WebSocket sendiri)
# WS_HOST=your-soketi-host.com
# WS_PORT=6001
# WS_SCHEME=https
```

### 2. Pengaturan Sesi & MikroTik
Kredensial MikroTik, nama sesi, IP, user, password, dan dnsname diatur secara normal melalui **Web Admin Panel** Mikhmon pada menu **Admin Settings > Add Router / Edit Settings**. Perubahan tersimpan otomatis di berkas `include/config.php` tanpa mempengaruhi variabel environment Anda.

### 3. URL Halaman Utama
*   **Web Admin Panel**: `http://172.16.11.91/admin.php`
*   **Portal Mandiri Pelanggan**: `http://172.16.11.91/` (Mengarah ke `frontpage.php`)
*   **REST API**: `http://172.16.11.91/api.php`
*   **Notification Webhook**: `http://172.16.11.91/notification.php`

---

## 📡 Integrasi & Notifikasi (Webhook)

Agar kode voucher terbuat secara otomatis di MikroTik setelah pelanggan melakukan pembayaran QRIS/VA:
1.  **Server Hosting/IP Publik**:
    Daftarkan URL notifikasi Anda di dashboard Midtrans pada menu **Settings > Payment > Notification URL**:
    `http://[domain-anda]/notification.php`
2.  **Server Lokal (Intranet)**:
    Gunakan tunnel (seperti Ngrok) untuk meneruskan port web server lokal Anda ke internet.
    ```bash
    ngrok http 80
    ```
    Dapatkan URL HTTPS dari Ngrok (misal: `https://abcd-123.ngrok-free.app`), lalu daftarkan di dashboard Midtrans:
    `https://abcd-123.ngrok-free.app/notification.php`

---

## 💻 Panduan REST API (`api.php`)

Gunakan header `X-API-Key` atau parameter query `api_key` untuk otentikasi.

### A. Mendapatkan Daftar Paket/Profil
*   **Endpoint**: `GET /api.php`
*   **Parameter**:
    *   `action=profiles`
    *   `session=Hotspot` (Nama sesi router Anda)
*   **Contoh Request**:
    `GET http://172.16.11.91/api.php?action=profiles&session=Hotspot&api_key=mikhmon_api_key_12345`

### B. Membuat Voucher Baru
*   **Endpoint**: `POST /api.php`
*   **Parameter POST**:
    *   `action=generate`
    *   `session=Hotspot`
    *   `profile=Profil_1Jam_2K` (Nama profil hotspot di MikroTik)
    *   `qty=1` (Jumlah voucher yang ingin dibuat)

---

## 📝 Changelog (Pembaruan Terbaru)

### 🎨 Tampilan & Navigasi (Mobile First)
*   **Halaman Depan Utama (`index.php` & `frontpage.php`)**: Pengunjung non-admin sekarang diarahkan langsung ke halaman depan berbayar (`frontpage.php`) saat mengakses root directory `index.php`.
*   **Premium Light Theme Overhaul**: Desain antarmuka diubah sepenuhnya ke tema terang premium (Light Theme) berbasis HSL (Soft whites, slate text, indigo & emerald accents).
*   **Bottom Navigation Bar (Mobile)**: Penambahan bilah navigasi bawah statis di HP dengan pelacakan scroll dinamis yang menyorot tab aktif (*Beranda, Beli Voucher, Kebijakan, Kontak*).
*   **Grid Layout Fix**: Penataan letak grid responsive 1 kolom di HP untuk mencegah card melebar melewati batas layar.

### ⚡ Fitur Transaksi & UX (Resilience)
*   **Tombol "Hubungkan Sekarang" (Auto-Login)**: Menampilkan tautan otomatis ke login page hotspot MikroTik berdasarkan konfigurasi `dnsname` ketika voucher lunas.
*   **Pemulihan Checkout (`localStorage`)**: Transaksi aktif di-caching di browser pembeli. Jika tab tertutup atau ter-refresh, sistem memunculkan banner melayang *"Pembayaran Tertunda"* untuk melanjutkan bayar atau membatalkannya.
*   **Edukasi Bayar QRIS**: Penambahan informasi tips cara membayar QRIS satu HP (screenshot QR & upload e-wallet).
*   **Limit Expire QRIS (10 Menit)**: Midtrans Snap diatur kadaluarsa otomatis dalam 10 menit agar tidak menumpuk status transaksi menggantung.

### 🛡️ Audit & Penguatan Keamanan (Security Hardening)
*   **HTTP Security Headers**: Menambahkan header keamanan (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`) di halaman depan.
*   **Checkout POST Cooldown**: Batasan jeda klik checkout 10 detik via session PHP untuk memitigasi serangan spam transaksi.
*   **Whitelisting URL Parameter**: Proteksi tampering parameter `?session=` agar hanya memuat session MikroTik yang valid.
*   **.htaccess Folder Lock**: Pembuatan otomatis file `.htaccess` berisi `Deny from all` di dalam direktori `voucher/` untuk mencegah download ilegal file log transaksi.
*   **Auto Garbage Collector**: Penghapusan otomatis file log JSON transaksi yang berumur lebih dari 2 hari (berjalan acak dengan probabilitas 5% saat halaman dimuat).

### 📡 Notifikasi WebSocket & Isolasi Konfigurasi (Real-time & Stability)
*   **Integrasi Pusher & Soketi**: Menyediakan dukungan WebSocket real-time terintegrasi untuk mendeteksi status transaksi sukses secara instan tanpa delay.
*   **Hybrid Fallback Mechanism**: Kecepatan mendeteksi pembayaran lunas ditingkatkan dengan mode socket, dan otomatis beralih ke HTTP Polling (5 detik) jika sambungan websocket gagal atau terputus.
*   **Pemisahan Berkas Konfigurasi (`env_config.php`)**: Memindahkan variabel environment dan helper audit log dari `include/config.php` ke file terpisah [env_config.php](file:///d:/mikhmonv3ws/Mikhmon%20Server/mikhmon/include/env_config.php). Ini mencegah kesalahan parsing daftar sesi router di halaman admin akibat parameter eksternal.

