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
- **Dana**: `com.dana`
- **OVO**: `com.ovo.id`
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
