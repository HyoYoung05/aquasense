package com.example.aquasense_mobile

import android.Manifest
import android.app.*
import android.content.*
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.PixelFormat
import android.os.*
import android.provider.Settings
import android.view.*
import android.widget.*
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat

internal object OwnerAlertDisplay {
    const val CHANNEL = "aquasense_owner_alerts_v1"
    const val MONITOR_CHANNEL = "aquasense_alert_monitor_v1"
    private val handler = Handler(Looper.getMainLooper())
    private var overlay: View? = null
    private var manager: WindowManager? = null
    private var expiry: Runnable? = null

    fun allowed(context: Context): Boolean = NotificationManagerCompat.from(context).areNotificationsEnabled() &&
        (Build.VERSION.SDK_INT < 33 || ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED)

    fun channels(context: Context) {
        if (Build.VERSION.SDK_INT >= 26) {
            val nm = context.getSystemService(NotificationManager::class.java)
            nm.createNotificationChannel(NotificationChannel(CHANNEL, "Grease trap alerts", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Warnings and critical alerts for your assigned grease trap"
                enableVibration(true)
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
            })
            nm.createNotificationChannel(NotificationChannel(MONITOR_CHANNEL, "Alert monitoring", NotificationManager.IMPORTANCE_LOW))
        }
    }

    fun openApp(context: Context): PendingIntent = PendingIntent.getActivity(context, 0,
        Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

    fun notify(context: Context, tag: String, id: Int, title: String, body: String) {
        if (!allowed(context)) return
        channels(context)
        val public = NotificationCompat.Builder(context, CHANNEL).setSmallIcon(R.drawable.ic_stat_water)
            .setContentTitle("AQUASENSE+ alert").setContentText("Open the app to see your alert.").build()
        val n = NotificationCompat.Builder(context, CHANNEL).setSmallIcon(R.drawable.ic_stat_water)
            .setContentTitle(title.take(120)).setContentText(body.take(400))
            .setStyle(NotificationCompat.BigTextStyle().bigText(body.take(400)))
            .setPriority(NotificationCompat.PRIORITY_HIGH).setCategory(NotificationCompat.CATEGORY_ALARM)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE).setPublicVersion(public)
            .setContentIntent(openApp(context)).setAutoCancel(true).build()
        try { NotificationManagerCompat.from(context).notify(tag, id, n) } catch (_: SecurityException) { }
    }

    fun hideOverlay() {
        if (Looper.myLooper() != Looper.getMainLooper()) { handler.post { hideOverlay() }; return }
        expiry?.let { handler.removeCallbacks(it) }; expiry = null
        overlay?.let { try { manager?.removeView(it) } catch (_: Exception) { } }
        overlay = null; manager = null
    }

    fun showOverlay(context: Context, title: String, message: String): Boolean {
        if (Looper.myLooper() != Looper.getMainLooper()) { handler.post { showOverlay(context, title, message) }; return false }
        if (!Settings.canDrawOverlays(context)) return false
        val keyguard = context.getSystemService(Context.KEYGUARD_SERVICE) as KeyguardManager
        if (keyguard.isKeyguardLocked) return false
        hideOverlay()
        val dp = context.resources.displayMetrics.density
        val card = LinearLayout(context).apply {
            orientation = LinearLayout.VERTICAL; setPadding((20*dp).toInt(), (16*dp).toInt(), (20*dp).toInt(), (12*dp).toInt())
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(Color.WHITE); cornerRadius = 16*dp; setStroke((2*dp).toInt(), Color.rgb(183, 48, 42))
            }
            elevation = 12*dp
        }
        card.addView(TextView(context).apply { text = title.take(120); textSize = 18f; setTextColor(Color.rgb(18,63,58)); setTypeface(null, 1) })
        card.addView(TextView(context).apply { text = message.take(300); textSize = 15f; setTextColor(Color.rgb(30,48,44)); setPadding(0,(8*dp).toInt(),0,(8*dp).toInt()) })
        val buttons = LinearLayout(context)
        buttons.addView(Button(context).apply { text = "Open app"; setOnClickListener { hideOverlay(); try { openApp(context).send() } catch (_: Exception) { } } }, LinearLayout.LayoutParams(0,-2,1f))
        buttons.addView(Button(context).apply { text = "Dismiss"; setOnClickListener { hideOverlay() } }, LinearLayout.LayoutParams(0,-2,1f))
        card.addView(buttons)
        val params = WindowManager.LayoutParams(
            (context.resources.displayMetrics.widthPixels - 32*dp).toInt().coerceAtMost((480*dp).toInt()),
            WindowManager.LayoutParams.WRAP_CONTENT,
            if (Build.VERSION.SDK_INT >= 26) WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY else WindowManager.LayoutParams.TYPE_PHONE,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
            PixelFormat.TRANSLUCENT).apply { gravity = Gravity.CENTER; x = 0; y = 0 }
        try {
            manager = context.getSystemService(Context.WINDOW_SERVICE) as WindowManager
            manager!!.addView(card, params); overlay = card
            expiry = Runnable { hideOverlay() }.also { handler.postDelayed(it, 15000) }
            return true
        } catch (_: Exception) { hideOverlay(); return false }
    }
}
