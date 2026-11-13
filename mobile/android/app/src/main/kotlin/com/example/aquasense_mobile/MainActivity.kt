package com.example.aquasense_mobile

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine

class MainActivity : FlutterActivity() {
    companion object { @Volatile var visible = false }
    private var wifiBridge: DeviceWifiBridge? = null
    private var alertBridge: OwnerAlertBridge? = null

    override fun onResume() { super.onResume(); visible = true }
    override fun onPause() { visible = false; super.onPause() }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        wifiBridge = DeviceWifiBridge(this, flutterEngine.dartExecutor.binaryMessenger)
        alertBridge = OwnerAlertBridge(this, flutterEngine.dartExecutor.binaryMessenger)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        wifiBridge?.onPermissionResult(requestCode)
    }

    override fun cleanUpFlutterEngine(flutterEngine: FlutterEngine) {
        wifiBridge?.close()
        wifiBridge = null
        alertBridge?.close()
        alertBridge = null
        super.cleanUpFlutterEngine(flutterEngine)
    }
}
