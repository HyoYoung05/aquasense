# ESP32 Hardware Phase 5 completion report

Date: 2026-10-17
Port: COM6 only
Status: **PARTIAL**

1. Previous firmware: 0.4.0.
2. New firmware: 0.5.0.
3. Created: `docs/POWER.md` and this report.
4. Modified: firmware config/main/network diagnostics, ESP32/root README files,
   and version records.
5. Exact supply: model, rated voltage, and rated current are **not available**.
6. Unloaded 5 V: **not measured**.
7. Loaded 5 V: **not measured**.
8. 3.3 V source: undecided pending supply/module-current inspection.
9. ESP32 VIN: still powered by USB for software verification; external VIN is
   not connected or claimed.
10. Sensor power: documented 5 V/3.3 V assignments; no sensors connected.
11. Common ground: required in the design; physical confirmation pending.
12. USB/external arrangement: USB only. Simultaneous USB/external power is not
    approved without inspecting the actual DevKit power path.
13. HC-SR04 stability: blocked; sensor absent.
14. DS18B20 stability: blocked; sensor absent.
15. SEN0189 ADC stability: blocked; sensor and final supply absent.
16. YF-S201 pulse stability: blocked; sensor absent.
17. ENS160+AHT2x I2C stability: blocked; combo board absent.
18. MQ-135 power/warm-up: software warm-up gate exists; load test blocked.
19. Wi-Fi stability: USB sensor-absent run connected at 192.168.50.173 and
    remained online through 51 seconds; full-load/final-supply result is blocked.
20. API/MySQL: existing contract and tests remain operational; no physical
    Phase 5 telemetry was submitted.
21. Power cycles: ten-cycle final-supply test not run.
22. Unexpected resets: zero during the 51-second USB verification run.
23. Brownout: none observed on USB; external/full-load result unavailable.
24. Sensor dropouts: not measurable because sensors are absent.
25. Long-duration test: not run; a short software runtime is not claimed as a
    multi-hour result.
26. Backup battery: **NOT IMPLEMENTED / BLOCKED** because the approved
    schematic defines no charger, protection, switchover, regulator, or
    backfeed protection.
27. Paper mismatch: the narrative mentions a 3.7 V backup battery while the
    final primary schematic does not define a safe battery architecture.
28. Phase 5 result: **PARTIAL**.
29. Blockers: actual enclosed supply, rating/polarity measurements, all six
    sensor modules, divider measurements, progressive loading, full-load run,
    ten power cycles, interruption recovery, and multi-hour stability test.
30. Ready for Phase 6 after blockers clear: sensor calibration and threshold
    integration. Phase 6 must not begin automatically.

No MT3608, TP4056, battery circuit, microSD, OLED, or buzzer was introduced.

Software evidence: firmware compiled at 973,492 bytes flash and 50,192 bytes
RAM and uploaded only to COM6 with verified hashes. The run reported
`POWER_ON`, minimum free heap 219,892 bytes, one Wi-Fi connection, zero
disconnects, zero accepted/failed telemetry attempts because HC-SR04 was
invalid, and no fake sensor upload. AQS-001 remained at 335 readings/latest ID
409. All 35 backend checks, 53 mobile API checks, and 32 focused Flutter tests
passed. The dependency tooling emitted existing desktop-plugin warnings; the
tests themselves passed. `flutter analyze` also completed with no issues.
