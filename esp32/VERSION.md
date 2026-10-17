# AQUASENSE+ ESP32 firmware version history

## Current version

0.7.0

## 0.7.0 - Hardware Phase 7 reliability and recovery - 2026-10-17

- Added an eight-record, fixed-allocation, RAM-only FIFO with a drop-oldest
  overflow policy, high-water/drop counters, and an on-device algorithm test.
- Kept each `reading_uuid` unchanged across retries and reused PHP's existing
  UUID/payload-hash duplicate protection.
- Separated capture from upload and classified network, timeout, 4xx, 5xx,
  429, success, and local configuration outcomes.
- Added state-based Wi-Fi connection/backoff diagnostics, sensor/ultrasonic
  counters, controlled I2C recovery, and analog rail-fault validation.
- Preserved Phase 6 calibration and server-authoritative alert behavior.
- Documented the roughly two-minute queue window, drop conditions, and total
  loss of unsent RAM records on reset/power failure.
- Physical outage, sensor-fault, latency, load, final-power, and extended-run
  verification remains PARTIAL until the full hardware is available.

## 0.6.0 - Hardware Phase 6 calibration gates - 2026-10-17

- Centralized all firmware calibration state in `calibration.h`.
- Separated raw SEN0189 ADC and YF-S201 pulse evidence from calibrated NTU and
  L/min, and retained MQ-135 as raw-only.
- Documented backend-authoritative level thresholds and the configured 40 °C
  Emulsion Warning.
- Added disabled-by-default calibrated turbidity and flow alert gates.
- Compiled at 973,988 bytes flash and 50,208 bytes RAM, uploaded only to COM6,
  and verified sensor-absent behavior without fake telemetry.
- Backend/API/alert/fresh-install/hardening regression suites passed.
- Physical calibration remains PARTIAL until sensors and references exist.

## 0.5.0 - Hardware Phase 5 power diagnostics - 2026-10-17

- Aligned the documented primary architecture to an enclosed regulated
  AC-to-DC adapter feeding low-voltage 5 V and 3.3 V rails with common ground.
- Added honest startup/runtime power diagnostics: declared power mode, reset
  warning, minimum heap, Wi-Fi transition counts, and telemetry counts.
- Explicitly reports that rail voltage is not monitored by firmware and must
  be measured with a multimeter.
- Did not introduce MT3608, TP4056, battery wiring, microSD, OLED, or buzzer.
- Physical Phase 5 remains PARTIAL until the actual supply and sensors permit
  progressive-load, power-cycle, interruption, and long-duration tests.

## 0.4.0 - Revised Hardware Phase 4 software integration - 2026-10-16

- Added independently scheduled DS18B20, SEN0189, YF-S201, ENS160+AHT2x, and
  MQ-135 drivers using the authoritative revised GPIO map.
- Added independent sensor validity states, raw/calibrated boundaries, minimal
  flow ISR, I2C scan/stability gates, AHT2x compensation, and MQ-135 warm-up.
- Extended retry-stable telemetry without fabricating missing readings or
  labeling uncalibrated ADC data as NTU or ppm.
- Compiled at 972,644 bytes flash and 50,160 bytes RAM and uploaded only to
  COM6. The sensor-absent runtime stayed online without reboot or fake writes.
- Physical Phase 4 remains PARTIAL until all modules and voltage measurements
  are available.

## 0.3.1 - Revised Hardware Phase 3 alignment - 2026-10-16

- Aligned the completion record with the revised paper's local-router and local
  PHP/MariaDB acceptance architecture; public hosting and Tailscale are not
  Phase 3 requirements.
- Classified malformed-payload HTTP 400 with other paced configuration errors
  instead of transient network/server retry behavior.
- Rebuilt public and ignored private firmware, restored it only to COM6 with
  verified hashes, connected to local Wi-Fi, and observed stable uptime with
  sensor-absent readings explicitly skipped.
- Local health returned HTTP 200; 32 backend and 50 mobile checks passed. Real
  sensor-to-database/UI proof remains deferred, so physical Phase 3 status is
  PARTIAL.

## 0.3.0 - Revised Hardware Phase 3 - 2026-10-15

- Preserved Phase 2 HC-SR04 acquisition and added the existing PHP telemetry
  contract without adding a duplicate endpoint.
- Added ignored Wi-Fi/API/device secrets, a committed placeholder, validation,
  AQS-001 and grease trap 12 identity, `X-Device-Key`, and retry-stable UUIDv4.
- Added bounded Wi-Fi connection attempts, alternate-network reconnection,
  five-second HTTP timeout, 5/10/30-second retry backoff, HTTP 429 handling, and
  configuration-error pacing without reboot.
- Sends only `device_id`, `grease_trap_id`, `ultrasonic_distance`, and
  `reading_uuid`; PHP remains authoritative for fill/status and MySQL writes.
- Invalid ultrasonic readings cannot create or send telemetry.
- Public all-warnings build passed at 942,088 bytes flash and 48,440 bytes RAM;
  ignored private build passed at 942,648 bytes flash and 48,440 bytes RAM.
- Uploaded the private build only to COM6 with verified hashes. Wi-Fi connected,
  a forced disconnect exercised the secondary timeout, and the primary network
  recovered without reboot.
- The AQS-001 database baseline remained unchanged during the sensor-absent run:
  335 readings, latest ID 409, and the previous `last_seen_at`.
- All 32 device telemetry checks and all 50 mobile API checks passed.
- Physical AQS-001 insertion and website/Flutter physical refresh remain
  deferred until the HC-SR04 is available.

## 0.2.0 - Revised Hardware Phase 2 - 2026-10-15

- Added HC-SR04 TRIG GPIO5 and divided ECHO GPIO18 acquisition using the
  revised-paper pin map.
- Added five raw Echo samples, a 30 ms pulse timeout, 2-400 cm validation, a
  minimum of three valid samples, median filtering, and raw diagnostics.
- Added temporary 30 cm empty / 5 cm full development calculation with 0-100%
  clamping. Final grease-trap calibration remains Phase 6 work.
- Enforced invalid results as `Distance: INVALID`, `Fill: UNKNOWN`, and
  `Sensor: ERROR`; no failed Echo becomes a zero/full/safe value.
- Added an on-device algorithm self-test for median selection, temporary
  10/20/30 cm calculation references, and invalid-input propagation.
- Warning-free build uses 275,372 bytes flash and 22,124 bytes static RAM.
- Uploaded only to COM6 at 115200 with verified hashes. Reset passed, and a
  sensor-absent 45-second observation produced 45 controlled `NO_ECHO` cycles,
  no unsafe values, no unexpected reboot, and stable 331,096-byte free heap.
- Physical distance and electrical tests remain deferred until the sensor is
  available. No Wi-Fi, PHP, MySQL, battery, or other sensor work was added.

## 0.1.0 - Revised Hardware Phase 1 - 2026-10-15

- Established the USB-only ESP32 DevKit V1 diagnostic foundation.
- Froze the revised paper's six-sensor GPIO plan and documented the DHT22 label
  discrepancy while reserving GPIO4 for DS18B20.
- Verified the ESP32 Dev Module profile, COM6 upload, Serial at 115200, EN reset,
  port reopening, and stable short-duration uptime.
