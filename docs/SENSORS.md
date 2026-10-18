# AQUASENSE+ sensor implementation status

Firmware 0.7.0 implements the six-module architecture while keeping every
unverified physical input disabled. Enable a module only after checking its
actual wiring, supply, and divider voltage.

| Module | Library / method | Data | Invalid or uncalibrated behavior |
| --- | --- | --- | --- |
| HC-SR04 | ESP32 pulse acquisition | Duration, median distance, test fill | Distance invalid, fill unknown, no telemetry |
| DS18B20 | OneWire + DallasTemperature | Wastewater temperature | Null/N/A; never humidity |
| SEN0189 | Latest + averaged ADC | Raw ADC and diagnostic voltage | NTU stays null until physical calibration |
| YF-S201 | GPIO interrupt counter | Pulses and frequency | L/min stays null until known-volume calibration |
| ENS160 | DFRobot_ENS160 | AQI, TVOC, eCO2 | Independent warm-up/error state |
| AHT2x companion | Adafruit_AHTX0 | Ambient temperature and humidity | Independent from ENS160 and DS18B20 |
| MQ-135 | Latest + averaged ADC | Raw ADC after warm-up | Gas ppm/value stays null until defensible calibration |

The code follows the 4A-4E initialization order and gives each subsystem an
independent `DISABLED`, `UNAVAILABLE`, `WARMING_UP`, `NOT_CALIBRATED`, `VALID`,
or `ERROR` state. One failure does not block the other sensors, Wi-Fi, or main
loop.

`config.h` leaves the five new modules disabled until physical checks are
possible. ENS160 and AHT2x addresses remain `0x00`. Run `I2CScanner` and require
three stable scans before entering actual addresses. The sensor-absent COM6
test produced seven stable zero-device scans; this verifies the scanner but is
not an address result for the missing board.

Phase 6 calibration software gates are implemented, but every physical
calibration remains pending. See `CALIBRATION.md`; no provisional factor or raw
ADC value is published as a calibrated engineering unit.

Phase 8 found no physical sensor module available for final installation. None
of the planned Chamber 1, Chamber 3, inlet-pipe, or wastewater-probe placements
is marked installed. Final mounting, moisture protection, calibration
revalidation, and real telemetry remain blocked. See `FINAL_ASSEMBLY.md` and
`../esp32/PHASE8-TEST-REPORT.md`.
