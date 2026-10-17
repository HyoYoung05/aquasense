# ESP32 Hardware Phase 1 test report

Date: 2026-10-15
Board port: COM6 only
Firmware: 0.1.0
Overall result: **PASS with one manual cable check remaining**

| Requirement | Result | Evidence |
| --- | --- | --- |
| Correct board profile | PASS | Compiled and uploaded with `esp32:esp32:esp32` (Arduino IDE ESP32 Dev Module); detected ESP32-D0WD-V3 rev 3.1 |
| Upload works | PASS | COM6 upload at 115200 completed; all written-data hashes verified |
| Serial Monitor at 115200 | PASS | Complete Phase 1 banner and five-second uptime diagnostics captured |
| EN/reset works | PASS | RTS-controlled EN reset produced a fresh POWERON_RESET ROM boot and firmware banner |
| USB reconnect | PASS / MANUAL FOLLOW-UP | COM6 returned after upload and Serial closed/reopened successfully; physically unplug/replug the cable once in Arduino IDE |
| No unexpected reboot | PASS (smoke test) | Uptime advanced through 51 seconds; heap remained 331,368 bytes; only commanded resets occurred |
| GPIO map documented | PASS | `pins.h`, `docs/PINOUT.md`, and ESP32 README match the revised paper |
| Sensors disconnected | PASS by configuration | No sensor library, `pinMode`, read, or write is present in the active sketch |
| Battery disconnected | MANUAL / REQUIRED | Firmware cannot verify wiring; keep battery and MT3608 disconnected during Phase 1 |
| Wi-Fi/backend absent | PASS | Active sketch contains no Wi-Fi, HTTP, credential, or backend code |

## Observed diagnostics

```text
AQUASENSE+
ESP32 Hardware Phase 1
Firmware version: 0.1.0
Chip model: ESP32-D0WD-V3
Chip revision: 301
CPU frequency: 240 MHz
Flash size: 4194304 bytes
Free heap: 331368 bytes
Reset reason: POWER_ON (1)
```

Build use: 270,572 of 1,310,720 bytes flash (20%); 22,116 of 327,680
bytes static RAM (6%).

## Manual closeout

With no sensors or battery connected, unplug the USB cable, wait five seconds,
reconnect it, select COM6 again if Arduino IDE requires it, and verify that the
same banner and increasing uptime return. Press the physical EN button once and
verify the POWER_ON banner returns. The automated EN path has already passed;
these two actions confirm the physical cable and button.

This report remains the Phase 1 historical result. The active firmware has now
advanced to Phase 2; see `PHASE2-TEST-REPORT.md` for current verification.
