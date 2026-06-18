# Mikhmon v3 - Modernized with REST API & Midtrans QRIS

Mikhmon v3 (Mikrotik Hotspot Monitor) adalah aplikasi web berbasis PHP untuk mengelola dan memantau Hotspot MikroTik, khususnya untuk pembuatan voucher otomatis. Versi ini telah dimodernisasi dengan penambahan **REST API** untuk integrasi eksternal dan **Portal Pembelian Voucher Mandiri** terintegrasi dengan **Midtrans Payment Gateway**.

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

## 🛠️ Panduan Konfigurasi

### 1. Pengaturan Kredensial & API Key
Buka file `include/config.php` dan sesuaikan parameter berikut:
```php
// API Key untuk mengamankan endpoint REST API (api.php)
$mikhmon_api_key = "mikhmon_api_key_12345";

// Kredensial Midtrans (Dapatkan dari Dashboard Midtrans Anda)
$midtrans_server_key = "SB-Mid-server-YOUR_SANDBOX_SERVER_KEY";
$midtrans_client_key = "SB-Mid-client-YOUR_SANDBOX_CLIENT_KEY";
$midtrans_is_production = false; // Ubah ke true jika sudah live/produksi
```

### 2. URL Halaman Utama
*   **Web Admin Panel**: `http://172.16.11.91/admin.php`
*   **Portal Mandiri Pelanggan**: `http://172.16.11.91/buy.php`
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
