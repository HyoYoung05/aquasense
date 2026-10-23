# ESP32 Hardware Phase 2 test report

Date: 2026-10-15
Board port: COM6 only
Firmware: 0.2.0
Overall result: **SOFTWARE PASS / PHYSICAL SENSOR TESTS DEFERRED**

| Requirement | Result | Evidence |
| --- | --- | --- |
| Revised GPIO5/GPIO18 map | PASS | Central `pins.h`; startup banner confirms both pins |
| Raw Echo duration | PASS | Five `pulseIn` durations are retained and printed per cycle |
| Timeout | PASS | 30,000 us bounded timeout; absent sensor returns 0 us |
| Invalid reading detection | PASS | No-Echo, range, sample-count, and calibration states are explicit |
| Median filtering | PASS (algorithm) | On-device fixed-vector self-test selects 20 cm median |
| Local fill formula | PASS (algorithm) | On-device check confirms 10 cm=80%, 20 cm=40%, 30 cm=0% for the documented 30/5 cm reference geometry |
| Clamp 0-100% | PASS (code review) | Calculation returns bounded result only for finite input and valid calibration |
| Failed-Echo safety | PASS (COM6) | Every failure printed INVALID / UNKNOWN / ERROR; no 0 cm, 0%, 100%, or OK substitution |
| Approximately 10 cm physical target | DEFERRED | HC-SR04 is not available |
| Approximately 20 cm physical target | DEFERRED | HC-SR04 is not available |
| Approximately 30 cm physical target | DEFERRED | HC-SR04 is not available |
| No Echo | PASS | Real COM6 sensor-absent run produced controlled `NO_ECHO` |
| Sensor disconnect | PASS for absent-input path | No connected sensor produced the same safe open/no-Echo behavior; wiring transition remains deferred |
| ESP32 restart | PASS | RTS/EN reset returned POWER_ON boot, self-test PASS, and resumed cycles |
| Continuous operation | PASS (smoke) | 45 observed seconds, 45 cycles, uptime through 81 seconds, no boot marker, stable 331,096-byte heap |
| No Wi-Fi/PHP/MySQL | PASS | Source contains no networking, HTTP, credential, or backend path |

## Build and upload

- Board profile: `esp32:esp32:esp32` / ESP32 Dev Module
- ESP32 core: 3.3.12
- Flash: 275,372 / 1,310,720 bytes (21%)
- Static RAM: 22,124 / 327,680 bytes (6%)
- Upload speed: 115200
- Flash verification: passed

## Physical closeout when the HC-SR04 is available

1. Disconnect USB before wiring.
2. Connect VCC to 5 V and GND to common GND.
3. Connect TRIG to GPIO5.
4. Route ECHO through 1 kOhm to the GPIO18 junction and 2 kOhm from that
   junction to GND. Never connect ECHO directly to GPIO18.
5. Reconnect USB and test flat targets near 10, 20, and 30 cm.
6. Record raw durations, all five distances, median, and temporary fill.
7. Disconnect ECHO and confirm INVALID / UNKNOWN / ERROR continues without a
   reboot or unsafe substitution.

Final physical grease-trap calibration is intentionally deferred to Phase 6.
No Phase 3 work is included.
