# MikhPay - Modernized with REST API & QRIS Mandiri

MikhPay (Mikrotik Hotspot Monitor & Transaction System) adalah aplikasi web berbasis PHP untuk mengelola dan memantau Hotspot MikroTik, khususnya untuk pembuatan voucher otomatis. Versi ini telah dimodernisasi dengan penambahan **REST API** untuk integrasi eksternal, **Portal Pembelian Voucher Mandiri** terintegrasi dengan **Sistem QRIS Dinamis Mandiri** (tanpa potongan payment gateway pihak ketiga), serta sistem **Pengerasan Keamanan (Security Hardening)** kelas enterprise.

Aplikasi ini dikembangkan dan dimodifikasi dari kode sumber asli [Mikhmon v3 oleh Laksamadi Guko](https://github.com/laksa19/mikhmonv3).

---

## 🚀 Fitur Utama & Modifikasi Baru

1. **Portal Pembelian Mandiri (`frontpage.php`)**
   - **Tampilan Modern**: Desain premium berorientasi *mobile-first* (Light Theme, Glassmorphism, dan Bottom Nav).
   - **Sistem QRIS Mandiri**: Membuat QR Code QRIS dinamis (berisi nominal unik tambahan) secara lokal (offline) langsung dari *string* QRIS Statis Anda tanpa pihak ketiga.
   - **Real-time Updates**: Status pembayaran dapat terpantau menggunakan HTTP Polling.

2. **REST API Endpoint (`api.php`)**
   - **Keamanan**: Otentikasi aman menggunakan parameter `api_key` atau header `X-API-Key`.
   - **Informasi Paket**: Endpoint cepat untuk melihat profil hotspot dan detail harga.
   - **Generate Voucher**: Pembuatan kode voucher secara otomatis dan programatis untuk diintegrasikan dengan aplikasi pihak ketiga.

3. **Verifikasi Pembayaran Otomatis (`qris_verify.php`)**
   - **Dukungan MacroDroid/Tasker**: Endpoint verifikasi ringan untuk disambungkan dengan aplikasi automasi HP (pembaca notifikasi E-Wallet/Mutasi Bank) menggunakan `QRIS_SECRET_TOKEN`.
   - **Auto-Cleanup**: Terdapat *cronjob* pembersih transaksi (`cron_qris.php`) untuk membatalkan tagihan kedaluwarsa setelah 15 menit agar kode unik terbebas.

4. **Penguatan Keamanan Sistem (Security Hardening - v1.7 & v1.8)**
   - **Brute-Force Protection**: Membatasi kegagalan percobaan login administrator maksimal **5 kali**. IP yang melanggar diblokir otomatis selama **10 menit** (`data/login_rate_limit.json`).
   - **Session Fixation Protection**: Meregenerasi ID sesi peramban secara otomatis (`session_regenerate_id(true)`) sesaat setelah login administrator sukses.
   - **Session Cookie Hardening**: Mengamankan parameter `session_start()` di semua entry point dengan parameter cookie ketat (`HttpOnly`, `SameSite=Lax`, `use_only_cookies`, dan `Secure` dinamis yang menyesuaikan protokol HTTPS/HTTP).
   - **Konfigurasi Aman `.env.php`**: Mengisolasi semua kunci API ke dalam berkas `.env.php` terproteksi eksekusi PHP (`exit;`), mencegah kebocoran informasi teks polos jika diakses publik di server non-Apache (Nginx, IIS, Caddy).
   - **Rate Limiting Webhook**: Membatasi laju request webhook Midtrans hingga maksimal 60 request per menit per alamat IP untuk menangkal serangan Denial of Service (DoS).
   - **Pembersihan Cache Logout**: Menghapus total `localStorage` & `sessionStorage` peramban saat administrator logout.

---

## 🛠️ Panduan Konfigurasi & Instalasi

### 1. Buat Berkas Lingkungan Terproteksi (`.env.php`)
Buat berkas baru bernama `.env.php` di root direktori MikhPay Anda (sejajar dengan `index.php`). Salin dan sesuaikan konfigurasi berikut:

```php
<?php header('HTTP/1.0 403 Forbidden'); exit; ?>
# ==============================================================================
# BERKAS KONFIGURASI LINGKUNGAN (.env.php)
# ==============================================================================

# API Key untuk mengamankan REST API (api.php / cron_retry.php)
MIKHMON_API_KEY="mikhmon_api_key_12345"

# Kredensial QRIS Mandiri (Ganti dengan string hasil scan QR statis Anda)
QRIS_MODE=true
QRIS_STATIC_STRING="00020101021126670016COM.GO-JEK.WWW0118...YOUR_QRIS_STRING..."
QRIS_SECRET_TOKEN="ganti_dengan_token_rahasia_anda_123"

# WebSocket (Pusher / Soketi) - Opsional
WS_APP_ID="YOUR_PUSHER_APP_ID"
WS_APP_KEY="YOUR_PUSHER_KEY"
WS_APP_SECRET="YOUR_PUSHER_SECRET"
WS_CLUSTER="ap1"

# Konfigurasi Tambahan Soketi (Hanya jika menggunakan server WebSocket mandiri)
# WS_HOST="your-soketi-host.com"
# WS_PORT="6001"
# WS_SCHEME="https"
```

### 2. Pengaturan Sesi & MikroTik
Kredensial MikroTik, nama sesi, IP, user, password, dan dnsname diatur secara otomatis melalui **Web Admin Panel** MikhPay pada menu **Admin Settings > Add Router / Edit Settings**.

> [!IMPORTANT]
> Berkas `include/config.php` bawaan repositori sudah dikonfigurasi secara dinamis untuk memuat sesi dari database (`data/database.php`). Anda **TIDAK PERLU** menyalin atau mengubah nama `config.php.example` menjadi `config.php`. Biarkan `include/config.php` bawaan apa adanya agar pengaturan sesi tetap tersimpan otomatis ke database.

### 3. URL Halaman Utama
*   **Web Admin Panel**: `http://localhost/admin.php`
*   **Portal Mandiri Pelanggan**: `http://localhost/` (Otomatis diarahkan ke `frontpage.php`)
*   **REST API**: `http://localhost/api.php`
*   **Notification Webhook**: `http://localhost/notification.php`

---

## 📡 Integrasi MacroDroid (Otomatisasi Verifikasi)

Agar kode voucher terbuat secara otomatis di MikroTik setelah pelanggan mentransfer saldo ke GoPay/OVO/Dana Anda:
1. **Siapkan HP Android Selalu Menyala**:
   Gunakan HP (yang login ke akun e-wallet/bank penerima dana QRIS Anda) dan pasang aplikasi **MacroDroid**.
2. **Buat Macro Pembaca Notifikasi**:
   Buat aturan jika muncul notifikasi uang masuk (misal dari GoPay: "Berhasil terima saldo Rp X..."), ambil (ekstrak) nominal uangnya ke dalam variabel.
3. **HTTP GET Request (Webhook)**:
   Buat aksi di MacroDroid untuk mengeksekusi URL berikut ke server MikhPay Anda:
   `http://[domain-anda.com]/qris_verify.php?token=ganti_dengan_token_rahasia_anda_123&nominal=[VARIABEL_NOMINAL]`
   Sistem MikhPay akan langsung mencocokkan nominal tersebut dengan *database* transaksi *pending* dan menerbitkan voucher dalam hitungan detik!

---

## 🔌 Integrasi Halaman Login MikroTik (`login.html`)

Agar pelanggan dapat membeli voucher secara langsung saat terhubung ke Hotspot, Anda perlu menghubungkan halaman login bawaan MikroTik (`login.html`) dengan portal MikhPay.

### 1. Tambahkan Tombol Beli Voucher di `login.html`
Edit berkas `login.html` di dalam folder `hotspot` MikroTik Anda (dapat diunduh via FTP atau menu Files di Winbox). Tambahkan kode tombol berikut:
```html
<a href="http://172.16.11.91/" style="display:block; background:#4f46e5; color:white; padding:12px; text-align:center; border-radius:8px; text-decoration:none; font-weight:bold; margin-top:10px;">
    Beli Voucher Otomatis (QRIS / E-Wallet)
</a>
```
*Ganti `http://172.16.11.91/` dengan alamat IP atau Domain tempat Anda meng-host MikhPay.*

### 2. Konfigurasi Walled Garden MikroTik
Karena pelanggan yang belum login diblokir akses internetnya oleh MikroTik, Anda wajib mendaftarkan alamat IP server MikhPay dan domain Midtrans/Pusher ke **Walled Garden** agar dapat diakses secara gratis sebelum login.

Jalankan perintah berikut di **New Terminal** Winbox MikroTik Anda:
```routeros
# Izinkan akses ke Web Server MikhPay
/ip hotspot walled-garden ip add dst-host=172.16.11.91 action=allow

# Izinkan sistem pembayaran e-wallet agar pelanggan bisa scan QR dari HP-nya
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
*Ganti `172.16.11.91` pada baris pertama dengan IP/Domain publik hosting portal MikhPay Anda.*

### 3. Login Otomatis (Auto-Login)
Setelah pembayaran lunas, portal MikhPay akan memunculkan tombol **"Hubungkan Sekarang"**. Tombol ini otomatis mengirimkan parameter login langsung ke MikroTik (`http://[dnsname]/login?username=[voucher]&password=[voucher]`) sehingga pengguna langsung terhubung ke internet tanpa perlu mengetik kode voucher secara manual.

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
    - `qty=1`

---

## 🛠️ Pemecahan Masalah (Troubleshooting)

### 1. Error: `Call to undefined function putenv()`
*   **Penyebab**: VPS (seperti aaPanel) menonaktifkan fungsi `putenv()` demi alasan keamanan melalui direktif `disable_functions` di file `php.ini`.
*   **Solusi**: MikhPay v1.0+ sudah dilengkapi helper `mikhmonEnv()` yang aman dan kompatibel tanpa bergantung pada `putenv()`. Jika Anda masih menemui kendala ini, pastikan Anda menggunakan berkas `include/env_config.php` terbaru.

### 2. Kolom Input Kosong Kembali setelah Klik "Save" / Ping Host Kosong
*   **Penyebab**: Web server (user `www` atau `www-data`) tidak memiliki hak akses menulis (*write permission*) pada folder `/data`. Hal ini terjadi jika Anda melakukan clone/pull git menggunakan user `root`.
*   **Solusi**: Ubah kepemilikan owner folder `data/` menjadi `www` lewat File Manager aaPanel (klik kanan folder `data` -> **Permission** -> Ubah Owner ke **`www`** dengan permission **`755`** / **`775`** dan centang **Apply to subdir**).

### 3. Jangan Menimpa `config.php` dengan `config.php.example`
*   **Penyebab**: Pengguna lama Mikhmon terbiasa mengubah nama berkas `.example` ke `.php`. Pada MikhPay v1.0+, berkas `include/config.php` sudah disertakan langsung di repositori untuk menjembatani sesi router ke database.
*   **Solusi**: Biarkan `include/config.php` bawaan apa adanya. Anda hanya perlu menyalin `.env.example` ke `.env.php` di root folder dan menyesuaikan API Key Anda di sana.

---

## 📝 Changelog & Riwayat Perubahan

Riwayat pembaruan sistem dan log perbaikan versi lengkap dapat Anda akses secara detail di berkas **[changelog.md](file:///d:/mikhmonv3ws/Mikhmon Server/mikhmon/changelog.md)**.
