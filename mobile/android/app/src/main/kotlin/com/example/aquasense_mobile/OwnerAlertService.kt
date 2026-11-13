package com.example.aquasense_mobile

import android.app.*
import android.content.*
import android.content.pm.ServiceInfo
import android.os.*
import androidx.core.app.NotificationCompat
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URI
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit

class OwnerAlertService : Service() {
    companion object {
        const val MONITOR_ID = 82001
        @Volatile var running = false
        fun preferences(c: Context) = c.getSharedPreferences("owner_alert_delivery", Context.MODE_PRIVATE)
    }
    private data class Session(val owner: Int, val base: String, val token: String)
    @Volatile private var session: Session? = null
    @Volatile private var connection: HttpURLConnection? = null
    private val executor = Executors.newSingleThreadScheduledExecutor()
    private val main = Handler(Looper.getMainLooper())
    private var seen = mutableMapOf<Int, String>()
    private var failures = 0

    override fun onBind(intent: Intent?) = null
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == "STOP") {
            preferences(this).edit().putBoolean("enabled", false).apply()
            stopSelf(); return START_NOT_STICKY
        }
        val owner = intent?.getIntExtra("ownerId", 0) ?: 0
        val base = intent?.getStringExtra("apiBaseUrl")?.trimEnd('/') ?: ""
        val token = intent?.getStringExtra("token") ?: ""
        val uri = try { URI(base) } catch (_: Exception) { null }
        if (owner < 1 || uri?.scheme != "https" || uri.host.isNullOrEmpty() || uri.userInfo != null || uri.query != null || uri.fragment != null || !Regex("[a-fA-F0-9]{64}").matches(token) || !OwnerAlertDisplay.allowed(this)) {
            stopSelf(); return START_NOT_STICKY
        }
        OwnerAlertDisplay.channels(this)
        val stop = PendingIntent.getService(this, 1, Intent(this, OwnerAlertService::class.java).setAction("STOP"), PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(this, OwnerAlertDisplay.MONITOR_CHANNEL)
            .setSmallIcon(R.drawable.ic_stat_water).setContentTitle("AQUASENSE+ alert monitoring")
            .setContentText("Checking alerts for your account. Tap to open the app.")
            .setContentIntent(OwnerAlertDisplay.openApp(this)).setOngoing(true)
            .addAction(0, "Stop", stop).setCategory(NotificationCompat.CATEGORY_SERVICE).build()
        try {
            if (Build.VERSION.SDK_INT >= 29) startForeground(MONITOR_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC)
            else startForeground(MONITOR_ID, notification)
        } catch (_: Exception) { status("Android paused monitoring. Reopen the app to try again."); stopSelf(); return START_NOT_STICKY }
        val updated = Session(owner, base, token)
        if (session == updated) return START_NOT_STICKY
        if (session != null) { // Invalidate pending work before switching owners/tokens.
            session = null
            connection?.disconnect(); (getSystemService(NOTIFICATION_SERVICE) as NotificationManager).cancelAll()
            OwnerAlertDisplay.hideOverlay()
            (getSystemService(NOTIFICATION_SERVICE) as NotificationManager).notify(MONITOR_ID, notification)
        }
        session = updated
        val saved = try { JSONObject(preferences(this).getString("seen_$owner", "{}") ?: "{}") } catch (_: Exception) { JSONObject() }
        val previous = saved.keys().asSequence().mapNotNull { k -> k.toIntOrNull()?.let { it to saved.optString(k) } }.toMap().toMutableMap()
        running = true; status("Monitoring your assigned grease trap alerts.")
        executor.execute { if (session === updated) { seen = previous; poll(updated) } }
        return START_NOT_STICKY
    }

    private fun status(message: String) { preferences(this).edit().putString("status", message).apply() }

    private fun poll(current: Session) {
        if (session !== current) return
        var delay = 5L
        try {
            val alerts = mutableListOf<JSONObject>()
            var page = 1
            do {
                val url = URI(current.base + "/alerts.php?status=UNRESOLVED&severity=ALL&range=30d&page=$page").toURL()
                val http = url.openConnection() as HttpURLConnection
                connection = http
                val data = try {
                    http.instanceFollowRedirects = false; http.connectTimeout = 8000; http.readTimeout = 8000
                    http.setRequestProperty("Authorization", "Bearer ${current.token}")
                    http.setRequestProperty("Accept", "application/json")
                    val code = http.responseCode
                    if (code == 401 || code == 403) { main.post { if (session === current) { status("Please sign in again to restart monitoring."); stopSelf() } }; return }
                    if (code != 200) throw java.io.IOException("Alert service unavailable")
                    val stream = http.inputStream
                    val bytes = stream.use { input ->
                        val output = java.io.ByteArrayOutputStream()
                        val buffer = ByteArray(8192)
                        while (output.size() <= 1048576) {
                            val count = input.read(buffer)
                            if (count < 0) break
                            output.write(buffer, 0, count)
                        }
                        output.toByteArray()
                    }
                    if (bytes.size > 1048576) throw java.io.IOException("Alert response too large")
                    val payload = JSONObject(String(bytes, Charsets.UTF_8))
                    if (!payload.optBoolean("success")) throw java.io.IOException("Invalid response")
                    payload.getJSONObject("data")
                } finally { connection = null; http.disconnect() }
                val list = data.getJSONArray("alerts")
                for (i in 0 until list.length()) alerts.add(list.getJSONObject(i))
                val pages = data.optInt("pages", 1)
                if (pages > 100) throw java.io.IOException("Too many alert pages")
                page++
            } while (page <= pages)
            if (session !== current) return
            val next = mutableMapOf<Int, String>()
            for (alert in alerts) {
                if (alert.optString("status") !in listOf("ACTIVE", "ACKNOWLEDGED")) continue
                val id = alert.optInt("id", 0); if (id < 1) continue
                val severity = alert.optString("severity")
                val fingerprint = alert.optString("first_triggered_at") + "|" + severity
                next[id] = fingerprint
                if (seen[id] == fingerprint) continue
                val title = alert.optString("label", "Grease trap alert")
                val body = alert.optString("grease_trap_name") + ": " + alert.optString("message")
                main.post {
                    if (session === current) {
                        OwnerAlertDisplay.notify(this, "owner-${current.owner}", id, title, body)
                        if (severity == "CRITICAL" && !MainActivity.visible) OwnerAlertDisplay.showOverlay(this, title, body)
                    }
                }
            }
            val removed = seen.keys - next.keys
            if (removed.isNotEmpty()) main.post {
                if (session === current) {
                    val nm = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
                    removed.forEach { nm.cancel("owner-${current.owner}", it) }
                    OwnerAlertDisplay.hideOverlay()
                }
            }
            seen = next
            preferences(this).edit().putString("seen_${current.owner}", JSONObject(next.mapKeys { it.key.toString() }).toString()).apply()
            failures = 0; status("Monitoring your assigned grease trap alerts.")
        } catch (_: Exception) {
            failures++; delay = (5L shl failures.coerceAtMost(3)).coerceAtMost(60L)
            if (session === current) status("Connection interrupted. Monitoring will retry.")
        }
        if (session === current && !executor.isShutdown) executor.schedule({ poll(current) }, delay, TimeUnit.SECONDS)
    }

    override fun onTimeout(startId: Int, fgsType: Int) {
        status("Android paused monitoring. Reopen the app to restart it.")
        stopSelf()
    }
    override fun onDestroy() {
        session = null; running = false; connection?.disconnect(); executor.shutdownNow()
        OwnerAlertDisplay.hideOverlay(); (getSystemService(NOTIFICATION_SERVICE) as NotificationManager).cancelAll()
        stopForeground(STOP_FOREGROUND_REMOVE); super.onDestroy()
    }
}
