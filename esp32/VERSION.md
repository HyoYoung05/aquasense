# AQUASENSE+ ESP32 test version history

## Current version

0.1.1

## 0.1.1 - 2026-09-21

- User identified the sensor as HC-SR04 on a 38-pin ESP32 using GPIO5 and GPIO18.
- Changed both local and example settings from TRIG GPIO25 / ECHO GPIO26 to
  TRIG GPIO5 / ECHO GPIO18, interpreting the supplied pins in TRIG/ECHO order.
- Added configured pin numbers to Serial startup output and bumped the firmware banner.
- Updated wiring, divider diagram, and troubleshooting instructions in README.md.
- Arduino CLI build passed for `esp32:esp32:esp32` (Espressif core 3.3.12):
  1,027,984 bytes flash (78%) and 48,824 bytes static RAM (14%).
- Upload is required to apply these changes. A valid hardware echo remains unverified.
- Prepared a credential-free GitHub source copy under the existing PHP repository's
  `esp32/` folder, with clone setup instructions and the IntelliSense refresh tool.

## Sensor troubleshooting documentation - 2026-09-21

- Documented repeated zero-duration echo timeouts, configured GPIO25/GPIO26 wiring,
  HC-SR04 power and voltage-divider checks, and a solid-target bench test.
- Confirmed the existing sketch uses a 10us trigger, 30ms echo timeout, and 65ms spacing.
- Clarified that UNKNOWN skips telemetry and that a printed zero is not a valid distance.
- Firmware remains 0.1.0. Actual sensor model and wiring still need confirmation;
  no hardware repair or successful physical reading is claimed.

## Editor configuration update — 2026-09-21

- Added VS Code C++ configuration for both the parent workspace and standalone ESP32 folder.
- Associated Arduino sketches with C++ and mapped the original `.ino` to the installed Espressif compiler and headers from the successful build.
- Added `tools/refresh_intellisense.py` to regenerate the ignored local compilation database after toolchain, library, or path changes; private configuration values are not copied.
- Verified all three reported headers resolve and the original sketch passes an ESP32 compiler syntax check with the generated settings. All editor JSON files parse successfully.
- Documented editor reload, regeneration, and temporary file cleanup in README.md.
- Firmware behavior and version remain 0.1.0; this change configures the editor only.

## 0.1.0 — 2026-09-21

- Added an Arduino IDE sketch for a classic ESP32 DevKit and one HC-SR04-style ultrasonic sensor.
- Centralized network, endpoint, device identity, private key, pins, calibration, thresholds, and interval in a private configuration header.
- Added bounded echo measurement, median filtering, raw Serial output, percentage conversion, and Normal/Warning/Critical statuses.
- Added JSON POST, bounded HTTP/TLS timeouts, network reconnection, and continued operation after failures.
- Paired the test with backend 0.3.0 and nullable-temperature support in Flutter 0.1.6+7.
- No additional sensors, battery system, or actuators.
- Actual sensor model, wiring, calibration, flashing, and physical end-to-end operation remain to be verified.
- Temporary project and credentials remain until the user explicitly requests removal.
## Software validation (2026-09-21)

- Sketch compiled with both the example configuration and the generated private local configuration for `esp32:esp32:esp32`, using Espressif Arduino core 3.3.12. The configured build uses 1,027,932 bytes of flash (78%) and 48,824 bytes of static RAM (14%).
- PHP device integration passed 51 checks; all existing PHP suites and session expiry passed.
- Flutter analysis and all 28 tests passed.
- Updated optional phone APK: `../aquasense_mobile/temporary-network-testing/aquasense-owner-ultrasonic-v0.1.6+7.apk` (uses existing Tailscale test API configuration).
- AQS-001 has no readings until actual hardware sends them; test-fixture data was removed.
- COM3 is an unidentified USB serial port. No sketch has been uploaded, and the sensor model is still unconfirmed.
