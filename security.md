# Security Policy & Best Practices

Dokumen ini menjelaskan kebijakan keamanan, fitur pengamanan yang diimplementasikan pada **MikhTrans v1.1**, serta rekomendasi konfigurasi untuk menjaga keamanan sistem Anda.

## Kebijakan Keamanan (Security Policy)

Jika Anda menemukan celah keamanan (vulnerability) pada aplikasi ini, mohon **jangan melaporkannya secara publik** melalui GitHub Issue. Silakan hubungi tim pengelola repositori secara privat untuk mencegah eksploitasi oleh pihak yang tidak bertanggung jawab.

---

## Fitur Keamanan yang Diimplementasikan

Aplikasi ini telah melalui proses *security hardening* untuk memitigasi celah keamanan yang umum terjadi pada aplikasi web:

### 1. Isolasi Kredensial Sensitif
*   **Sistem `.env`**: Semua kunci API sensitif (seperti `MIDTRANS_SERVER_KEY`, `WS_APP_SECRET`, dll.) disimpan di berkas `.env` yang berada di luar jangkauan parser publik.
*   **Git Protection**: Berkas `.env` secara otomatis diabaikan oleh Git melalui `.gitignore` untuk mencegah kebocoran kredensial ke repositori publik (GitHub).

### 2. Perlindungan Akses Berkas Log & Data Transaksi
*   **`.htaccess` Folder Lock**: Direktori `/logs` dan `/voucher` (tempat menyimpan berkas JSON transaksi pending) diproteksi menggunakan berkas `.htaccess` dengan aturan `Deny from all`. Hal ini mencegah pihak luar mengunduh file log atau data transaksi melalui HTTP request langsung.
*   **Auto Garbage Collector**: Sistem secara acak menghapus berkas JSON transaksi yang berumur lebih dari 2 hari dengan probabilitas 5% saat halaman dimuat untuk meminimalkan penumpukan data.

### 3. Validasi Keamanan Transaksi (Midtrans Signature)
*   **Signature Verification (HMAC SHA512)**: Setiap notifikasi webhook yang masuk dari Midtrans divalidasi keasliannya menggunakan algoritma enkripsi SHA512 yang menggabungkan `order_id`, `status_code`, `gross_amount`, dan `MIDTRANS_SERVER_KEY`. Notifikasi palsu yang dikirim oleh penyerang akan otomatis ditolak (HTTP 401 Unauthorized).

### 4. Mitigasi Serangan & Tampering
*   **Checkout Rate Limiting**: Penambahan batasan jeda klik checkout (cooldown selama 10 detik) berbasis session PHP untuk mencegah spam pembuatan token transaksi ke Midtrans.
*   **Parameter Whitelisting**: Parameter query `?session=` divalidasi secara ketat. Sistem hanya akan memproses nama sesi router MikroTik yang terdaftar di berkas `include/config.php` untuk mencegah serangan *Local File Inclusion* (LFI) atau manipulasi parameter.
*   **HTTP Security Headers**: Halaman depan dilengkapi dengan header keamanan standar industri untuk mencegah serangan XSS, Clickjacking, dan MIME sniffing:
    ```php
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    ```

---

## Rekomendasi Keamanan untuk Produksi (Deployment)

Untuk memastikan sistem Anda benar-benar aman saat digunakan oleh publik, terapkan langkah-langkah berikut:

### 1. Wajibkan Penggunaan HTTPS (SSL)
Selalu gunakan sertifikat SSL/TLS pada server hosting Anda. HTTPS memastikan data sensitif seperti kode voucher dan data pelanggan tidak dapat disadap di jaringan (Man-in-the-Middle attack).

### 2. Batasi Akses API MikroTik
*   Jangan membuka port API MikroTik (`8728` atau `8729` untuk SSL) secara bebas ke seluruh internet.
*   Pada pengaturan router MikroTik Anda (`IP -> Services`), batasi opsi **Available From** pada port API hanya untuk alamat IP publik milik Web Server hosting MikhTrans Anda.

### 3. Gunakan Password Admin yang Kuat
Ganti username default `mikhmon` dan password default `1234` di menu **Admin Settings** MikhTrans dengan kombinasi karakter yang rumit (panjang minimal 12 karakter, terdiri dari huruf besar, huruf kecil, angka, dan simbol).
