# AQUASENSE+ ESP32 ultrasonic bench test

Version **0.1.1** — Arduino IDE, classic ESP32 DevKit, one trigger/echo ultrasonic sensor.
This is a temporary experiment. Retain the project and private settings until the user explicitly requests removal.
No other sensors, battery system, pumps, valves, buzzer, or grease-trap automation are implemented.

## Setup after cloning this repository

This `esp32/` folder contains shareable test source; the PHP backend is in the
repository root. The original local testing workspace is `aquasense-esp32` alongside
`aquasense-web`. Sections describing trap 12, local configuration, or APK paths below
record that original computer's setup, not accounts or files supplied by a clone.

1. Set up the PHP application and existing database using the root README and apply
   both database migrations. This telemetry endpoint currently requires development mode.
2. Prepare the demo owner using the backend development setup instructions.
3. From the repository root, provision the test device using the command below.
   Use the returned trap ID and device key; do not assume trap 12 on another database.
4. Copy `UltrasonicTest/config.example.h` to `UltrasonicTest/config.local.h` and set
   your Wi-Fi, reachable API URL, device key, and returned trap ID. Set matching calibration.
5. Open `UltrasonicTest/UltrasonicTest.ino` in Arduino IDE and select ESP32 Dev Module.
6. For VS Code, open this `esp32` folder and follow the IntelliSense section to generate
   your own local compiler database. The clone does not include the original parent workspace settings.

The local header, device metadata, generated compiler database, and firmware binaries
are deliberately excluded. A clone does not inherit the original computer's credentials.

## Hardware assumption and wiring

The user identified an **HC-SR04** and a 38-pin ESP32 board, with GPIO5 and GPIO18.
This configuration interprets that order as **TRIG = GPIO5, ECHO = GPIO18**.
Match the physical wiring to those assignments. The board's exact model/pinout still needs checking;
the pin count alone does not identify its supply pins.
Use a USB cable to power the ESP32. Keep the sensor/electronics dry and above a stable test surface.

| HC-SR04 | ESP32 DevKit |
| --- | --- |
| VCC | Verified board 5V supply pin (USB-powered board; check board pinout) |
| GND | GND |
| TRIG | GPIO5 |
| ECHO | GPIO18 through the divider below |

Never connect the HC-SR04's 5V Echo directly to an ESP32 GPIO.

```text
HC-SR04 ECHO --- 1 kOhm ---+--- GPIO18
                          |
                        2 kOhm
                          |
                         GND
```

The nominal GPIO voltage is 5 x 2/(1+2) = 3.33V. Use a common ground.
Wire with power disconnected. GPIO numbers refer to the board's IO5/IO18 labels, not header position numbers.
Do not add a second external power supply while the board is USB powered.

## Troubleshooting: Raw echo 0 us / INVALID

`pulseIn(ECHO_PIN, HIGH, 30000UL)` returning zero means no complete echo pulse
was measured within the 30ms timeout. The printed `0.00 cm` is not a measured
zero distance. Five invalid attempts produce UNKNOWN and skip telemetry;
this prevents an unavailable sensor from reporting a falsely empty or full trap.
This log occurs during sensor measurement, before a telemetry POST is attempted.

1. Confirm the actual sensor model and board model. This wiring and timing assume
   an HC-SR04 and classic ESP32 DevKit; other modules can have different interfaces.
2. Disconnect USB power before changing wiring. The current private and example
   configurations both use TRIG GPIO5 and ECHO GPIO18. Read the board's GPIO labels,
   not physical header positions, and check for swapped TRIG/ECHO wires.
3. For an HC-SR04, use its required 5V supply from the verified USB-powered board
   supply pin and connect sensor GND, ESP32 GND, and divider GND together.
   Do not connect its 5V Echo directly to GPIO18; use the divider shown above.
4. Check jumper continuity and breadboard rows, including any split power rails.
   If a meter is available, measure sensor VCC relative to sensor GND; it should
   be approximately 5V for an HC-SR04. Avoid bridging adjacent pins with probes.
5. Aim the dry sensor straight at a large, flat, hard surface about 20cm away.
   Start with a solid target before testing liquid. At 20cm, expect approximately
   1,166us of echo using the sketch's formula; small variations are normal.
6. Reconnect power and watch Serial Monitor at 115200 baud. If every reading stays
   zero, record the module label and actual connections before changing firmware.
   A stuck Echo signal, missing power/ground, incompatible module mode, bad cable,
   damaged sensor, or damaged GPIO can all require further hardware checks.

The existing 10us trigger and 65ms spacing follow the linked HC-SR04 datasheet.
Changing API settings or fill thresholds cannot restore a missing echo.
If pins are intentionally changed in `config.local.h`, recompile and upload the sketch.
Version 0.1.1 corrects the previous GPIO25/GPIO26 configuration for the reported
GPIO5/GPIO18 wiring. Upload the updated sketch, then look for `v0.1.1` and
`Ultrasonic pins: TRIG=GPIO5, ECHO=GPIO18` at startup to verify the board is running it.
The configured 0.1.1 sketch compiled successfully for `esp32:esp32:esp32` with
Espressif core 3.3.12 (1,027,984 bytes flash; 48,824 bytes static RAM).

## Calculation and test thresholds

```text
distance_cm = echo_duration_us x 0.0343 / 2
fill_percent = clamp(100 x (empty_distance_cm - distance_cm)
                         / (empty_distance_cm - full_distance_cm), 0, 100)
```

Distances are measured vertically from the sensor face. Empty and full references are calibration values for your actual test container.
Example defaults: empty = 30cm, full = 5cm; a 12.4cm reading gives 70.4%.
Full means the chosen maximum liquid level with sensor clearance; do not let liquid reach the module.
This estimates fill HEIGHT. It estimates volume only for a constant-cross-section container.
It cannot distinguish oil, water, or foam, and it is not a safety-certified overflow alarm.

Default test classification:

| Percentage | Status |
| --- | --- |
| Below 75% | NORMAL |
| 75% to below 90% | WARNING |
| 90% and above | CRITICAL |

Both alert thresholds are below 100%. Serial output and stored status are the test warning mechanism.
No physical alarm or automatic shutoff is included.
The server recalculates the percentage and status instead of trusting client fields.

## Current local setup

- Device code: AQS-001.
- Dedicated temporary trap ID: **12**, named Ultrasonic bench test (temporary), under the existing Demo Kusina owner.
- Existing demo trap/device and their historical readings are unchanged.
- Local URL is in the ignored config.local.h; the sketch does not repeat an IP address.
- Test-specific server configuration is in device_ultrasonic_test_config.
- The endpoint is intentionally disabled outside development mode.
- Temperature and other absent sensors remain NULL. Hardware samples have is_test=1 and is_simulated=0.
- Software regression fixtures are deleted after tests. No real physical reading has been claimed or inserted for AQS-001.

The ESP32 must be on a network that can reach the configured Apache address. For this local test, use the API computer's LAN.
The phone may use the existing Tailscale test connection. Installing Tailscale on the phone does not route an ESP32 on a different network to the API.
Production will need a reachable HTTPS API and a reviewed production ingestion path; this experimental endpoint stays development-only.

## Open, configure, and upload

1. In Arduino IDE, install **esp32 by Espressif Systems** in Boards Manager if needed.
2. Open UltrasonicTest/UltrasonicTest.ino; keep the matching folder name.
3. Select **ESP32 Dev Module** for a classic ESP32 DevKit and select its USB serial port.
4. Open UltrasonicTest/config.local.h. On this computer it contains both supplied test Wi-Fi networks, the local endpoint, device key, and trap 12.
   The nonsecret config.example.h is the template for another checkout.
5. Verify EMPTY_DISTANCE_CM, FULL_DISTANCE_CM, WARNING_PERCENT and CRITICAL_PERCENT against your physical test container and saved server calibration.
6. Click Verify, then Upload. Open Serial Monitor at **115200 baud**.
7. Move a flat target or change clean-water level gradually. Keep the sensor face dry.
8. Watch raw echo duration/distance, filtered distance, percentage, status, HTTP code, and the JSON response.
9. HTTP 201 with success=true confirms the backend stored a reading. Refresh the Flutter dashboard to retrieve it.

The sketch takes five samples with at least 65ms spacing, requires three valid samples, and uses the median.
Each echo wait is bounded to 30ms. No echo or distances outside 2–400cm are invalid; no fabricated zero-fill value is sent.
The test cycle waits five seconds after a sample/upload attempt, so slow networking lengthens the interval.
Wi-Fi attempts alternate configured networks every 15 seconds while disconnected. No password or device key is printed.
HTTP connect/read timeouts are five seconds; failures are printed and the next cycle uses a fresh reading.
There is no reboot loop and no persistent offline queue. Data collected while offline is not backfilled as fresh telemetry.

## Endpoint contract

```text
POST /AQUASENSE+/aquasense-web/api/device/telemetry.php
Content-Type: application/json
Authorization: Bearer <64-hex private device key>
```

```json
{
  "device_id": "AQS-001",
  "grease_trap_id": 12,
  "ultrasonic_distance": 12.4,
  "waste_level_percent": 70.4,
  "status": "NORMAL"
}
```

The key is separate from owner login tokens and database passwords. PHP stores only its hash.
Only the active assigned device can write to the active trap/establishment.
The body is limited to 2048 bytes and exactly the five documented fields.
The server rejects nonnumeric/out-of-range values, mismatched calibration/status, and samples less than two seconds apart.
Timestamps come from the server in UTC.

| HTTP | Meaning |
| --- | --- |
| 201 | Reading stored |
| 400 / 415 / 422 | JSON/type/range or calibration problem |
| 401 / 403 | Device key, active status, or trap assignment problem |
| 404 | Test endpoint disabled outside development |
| 405 | POST required |
| 413 | Body too large |
| 429 | Device is posting too frequently |
| 503 | Server/database unavailable; sketch keeps running |

HTTP errors never trigger ESP.restart(). Do not use setInsecure() for HTTPS.
For an HTTPS test server, configure TLS_ROOT_CA and allow clock synchronization; certificate validation is retained.

## Existing backend setup on another development checkout

Back up the existing database. Apply migrations in order after the original schema:

- 001-mobile-tokens.sql, if not already applied.
- 002-ultrasonic-test.sql, once only.

Migration 002 makes temperature nullable, adds the test flag and WARNING enum value, and creates the calibration table in the SAME database.
It does not delete or overwrite readings. Fresh installs also need both migrations.
Prepare the existing demo owner using the documented mobile development helper if absent.
Then run from the repository root:

```powershell
php database/ultrasonic-test-setup.php --allow-test-fixture --output=esp32/device.local.json
```

This creates a dedicated trap and AQS-001 and writes its private key/calibration metadata.
It refuses to overwrite an existing device or credential file. Do not run it again on this configured computer.
Copy the resulting device code, trap ID, and device_api_key into your ignored config.local.h.
Use config.example.h for the remaining settings. Never put a real key in the example file.

## Changing calibration

Change the constants in config.local.h and update the matching database row before uploading.
For example, to retain the documented defaults, run on the configured development database:

```sql
UPDATE device_ultrasonic_test_config c
JOIN devices d ON d.id = c.device_id
SET c.empty_distance_cm = 30, c.full_distance_cm = 5,
    c.warning_percent = 75, c.critical_percent = 90
WHERE d.device_code = 'AQS-001';
```

The firmware and server must use the same calibration. Full distance must be at least 2cm, empty must exceed full and be at most 400cm,
and 0 < warning < critical < 100. A calibration mismatch returns HTTP 422.
The private device.local.json contains the initial provisioning values, not a live synchronization mechanism.

## Verify stored data

```sql
SELECT r.id, d.device_code, a.grease_trap_id, r.ultrasonic_distance_cm,
       r.waste_level_percent, r.level_status, r.temperature_c,
       r.is_test, r.is_simulated, r.recorded_at
FROM sensor_readings r
JOIN device_assignments a ON a.id = r.device_assignment_id
JOIN devices d ON d.id = a.device_id
WHERE d.device_code = 'AQS-001'
ORDER BY r.id DESC LIMIT 20;
```

The Flutter owner API already returns these readings. The client needs the updated nullable-temperature model (0.1.6+7);
an older installed APK should be rebuilt before testing. Refresh retrieves the latest snapshot; there is no new automatic polling.
The administrative website can use the shared PHP reader later; no administrative monitoring UI is added in this experiment.

## VS Code IntelliSense (missing WiFi.h / HTTPClient.h / WiFiClientSecure.h)

These headers come with the Espressif Arduino ESP32 board package. Do not install
an unrelated WiFi library to fix editor error 1696. Arduino IDE compilation and
VS Code IntelliSense have separate configuration.

The `.vscode` configuration associates `.ino` files with C++ and reads a local
compilation database mapped to the original `UltrasonicTest.ino`. It uses the
installed compiler, board definitions, Arduino core, networking libraries, and
ESP-IDF headers from the successful Arduino build. It also includes `Arduino.h`,
which Arduino normally adds to its generated C++ source. No diagnostics are disabled.

Open this `esp32/` folder in VS Code to load the supplied configuration.
The original local workspace also has parent-folder settings; those are not part of this clone.
After this change, run **Developer: Reload Window** from the Command Palette.
If old errors remain, run **C/C++: Reset IntelliSense Database**, then reopen the sketch.
Arduino IDE **Verify** remains the authoritative ESP32 build check.

After moving the project, changing the board package, or adding a library, compile
the sketch and regenerate the editor database. From `esp32`, with Python 3 installed:

```powershell
$arduinoCli = Join-Path $env:ProgramFiles 'Arduino IDE\resources\app\lib\backend\resources\arduino-cli.exe'
$esp32Build = Join-Path $env:TEMP 'aquasense-esp32-build'
& $arduinoCli compile --fqbn esp32:esp32:esp32 --build-path $esp32Build .\UltrasonicTest
if ($LASTEXITCODE -eq 0) { python .\tools\refresh_intellisense.py $esp32Build }
```

The generated `.vscode/compile_commands.local.json` contains this computer's paths
and is ignored by Git. It embeds build flags rather than depending on temporary
Arduino response files, and it does not copy Wi-Fi passwords or device keys.
Python is only needed to refresh editor settings; Arduino IDE can still build and upload normally.
See [Microsoft's IntelliSense configuration documentation](https://code.visualstudio.com/docs/cpp/configure-intellisense).

## Temporary files and cleanup

- Keep this whole `esp32/` folder until removal is explicitly requested.
- Private files: device.local.json and UltrasonicTest/config.local.h.
- Generated firmware belongs outside this web-served folder; compiled firmware can contain test Wi-Fi passwords and the device key.
- .gitignore excludes private configuration; .htaccess denies HTTP access to the entire firmware directory.
- Device AQS-001, its calibration, test trap 12, its assignment, and future readings are database records, not files.
  Review and remove only those test records when cleanup is requested. Retain unrelated data and backups.
- Previously created mobile temporary folders are unchanged by this test.
- Editor support is also temporary: `.vscode/settings.json`, `.vscode/c_cpp_properties.json`,
  `.vscode/compile_commands.local.json`, and `tools/refresh_intellisense.py` inside this folder.
  In the parent folder, remove the ESP32 `compileCommands` configuration from
  `.vscode/c_cpp_properties.json` and the `*.ino` association from `.vscode/settings.json`
  when retiring this test. Preserve the Flutter CMake setting and any later unrelated settings.

## References

- [HC-SR04 datasheet](https://cdn.sparkfun.com/datasheets/Sensors/Proximity/HCSR04.pdf)
- [ESP32 DevKitC board reference](https://docs.espressif.com/projects/esp-dev-kits/en/latest/esp32/esp32-devkitc/user_guide.html)
- [Arduino ESP32 Wi-Fi API](https://docs.espressif.com/projects/arduino-esp32/en/latest/api/wifi.html)
## Software validation (2026-09-21)

- Sketch compiled with both the example configuration and the generated private local configuration for `esp32:esp32:esp32`, using Espressif Arduino core 3.3.12. The configured build uses 1,027,932 bytes of flash (78%) and 48,824 bytes of static RAM (14%).
- PHP device integration passed 51 checks; all existing PHP suites and session expiry passed.
- Flutter analysis and all 28 tests passed.
- Updated optional phone APK: `../aquasense_mobile/temporary-network-testing/aquasense-owner-ultrasonic-v0.1.6+7.apk` (uses existing Tailscale test API configuration).
- AQS-001 has no readings until actual hardware sends them; test-fixture data was removed.
- During the initial software validation, COM3 was unidentified and no upload was performed by Codex.
  The user subsequently reported serial readings and identified an HC-SR04 on a 38-pin ESP32.
  Version 0.1.1 must be uploaded to apply the GPIO5/GPIO18 correction; a valid physical reading remains unverified.
