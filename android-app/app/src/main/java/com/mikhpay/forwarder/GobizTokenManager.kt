package com.mikhpay.forwarder

import android.content.Context
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.webkit.CookieManager
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import okhttp3.*
import org.json.JSONObject
import java.io.IOException
import java.util.regex.Pattern

/**
 * Singleton manager to perform Silent Auto-Refresh of GoBiz access_token using a headless WebView.
 * Runs in background without interrupting the user.
 */
object GobizTokenManager {

    private const val TAG = "GobizTokenManager"
    const val PREFS_NAME = "MikhPaySettings"
    const val KEY_LAST_REFRESH_TIME = "last_gobiz_refresh_time"
    const val KEY_GOBIZ_SESSION_ACTIVE = "gobiz_session_active"
    const val KEY_LAST_REFRESH_STATUS = "last_gobiz_refresh_status"

    // Interval refresh otomatis: 6 jam (sebelum token 24 jam kedaluwarsa)
    private const val REFRESH_INTERVAL_MS = 6 * 60 * 60 * 1000L
    private const val GOBIZ_PORTAL_URL = "https://app.gobiz.co.id"

    @Volatile
    private var isRefreshing = false
    private var headlessWebView: WebView? = null
    private var timeoutHandler: Handler? = null
    private var checkCookieRunnable: Runnable? = null

    /**
     * Checks if silent refresh is needed based on elapsed time (> 6 jam) or force flag.
     */
    fun checkAndSilentRefresh(context: Context, force: Boolean = false, onResult: ((Boolean, String) -> Unit)? = null) {
        val sharedPref = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val webhookUrl = sharedPref.getString("webhook_url", "") ?: ""
        val apiKey = sharedPref.getString("api_key", "") ?: ""
        val lastRefreshTime = sharedPref.getLong(KEY_LAST_REFRESH_TIME, 0L)
        val isSessionActive = sharedPref.getBoolean(KEY_GOBIZ_SESSION_ACTIVE, false)

        if (webhookUrl.isEmpty() || apiKey.isEmpty()) {
            Log.d(TAG, "Webhook URL atau API Key belum diatur. Melewati auto-refresh.")
            onResult?.invoke(false, "Webhook URL / API Key belum diatur")
            return
        }

        val elapsed = System.currentTimeMillis() - lastRefreshTime
        if (!force && !isSessionActive && lastRefreshTime == 0L) {
            // Pengguna belum pernah login di WebView
            Log.d(TAG, "Belum ada sesi GoBiz aktif. Silakan login manual terlebih dahulu.")
            onResult?.invoke(false, "Belum pernah login GoBiz")
            return
        }

        if (!force && elapsed < REFRESH_INTERVAL_MS) {
            val hoursLeft = (REFRESH_INTERVAL_MS - elapsed) / (1000 * 60 * 60)
            Log.d(TAG, "Token GoBiz masih segar (diperbarui ${(elapsed / (1000 * 60))} menit lalu). Refresh berikutnya dalam ~${hoursLeft + 1} jam.")
            onResult?.invoke(true, "Token masih aktif")
            return
        }

        if (isRefreshing) {
            Log.d(TAG, "Silent refresh sedang berlangsung...")
            onResult?.invoke(false, "Proses refresh sedang berjalan")
            return
        }

        Log.i(TAG, "Memulai Silent Auto-Refresh Token GoBiz via Headless WebView...")
        startHeadlessRefresh(context.applicationContext, webhookUrl, apiKey, onResult)
    }

    /**
     * Spawns a headless WebView on the Main Looper to silently hit GoBiz portal and capture refreshed cookies.
     */
    private fun startHeadlessRefresh(
        appContext: Context,
        webhookUrl: String,
        apiKey: String,
        onResult: ((Boolean, String) -> Unit)?
    ) {
        Handler(Looper.getMainLooper()).post {
            if (isRefreshing) return@post
            isRefreshing = true

            try {
                // Bersihkan instance lama jika ada
                destroyHeadlessWebView()

                val webView = WebView(appContext)
                headlessWebView = webView

                val settings = webView.settings
                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true
                settings.databaseEnabled = true
                settings.cacheMode = WebSettings.LOAD_DEFAULT
                settings.userAgentString = "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36"

                val cookieManager = CookieManager.getInstance()
                cookieManager.setAcceptCookie(true)
                cookieManager.setAcceptThirdPartyCookies(webView, true)

                var tokenCaptured = false

                // Polling helper untuk memeriksa cookies berkala selama halaman memuat
                timeoutHandler = Handler(Looper.getMainLooper())
                var attempts = 0
                val maxAttempts = 12 // 12 x 2s = 24 detik maksimal

                checkCookieRunnable = object : Runnable {
                    override fun run() {
                        if (tokenCaptured || !isRefreshing) return

                        val cookies = cookieManager.getCookie(GOBIZ_PORTAL_URL) 
                            ?: cookieManager.getCookie("https://portal.gofoodmerchant.co.id") 
                            ?: ""

                        if (cookies.contains("access_token=")) {
                            val matcher = Pattern.compile("access_token=([^;]+)").matcher(cookies)
                            if (matcher.find()) {
                                val token = matcher.group(1)?.trim()
                                if (!token.isNullOrEmpty() && token.length > 20) {
                                    tokenCaptured = true
                                    Log.i(TAG, "Token GoBiz berhasil tertangkap secara silent!")

                                    var refreshToken = ""
                                    val refreshMatcher = Pattern.compile("refresh_token=([^;]+)").matcher(cookies)
                                    if (refreshMatcher.find()) {
                                        refreshToken = refreshMatcher.group(1)?.trim() ?: ""
                                    }

                                    uploadRefreshedToken(appContext, webhookUrl, apiKey, token, refreshToken, onResult)
                                    return
                                }
                            }
                        }

                        attempts++
                        if (attempts < maxAttempts) {
                            timeoutHandler?.postDelayed(this, 2000L)
                        } else {
                            Log.w(TAG, "Timeout Silent Refresh: Tidak menemukan access_token setelah 24 detik.")
                            saveRefreshStatus(appContext, "Gagal: Sesi GoBiz kedaluwarsa atau offline.")
                            cleanup(false, "Timeout atau sesi kedaluwarsa", onResult)
                        }
                    }
                }

                webView.webViewClient = object : WebViewClient() {
                    override fun onPageFinished(view: WebView?, url: String?) {
                        Log.d(TAG, "Headless WebView loaded: $url")
                        // Segera cek cookies saat page finish
                        checkCookieRunnable?.run()
                    }
                }

                // Mulai muat URL GoBiz
                webView.loadUrl(GOBIZ_PORTAL_URL)
                // Mulai polling cookie pertama setelah 2.5 detik
                timeoutHandler?.postDelayed(checkCookieRunnable!!, 2500L)

            } catch (e: Exception) {
                Log.e(TAG, "Error saat inisialisasi Headless WebView: ${e.message}")
                cleanup(false, "Error: ${e.message}", onResult)
            }
        }
    }

    /**
     * Mengunggah token baru ke server MikhPay via api.php?action=update_gopay_token
     */
    private fun uploadRefreshedToken(
        context: Context,
        webhookUrl: String,
        apiKey: String,
        token: String,
        refreshToken: String,
        onResult: ((Boolean, String) -> Unit)?
    ) {
        val apiUrl = resolveApiUrl(webhookUrl)
        val client = OkHttpClient.Builder().build()
        val formBody = FormBody.Builder()
            .add("token", token)
            .add("refresh_token", refreshToken)
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
                Log.e(TAG, "Gagal mengunggah refreshed token ke server MikhPay: ${e.message}")
                saveRefreshStatus(context, "Gagal kirim ke server: ${e.message}")
                cleanup(false, "Gagal koneksi ke server MikhPay", onResult)
            }

            override fun onResponse(call: Call, response: Response) {
                val respBody = response.body?.string() ?: ""
                var success = false
                var msg = "Server error"

                if (response.isSuccessful) {
                    try {
                        val json = JSONObject(respBody)
                        if (json.optString("status") == "success") {
                            success = true
                            msg = "Silent Auto-Refresh Berhasil"
                            val sharedPref = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                            with(sharedPref.edit()) {
                                putLong(KEY_LAST_REFRESH_TIME, System.currentTimeMillis())
                                putBoolean(KEY_GOBIZ_SESSION_ACTIVE, true)
                                putString(KEY_LAST_REFRESH_STATUS, "Sukses diperbarui")
                                apply()
                            }
                            Log.i(TAG, "Token GoBiz di server MikhPay berhasil diperbarui secara otomatis di background!")
                        } else {
                            msg = json.optString("message", respBody)
                        }
                    } catch (e: Exception) {
                        msg = "Respon server tidak valid"
                    }
                } else {
                    msg = "HTTP ${response.code}: $respBody"
                }

                saveRefreshStatus(context, if (success) "Sukses diperbarui" else "Gagal: $msg")
                cleanup(success, msg, onResult)
            }
        })
    }

    private fun cleanup(success: Boolean, message: String, onResult: ((Boolean, String) -> Unit)?) {
        Handler(Looper.getMainLooper()).post {
            destroyHeadlessWebView()
            isRefreshing = false
            onResult?.invoke(success, message)
        }
    }

    private fun destroyHeadlessWebView() {
        try {
            checkCookieRunnable?.let { timeoutHandler?.removeCallbacks(it) }
            timeoutHandler = null
            checkCookieRunnable = null

            headlessWebView?.let {
                it.stopLoading()
                it.clearHistory()
                it.destroy()
            }
            headlessWebView = null
        } catch (e: Exception) {
            Log.e(TAG, "Error saat membersihkan Headless WebView: ${e.message}")
        }
    }

    private fun saveRefreshStatus(context: Context, status: String) {
        val sharedPref = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        sharedPref.edit().putString(KEY_LAST_REFRESH_STATUS, status).apply()
    }

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
}
