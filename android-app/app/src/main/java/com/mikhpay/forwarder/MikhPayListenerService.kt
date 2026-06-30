package com.mikhpay.forwarder

import android.app.Notification
import android.content.Context
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException

class MikhPayListenerService : NotificationListenerService() {

    private val TAG = "MikhPayForwarder"
    private val client = OkHttpClient()

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        val packageName = sbn.packageName
        
        // Load Settings
        val sharedPref = getSharedPreferences("MikhPaySettings", Context.MODE_PRIVATE)
        val webhookUrl = sharedPref.getString("webhook_url", "") ?: ""
        val apiKey = sharedPref.getString("api_key", "") ?: ""
        val whitelistStr = sharedPref.getString("package_whitelist", "") ?: ""
        val customRegexStr = sharedPref.getString("custom_regex", "") ?: ""

        if (webhookUrl.isEmpty()) {
            Log.w(TAG, "Webhook URL not configured, ignoring notification.")
            return
        }

        // Parse Whitelist Packages
        val whitelist = whitelistStr.split(",").map { it.trim() }.filter { it.isNotEmpty() }
        
        // Check if the source application is in the whitelisted packages
        val isWhitelisted = whitelist.any { it.equals(packageName, ignoreCase = true) }
        if (!isWhitelisted) {
            Log.d(TAG, "Notification from ignored package: $packageName")
            return
        }

        // Extract Notification Content
        val extras = sbn.notification.extras
        val title = extras.getString(Notification.EXTRA_TITLE, "") ?: ""
        val text = extras.getCharSequence(Notification.EXTRA_TEXT, "")?.toString() ?: ""
        val timestamp = sbn.postTime / 1000

        Log.d(TAG, "Processing notification from $packageName - Title: $title, Text: $text")

        // Parse Amount using Regular Expressions
        val amount = extractAmount(title, text, customRegexStr)
        if (amount == null) {
            Log.w(TAG, "Failed to parse monetary amount from notification.")
            saveLog("FAILED", packageName, "-", "Gagal memproses nominal dari teks: \"$text\"")
            return
        }

        Log.i(TAG, "Successfully parsed amount: $amount from $packageName. Forwarding...")

        // Construct Payload & Send Webhook
        forwardWebhook(webhookUrl, apiKey, amount, packageName, title, text, timestamp)
    }

    override fun onNotificationRemoved(sbn: StatusBarNotification) {
        // No action needed on notification dismiss/remove
    }

    /**
     * Extracts numerical payment amount from notification text.
     * Supports formats like: Rp 10.045, Rp. 10.045, IDR 10,045, or raw 10.045 / 10045
     */
    private fun extractAmount(title: String, body: String, customRegexStr: String): String? {
        val fullText = "$title $body"

        // Regex 0: Try custom regex first if configured by user
        if (customRegexStr.isNotEmpty()) {
            try {
                val customRegex = Regex(customRegexStr, RegexOption.IGNORE_CASE)
                val match = customRegex.find(fullText)
                if (match != null) {
                    val matchedVal = if (match.groupValues.size > 1) match.groupValues[1] else match.value
                    return matchedVal.replace(".", "").replace(",", "").trim()
                }
            } catch (e: Exception) {
                Log.e(TAG, "Invalid custom Regex: ${e.message}")
            }
        }

        // Regex 1: Matches Rp/IDR prefix (e.g. Rp 10.045 or Rp. 10.045 or IDR 10,000)
        val rpRegex = Regex("""(?:Rp\.?|IDR)\s*([0-9]{1,3}(?:\.[0-9]{3})+|[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)""", RegexOption.IGNORE_CASE)
        var match = rpRegex.find(fullText)
        if (match != null) {
            return match.groupValues[1].replace(".", "").replace(",", "").trim()
        }

        // Regex 2: Matches any 4 to 8 digit number with dots/commas (e.g. 10.045)
        val digitRegex = Regex("""\b([0-9]{1,3}(?:\.[0-9]{3})+|[0-9]{1,3}(?:,[0-9]{3})+)\b""")
        match = digitRegex.find(fullText)
        if (match != null) {
            return match.groupValues[1].replace(".", "").replace(",", "").trim()
        }

        return null
    }

    private fun forwardWebhook(
        url: String,
        apiKey: String,
        amount: String,
        sender: String,
        title: String,
        body: String,
        timestamp: Long
    ) {
        val json = JSONObject()
        json.put("amount", amount)
        json.put("sender", sender)
        json.put("title", title)
        json.put("body", body)
        json.put("timestamp", timestamp)
        json.put("api_key", apiKey)

        val requestBody = json.toString().toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        val request = Request.Builder()
            .url(url)
            .post(requestBody)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                Log.e(TAG, "Failed to send webhook to $url: ${e.message}")
                saveLog("FAILED", sender, amount, "Gagal koneksi ke server: ${e.message}")
            }

            override fun onResponse(call: Call, response: Response) {
                val respBody = response.body?.string() ?: ""
                if (response.isSuccessful) {
                    Log.i(TAG, "Webhook delivered successfully! Response: $respBody")
                    saveLog("SUCCESS", sender, amount, "Berhasil terkirim. Respon: $respBody")
                    triggerSuccessFeedback()
                } else {
                    Log.e(TAG, "Webhook returned error code ${response.code}: $respBody")
                    saveLog("ERROR", sender, amount, "Server menolak (${response.code}): $respBody")
                }
            }
        })
    }

    private fun saveLog(status: String, sender: String, amount: String, message: String) {
        val sharedPref = getSharedPreferences("MikhPaySettings", Context.MODE_PRIVATE)
        val logsStr = sharedPref.getString("notification_logs", "[]") ?: "[]"
        try {
            val jsonArray = org.json.JSONArray(logsStr)
            val log = JSONObject().apply {
                put("timestamp", System.currentTimeMillis())
                put("status", status)
                put("sender", sender)
                put("amount", amount)
                put("message", message)
            }
            val newArray = org.json.JSONArray()
            newArray.put(log)
            for (i in 0 until minOf(jsonArray.length(), 19)) {
                newArray.put(jsonArray.getJSONObject(i))
            }
            sharedPref.edit().putString("notification_logs", newArray.toString()).apply()
        } catch (e: Exception) {
            Log.e(TAG, "Failed to save log: ${e.message}")
        }
    }

    private fun triggerSuccessFeedback() {
        try {
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.S) {
                val vibratorManager = getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as android.os.VibratorManager
                vibratorManager.defaultVibrator.vibrate(android.os.VibrationEffect.createOneShot(150, android.os.VibrationEffect.DEFAULT_AMPLITUDE))
            } else {
                @Suppress("DEPRECATION")
                val vibrator = getSystemService(Context.VIBRATOR_SERVICE) as android.os.Vibrator
                @Suppress("DEPRECATION")
                vibrator.vibrate(150)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Feedback trigger error: ${e.message}")
        }
    }
}
