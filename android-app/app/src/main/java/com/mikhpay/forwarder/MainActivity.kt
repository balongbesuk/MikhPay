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
    private lateinit var inputWebhookUrl: EditText
    private lateinit var inputApiKey: EditText
    private lateinit var inputPackageWhitelist: EditText
    private lateinit var btnSave: Button
    private lateinit var btnTest: Button

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

        // Load Saved Configuration
        val sharedPref = getSharedPreferences("MikhPaySettings", Context.MODE_PRIVATE)
        val savedUrl = sharedPref.getString("webhook_url", "")
        val savedKey = sharedPref.getString("api_key", "")
        // Default whitelist with standard payment and banking apps for user convenience
        val defaultWhitelist = "com.sg.gobiz, com.dana, com.ovo.id, com.shopee.partner, id.co.bca.mobile, mobi.winpay.merchant"
        val savedWhitelist = sharedPref.getString("package_whitelist", defaultWhitelist)

        inputWebhookUrl.setText(savedUrl)
        inputApiKey.setText(savedKey)
        inputPackageWhitelist.setText(savedWhitelist)

        // Save Settings Trigger
        btnSave.setOnClickListener {
            val url = inputWebhookUrl.text.toString().trim()
            val key = inputApiKey.text.toString().trim()
            val whitelist = inputPackageWhitelist.text.toString().trim()

            if (url.isEmpty()) {
                inputWebhookUrl.error = "Webhook URL is required"
                return@setOnClickListener
            }

            with(sharedPref.edit()) {
                putString("webhook_url", url)
                putString("api_key", key)
                putString("package_whitelist", whitelist)
                apply()
            }
            Toast.makeText(this, "Settings saved successfully", Toast.LENGTH_SHORT).show()
        }

        // Grant Permission Trigger
        btnGrantPermission.setOnClickListener {
            startActivity(Intent("android.settings.ACTION_NOTIFICATION_LISTENER_SETTINGS"))
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
    }

    override fun onResume() {
        super.onResume()
        updatePermissionStatus()
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
}
