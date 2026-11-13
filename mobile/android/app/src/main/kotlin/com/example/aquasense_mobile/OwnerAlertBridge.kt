package com.example.aquasense_mobile

import android.content.Intent
import android.os.Build
import android.provider.Settings
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.MethodChannel

internal class OwnerAlertBridge(private val activity: MainActivity, messenger: BinaryMessenger) {
    private val channel = MethodChannel(messenger, "aquasense/alert_delivery")
    init {
        channel.setMethodCallHandler { call, result ->
            try {
                when (call.method) {
                    "sync", "setEnabled" -> {
                        if (call.method == "setEnabled") OwnerAlertService.preferences(activity).edit()
                            .putBoolean("enabled", call.argument<Boolean>("enabled") == true).apply()
                        val session = call.argument<Map<String, Any>>("session")
                        val enabled = OwnerAlertService.preferences(activity).getBoolean("enabled", true)
                        if (session == null || !enabled || !OwnerAlertDisplay.allowed(activity)) {
                            activity.stopService(Intent(activity, OwnerAlertService::class.java))
                            OwnerAlertDisplay.hideOverlay()
                            if (session == null) (activity.getSystemService(android.content.Context.NOTIFICATION_SERVICE) as android.app.NotificationManager).cancelAll()
                        } else {
                            val intent = Intent(activity, OwnerAlertService::class.java)
                                .putExtra("ownerId", (session["ownerId"] as? Number)?.toInt() ?: 0)
                                .putExtra("apiBaseUrl", session["apiBaseUrl"] as? String ?: "")
                                .putExtra("token", session["token"] as? String ?: "")
                            if (Build.VERSION.SDK_INT >= 26) activity.startForegroundService(intent) else activity.startService(intent)
                        }
                        result.success(status())
                    }
                    "status" -> result.success(status())
                    "testNotification" -> {
                        if (!OwnerAlertDisplay.allowed(activity)) result.error("permission", "Enable notifications first.", null)
                        else {
                            OwnerAlertDisplay.notify(activity, "test", 82002, "AQUASENSE+ test notification", "Notifications are enabled. This is a test, not a grease trap alert.")
                            result.success(true)
                        }
                    }
                    "testOverlay" -> {
                        if (!Settings.canDrawOverlays(activity)) result.error("permission", "Allow display over other apps first.", null)
                        else {
                            result.success(OwnerAlertDisplay.showOverlay(activity, "AQUASENSE+ test pop-up", "This is a test. Critical alerts can appear here while you use another app."))
                        }
                    }
                    else -> result.notImplemented()
                }
            } catch (_: Exception) {
                result.error("delivery", "Android could not start alert delivery. Reopen the app and check permissions.", null)
            }
        }
    }
    private fun status(): Map<String, Any> = mapOf(
        "supported" to true,
        "enabled" to OwnerAlertService.preferences(activity).getBoolean("enabled", true),
        "running" to OwnerAlertService.running,
        "status" to when {
            !OwnerAlertService.preferences(activity).getBoolean("enabled", true) -> "Alert monitoring is off. Turn it on to receive warnings."
            !OwnerAlertDisplay.allowed(activity) -> "Enable notification permission to receive warnings."
            OwnerAlertService.running -> OwnerAlertService.preferences(activity).getString("status", "Monitoring alerts") ?: "Monitoring alerts"
            else -> "Monitoring is stopped. Reopen the app to restart it."
        },
        "notificationsEnabled" to OwnerAlertDisplay.allowed(activity),
        "overlayEnabled" to Settings.canDrawOverlays(activity)
    )
    fun close() { channel.setMethodCallHandler(null) }
}
