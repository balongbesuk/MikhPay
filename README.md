# MikhTrans v1.1 - Modernized with REST API & Midtrans QRIS

MikhTrans v1.1 (Mikrotik Hotspot Monitor & Transaction System) adalah aplikasi web berbasis PHP untuk mengelola dan memantau Hotspot MikroTik, khususnya untuk pembuatan voucher otomatis. Versi ini telah dimodernisasi dengan penambahan **REST API** untuk integrasi eksternal dan **Portal Pembelian Voucher Mandiri** terintegrasi dengan **Midtrans Payment Gateway**.

Aplikasi ini dikembangkan dan dimodifikasi dari kode sumber asli [Mikhmon v3 oleh Laksamadi Guko](https://github.com/laksa19/mikhmonv3).

---

## 🚀 Fitur Utama & Modifikasi Baru

1. **Portal Pembelian Mandiri (`frontpage.php`)**
   - **Tampilan Modern**: Desain premium berorientasi *mobile-first* (Light Theme, Glassmorphism, dan Bottom Nav).
   - **Integrasi Pembayaran**: Mendukung **Midtrans Snap SDK** untuk pembayaran aman via **QRIS**, Virtual Account (BCA, Mandiri, BNI, BRI), dan e-Wallet.
   - **Real-time Updates**: Menggunakan **WebSockets (Pusher/Soketi)** untuk mendeteksi pembayaran lunas secara instan.
   - **Polling Fallback**: Transisi otomatis ke HTTP Polling jika websocket mengalami kendala atau gagal tersambung dalam 5 detik.

2. **REST API Endpoint (`api.php`)**
   - **Keamanan**: Otentikasi aman menggunakan parameter `api_key` atau header `X-API-Key`.
   - **Informasi Paket**: Endpoint cepat untuk melihat profil hotspot dan detail harga.
   - **Generate Voucher**: Pembuatan kode voucher secara otomatis dan programatis untuk diintegrasikan dengan aplikasi pihak ketiga (misal: sistem POS atau web eksternal).

3. **Webhook Notification Handler (`notification.php`)**
   - **Validasi HMAC SHA512**: Verifikasi keamanan signature transaksi langsung dari Midtrans.
   - **Idempotensi**: Pencegahan pemrosesan transaksi berulang untuk menjaga konsistensi database dan router.
   - **Background Processing**: Voucher MikroTik langsung digenerate secara instan saat notifikasi settlement diterima.

4. **Kepatuhan & Penguatan Keamanan (Security Hardening)**
   - **HTTP Security Headers**: Implementasi header keamanan ketat di sisi klien.
   - **Proteksi Direktori**: Mengunci akses langsung ke folder `/logs` dan `/voucher` via aturan `.htaccess`.
   - **Pemisahan Konfigurasi**: Kunci API eksternal diisolasi ke dalam berkas [env_config.php](file:///d:/mikhmonv3ws/Mikhmon%20Server/mikhmon/include/env_config.php) dan `.env` agar tidak mengganggu parser config admin Mikhmon.

---

## 🛠️ Panduan Konfigurasi & Instalasi

### 1. Salin Berkas Lingkungan (`.env`)
Buat file baru bernama `.env` di root direktori MikhTrans Anda (sejajar dengan `index.php`). Salin dan sesuaikan konfigurasi berikut:

```env
# API Key untuk mengamankan REST API (api.php)
MIKHMON_API_KEY=mikhmon_api_key_12345

# Kredensial Midtrans (Dapatkan dari Dashboard Midtrans)
MIDTRANS_SERVER_KEY=SB-Mid-server-YOUR_SANDBOX_SERVER_KEY
MIDTRANS_CLIENT_KEY=SB-Mid-client-YOUR_SANDBOX_CLIENT_KEY
MIDTRANS_IS_PRODUCTION=false # Ubah ke true jika sudah live/produksi

# WebSocket (Pusher / Soketi) - Opsional untuk status lunas real-time instan
WS_APP_ID=YOUR_PUSHER_APP_ID
WS_APP_KEY=YOUR_PUSHER_KEY
WS_APP_SECRET=YOUR_PUSHER_SECRET
WS_CLUSTER=ap1

# Konfigurasi Tambahan Soketi (Hanya jika menggunakan server WebSocket mandiri)
# WS_HOST=your-soketi-host.com
# WS_PORT=6001
# WS_SCHEME=https
```

### 2. Pengaturan Sesi & MikroTik
Kredensial MikroTik, nama sesi, IP, user, password, dan dnsname diatur secara otomatis melalui **Web Admin Panel** MikhTrans pada menu **Admin Settings > Add Router / Edit Settings**. 

> [!IMPORTANT]
> Berkas `include/config.php` bawaan repositori sudah dikonfigurasi secara dinamis untuk memuat sesi dari database (`data/database.json`). Anda **TIDAK PERLU** menyalin atau mengubah nama `config.php.example` menjadi `config.php`. Biarkan `include/config.php` bawaan apa adanya agar pengaturan sesi tetap tersimpan otomatis ke database.

### 3. URL Halaman Utama
*   **Web Admin Panel**: `http://localhost/admin.php`
*   **Portal Mandiri Pelanggan**: `http://localhost/` (Otomatis diarahkan ke `frontpage.php`)
*   **REST API**: `http://localhost/api.php`
*   **Notification Webhook**: `http://localhost/notification.php`

---

## 📡 Integrasi & Webhook Midtrans

Agar kode voucher terbuat secara otomatis di MikroTik setelah pelanggan melakukan pembayaran QRIS/VA:
1. **Server Hosting / IP Publik**:
   Daftarkan URL notifikasi Anda di dashboard Midtrans pada menu **Settings > Payment > Notification URL**:
   `http://[domain-anda]/notification.php`
2. **Server Lokal (Intranet)**:
   Gunakan tunnel (seperti Ngrok) untuk meneruskan port web server lokal Anda ke internet.
   ```bash
   ngrok http 80
   ```
   Dapatkan URL HTTPS dari Ngrok (misal: `https://abcd-123.ngrok-free.app`), lalu daftarkan di dashboard Midtrans:
   `https://abcd-123.ngrok-free.app/notification.php`

---

## 🔌 Integrasi Halaman Login MikroTik (`login.html`)

Agar pelanggan dapat membeli voucher secara langsung saat terhubung ke Hotspot, Anda perlu menghubungkan halaman login bawaan MikroTik (`login.html`) dengan portal MikhTrans.

### 1. Tambahkan Tombol Beli Voucher di `login.html`
Edit berkas `login.html` di dalam folder `hotspot` MikroTik Anda (dapat diunduh via FTP atau menu Files di Winbox). Tambahkan kode tombol berikut:
```html
<a href="http://172.16.11.91/" style="display:block; background:#4f46e5; color:white; padding:12px; text-align:center; border-radius:8px; text-decoration:none; font-weight:bold; margin-top:10px;">
    Beli Voucher Otomatis (QRIS / E-Wallet)
</a>
```
*Ganti `http://172.16.11.91/` dengan alamat IP atau Domain tempat Anda meng-host MikhTrans.*

### 2. Konfigurasi Walled Garden MikroTik
Karena pelanggan yang belum login diblokir akses internetnya oleh MikroTik, Anda wajib mendaftarkan alamat IP server MikhTrans dan domain Midtrans/Pusher ke **Walled Garden** agar dapat diakses secara gratis sebelum login.

Jalankan perintah berikut di **New Terminal** Winbox MikroTik Anda:
```routeros
# Izinkan akses ke Web Server MikhTrans
/ip hotspot walled-garden ip add dst-host=172.16.11.91 action=allow

# Izinkan sistem pembayaran Midtrans & E-Wallet (GoPay, ShopeePay, dll)
/ip hotspot walled-garden add dst-host=*.midtrans.com action=allow
/ip hotspot walled-garden add dst-host=*.sandbox.midtrans.com action=allow
/ip hotspot walled-garden add dst-host=*.gopay.co.id action=allow
/ip hotspot walled-garden add dst-host=*.go-pay.co.id action=allow
/ip hotspot walled-garden add dst-host=*.gopayapi.com action=allow
/ip hotspot walled-garden add dst-host=*.shopeepay.co.id action=allow

# Izinkan WebSocket Pusher untuk deteksi real-time
/ip hotspot walled-garden add dst-host=*.pusher.com action=allow
/ip hotspot walled-garden add dst-host=*.pusherapp.com action=allow

# Izinkan font dan aset web CDN
/ip hotspot walled-garden add dst-host=fonts.googleapis.com action=allow
/ip hotspot walled-garden add dst-host=fonts.gstatic.com action=allow
/ip hotspot walled-garden add dst-host=cdnjs.cloudflare.com action=allow
```
*Ganti `172.16.11.91` pada baris pertama dengan IP/Domain publik hosting portal MikhTrans Anda.*

### 3. Login Otomatis (Auto-Login)
Setelah pembayaran lunas, portal MikhTrans akan memunculkan tombol **"Hubungkan Sekarang"**. Tombol ini otomatis mengirimkan parameter login langsung ke MikroTik (`http://[dnsname]/login?username=[voucher]&password=[voucher]`) sehingga pengguna langsung terhubung ke internet tanpa perlu mengetik kode voucher secara manual.

---

## 💻 Panduan REST API (`api.php`)

Gunakan header `X-API-Key` atau parameter query `api_key` untuk otentikasi.

### A. Mendapatkan Daftar Paket/Profil
*   **Endpoint**: `GET /api.php`
*   **Parameter**:
    - `action=profiles`
    - `session=Hotspot` (Nama sesi router Anda)
*   **Contoh Request**:
    `GET http://localhost/api.php?action=profiles&session=Hotspot&api_key=mikhmon_api_key_12345`

### B. Membuat Voucher Baru
*   **Endpoint**: `POST /api.php`
*   **Parameter POST**:
    - `action=generate`
    - `session=Hotspot`
    - `profile=Profil_1Jam_2K` (Nama profil hotspot di MikroTik)
    - `qty=1` (Jumlah voucher yang ingin dibuat)

---

## 🛠️ Pemecahan Masalah (Troubleshooting)

Berikut adalah beberapa kendala umum yang sering terjadi saat melakukan deploy MikhTrans ke VPS dan cara mengatasinya:

### 1. Error: `Call to undefined function putenv()`
*   **Penyebab**: Panel VPS (seperti aaPanel) menonaktifkan fungsi `putenv()` demi alasan keamanan melalui direktif `disable_functions` di file `php.ini`.
*   **Solusi Kode**: Pada versi 2.0+, MikhTrans sudah dibekali helper `mikhmonEnv()` yang aman dan kompatibel tanpa bergantung pada `putenv()`. Jika Anda masih menemui kendala ini, pastikan Anda menggunakan berkas `include/env_config.php` terbaru.
*   **Solusi Server (aaPanel)**: Jika ingin mengaktifkannya kembali di server:
    1. Buka **aaPanel** -> **App Store** -> Pilih **PHP Settings** (versi PHP yang Anda gunakan).
    2. Masuk ke tab **Disabled functions**.
    3. Cari **`putenv`** di dalam daftar, lalu klik **Del** (Hapus).
    4. Masuk ke tab **Service** lalu klik **Restart**.

### 2. Kolom Input Kosong Kembali setelah Klik "Save" / Ping Host Kosong
*   **Penyebab**: Web server (user `www` atau `www-data`) tidak memiliki hak akses menulis (*write permission*) pada folder `/data`. Hal ini terjadi jika Anda melakukan clone/pull git menggunakan user `root`.
*   **Solusi**: Ubah kepemilikan owner folder `data/` menjadi `www` lewat File Manager aaPanel (klik kanan folder `data` -> **Permission** -> Ubah Owner ke **`www`** dengan permission **`755`** / **`775`** dan centang **Apply to subdir**).
*   **Perintah Terminal SSH**:
    ```bash
    chown -R www:www /www/wwwroot/vc.test/data
    chmod -R 755 /www/wwwroot/vc.test/data
    ```

### 3. Jangan Menimpa `config.php` dengan `config.php.example`
*   **Penyebab**: Pengguna lama Mikhmon terbiasa mengubah nama berkas `.example` ke `.php`. Pada MikhTrans v1.0+, berkas `include/config.php` sudah disertakan langsung di repositori untuk menjembatani sesi router ke database.
*   **Solusi**: Biarkan `include/config.php` bawaan apa adanya. Anda hanya perlu menyalin `.env.example` ke `.env` di root folder dan menyesuaikan API Key Midtrans Anda di sana.

---

## 📝 Changelog & Riwayat Perubahan

Riwayat pembaruan sistem dan log perbaikan versi lengkap dapat Anda akses secara detail di berkas **[changelog.md](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/changelog.md)**.

