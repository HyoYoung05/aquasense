# AQUASENSE+ connected-sensor verification

Date: 2026-10-22 (Asia/Manila)  
Authorized port: COM7  
Firmware: 0.7.1  
Overall result: **PARTIAL**

This is a post-Phase-8 physical follow-up. It does not create another hardware
phase or replace the calibration and final-assembly acceptance requirements.

## Build and upload

- COM7 was detected as the ESP32 DevKit V1 CH340 interface.
- The controlled firmware compiled for `esp32:esp32:esp32` at 989,088 bytes of
  flash and 51,200 bytes of static RAM.
- Upload to COM7 completed and every written flash region passed hash
  verification.
- A private, ignored `config.local.h` enabled only the physically observed
  paths. Wi-Fi, API URL, and the device credential remain in ignored
  `secrets.h`.
- The public configuration defaults remain safe and disabled.

## Sensor evidence

| Sensor path | Observed result | Decision |
| --- | --- | --- |
| HC-SR04, GPIO5/GPIO18 | Produced real median distances and valid packets, but the captured run also counted 14 no-echo timeouts and large changes between about 8.5 cm and 71.2 cm | **PARTIAL**; repeat fixed-target 10/20/30 cm tests and inspect mounting, target, divider, and wiring |
| DS18B20, GPIO4 | Valid readings around 33.2-34.6 C after GPIO4's internal pull-up was added as a development aid | **PASS for communication**; external schematic pull-up and reference-temperature calibration are still required |
| SEN0189, GPIO34 | Raw ADC reached PHP/MySQL; one rail-fault interval was rejected and later recovered | **PARTIAL**; divider must be checked with a meter and NTU calibration is incomplete |
| YF-S201, GPIO19 | Kept disabled. The earlier unrelated diagnostic read a floating GPIO27 and reported impossible 581-628 L/min values | **NOT VERIFIED**; confirm the protected signal is on official GPIO19 before enabling; known-volume calibration remains required |
| AHT2x, GPIO21/GPIO22 | Three repeated scans identified address `0x38`; ambient temperature and humidity reached PHP/MySQL | **PARTIAL**; intermittent I2C errors recovered automatically but require wiring/power correction |
| ENS160, GPIO21/GPIO22 | Repeated scans found no device at `0x52` or `0x53` | **FAIL / NOT DETECTED**; inspect the exact module, supply, SDA/SCL, ground, and address selection |
| MQ-135, GPIO35 | Three-minute warm-up gate completed; raw ADC values reached PHP/MySQL without any ppm claim, but low-rail/noisy samples occurred | **PARTIAL**; inspect AO/divider/ground/power and complete baseline calibration |

The firmware kept every invalid field nullable. It did not convert a timeout,
rail fault, missing I2C device, or uncalibrated signal into a safe zero or a
fabricated engineering unit.

## End-to-end evidence

- The ESP32 joined Wi-Fi at `192.168.50.123` and reached the local PHP endpoint
  hosted at `192.168.50.195`.
- Captured requests returned HTTP 201 with no network, timeout, 4xx, or 5xx
  failures.
- MySQL records beginning at ID 11141 contain physical HC-SR04 distance plus
  valid optional fields. Later records contain DS18B20 temperature, SEN0189 raw
  ADC, AHT2x ambient temperature/humidity, and MQ-135 raw ADC when valid.
- The website monitoring model returned AQS-001 as `ONLINE`, with a current
  physical, non-simulated reading.
- The owner-scoped Flutter monitoring model returned the same AQS-001 record,
  field values, freshness state, and server-derived level status.

The verified path is:

```text
Sensors -> ESP32 COM7 -> Wi-Fi -> PHP API -> MySQL
                                      |-> website monitoring
                                      `-> Flutter monitoring API
```

The website and Flutter app read PHP/MySQL data; they do not connect directly
to the ESP32.

## Required corrections before full acceptance

1. Stabilize HC-SR04 and repeat fixed 10 cm, 20 cm, 30 cm, no-echo, and restart
   tests.
2. Confirm YF-S201 signal protection and connection to official GPIO19, then
   enable pulse-only capture and perform a known-volume calibration.
3. Restore ENS160 detection on the shared I2C bus. AHT2x at `0x38` alone does
   not prove the ENS160 IC is present.
4. Correct the intermittent AHT2x I2C connection and repeat a sustained run.
5. Inspect MQ-135 AO, divider, common ground, and 5 V supply; repeat warm-up and
   establish a documented raw baseline before any calibrated gas claim.
6. Measure the protected GPIO18, GPIO19, GPIO34, and GPIO35 signals with a
   multimeter and record that none exceeds 3.3 V.
7. Complete final sensor calibration, final regulated-power testing, enclosure,
   power-cycle, outage, and long-duration checks.

