# Owner device Wi-Fi setup

Owner app 0.9.34+54 adds Settings > Device Wi-Fi. Firmware 0.8.18 adds
Espressif BLE provisioning with Security 1 and a unique device setup code.
Wi-Fi names/passwords go directly from the Android app to the ESP32 over the
encrypted provisioning session. They do not enter the AQUASENSE API, account
database, analytics, or phone preferences. Release builds strip Android logs
because the provisioning SDK includes debug logging of setup information.

## One-time installation

The ESP32 needs a USB firmware upload once. Existing firmware cannot receive
Wi-Fi details from the app until it has this provisioning support.

Build the active sketch using ESP32 Dev Module with the Huge APP partition:

In Arduino IDE, select these settings under **Tools** before Verify or Upload:

- **Board:** ESP32 Dev Module
- **Flash Size:** 4MB (32Mb)
- **Partition Scheme:** Huge APP (3MB No OTA/1MB SPIFFS)
- **Erase All Flash Before Sketch Upload:** Disabled

The default 1,310,720-byte application partition is too small for this firmware
(about 1.91 MB with BLE Wi-Fi setup). Huge APP provides 3,145,728 bytes. The
erase setting must remain disabled to retain saved Wi-Fi and the setup code.

```
arduino-cli compile --fqbn esp32:esp32:esp32:PartitionScheme=huge_app,EraseFlash=none AquaSenseSensors
arduino-cli upload --fqbn esp32:esp32:esp32:PartitionScheme=huge_app,EraseFlash=none --port COM7 AquaSenseSensors
```

Use the actual device port. Keep the ignored device credentials, API URL,
sensor calibration and hardware profile for that specific unit. Do not use
Erase All Flash: that removes the saved network and the device setup code.
Use normal component uploads rather than writing a merged full-flash image
over the NVS area. Provisioning increases firmware size, requiring the 3 MB
app partition on this 4 MB ESP32; this profile does not provide OTA updates.

After the initial upload, start setup with the BOOT button as described below
or send `WIFI_SETUP` followed by a newline through USB Serial at 115200 baud.
The installer reads `[SETUP] Device setup code:` from the serial output and
supplies that 16-character code on a private label with the device. It is
random per device, stored in NVS, and reused for later owner Wi-Fi changes.
Do not publish it or substitute the device API key as the setup code.

## Changing Wi-Fi through the app

1. Use the updated Android app and turn on Bluetooth.
2. With the ESP32 running, hold BOOT for three seconds, then release it.
   Holding BOOT during power-on instead enters the ESP32 bootloader.
3. Open Settings > Device Wi-Fi and tap Find my device. Allow Nearby devices
   permission; Android 11 and earlier use Location permission for BLE scanning.
4. Select the AQUASENSE device. Enter its setup code, the exact Wi-Fi name,
   and the network password. Use a 2.4 GHz personal Wi-Fi network. Spaces in
   names/passwords are preserved. Choose the explicit no-password option only
   for an open network. Captive portals and enterprise login are unsupported.
5. Tap Connect device to Wi-Fi. Success is shown after the ESP32 reports a
   Wi-Fi connection, not merely after the phone sends the password.
6. The ESP32 stores the connected network and restarts after ten seconds.
   It prioritizes the saved owner network after restart, then retains the
   existing compiled fallback networks. Return to Monitoring for live readings.

Setup pauses sensor processing/telemetry while it controls the Wi-Fi driver.
The BLE setup window lasts five minutes. A failed attempt or an expired
window does not replace the previous saved owner network. No cloud access
is required to transfer credentials. The owner still needs internet access
for production monitoring after provisioning.

## Verification and limitations

Automated app tests exercise input byte limits, exact credential forwarding,
discovery, confirmation-before-success, password clearing, cancellation,
unsupported platforms, and Settings regressions. Native Android compilation
and the ESP32 firmware build verify platform APIs. Firmware 0.8.19 was uploaded
to COM7 on 2026-11-09 and all flash component hashes verified. Serial output
confirmed wastewater temperature, turbidity raw ADC, flow and MQ-135 raw ADC;
the failed ENS160+AHT2x module has been removed from firmware and app.

Production heartbeats returned HTTP 200. Telemetry returned HTTP 422
(`ultrasonic_distance must be a JSON number`) while HC-SR04 reported NO_ECHO.
The production partial-telemetry update remains necessary to accept independent
readings without distance. Real BLE pairing, correct and incorrect Wi-Fi
passwords, power-cycle persistence, setup timeout and wrong-code rejection
still require verification with an Android phone.

This app flow currently supports Android; browser and desktop builds show an
explicit unsupported-platform message. Credentials are stored in the ESP32's
NVS; physical flash protection requires a separate device encryption setup.

Protocol reference: https://github.com/espressif/esp-idf-provisioning-android
