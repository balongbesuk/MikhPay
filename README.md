<p align="center">
  <img src="img/logo.png" alt="MikhPay Logo" width="300" style="border-radius: 24px;" />
</p>

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
   - **Rate Limiting Webhook**: Membatasi laju request verifikasi webhook hingga maksimal 60 request per menit per alamat IP untuk menangkal serangan Denial of Service (DoS).
   - **Pembersihan Cache Logout**: Menghapus total `localStorage` & `sessionStorage` peramban saat administrator logout.

---

## 🛠️ Panduan Konfigurasi & Instalasi

> [!IMPORTANT]
> **PENTING UNTUK INSTALASI DI VPS/LINUX (aaPanel, cPanel, dll.)**:
> Setelah Anda melakukan `git clone` atau mengunggah berkas ke VPS, folder-folder berikut wajib memiliki pemilik (**Owner**) **`www`** (atau user web server Anda) dan izin menulis (**Write Permission / 755 / 775**):
> - **`data/`** (Penyimpanan database JSON & konfigurasi)
> - **`voucher/`** (Penyimpanan data transaksi pembelian voucher pelanggan)
> - **`logs/`** (Penyimpanan catatan error log sistem)
> - **`include/`** (Penyimpanan file konfigurasi dinamis)
>
> Jika folder di atas masih dimiliki oleh **`root`** (biasanya karena proses upload/clone via root), Anda akan mengalami masalah: **gagal menyimpan tambah router**, **mengubah password loading berputar terus-menerus**, serta **status beli voucher error saat bayar QRIS** karena file transaksi gagal terbuat.
>
> **Solusi Cepat via Terminal SSH (sebagai root):**
> ```bash
> chown -R www:www data voucher logs include
> chmod -R 755 data voucher logs include
> ```
> *(Ubah `www:www` sesuai user web server di panel Anda jika berbeda, misalnya `www-data`)*.

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

## 💳 Sistem Verifikasi Pembayaran QRIS (2 Opsi Fleksibel & Hybrid)

MikhPay v3.1 memperkenalkan arsitektur verifikasi pembayaran ganda yang fleksibel. Anda bebas memilih metode verifikasi yang paling sesuai dengan kebutuhan infrastruktur hotspot Anda:

| Fitur / Perbandingan | Opsi 1: Notif Forwarder (Android) | Opsi 2: GoPay Merchant Direct API |
| :--- | :--- | :--- |
| **Kebutuhan Perangkat** | Membutuhkan HP Android menyala | **100% Tanpa HP** (Cukup Web Server) |
| **Kecepatan Respon** | Instan (< 2 detik via Push Notif) | 3 - 5 detik (On-demand polling) |
| **Ketergantungan Baterai** | Tergantung baterai & sinyal HP | Bebas dari masalah baterai HP tertidur |
| **Metode Kerja** | Event-driven webhook (`qris_verify.php`) | Polling mutasi jurnal GoBiz resmi |
| **Biaya Gateway** | **0% (Gratis)** | **0% (Gratis)** |

> [!TIP]
> **Mode Hybrid (Direkomendasikan)**: Anda dapat mengaktifkan **kedua opsi sekaligus**. Aplikasi HP Forwarder bertindak sebagai jalur kilat instan, sementara API GoPay bertindak sebagai *safety net* otomatis jika notifikasi di HP Anda tertunda. Proteksi kunci transaksi memastikan voucher tidak akan pernah terbit ganda.

---

### 🔑 Panduan Memasang Token GoPay Merchant (Opsi 2: Tanpa HP)

Untuk mengaktifkan pembacaan mutasi otomatis langsung dari server tanpa membutuhkan HP Android:

#### Langkah 1: Ambil Token Sesi dari Portal GoBiz Web
1. Buka peramban komputer (Google Chrome / Edge / Firefox) dan kunjungi portal resmi:  
   👉 **https://portal.gofoodmerchant.co.id/** atau **https://app.gobiz.co.id/**
2. Masuk (Login) menggunakan akun GoPay Merchant / GoBiz Anda.
3. Buka menu **Developer Tools**:
   - Tekan tombol **`F12`** di keyboard, atau klik kanan di mana saja lalu pilih **Inspect (Periksa)**.
4. Salin Token Sesi:
   - Masuk ke tab **Application** (pada Google Chrome) atau tab **Storage** (pada Firefox).
   - Di panel sebelah kiri, buka menu **Cookies** &gt; pilih `https://portal.gofoodmerchant.co.id`.
   - Cari baris cookie bernama **`access_token`**. Klik dua kali nilainya, lalu salin (**Copy**) teks token tersebut (diawali dengan `eyJhbGciOi...`).
   *(Alternatif: Anda juga bisa menyalin seluruh nilai header `Cookie` dari salah satu request di tab **Network**)*.

#### Langkah 2: Masukkan Token ke MikhPay
1. Buka Web Admin Panel MikhPay Anda di browser: `http://localhost/admin.php`.
2. Buka menu dropdown **MikhPay Billing** &gt; pilih **GoPay Merchant**.
3. Gulir ke bagian **Metode 2: Input Bearer Token Manual**.
4. Tempelkan (*paste*) token atau string cookie yang telah Anda salin tadi ke kolom **Bearer Token / Cookie Sesi**.
5. Klik tombol **Simpan & Uji Token**.

Sistem akan otomatis menghubungi server GoBiz, memvalidasi sesi, mendeteksi nama toko Anda (contoh: *Toko Anda - ID: G123456789*), dan mengaktifkan fitur auto-sync.

#### Langkah 3: Otomatisasi 24 Jam di Server (Background Cron)

**Apa fungsinya?**
- **Di Halaman Pembeli (Otomatis & Real-Time)**: Saat pembeli sedang membuka halaman QRIS di HP-nya, sistem otomatis memeriksa mutasi GoPay setiap interval ~5 detik (*Smart On-Demand Sync*). Begitu dana masuk, voucher langsung terbit di layar seketika tanpa perlu cron!
- **Background Cron (Jaring Pengaman Jika Pembeli Menutup Browser)**: Jika pembeli membayar lalu langsung menutup browser/HP-nya mati, tidak ada browser yang memicu sync. Di sinilah background cron bertugas mengecek mutasi GoPay di belakang layar setiap menit agar voucher tetap terbuat di MikroTik.

**Cara Memasang di Hosting / VPS:**

1. **Di cPanel Hosting**:
   - Masuk ke cPanel &gt; cari menu **Cron Jobs**.
   - Pilih interval **Once Per Minute (`* * * * *`)**.
   - Pada kolom **Command**, masukkan:
     ```bash
     php -q /home/USER_CPANEL_ANDA/public_html/process/gopay_sync.php
     ```
   - Atau bisa menggunakan trigger URL (Web Cron):
     ```bash
     curl -s "https://domainanda.com/process/gopay_sync.php?api_key=API_KEY_ANDA" >/dev/null 2>&1
     ```

2. **Di aaPanel / VPS Linux (Ubuntu / Debian)**:
   - Di aaPanel: Buka menu **Cron** &gt; Type of Task: **Shell Script** &gt; Execution cycle: **Every 1 Minute** &gt; Script Content:
     ```bash
     php /www/wwwroot/domainanda.com/process/gopay_sync.php >/dev/null 2>&1
     ```
   - Di Terminal SSH: Ketik `crontab -e`, lalu tambahkan baris berikut di paling bawah:
     ```bash
     * * * * * php /var/www/html/process/gopay_sync.php >/dev/null 2>&1
     ```

3. **Di Komputer Windows Lokal (Mikhmon Server bawaan)**:
   - Buka **Task Scheduler** &gt; Create Basic Task &gt; Trigger: Daily/Repeating &gt; Action: Start a Program:
     - Program: `"D:\mikhmonv3ws\Mikhmon Server\php\m-php.exe"`
     - Argument: `"D:\mikhmonv3ws\Mikhmon Server\mikhmon\process\gopay_sync.php"`

> [!NOTE]
> **Privasi & Keamanan Git**: Token sesi, kredensial router, nomor HP, dan log mutasi GoPay Anda disimpan di dalam berkas lokal `data/database.php` dan `data/gopay_processed.json` yang telah dimasukkan ke dalam `.gitignore`. Data sensitif Anda aman dan **TIDAK AKAN PERNAH** terunggah ke repositori Git publik.

---

## 📱 Aplikasi Android MikhPay-Forwarder (Alternatif Lebih Praktis)

Sebagai alternatif pengganti MacroDroid yang lebih mudah dikonfigurasi, andal, dan ramah baterai, Anda dapat menggunakan aplikasi Android bawaan **MikhPay-Forwarder** yang berada di dalam repositori ini pada folder [android-app/](android-app/).

### Fitur Aplikasi Android:
- **1-Click Login GoBiz & Auto-Sync Token**: Terintegrasi langsung dengan portal web resmi GoBiz via WebView di dalam aplikasi. Cukup login akun GoBiz di aplikasi, token sesi `access_token` otomatis tertangkap dan langsung tersinkronkan ke server MikhPay Anda tanpa perlu DevTools/F12 di PC!
- **Silent Auto-Refresh Token GoBiz (Anti-Expired 24 Jam)**: Aplikasi secara cerdas memperbarui token GoBiz di latar belakang (*headless WebView*) setiap 6–8 jam sekali dan langsung mengunggah token baru ke server MikhPay tanpa perlu menginput OTP atau login ulang setiap hari!
- **Konfigurasi Instan**: Hanya perlu mengisi URL Webhook (`https://yourdomain.com/qris_verify.php`) dan Token API Anda.
- **Sistem Latar Belakang Tangguh**: Menggunakan *NotificationListenerService* bawaan Android yang berjalan 24/7 di latar belakang dengan konsumsi daya sangat rendah.
- **Mulai Otomatis (Auto-Start on Boot)**: Otomatis aktif kembali di latar belakang saat HP dinyalakan ulang (restart) tanpa intervensi manual.
- **Abaikan Optimasi Baterai**: Satu ketukan tombol untuk menonaktifkan pembatasan daya baterai Android agar layanan tidak dibunuh oleh sistem HP secara sepihak.
- **Auto-Parsing & Regex Kustom**: Mengekstrak nominal transfer secara cerdas dari notifikasi (seperti GoPay Merchant, Dana, OVO, Shopee Partner, dll.). Dilengkapi kolom pengatur Regex kustom langsung di aplikasi untuk format teks khusus.
- **Panel Log Riwayat Lokal**: Menampilkan daftar 20 pengiriman notifikasi terakhir secara visual lengkap dengan status sukses/gagal dan respon server di halaman utama.
- **Simulator Notifikasi Terintegrasi**: Tombol uji simulasi terintegrasi untuk memicu notifikasi masuk tiruan langsung dari aplikasi guna memverifikasi parser Regex dan Webhook secara end-to-end tanpa uang sungguhan.
- **Respon Getar Sukses (Haptic Feedback)**: HP bergetar singkat setiap kali notifikasi pembayaran masuk berhasil terverifikasi dan diteruskan ke server MikhPay.

*Panduan detail kompilasi dan cara penggunaan dapat Anda lihat di [README.md Aplikasi Android](android-app/README.md).*

---

## 📡 Integrasi MacroDroid (Otomatisasi Verifikasi)

Agar kode voucher terbuat secara otomatis di MikroTik setelah pelanggan mentransfer saldo ke GoPay/OVO/Dana Anda:

### 1. Persiapan HP Android
- Gunakan HP Android yang login ke akun e-wallet/bank penerima dana QRIS Anda (yang akan memunculkan notifikasi mutasi saldo masuk).
- Unduh dan pasang aplikasi **MacroDroid - Device Automation** dari Google Play Store.
- Buka MacroDroid, berikan semua izin akses yang diminta, terutama **Akses Notifikasi** (*Notification Access*) di Pengaturan Sistem Android Anda.

### 2. Konfigurasi Penghemat Baterai & Latar Belakang (Wajib!)
Agar notifikasi dan MacroDroid tidak dimatikan oleh sistem Android saat HP dalam kondisi terkunci:
1. Masuk ke **Pengaturan HP > Aplikasi > GoPay Merchant / MacroDroid**.
2. Di bagian **Baterai/Battery**, setel ke **Tidak Dibatasi / Unrestricted**.
3. Di bagian Recent Apps HP Anda, kunci (*Lock*) aplikasi GoPay Merchant dan MacroDroid agar tidak terhapus saat membersihkan RAM.

### 3. Buat Makro Baru di MacroDroid
- Klik **Tambah Makro** (*Add Macro*).
- **Pemicu (Triggers - Merah):**
  - Klik **+** > pilih **Event Perangkat > Notifikasi > Notifikasi Diterima**.
  - Pilih **Pilih Aplikasi** > centang **GoPay Merchant** (atau aplikasi e-wallet Anda lainnya).
  - Di kolom **Teks Berisi**, pilih **Sembarang** (*Any*).
- **Tindakan (Actions - Biru):**
  - Klik **+** > pilih **Aplikasi > HTTP Request**.
  - Pilih metode **GET**.
  - Masukkan URL Webhook Anda. Ganti `domain-anda.com` dengan alamat web MikhPay Anda, gunakan token rahasia dari `.env.php`, dan gunakan variabel `{notification}` di ujungnya (klik tombol titik tiga `[...]` -> Notification -> Notification Text untuk memasukkannya secara otomatis):
    ```text
    https://[domain-anda.com]/qris_verify.php?token=[token_rahasia_anda]&nominal={notification}
    ```
    *(Gunakan `https://` jika web server Anda memaksa sambungan SSL agar request tidak diblokir/dialihkan).*
  - Klik **Simpan** (ikon disket di pojok kanan bawah).

Selesai! Server MikhPay Anda secara otomatis akan membaca kalimat notifikasi tersebut, menyaring angkanya, dan langsung memproses pelunasan voucher dalam hitungan detik.

---

## 🔌 Integrasi Halaman Login MikroTik (`login.html`)

Agar pelanggan dapat membeli voucher secara langsung saat terhubung ke Hotspot, Anda perlu menghubungkan halaman login bawaan MikroTik (`login.html`) dengan portal MikhPay.

### 1. Tambahkan Tombol Beli Voucher di `login.html`
Edit berkas `login.html` di dalam folder `hotspot` MikroTik Anda (dapat diunduh via FTP atau menu Files di Winbox). Tambahkan kode tombol berikut:
```html
<a href="http://[ip-atau-domain-mikhpay]/" style="display:block; background:#4f46e5; color:white; padding:12px; text-align:center; border-radius:8px; text-decoration:none; font-weight:bold; margin-top:10px;">
    Beli Voucher Otomatis (QRIS / E-Wallet)
</a>
```
*Ganti `[ip-atau-domain-mikhpay]` dengan alamat IP atau Domain tempat Anda meng-host MikhPay.*

### 2. Konfigurasi Walled Garden MikroTik
Karena pelanggan yang belum login diblokir akses internetnya oleh MikroTik, Anda wajib mendaftarkan alamat IP server MikhPay ke **Walled Garden** agar dapat diakses secara gratis sebelum login.

Jalankan perintah berikut di **New Terminal** Winbox MikroTik Anda:
```routeros
# Izinkan akses ke Web Server MikhPay
/ip hotspot walled-garden ip add dst-host=[ip-atau-domain-mikhpay] action=allow

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
*Ganti `[ip-atau-domain-mikhpay]` pada baris pertama dengan IP/Domain publik hosting portal MikhPay Anda.*

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

### 4. Transaksi QRIS Berhasil Masuk Tapi Halaman Web Tetap Bengong (Status Pending)
*   **Penyebab 1 (Izin Folder):** Web server tidak memiliki hak akses menulis (*write permission*) pada folder `/voucher` di server aaPanel/Hosting Anda, sehingga berkas transaksi `.json` tidak bisa tersimpan.
*   **Solusi 1:** Masuk ke File Manager hosting Anda, cari folder `voucher/` (buat jika belum ada), klik kanan -> **Permission** -> Ubah Owner ke **`www`** (atau `www-data`) dengan permission **`755`** atau **`775`** (centang *Apply to subdir*). Lakukan hal yang sama untuk folder `data/` dan `logs/`.
*   **Penyebab 2 (Variabel MacroDroid Salah):** Pada aksi HTTP Request di MacroDroid, Anda memasukkan variabel `{not_title}` (Judul Notifikasi). Ini salah karena judul notifikasi adalah "GoPay Merchant", bukan teks isi pesan yang mengandung nominal transfer.
*   **Solusi 2:** Ubah parameter `&nominal={not_title}` pada URL MacroDroid menjadi **`&nominal={notification}`** (atau `[notification]`) agar yang dikirimkan adalah isi teks notifikasi lengkap.
*   **Penyebab 3 (OPcache Caching):** Pada aaPanel, konfigurasi `.env.php` yang baru saja diperbarui tidak langsung dibaca oleh web server karena tersimpan di memori *OPcache PHP-FPM*.
*   **Solusi 3:** Lakukan *restart* layanan PHP (PHP-FPM) atau Nginx di aaPanel Anda untuk membersihkan *cache* memori.

### 5. Gagal Membuat Voucher Saat Verifikasi QRIS ("paid_pending_generate" / Router Offline)
*   **Penyebab 1 (Kunci Enkripsi Mismatch):** Anda baru saja mengubah atau menambahkan `ENCRYPTION_KEY` pada file `.env.php`. Karena kunci berubah, password MikroTik Anda yang tersimpan di database (dienkripsi dengan kunci lama) tidak dapat didekripsi dengan benar (menjadi karakter acak/sampah) sehingga koneksi ditolak.
*   **Solusi 1:** Masuk ke Admin Panel MikhPay Anda -> **Session Settings** -> klik **Edit** pada router Anda -> Ketik ulang password asli MikroTik Anda (misal `password_mikrotik_anda`) -> Klik **Save**. Ini akan mengenkripsi ulang password menggunakan kunci baru Anda di `.env.php`.
*   **Penyebab 2 (API Port MikroTik Mati):** Fitur API pada MikroTik belum diaktifkan.
*   **Solusi 2:** Buka Winbox -> masuk ke **IP > Services** -> pastikan status **`api`** (port `8728`) dalam keadaan aktif (Enabled).

---

## 📝 Changelog & Riwayat Perubahan

Riwayat pembaruan sistem dan log perbaikan versi lengkap dapat Anda akses secara detail di berkas [changelog.md](changelog.md).

