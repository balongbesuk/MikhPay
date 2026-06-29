# MikhPay-Forwarder (Android App)

A lightweight, open-source Android application designed to listen for incoming payment notifications on your server phone and automatically forward them via webhook to your MikhPay system (`qris_verify.php`).

This replaces the need for third-party automation tools like MacroDroid, providing a simplified setup, zero background crashes, and minimum battery impact.

---

## Features

- **Automated Listening**: Uses Android's native `NotificationListenerService` (keeps running 24/7 in the background).
- **Intelligent Regex Parser**: Automatically extracts numerical amounts from raw notification text (e.g., parses `10045` from `Rp 10.045` or `Rp. 10.045`).
- **Flexible Package Whitelist**: Configure exactly which apps (GoBiz, Dana, OVO, ShopeePartner, BCA Mobile, etc.) the app should capture notifications from.
- **Connection Tester**: Check integration with your MikhPay server with a single click.
- **Cleartext Support**: Supports standard HTTP (`usesCleartextTraffic=true`) for local hotspot servers running without SSL (e.g., `http://192.168.88.1`).

---

## Whitelisted Packages (Common Defaults)

Here are the package names for popular payment and banking apps in Indonesia:
- **GoBiz (GoPay Merchant)**: `com.sg.gobiz`
- **Dana**: `com.dana`
- **OVO**: `com.ovo.id`
- **Shopee Partner**: `com.shopee.partner`
- **BCA Mobile**: `id.co.bca.mobile`
- **Winpay Merchant**: `mobi.winpay.merchant`

---

## How to Configure

1. **Install the APK** on the Android phone that receives the payment notification SMS/push alerts.
2. **Grant Notification Access**: Tap the **"Grant Access Permission"** button. Locate **MikhPay Forwarder** in the system list and toggle it **ON**.
3. **Configure Settings**:
   - **Webhook URL**: Enter your MikhPay verification endpoint (e.g., `https://yourwifi.id/qris_verify.php`).
   - **API Key**: Enter the secret key/token defined in your MikhPay configuration files.
   - **Target Whitelist**: Comma-separated package list of apps to listen to.
4. **Save Settings**: Tap **"Save Settings"**.
5. **Test Webhook**: Tap **"Test Webhook"** to send a dummy payload. If everything is correct, you will receive a success popup!

---

## How to Build/Compile from Source

You can build this project using **Android Studio** or from the command line using **Gradle**:

### Using Android Studio
1. Open Android Studio.
2. Select **File > Open** and choose the `android-app/` directory.
3. Wait for Gradle sync to complete.
4. Go to **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
5. The compiled APK will be saved under: `app/build/outputs/apk/release/app-release.apk` (or `app-debug.apk`).

### Using Command Line (Gradle Wrapper)
Navigate to the `android-app` directory in your terminal and run:

```bash
# On Windows
gradlew.bat assembleRelease

# On Linux/macOS
./gradlew assembleRelease
```
The resulting APK will be placed in the `app/build/outputs/apk/release/` directory.
