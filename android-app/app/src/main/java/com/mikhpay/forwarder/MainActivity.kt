package com.mikhpay.forwarder

import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.provider.Settings
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException

class MainActivity : AppCompatActivity() {

    private lateinit var statusDot: View
    private lateinit var statusText: TextView
    private lateinit var btnGrantPermission: Button
    private lateinit var batteryStatusDot: View
    private lateinit var batteryStatusText: TextView
    private lateinit var btnIgnoreBattery: Button
    private lateinit var inputWebhookUrl: EditText
    private lateinit var inputApiKey: EditText
    private lateinit var inputCustomRegex: EditText
    private lateinit var inputPackageWhitelist: EditText
    private lateinit var btnSave: Button
    private lateinit var btnTest: Button
    private lateinit var logsContainer: android.widget.LinearLayout
    private lateinit var btnSimulateNotif: Button
    private lateinit var btnClearLogs: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        // Bind Views
        statusDot = findViewById(R.id.status_dot)
        statusText = findViewById(R.id.status_text)
        btnGrantPermission = findViewById(R.id.btn_grant_permission)
        inputWebhookUrl = findViewById(R.id.input_webhook_url)
        inputApiKey = findViewById(R.id.input_api_key)
        inputPackageWhitelist = findViewById(R.id.input_package_whitelist)
        btnSave = findViewById(R.id.btn_save)
        btnTest = findViewById(R.id.btn_test)
        batteryStatusDot = findViewById(R.id.battery_status_dot)
        batteryStatusText = findViewById(R.id.battery_status_text)
        btnIgnoreBattery = findViewById(R.id.btn_ignore_battery)
        inputCustomRegex = findViewById(R.id.input_custom_regex)
        logsContainer = findViewById(R.id.logs_container)
        btnSimulateNotif = findViewById(R.id.btn_simulate_notif)
        btnClearLogs = findViewById(R.id.btn_clear_logs)

        // Load Saved Configuration
        val sharedPref = getSharedPreferences("MikhPaySettings", Context.MODE_PRIVATE)
        val savedUrl = sharedPref.getString("webhook_url", "")
        val savedKey = sharedPref.getString("api_key", "")
        val savedRegex = sharedPref.getString("custom_regex", "")
        // Default whitelist with standard payment and banking apps for user convenience
        val defaultWhitelist = "com.gojek.gopaymerchant, com.sg.gobiz, com.dana, com.ovo.id, com.shopee.partner, id.co.bca.mobile, mobi.winpay.merchant, com.mikhpay.forwarder"
        val savedWhitelist = sharedPref.getString("package_whitelist", defaultWhitelist)

        inputWebhookUrl.setText(savedUrl)
        inputApiKey.setText(savedKey)
        inputCustomRegex.setText(savedRegex)
        inputPackageWhitelist.setText(savedWhitelist)

        // Save Settings Trigger
        btnSave.setOnClickListener {
            val url = inputWebhookUrl.text.toString().trim()
            val key = inputApiKey.text.toString().trim()
            val regex = inputCustomRegex.text.toString().trim()
            val whitelist = inputPackageWhitelist.text.toString().trim()

            if (url.isEmpty()) {
                inputWebhookUrl.error = "Webhook URL is required"
                return@setOnClickListener
            }

            with(sharedPref.edit()) {
                putString("webhook_url", url)
                putString("api_key", key)
                putString("custom_regex", regex)
                putString("package_whitelist", whitelist)
                apply()
            }
            Toast.makeText(this, "Settings saved successfully", Toast.LENGTH_SHORT).show()
        }

        // Grant Permission Trigger
        btnGrantPermission.setOnClickListener {
            startActivity(Intent("android.settings.ACTION_NOTIFICATION_LISTENER_SETTINGS"))
        }

        // Ignore Battery Optimization Trigger
        btnIgnoreBattery.setOnClickListener {
            try {
                val intent = Intent().apply {
                    action = Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS
                    data = android.net.Uri.parse("package:$packageName")
                }
                startActivity(intent)
            } catch (e: Exception) {
                try {
                    val intent = Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS)
                    startActivity(intent)
                } catch (ex: Exception) {
                    Toast.makeText(this, "Please disable battery optimization manually in system settings", Toast.LENGTH_LONG).show()
                }
            }
        }

        // Test Webhook Trigger
        btnTest.setOnClickListener {
            val url = inputWebhookUrl.text.toString().trim()
            val key = inputApiKey.text.toString().trim()
            if (url.isEmpty()) {
                inputWebhookUrl.error = "Webhook URL is required to test"
                return@setOnClickListener
            }
            testWebhookConnection(url, key)
        }

        // Simulate Notification Trigger
        btnSimulateNotif.setOnClickListener {
            simulateTestNotification()
        }

        // Clear Logs Trigger
        btnClearLogs.setOnClickListener {
            sharedPref.edit().putString("notification_logs", "[]").apply()
            populateLogs()
            Toast.makeText(this, "Riwayat log dihapus", Toast.LENGTH_SHORT).show()
        }
    }

    override fun onResume() {
        super.onResume()
        updatePermissionStatus()
        updateBatteryStatus()
        populateLogs()
    }

    private fun updatePermissionStatus() {
        if (isNotificationServiceEnabled()) {
            statusDot.setBackgroundResource(R.drawable.circle_green)
            statusText.text = "Notification Access Enabled"
            statusText.setTextColor(resources.getColor(R.color.primary_color, null))
            btnGrantPermission.visibility = View.GONE
        } else {
            statusDot.setBackgroundResource(R.drawable.circle_red)
            statusText.text = "Notification Access Disabled"
            statusText.setTextColor(resources.getColor(R.color.teal_700, null))
            btnGrantPermission.visibility = View.VISIBLE
        }
    }

    private fun updateBatteryStatus() {
        val pm = getSystemService(Context.POWER_SERVICE) as android.os.PowerManager
        if (pm.isIgnoringBatteryOptimizations(packageName)) {
            batteryStatusDot.setBackgroundResource(R.drawable.circle_green)
            batteryStatusText.text = "Battery Saver: Ignored (Stable)"
            batteryStatusText.setTextColor(resources.getColor(R.color.primary_color, null))
            btnIgnoreBattery.visibility = View.GONE
        } else {
            batteryStatusDot.setBackgroundResource(R.drawable.circle_red)
            batteryStatusText.text = "Battery Saver: Optimizing (Unstable)"
            batteryStatusText.setTextColor(resources.getColor(R.color.teal_700, null))
            btnIgnoreBattery.visibility = View.VISIBLE
        }
    }

    private fun isNotificationServiceEnabled(): Boolean {
        val pkgName = packageName
        val flat = Settings.Secure.getString(contentResolver, "enabled_notification_listeners")
        if (!flat.isNullOrEmpty()) {
            val names = flat.split(":")
            for (name in names) {
                val cn = ComponentName.unflattenFromString(name)
                if (cn != null && cn.packageName == pkgName) {
                    return true
                }
            }
        }
        return false
    }

    private fun testWebhookConnection(url: String, apiKey: String) {
        val client = OkHttpClient()
        val json = JSONObject()
        json.put("amount", "12345")
        json.put("sender", "com.mikhpay.forwarder")
        json.put("title", "MikhPay Webhook Test")
        json.put("body", "Uang masuk Rp 12.345 sukses terkirim dari forwarder.")
        json.put("timestamp", System.currentTimeMillis() / 1000)
        json.put("api_key", apiKey)

        val requestBody = json.toString().toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        val request = Request.Builder()
            .url(url)
            .post(requestBody)
            .build()

        Toast.makeText(this, "Testing connection...", Toast.LENGTH_SHORT).show()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                runOnUiThread {
                    Toast.makeText(this@MainActivity, "Connection Failed: ${e.message}", Toast.LENGTH_LONG).show()
                }
            }

            override fun onResponse(call: Call, response: Response) {
                val body = response.body?.string() ?: ""
                runOnUiThread {
                    if (response.isSuccessful) {
                        Toast.makeText(this@MainActivity, "Test Success! Server Response: $body", Toast.LENGTH_LONG).show()
                    } else {
                        Toast.makeText(this@MainActivity, "Server Error (${response.code}): $body", Toast.LENGTH_LONG).show()
                    }
                }
            }
        })
    }

    private fun simulateTestNotification() {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as android.app.NotificationManager
        val channelId = "mikhpay_test_channel"
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
            val channel = android.app.NotificationChannel(channelId, "MikhPay Simulator", android.app.NotificationManager.IMPORTANCE_DEFAULT)
            notificationManager.createNotificationChannel(channel)
        }
        
        val notification = androidx.core.app.NotificationCompat.Builder(this, channelId)
            .setContentTitle("GoPay Merchant")
            .setContentText("Uang masuk sebesar Rp 25.045 dari Pelanggan Sukses")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setAutoCancel(true)
            .build()
        
        notificationManager.notify(99, notification)
        Toast.makeText(this, "Simulasi notifikasi terkirim! Pastikan izin notifikasi aktif & whitelist mencakup paket ini.", Toast.LENGTH_LONG).show()
    }

    private fun populateLogs() {
        logsContainer.removeAllViews()
        val sharedPref = getSharedPreferences("MikhPaySettings", Context.MODE_PRIVATE)
        val logsStr = sharedPref.getString("notification_logs", "[]") ?: "[]"
        try {
            val jsonArray = org.json.JSONArray(logsStr)
            if (jsonArray.length() == 0) {
                val emptyTv = TextView(this).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(android.widget.LinearLayout.LayoutParams.MATCH_PARENT, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT)
                    text = "Belum ada riwayat notifikasi terkirim."
                    setTextColor(android.graphics.Color.parseColor("#666666"))
                    textSize = 14f
                    gravity = android.view.Gravity.CENTER
                    setPadding(0, 24, 0, 24)
                }
                logsContainer.addView(emptyTv)
                return
            }

            for (i in 0 until jsonArray.length()) {
                val log = jsonArray.getJSONObject(i)
                val status = log.optString("status", "")
                val sender = log.optString("sender", "").substringAfterLast(".")
                val amount = log.optString("amount", "")
                val message = log.optString("message", "")
                val timestamp = log.optLong("timestamp", 0)

                val timeStr = android.text.format.DateFormat.format("HH:mm:ss", timestamp).toString()

                val row = android.widget.LinearLayout(this).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(android.widget.LinearLayout.LayoutParams.MATCH_PARENT, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                        setMargins(0, 0, 0, 12)
                    }
                    orientation = android.widget.LinearLayout.VERTICAL
                    setPadding(16, 16, 16, 16)
                    setBackgroundResource(R.drawable.edittext_background)
                }

                val header = android.widget.LinearLayout(this).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(android.widget.LinearLayout.LayoutParams.MATCH_PARENT, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT)
                    orientation = android.widget.LinearLayout.HORIZONTAL
                }

                val senderTv = TextView(this).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(0, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
                    text = "${sender.uppercase()} - Rp $amount"
                    setTextColor(android.graphics.Color.WHITE)
                    textSize = 13f
                    setTypeface(null, android.graphics.Typeface.BOLD)
                }

                val statusTv = TextView(this).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(android.widget.LinearLayout.LayoutParams.WRAP_CONTENT, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT)
                    text = status
                    textSize = 11f
                    setTypeface(null, android.graphics.Typeface.BOLD)
                    setPadding(12, 4, 12, 4)
                    if (status == "SUCCESS") {
                        setTextColor(android.graphics.Color.parseColor("#10b981"))
                    } else {
                        setTextColor(android.graphics.Color.parseColor("#ef4444"))
                    }
                }

                header.addView(senderTv)
                header.addView(statusTv)

                val detail = TextView(this).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(android.widget.LinearLayout.LayoutParams.MATCH_PARENT, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                        setMargins(0, 6, 0, 0)
                    }
                    text = "[$timeStr] $message"
                    setTextColor(android.graphics.Color.parseColor("#888888"))
                    textSize = 12f
                }

                row.addView(header)
                row.addView(detail)
                logsContainer.addView(row)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
