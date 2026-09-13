package com.mikhpay.forwarder

import android.content.Context
import android.graphics.Bitmap
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.view.View
import android.webkit.*
import android.widget.Button
import android.widget.ImageButton
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException
import java.util.regex.Pattern

class GobizLoginActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var webProgress: ProgressBar
    private lateinit var btnBack: ImageButton
    private lateinit var btnRefresh: Button
    private lateinit var syncOverlay: View
    private lateinit var syncStatusText: TextView

    private val cookieHandler = Handler(Looper.getMainLooper())
    private var isSyncing = false
    private var isTokenDetected = false

    private val targetUrl = "https://portal.gofoodmerchant.co.id/"

    // Periodic cookie checker for Single Page App (SPA) token changes
    private val cookieCheckRunnable = object : Runnable {
        override fun run() {
            if (!isTokenDetected && !isSyncing) {
                checkCookiesForToken()
                cookieHandler.postDelayed(this, 1500)
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_gobiz_login)

        webView = findViewById(R.id.web_view)
        webProgress = findViewById(R.id.web_progress)
        btnBack = findViewById(R.id.btn_back)
        btnRefresh = findViewById(R.id.btn_refresh)
        syncOverlay = findViewById(R.id.sync_overlay)
        syncStatusText = findViewById(R.id.sync_status_text)

        btnBack.setOnClickListener {
            finish()
        }

        btnRefresh.setOnClickListener {
            webView.reload()
        }

        setupWebView()
        webView.loadUrl(targetUrl)

        // Start periodic cookie watcher
        cookieHandler.postDelayed(cookieCheckRunnable, 2000)
    }

    override fun onDestroy() {
        super.onDestroy()
        cookieHandler.removeCallbacks(cookieCheckRunnable)
        webView.stopLoading()
    }

    private fun setupWebView() {
        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.setSupportZoom(true)
        settings.builtInZoomControls = true
        settings.displayZoomControls = false
        settings.mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
        // Use modern Chrome User-Agent
        settings.userAgentString = "Mozilla/5.0 (Linux; Android 12; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"

        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true)
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                webProgress.progress = newProgress
                if (newProgress >= 100) {
                    webProgress.visibility = View.GONE
                } else {
                    webProgress.visibility = View.VISIBLE
                }
            }
        }

        webView.webViewClient = object : WebViewClient() {
            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                checkCookiesForToken()
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                checkCookiesForToken()
            }
        }
    }

    /**
     * Inspect active cookies for GoBiz JWT access_token
     */
    private fun checkCookiesForToken() {
        if (isTokenDetected || isSyncing) return

        val cookieManager = CookieManager.getInstance()
        val cookies = cookieManager.getCookie(targetUrl) ?: cookieManager.getCookie("https://app.gobiz.co.id") ?: ""

        if (cookies.contains("access_token=")) {
            val matcher = Pattern.compile("access_token=([^;]+)").matcher(cookies)
            if (matcher.find()) {
                val token = matcher.group(1)?.trim()
                if (!token.isNullOrEmpty() && token.length > 20) {
                    isTokenDetected = true
                    runOnUiThread {
                        onTokenFound(token)
                    }
                }
            }
        }
    }

    /**
     * Triggered when token is successfully detected from GoBiz session
     */
    private fun onTokenFound(token: String) {
        isSyncing = true
        syncOverlay.visibility = View.VISIBLE
        syncStatusText.text = "Token terdeteksi! Mengirim ke server MikhPay..."

        val sharedPref = getSharedPreferences("MikhPaySettings", Context.MODE_PRIVATE)
        val webhookUrl = sharedPref.getString("webhook_url", "") ?: ""
        val apiKey = sharedPref.getString("api_key", "") ?: ""

        if (webhookUrl.isEmpty()) {
            syncOverlay.visibility = View.GONE
            isSyncing = false
            Toast.makeText(this, "Webhook URL belum diatur di menu utama.", Toast.LENGTH_LONG).show()
            return
        }

        // Derive api.php URL from webhook_url
        val apiUrl = resolveApiUrl(webhookUrl)

        val client = OkHttpClient.Builder().build()
        val formBody = FormBody.Builder()
            .add("token", token)
            .add("api_key", apiKey)
            .build()

        val fullApiUrl = if (apiUrl.contains("?")) {
            "$apiUrl&api_key=${java.net.URLEncoder.encode(apiKey, "UTF-8")}"
        } else {
            "$apiUrl?api_key=${java.net.URLEncoder.encode(apiKey, "UTF-8")}"
        }

        val request = Request.Builder()
            .url(fullApiUrl)
            .addHeader("X-API-Key", apiKey)
            .post(formBody)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                runOnUiThread {
                    syncOverlay.visibility = View.GONE
                    isSyncing = false
                    isTokenDetected = false
                    AlertDialog.Builder(this@GobizLoginActivity)
                        .setTitle("Gagal Sinkronisasi")
                        .setMessage("Tidak dapat menghubungi server MikhPay: ${e.message}")
                        .setPositiveButton("Coba Lagi") { _, _ ->
                            onTokenFound(token)
                        }
                        .setNegativeButton("Tutup", null)
                        .show()
                }
            }

            override fun onResponse(call: Call, response: Response) {
                val respBody = response.body?.string() ?: ""
                runOnUiThread {
                    syncOverlay.visibility = View.GONE
                    if (response.isSuccessful) {
                        try {
                            val json = JSONObject(respBody)
                            val status = json.optString("status", "")
                            if (status == "success") {
                                triggerHapticSuccess()
                                val merchantName = json.optString("merchant_name", "Toko Anda")
                                val merchantId = json.optString("merchant_id", "")

                                AlertDialog.Builder(this@GobizLoginActivity)
                                    .setTitle("Sinkronisasi Berhasil!")
                                    .setMessage("Akun GoBiz terhubung dengan sukses ke server MikhPay.\n\nMerchant: $merchantName\nID: $merchantId\n\nAuto-Sync di server kini aktif.")
                                    .setCancelable(false)
                                    .setPositiveButton("Selesai") { _, _ ->
                                        finish()
                                    }
                                    .show()
                                return@runOnUiThread
                            }
                        } catch (e: Exception) {
                            // Fallback to error alert
                        }
                    }

                    isSyncing = false
                    isTokenDetected = false
                    AlertDialog.Builder(this@GobizLoginActivity)
                        .setTitle("Respon Server")
                        .setMessage("Server merespons: $respBody")
                        .setPositiveButton("OK", null)
                        .show()
                }
            }
        })
    }

    /**
     * Resolve target api.php URL from configured webhook URL
     */
    private fun resolveApiUrl(webhookUrl: String): String {
        val trimmed = webhookUrl.trim()
        val base = when {
            trimmed.contains("qris_verify.php") -> trimmed.replace("qris_verify.php", "api.php")
            trimmed.endsWith("/") -> "${trimmed}api.php"
            else -> {
                val lastSlash = trimmed.lastIndexOf('/')
                if (lastSlash != -1 && trimmed.substring(lastSlash).contains(".php")) {
                    "${trimmed.substring(0, lastSlash)}/api.php"
                } else {
                    "$trimmed/api.php"
                }
            }
        }
        return if (base.contains("?")) "$base&action=update_gopay_token" else "$base?action=update_gopay_token"
    }

    /**
     * Vibrate on success
     */
    private fun triggerHapticSuccess() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val vibratorManager = getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as VibratorManager
                val vibrator = vibratorManager.defaultVibrator
                vibrator.vibrate(VibrationEffect.createPredefined(VibrationEffect.EFFECT_CLICK))
            } else {
                @Suppress("DEPRECATION")
                val vibrator = getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(VibrationEffect.createOneShot(150, VibrationEffect.DEFAULT_AMPLITUDE))
                } else {
                    @Suppress("DEPRECATION")
                    vibrator.vibrate(150)
                }
            }
        } catch (e: Exception) {
            // Ignore haptic errors on unsupported devices
        }
    }
}
