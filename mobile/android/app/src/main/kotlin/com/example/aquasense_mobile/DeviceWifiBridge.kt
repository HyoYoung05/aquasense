package com.example.aquasense_mobile

import android.Manifest
import android.app.Activity
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.le.ScanResult
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.espressif.provisioning.DeviceConnectionEvent
import com.espressif.provisioning.ESPConstants
import com.espressif.provisioning.ESPDevice
import com.espressif.provisioning.ESPProvisionManager
import com.espressif.provisioning.listeners.BleScanListener
import com.espressif.provisioning.listeners.ProvisionListener
import com.espressif.provisioning.listeners.ResponseListener
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import org.greenrobot.eventbus.EventBus
import org.greenrobot.eventbus.Subscribe
import org.greenrobot.eventbus.ThreadMode
import org.json.JSONObject

/** Local encrypted BLE provisioning only. No Wi-Fi credentials enter the API or storage. */
class DeviceWifiBridge(private val activity: Activity, messenger: BinaryMessenger) {
    private val channel = MethodChannel(messenger, "aquasense/device_wifi")
    private val handler = Handler(Looper.getMainLooper())
    private val manager = ESPProvisionManager.getInstance(activity.applicationContext)
    private val devices = linkedMapOf<String, Pair<BluetoothDevice, String>>()
    private var pending: MethodChannel.Result? = null
    private var device: ESPDevice? = null
    private var phase = "idle"
    private var ssid = ""
    private var password = ""
    private var permissionCall: MethodCall? = null
    private var generation = 0
    private val scanTimeout = Runnable { finishScan() }
    private val operationTimeout = Runnable { fail("TIMEOUT", "Setup timed out. Check the device and try again.") }

    init {
        EventBus.getDefault().register(this)
        channel.setMethodCallHandler { call, result ->
            if (call.method == "cancel") { cancel(); result.success(null); return@setMethodCallHandler }
            if (call.method != "scan" && call.method != "provision") { result.notImplemented(); return@setMethodCallHandler }
            if (pending != null) { result.error("BUSY", "A device setup operation is already running.", null); return@setMethodCallHandler }
            pending = result
            try {
                val missing = permissions().filter { ContextCompat.checkSelfPermission(activity, it) != PackageManager.PERMISSION_GRANTED }
                if (missing.isNotEmpty()) {
                    permissionCall = call
                    phase = "permission"
                    ActivityCompat.requestPermissions(activity, missing.toTypedArray(), PERMISSION_REQUEST)
                } else execute(call)
            } catch (_: Exception) { fail("BLUETOOTH", "Unable to start Bluetooth setup. Check Nearby devices permission.") }
        }
    }

    private fun permissions(): List<String> = if (Build.VERSION.SDK_INT >= 31)
        listOf(Manifest.permission.BLUETOOTH_SCAN, Manifest.permission.BLUETOOTH_CONNECT)
    else listOf(Manifest.permission.ACCESS_FINE_LOCATION)

    fun onPermissionResult(requestCode: Int) {
        if (requestCode != PERMISSION_REQUEST || permissionCall == null) return
        val call = permissionCall!!
        permissionCall = null
        if (permissions().any { ContextCompat.checkSelfPermission(activity, it) != PackageManager.PERMISSION_GRANTED }) {
            fail("PERMISSION", "Allow Nearby devices access (or Location on older Android) to find your ESP32.")
        } else try { execute(call) } catch (_: Exception) { fail("BLUETOOTH", "Unable to start Bluetooth setup.") }
    }

    private fun execute(call: MethodCall) {
        val adapter = (activity.getSystemService(Context.BLUETOOTH_SERVICE) as BluetoothManager).adapter
        if (adapter == null || !adapter.isEnabled) { fail("BLUETOOTH_OFF", "Turn on Bluetooth and try again."); return }
        if (call.method == "scan") scan() else provision(call)
    }

    private fun scan() {
        phase = "scan"
        devices.clear()
        val scanGeneration = ++generation
        handler.postDelayed(scanTimeout, 12000)
        manager.searchBleEspDevices("AQUASENSE_", object : BleScanListener {
            override fun scanStartFailed() { handler.post { if (generation == scanGeneration) fail("SCAN", "Could not scan. Turn on Bluetooth and check permissions.") } }
            override fun onPeripheralFound(found: BluetoothDevice, scanResult: ScanResult) {
                handler.post {
                    if (generation != scanGeneration || phase != "scan") return@post
                    val name = scanResult.scanRecord?.deviceName ?: found.name ?: return@post
                    val uuid = scanResult.scanRecord?.serviceUuids?.firstOrNull { it.toString().equals(SERVICE_UUID, true) } ?: return@post
                    if (name.startsWith("AQUASENSE_")) devices[found.address] = Pair(found, uuid.toString())
                }
            }
            override fun scanCompleted() { handler.post { if (generation == scanGeneration) finishScan() } }
            override fun onFailure(e: Exception) { handler.post { if (generation == scanGeneration) fail("SCAN", "Could not scan for devices. Check Bluetooth and try again.") } }
        })
    }

    private fun finishScan() {
        if (phase != "scan") return
        // Change phase first; stopBleScan can synchronously call scanCompleted.
        phase = "idle"
        ++generation
        handler.removeCallbacks(scanTimeout)
        manager.stopBleScan()
        val result = pending
        pending = null
        result?.success(devices.map { (id, value) -> mapOf("id" to id, "name" to (value.first.name ?: "AQUASENSE device")) })
    }

    private fun provision(call: MethodCall) {
        val id = call.argument<String>("deviceId") ?: ""
        val proof = call.argument<String>("setupCode") ?: ""
        val network = call.argument<String>("ssid") ?: ""
        val secret = call.argument<String>("password") ?: ""
        val selected = devices[id]
        val passBytes = secret.toByteArray(Charsets.UTF_8).size
        val passValid = secret.isEmpty() || passBytes in 8..63 || (secret.length == 64 && secret.matches(Regex("[a-fA-F0-9]{64}")))
        if (selected == null || !proof.matches(Regex("[a-fA-F0-9]{16}")) || network.toByteArray(Charsets.UTF_8).size !in 1..32 || !passValid) {
            fail("VALIDATION", "Select a device, enter its 16-character setup code, and check the Wi-Fi details."); return
        }
        ssid = network
        password = secret
        phase = "connect"
        device = manager.createESPDevice(ESPConstants.TransportType.TRANSPORT_BLE, ESPConstants.SecurityType.SECURITY_1)
        device!!.setProofOfPossession(proof.lowercase())
        handler.postDelayed(operationTimeout, 90000)
        device!!.connectBLEDevice(selected.first, selected.second)
    }

    @Subscribe(threadMode = ThreadMode.MAIN)
    fun onDeviceConnection(event: DeviceConnectionEvent) {
        if (phase != "connect" && phase != "session" && phase != "provision") return
        when (event.eventType.toInt()) {
            ESPConstants.EVENT_DEVICE_CONNECTED.toInt() -> {
                if (phase != "connect") return
                val active = device ?: return
                // Never let SDK capability negotiation downgrade to unencrypted Security 0.
                val security = try { JSONObject(active.versionInfo).getJSONObject("prov").optInt("sec_ver", -1) } catch (_: Exception) { -1 }
                val capabilities = active.deviceCapabilities
                if (security != 1 || capabilities.contains("no_pop") || capabilities.contains("no_sec")) {
                    fail("SECURITY", "This device does not support the required secure setup code. Update its firmware."); return
                }
                phase = "session"
                val sessionGeneration = generation
                active.initSession(object : ResponseListener {
                    override fun onSuccess(data: ByteArray?) { handler.post {
                        if (generation != sessionGeneration || phase != "session") return@post
                        if (active.securityType != ESPConstants.SecurityType.SECURITY_1 || active.proofOfPossession.isNullOrEmpty()) {
                            fail("SECURITY", "Secure setup could not be established."); return@post
                        }
                        phase = "provision"
                        active.provision(ssid, password, provisionListenerFor(sessionGeneration))
                        ssid = ""; password = ""
                    } }
                    override fun onFailure(e: Exception) { handler.post { if (generation == sessionGeneration) fail("SETUP_CODE", "Could not verify the setup code. Check it and try again.") } }
                })
            }
            ESPConstants.EVENT_DEVICE_CONNECTION_FAILED.toInt(), ESPConstants.EVENT_DEVICE_DISCONNECTED.toInt() -> fail("DISCONNECTED", "The device disconnected before confirming Wi-Fi. Check its setup mode and try again.")
        }
    }

    private fun provisionListenerFor(expectedGeneration: Int) = object : ProvisionListener {
        private fun reportFailure(message: String) { handler.post { if (generation == expectedGeneration && phase == "provision") fail("WIFI", message) } }
        override fun createSessionFailed(e: Exception) = reportFailure("Could not verify the device setup code.")
        override fun wifiConfigSent() {}
        override fun wifiConfigFailed(e: Exception) = reportFailure("Could not send Wi-Fi details. Try again near the device.")
        override fun wifiConfigApplied() {}
        override fun wifiConfigApplyFailed(e: Exception) = reportFailure("The device could not apply these Wi-Fi details.")
        override fun provisioningFailedFromDevice(reason: ESPConstants.ProvisionFailureReason) = reportFailure(when (reason) {
            ESPConstants.ProvisionFailureReason.AUTH_FAILED -> "The Wi-Fi password was rejected. Check it and try again."
            ESPConstants.ProvisionFailureReason.NETWORK_NOT_FOUND -> "The Wi-Fi network was not found. Use a nearby 2.4 GHz network."
            else -> "The device could not connect to Wi-Fi. Check the network and try again."
        })
        override fun deviceProvisioningSuccess() { handler.post {
            if (generation != expectedGeneration || phase != "provision") return@post
            val result = pending
            pending = null
            cleanup()
            result?.success(null)
        } }
        override fun onProvisioningFailed(e: Exception) = reportFailure("Wi-Fi setup failed. Check the details and try again.")
    }

    private fun fail(code: String, message: String) { val result = pending; pending = null; cleanup(); result?.error(code, message, null) }
    private fun cleanup() {
        phase = "idle"; ++generation
        permissionCall = null; ssid = ""; password = ""
        handler.removeCallbacks(scanTimeout); handler.removeCallbacks(operationTimeout)
        try { manager.stopBleScan() } catch (_: Exception) {}
        device?.setProofOfPossession("")
        try { device?.disconnectDevice() } catch (_: Exception) {}
        device = null
    }
    private fun cancel() { fail("CANCELLED", "Device setup cancelled.") }
    fun close() {
        cancel()
        channel.setMethodCallHandler(null)
        if (EventBus.getDefault().isRegistered(this)) EventBus.getDefault().unregister(this)
    }

    companion object {
        private const val PERMISSION_REQUEST = 4721
        private const val SERVICE_UUID = "021a9004-0382-4aea-bff4-6b3f1c5adfb4"
    }
}
