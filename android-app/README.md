<p align="center">
<img width="300" height="600" alt="image" src="https://github.com/user-attachments/assets/c231ce2d-e0c2-4197-a0d8-6a63db087fda" />
</p>

# MikhPay-Forwarder (Aplikasi Android)

Aplikasi Android native ringan yang dirancang untuk mendengarkan notifikasi pembayaran e-wallet/bank secara otomatis di HP server operator, lalu meneruskannya secara real-time via Webhook JSON ke sistem verifikasi MikhPay Anda (`qris_verify.php`).

Aplikasi ini menggantikan kebutuhan alat otomatisasi pihak ketiga seperti MacroDroid, memberikan setup yang jauh lebih sederhana, bebas crash latar belakang, dan konsumsi baterai yang sangat minim.

---

## Fitur Utama

- **Penyadapan Notifikasi Latar Belakang**: Menggunakan layanan bawaan Android `NotificationListenerService` yang berjalan stabil 24/7 di background.
- **Mulai Otomatis (Auto-Start on Boot)**: Dilengkapi `BootReceiver` agar aplikasi langsung aktif otomatis ketika HP dinyalakan ulang (restart).
- **Pengabaian Optimasi Baterai**: Akses satu-klik langsung dari aplikasi untuk menonaktifkan pembatasan daya baterai sistem Android, menjaga agar sistem operasi HP tidak membunuh aplikasi di latar belakang.
- **Regex Parsing Dinamis**: Membaca teks nominal uang secara cerdas dari isi notifikasi (contoh: mengekstrak `10045` dari `Rp 10.045` atau `IDR 10,045`). Dilengkapi **Kustomisasi Regex** langsung pada form pengaturan aplikasi.
- **Daftar Putih Aplikasi (Whitelist)**: Anda dapat menentukan aplikasi perbankan atau e-wallet mana saja yang ingin disadap notifikasinya (GoBiz, Dana, OVO, ShopeePartner, BCA Mobile, dll.).
- **Panel Log Riwayat Lokal**: Menampilkan daftar riwayat 20 pengiriman notifikasi terakhir secara visual di halaman utama lengkap dengan cap waktu, nominal, status sukses/gagal, serta respon balik dari server.
- **Simulator Notifikasi Terintegrasi**: Tombol simulasi untuk mengirimkan notifikasi tiruan (GoPay Rp 25.045) ke HP Anda sendiri untuk menguji Regex, Listener, dan koneksi Webhook secara langsung tanpa uang sungguhan.
- **Dukungan HTTP (Cleartext)**: Mendukung pengiriman data ke alamat HTTP lokal non-SSL (`usesCleartextTraffic=true`) untuk server hotspot lokal (misalnya `http://192.168.88.1`).

---

## Nama Paket Aplikasi (Package Name Whitelist Bawaan)

Berikut adalah nama paket aplikasi populer untuk mempermudah konfigurasi whitelist:
- **GoPay Merchant / GoBiz**: `com.gojek.gopaymerchant` atau `com.sg.gobiz`
- **Dana**: `id.dana`
- **OVO**: `ovo.id`
- **Shopee Partner**: `com.shopee.partner`
- **BCA Mobile**: `id.co.bca.mobile`
- **Winpay Merchant**: `mobi.winpay.merchant`
- **MikhPay Simulator**: `com.mikhpay.forwarder` *(Wajib dimasukkan ke whitelist jika ingin menguji tombol simulasi!)*

---

## Cara Konfigurasi & Pengujian

1. **Instal APK** pada HP Android yang digunakan untuk menerima SMS/notifikasi mutasi saldo masuk.
2. **Berikan Izin Akses**:
   - **Izin Notifikasi (Android 13+)**: Izinkan aplikasi mengirim notifikasi saat pertama kali dibuka agar tombol simulasi bisa memunculkan alert.
   - **Akses Penyadap Notifikasi**: Ketuk tombol **"Grant Access Permission"**. Temukan **MikhPay Forwarder** di daftar pengaturan sistem, lalu aktifkan tombol izinnya.
   - **Abaikan Optimasi Baterai**: Ketuk tombol merah **"Disable Battery Optimization"** dan pilih **Izinkan (Allow)** pada dialog sistem.
3. **Isi Konfigurasi Utama**:
   - **Webhook URL**: Masukkan endpoint verifikasi MikhPay Anda (contoh: `https://wifiku.id/qris_verify.php`).
   - **API Key (Token)**: Masukkan token rahasia MikhPay Anda.
   - **Custom Parsing Regex (Opsional)**: Isi jika ingin menggunakan pola pencarian angka nominal kustom. Kosongkan untuk menggunakan pola bawaan sistem.
   - **Target Whitelist**: Masukkan daftar nama paket aplikasi target yang dipisahkan dengan koma (contoh: `com.gojek.gopaymerchant, com.mikhpay.forwarder`).
4. **Simpan Pengaturan**: Ketuk tombol **"Save Settings"**.
5. **Uji Hubungan Server**: Ketuk **"Test Webhook"** untuk mengirimkan data pengujian.
6. **Jalankan Simulasi**: Ketuk **"Simulasi Notifikasi"** di bagian bawah. Notifikasi tiruan GoPay akan muncul di layar atas Anda, disusul HP bergetar singkat, dan baris log sukses akan langsung terisi pada riwayat log di bawah.

---

## Cara Kompilasi (Build) dari Kode Sumber

Anda dapat meng-compile ulang proyek ini menggunakan **Android Studio** atau melalui Terminal:

### Menggunakan Android Studio
1. Buka Android Studio.
2. Pilih **File > Open** lalu arahkan ke direktori folder `android-app/`.
3. Tunggu hingga proses sinkronisasi Gradle selesai (`BUILD SUCCESSFUL`).
4. Buka menu **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
5. Berkas APK hasil kompilasi akan tersimpan di: `app/build/outputs/apk/release/app-release.apk` (atau di folder `release` yang Anda tentukan).

### Menggunakan Command Line (Terminal Windows PowerShell)
Buka terminal di dalam direktori folder `android-app/` lalu jalankan perintah:
```powershell
.\gradlew.bat assembleRelease
```
Hasil berkas APK siap pasang akan tersimpan di dalam folder `/app/release/` atau `/app/build/outputs/apk/release/`.

---

## 📥 Panduan Instalasi APK di HP Android

Karena aplikasi ini dikompilasi secara mandiri (*self-signed*) dan di-host di luar Google Play Store, silakan ikuti panduan berikut agar proses instalasi berjalan lancar:

1. **Kirim Berkas APK ke HP**: Pindahkan berkas `app-release.apk` hasil kompilasi dari komputer ke HP Android Anda (bisa melalui kabel data USB, Google Drive, atau dikirim lewat WhatsApp).
2. **Izinkan Instalasi dari Sumber Tidak Dikenal**:
   * Buka berkas APK tersebut di HP. Jika muncul peringatan keamanan, klik **Settings / Pengaturan**.
   * Aktifkan opsi **"Allow from this source"** (Izinkan dari sumber ini) untuk aplikasi pengelola berkas (File Manager) atau WhatsApp yang Anda gunakan untuk membuka berkas.
3. **Peringatan Google Play Protect (Penting)**:
   * Google Play Protect akan memunculkan dialog peringatan karena aplikasi ini belum didaftarkan secara berbayar di Google Play Store (berwarna merah/kuning).
   * **PENTING**: Klik tulisan **"Install Anyway"** (Tetap Instal). Jangan mengklik tombol *OK* karena itu akan membatalkan proses instalasi aplikasi.
4. **Buka Aplikasi**: Lakukan izin Notifikasi dan Akses Penyadap Notifikasi sesuai langkah konfigurasi di atas.

---

## 🛠️ Masalah & Solusi (Troubleshooting / Q&A)

#### Q: Notifikasi tiruan dari tombol "Simulasi Notifikasi" tidak muncul di layar atas HP saya.
* **Solusi 1 (Izin Notifikasi Diblokir)**: Masuk ke **Pengaturan HP > Aplikasi > Kelola Aplikasi > MikhPay Forwarder**. Ketuk menu **Notifications** (Notifikasi), lalu pastikan statusnya aktif (**Allowed/Izinkan**). Pada Android 13+, Anda wajib mengklik "Allow" pada popup izin saat aplikasi pertama kali dijalankan.
* **Solusi 2 (Sistem Operasi HP Menahan)**: Di beberapa perangkat dengan antarmuka kustom (seperti MIUI/HyperOS Xiaomi, Oppo, Vivo), pastikan Anda memberikan izin akses layar kunci dan notifikasi mengambang (*floating notifications*) untuk aplikasi MikhPay di menu perizinan aplikasi.

#### Q: Notifikasi simulasi muncul, tapi log di bawah tetap kosong ("Belum ada riwayat").
* **Solusi 1 (Belum Klik Save)**: Pastikan Anda sudah mengklik tombol hijau **Save Settings** terlebih dahulu sebelum menekan tombol simulasi.
* **Solusi 2 (Whitelist Tidak Sesuai)**: Pastikan nama paket **`com.mikhpay.forwarder`** sudah terdaftar pada kolom *Target Apps Whitelist*. Jika tidak terdaftar, layanan listener akan mengabaikan notifikasi simulasi tersebut karena dianggap bukan berasal dari aplikasi yang disetujui.

#### Q: Mengapa setelah HP didiamkan beberapa jam, aplikasi berhenti meneruskan notifikasi otomatis?
* **Solusi**: Sistem Android memiliki fitur manajemen daya baterai yang agresif. Anda harus menonaktifkan optimasi baterai untuk aplikasi MikhPay:
  1. Ketuk tombol merah **"Disable Battery Optimization"** di aplikasi MikhPay dan pilih **Allow**.
  2. Buka **App Info MikhPay > Penghemat Baterai (Battery Saver)**, ganti setelannya dari *Pintar/Smart* menjadi **"Tidak Dibatasi" (No Restrictions / Unrestricted)**.

#### Q: Log Riwayat menunjukkan status "FAILED: Gagal koneksi ke server".
* **Solusi 1 (Jaringan Berbeda)**: Jika Webhook URL Anda menggunakan IP lokal (misalnya `http://192.168.1.100/qris_verify.php`), pastikan HP Android Anda terhubung ke jaringan Wi-Fi yang sama dengan komputer/server tempat database MikhPay berada. HP tidak akan bisa mengakses server lokal jika menggunakan kuota data seluler.
* **Solusi 2 (Salah Ketik URL)**: Periksa kembali penulisan URL Webhook. Pastikan tidak ada spasi di ujung teks, dan gunakan awalan protokol yang benar (`http://` atau `https://`).

#### Q: Angka nominal transaksi tidak terbaca dari notifikasi pembayaran e-wallet.
* **Solusi**: Struktur pesan notifikasi aplikasi e-wallet mungkin telah diperbarui. Silakan gunakan kolom **Custom Parsing Regex (Opsional)** untuk menulis pola pencari nominal Anda sendiri. Sistem akan mencari kecocokan grup tangkapan pertama `(...)` dalam pola Regex Anda sebagai nominal bersih yang dikirim ke server.

##### 💡 Contoh Pola Custom Regex & Hasil Ekstraksinya:

1. **Pola Standar (Rp / IDR Bawaan)**:
   * **Pola Regex**: `(?:Rp\.?|IDR)\s*([0-9]{1,3}(?:\.[0-9]{3})+|[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)`
   * **Contoh Teks**: *"Uang masuk Rp 10.043 sukses"* atau *"Credit IDR 10,000"*
   * **Hasil Ekstraksi**: `10043` atau `10000` *(Pembersih tanda pemisah titik/koma bekerja secara otomatis)*.

2. **Mencari Nominal Setelah Kata Spesifik (Contoh: "sebesar [Nominal]")**:
   * **Pola Regex**: `sebesar\s+([0-9.,]+)`
   * **Contoh Teks**: *"Anda menerima transfer sebesar 25.050 dari Pelanggan"*
   * **Hasil Ekstraksi**: `25050`.

3. **Mencari Nominal Setelah Simbol/Kata Tertentu (Contoh: "Credit: [Nominal]")**:
   * **Pola Regex**: `Credit:\s*([0-9.,]+)`
   * **Contoh Teks**: *"Mutasi saldo masuk - Credit: 15,000"*
   * **Hasil Ekstraksi**: `15000`.

4. **Regex Sederhana Mengambil Angka Pemisah Ribuan (4-8 Digit)**:
   * **Pola Regex**: `\b([0-9]{1,3}(?:\.[0-9]{3})+|[0-9]{1,3}(?:,[0-9]{3})+)\b`
   * **Contoh Teks**: *"Terima transfer 5.025 dari GoPay"*
   * **Hasil Ekstraksi**: `5025`.


