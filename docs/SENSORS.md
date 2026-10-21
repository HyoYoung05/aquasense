# AQUASENSE+ sensor implementation status

Firmware 0.7.1 implements the six-module architecture. Committed defaults keep
every physical input disabled; an ignored `config.local.h` enables only the
paths checked on the connected prototype.

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

`config.h` provides safe defaults and loads ignored `config.local.h` when
present. On 2026-10-22, repeated COM7 scans found AHT2x at `0x38` but no ENS160
at `0x52` or `0x53`. AHT2x was enabled locally; ENS160 remains disabled.

Phase 6 calibration software gates are implemented, but every physical
calibration remains pending. See `CALIBRATION.md`; no provisional factor or raw
ADC value is published as a calibrated engineering unit.

A post-Phase-8 COM7 follow-up verified real telemetry for HC-SR04, DS18B20,
SEN0189 raw ADC, AHT2x, and valid MQ-135 raw samples. YF-S201 is still gated,
ENS160 is absent, and several signals are intermittent. Final mounting,
moisture protection, calibration, and sustained stability remain blocked. See
`FINAL_ASSEMBLY.md`, `../esp32/PHASE8-TEST-REPORT.md`, and the
[connected-sensor report](../esp32/PHYSICAL-VERIFICATION-2026-10-22.md).
