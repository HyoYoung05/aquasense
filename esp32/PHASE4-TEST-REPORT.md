# ESP32 Hardware Phase 4 completion report

Date: 2026-10-16
Port: COM6 only
Status: **PARTIAL**

Sensors are unavailable, as confirmed by the user. Software, build, COM6,
database, web, and Flutter paths were verified without fabricating readings.

1. **Previous firmware:** 0.3.1.

2. **New firmware:** 0.4.0.

3. **Created:** I2C scanner; temperature, turbidity, flow, I2C/environment,
   gas, and sensor-data modules; migration 011; sensor documentation; report.

4. **Modified:** main sketch, configuration/pins/telemetry, PHP ingestion and
   monitoring, schema/setup/tests, admin UI, Flutter model/screen, API docs,
   README files, and version records.

5. **DS18B20:** OneWire + DallasTemperature on GPIO4. No sample exists because
   the probe is absent. Disconnect, 85 C sentinel, nonfinite, and range failures
   become N/A/ERROR and null telemetry. No humidity is generated.

6. **SEN0189:** ADC GPIO34 behind the 10 kOhm/20 kOhm divider. Voltage and raw
   range are unmeasured. Raw ADC has a dedicated field; NTU is uncalibrated and
   deferred to Phase 6.

7. **YF-S201:** protected GPIO19. The IRAM ISR only increments a volatile
   counter; the loop performs atomic copy and calculations. No pulse/flow result
   exists. The 450-pulse/L factor is provisional until Phase 6.

8. **ENS160+AHT2x:** GPIO21/GPIO22 I2C with 50 ms timeout. Libraries are
   DFRobot_ENS160 and Adafruit_AHTX0. Seven scans found zero devices because the
   board is absent, so addresses remain unset. No AQI, TVOC, eCO2, ambient
   temperature, or humidity exists. Valid AHT2x data is used for compensation;
   the IC validity states remain independent.

9. **MQ-135:** divided ADC GPIO35. Voltage/raw response are unmeasured. Firmware
   exposes at least 180 seconds of warm-up, then raw ADC only. PPM calibration
   is deferred to Phase 6.

10. **Validity output:** HC-SR04 was ERROR/INVALID; DS18B20, SEN0189, YF-S201,
    ENS160, AHT2x, and MQ-135 were N/A/DISABLED. Six independent state values
    are implemented.

11. **JSON:** supports `device_id`, `grease_trap_id`,
    `ultrasonic_distance`, `reading_uuid`, `temperature`, `turbidity_raw`,
    `flow_pulse_count`, `flow_rate`, `ambient_temperature`, `humidity`, `aqi`,
    `tvoc_ppb`, `eco2_ppm`, and `gas_raw`. Only valid optional values appear.
    No physical JSON was emitted because HC-SR04 was invalid.

12. **PHP/API:** strict Phase 4 validation/ingestion was added while preserving
    device authentication, assignment checks, server-derived level/status,
    retry identity, and safe errors.

13. **MySQL:** migration 011 adds nullable raw ADC, pulse, AHT2x, and ENS160
    columns with constraints. It is safe to reapply. No new database exists.

14. **Website:** PHP lint and API fixtures passed. Admin monitoring displays
    raw/calibrated sensor semantics without fake values.

15. **Flutter:** mappings, cards, history labels, and chart metrics use the same
    fields. Flutter analysis and 53 owner API checks passed.

16. **Wi-Fi:** with new hardware gated off, COM6 connected at 192.168.50.165,
    remained online beyond 26 seconds, and heap stabilized around 220 kB. The
    all-sensors-active test is blocked.

17. **Brownout/reset:** none in the USB sensor-absent run. `POWER_ON` was the
    only reset reason. Full-load power testing belongs to Phase 5.

18. **Interference/timing:** no physical interference can be assessed. Code
    uses independent `millis()` schedules, asynchronous DS18B20 conversion,
    minimal ISR, paced I2C retries, and bounded timeouts.

19. **Schema mismatch:** dedicated raw ADC, flow pulse, AHT2x, and ENS160 fields
    were absent. Nullable additive fields now preserve unit semantics.

20. **Paper inconsistency:** DS18B20 is sometimes attributed humidity and
    Figure 14 labels DHT22. Actual firmware uses DS18B20 for wastewater
    temperature and AHT2x for ambient temperature/humidity.

21. **Result:** PARTIAL. Implementation, compile, COM6 runtime, API, database,
    website, and Flutter paths pass; physical acceptance cannot pass yet.

22. **Blockers:** no sensors, no divider voltage measurements, no real I2C
    addresses, no samples/pulses/warm-up responses, and no per-addition or
    full-array stability tests.

23. **Deferred:** Phase 5 validates final power/load/current/brownouts. Phase 6
    establishes all physical calibration curves, factors, baselines, and
    production thresholds.

Evidence: scanner 293,848 bytes flash / 23,508 bytes RAM; firmware 972,644
bytes flash / 50,160 bytes RAM; 35 backend checks; 53 mobile API checks;
Flutter analysis; AQS-001 unchanged at 335 readings, latest ID 409, and
`last_seen_at` 2026-09-30 12:17:26. Compiler warnings were confined to the
installed DFRobot ENS160 and OneWire libraries.

## Physical follow-up — 2026-10-22

The user connected the sensor set and corrected the active board port to COM7.
Firmware 0.7.1 compiled at 989,088 bytes flash and 51,200 bytes RAM and was
hash-verified on COM7.

- HC-SR04, DS18B20, SEN0189 raw ADC, AHT2x, and valid MQ-135 raw samples reached
  PHP/MySQL; website and Flutter monitoring read the current AQS-001 record.
- DS18B20 became valid after a supplemental internal pull-up was added; the
  external schematic resistor is still required.
- AHT2x was repeatedly detected at `0x38`; ENS160 was not detected at `0x52`
  or `0x53`.
- YF-S201 remains disabled until the official GPIO19 protected input is
  electrically verified. No fabricated pulse count or L/min value is stored.
- HC-SR04 no-echo events, intermittent AHT2x/I2C errors, a SEN0189 rail-fault
  interval, and noisy MQ-135 readings keep Phase 4 **PARTIAL**.

See [the connected-sensor report](PHYSICAL-VERIFICATION-2026-10-22.md).
