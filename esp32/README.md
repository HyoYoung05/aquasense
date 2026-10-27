# AQUASENSE+ ESP32 Hardware — Final Phase 8

Firmware **0.8.6** is the renewed six-sensor target in
`AquaSenseSensors/AquaSenseSensors.ino`. It preserves the Phase 1–7 sensor,
telemetry, power, calibration, and bounded outage-recovery implementation. A
2026-10-27 COM10 deployment uses the production HTTPS endpoint and keeps every
invalid physical input explicit instead of sending fabricated telemetry.

```text
HC-SR04 -> ESP32 -> Wi-Fi -> PHP API -> MySQL/MariaDB -> website / Flutter
```

The ESP32 never accesses MySQL. Public sensor gates remain disabled by default;
an ignored local hardware profile enables only paths verified on one prototype.
The current private COM10 profile enables all six official sensors. ENS160 now
checks both `0x52` and `0x53`; AHT2x remains disabled because it is not an
official seventh sensor. Physical wiring and calibration still determine
whether each enabled input becomes valid.

## Six physical modules

1. HC-SR04 on TRIG GPIO5 and divided ECHO GPIO18.
2. DS18B20 wastewater probe on GPIO4 with the documented pull-up.
3. SEN0189 analog turbidity module on divided GPIO34.
4. YF-S201 flow module on protected GPIO19.
5. ENS160+AHT2x combination module on SDA GPIO21/SCL GPIO22; optional ENS160
   INT is GPIO26.
6. MQ-135 analog gas/odor module on divided GPIO35.

ENS160 and AHT2x are separate ICs on one physical module. DS18B20 never
supplies humidity, and AHT2x ambient temperature never overwrites DS18B20
wastewater temperature. There is no microSD, OLED, buzzer, or DHT22.

## Firmware structure

The user-supplied combined sketch was reviewed as a hardware reference. Its
useful initialization details were incorporated: 12-bit DS18B20 resolution,
12-bit ADC setup for SEN0189 and MQ-135, 100 kHz I2C, and a pulled-up
falling-edge flow input. The official YF-S201 signal remains GPIO19. Its old
GPIO27 assignment and uncalibrated NTU/L/min conversions were not copied.

Firmware 0.8.0 compiled at 991,600 bytes flash and 51,200 bytes static RAM and
was hash-verified on COM7. Its startup banner identifies `ESP32 Six-Sensor
Firmware`, Wi-Fi connected, and live telemetry received HTTP 201 responses.

Firmware 0.8.6 first waits for one of up to ten Wi-Fi networks configured in
ignored `secrets.h`. During the 60-second startup window it rotates immediately
through every non-empty slot. The 55-second attempt budget is divided by the
number of configured slots and capped at 12 seconds per network, so all ten
options can be reached inside the initial minute. It then returns to normal
bounded backoff if none connects. Sensor initialization,
sensor reads, telemetry capture, and `AQUASENSE+ SIX-SENSOR LIVE REPORT` output
remain locked until the first successful Wi-Fi connection. After that first
connection, one live report prints every five seconds while Wi-Fi remains
online. A later disconnect pauses sensor reads, live reports, and new telemetry
capture; the website/app retain the last server-accepted reading while Serial
shows reconnection status only. Fresh sensor processing resumes automatically
after any configured network connects.
The report always contains numbered sections for all six official
sensors. Disabled, unavailable, warming, and uncalibrated inputs remain visible
with their real state. HC-SR04 still uses five samples and a median internally;
the individual echo lines are hidden unless `VERBOSE_ULTRASONIC_SERIAL` is
temporarily enabled. An uncalibrated local fill remains `UNKNOWN`; the PHP API
continues to calculate the stored fill from the grease trap calibration. System
health prints separately every 30 seconds.

Valid sensor snapshots are offered to the PHP API every five seconds. The API
evaluates threshold alerts in the same transaction that stores each accepted
reading; failed or invalid measurements are never converted into fake safe
values.

When editing the ignored private mirror directly in VS Code, its local
`.vscode/c_cpp_properties.json` must point to the generated Arduino compile
database. The active development mirror includes this local configuration, so
`Arduino.h` resolves using the installed ESP32 3.3.12 core. `AQUASENSE` is also
included in the workspace spelling dictionary.

- `temperature_sensor`: asynchronous OneWire/DallasTemperature conversion,
  disconnect/sentinel/range validation, and controlled reinitialization.
- `turbidity_sensor`: latest/averaged ADC, divider/source voltage diagnostics,
  and raw data only until an approved NTU calibration exists.
- `flow_sensor`: minimal ISR, atomic pulse snapshots, and frequency. The
  provisional 450-pulse/L reference cannot produce L/min until known-volume
  calibration is approved.
- `i2c_bus` and `environment_sensor`: repeated stable address detection,
  bounded I2C timeout, independent ENS160/AHT2x states, optional AHT2x
  compensation, and paced retries.
- `gas_sensor`: ADC filtering, a minimum three-minute development warm-up gate,
  raw data only, and no fabricated ppm.
- `sensor_data`: independent `DISABLED`, `UNAVAILABLE`, `WARMING_UP`,
  `NOT_CALIBRATED`, `VALID`, and `ERROR` states.
- `telemetry`: retry-stable snapshots that include only valid optional fields.

All schedules use `millis()`. One failed sensor does not stop the others,
Wi-Fi recovery, or the main loop.

## Private configuration and activation

Copy `secrets.example.h` to ignored `secrets.h` for Wi-Fi, endpoint, device
key, and optional trusted CA. Copy `config.local.example.h` to ignored
`config.local.h` for prototype-specific sensor gates and I2C addresses. Never
commit either private file.

The ESP32 radio supports 2.4 GHz Wi-Fi. Configure phone hotspots and routers to
offer a 2.4 GHz band; a 5 GHz-only SSID cannot be joined by ESP32 DevKit V1.

Before enabling each Phase 4 sensor in `config.local.h`:

1. Wire only that module using [the pinout](../docs/PINOUT.md).
2. Confirm the actual board supply requirement.
3. Measure protected signals and keep the ESP32 pin at or below 3.3 V.
4. For ENS160+AHT2x, run `I2CScanner` for three stable scans, then enter the
   actual addresses.
5. Enable only that module and repeat sensor, HC-SR04, Wi-Fi, API, database,
   website, Flutter, and reset checks.

## Connected-sensor verification on 2026-10-22

- Firmware 0.7.1 compiled at 989,088 bytes flash and 51,200 bytes RAM and was
  hash-verified on COM7.
- HC-SR04, DS18B20, SEN0189 raw ADC, AHT2x, and valid MQ-135 raw samples reached
  the PHP API and MySQL. Website and Flutter monitoring both returned AQS-001
  as online with the current physical record.
- DS18B20 is valid after adding an internal development pull-up; the external
  schematic pull-up remains required.
- Repeated I2C scans found only AHT2x at `0x38`; ENS160 was not detected.
- YF-S201 remains disabled until its protected signal is verified on official
  GPIO19. No false flow rate is stored.
- HC-SR04 timeouts, intermittent AHT2x errors, a recoverable SEN0189 rail fault,
  and noisy MQ-135 samples keep physical acceptance PARTIAL.

See the [full connected-sensor report](PHYSICAL-VERIFICATION-2026-10-22.md).

## Telemetry semantics

Valid optional fields are `temperature`, `turbidity_raw`,
`flow_pulse_count`, `flow_rate`, `ambient_temperature`, `humidity`, `aqi`,
`tvoc_ppb`, `eco2_ppm`, and `gas_raw`. PHP stores raw ADC values in dedicated
nullable columns. It never labels SEN0189 raw ADC as NTU or MQ-135 raw ADC as
gas ppm.

## Phase 4 verification on 2026-10-16

- I2C scanner compiled at 293,848 bytes flash and 23,508 bytes RAM.
- Uploaded scanner only to COM6; seven stable scans reported zero devices
  because the combo module was absent.
- Full private firmware compiled with all warnings enabled at 972,644 bytes
  flash and 50,160 bytes static RAM. Warnings came only from installed
  DFRobot ENS160 and OneWire libraries.
- Uploaded firmware 0.4.0 only to COM6 with verified hashes.
- The ESP32 reported `POWER_ON`, passed its calculation self-test, connected at
  `192.168.50.165`, and ran beyond 26 seconds without reboot or heap decline.
- HC-SR04 remained honest `NO_ECHO`/`INVALID`; unavailable Phase 4 subsystems
  printed `N/A` and `DISABLED`; no request was sent.
- AQS-001 remained at 335 readings, latest ID 409, and `last_seen_at`
  `2026-09-30 12:17:26`.
- 35 backend checks, 53 mobile API checks, and Flutter analysis passed.

The approved Phase 5 primary architecture is an enclosed regulated AC-to-DC
adapter feeding safe low-voltage 5 V and 3.3 V rails with one common ground.
See [the power specification](../docs/POWER.md). MT3608, TP4056, arbitrary
battery wiring, microSD, OLED, and buzzer are excluded. The backup-battery
architecture is not finalized in the current schematic.

Firmware reports its declared power mode, reset reason, minimum heap, Wi-Fi
connection transitions, and telemetry counts. It explicitly reports that no
rail-voltage monitor exists; multimeter values belong in the physical report.

Physical readings, rail/divider voltages, per-sensor progressive loading, ten
power cycles, interruption recovery, and multi-hour stability remain blocked
by absent hardware. Hardware Phases 4 and 5 are therefore **PARTIAL**. See the
[Phase 4 report](PHASE4-TEST-REPORT.md) and
[Phase 5 report](PHASE5-TEST-REPORT.md).

## Phase 5 software verification on 2026-10-17

- Firmware 0.5.0 compiled at 973,492 bytes flash and 50,192 bytes static RAM.
- Uploaded only to COM6 with verified hashes.
- USB-powered ESP32 reported `POWER_ON`, connected at `192.168.50.173`, and
  reached 51 seconds without brownout, reboot, or heap decline; minimum free
  heap stabilized at 219,892 bytes.
- Sensor-absent HC-SR04 remained invalid, every Phase 4 module remained
  disabled/N/A, and the firmware sent no fake request.
- AQS-001 stayed at 335 readings, latest ID 409, and its prior `last_seen_at`.
- 35 backend checks, 53 mobile API checks, and 32 focused Flutter tests passed.

## Phase 6 software verification on 2026-10-17

- Central calibration state is in `calibration.h`; all physical approval flags
  remain false.
- Backend trap geometry and thresholds remain authoritative. Firmware sends
  distance and cannot override the server's fill/state result.
- SEN0189 raw ADC remains distinct from NTU; YF-S201 pulse counts remain
  distinct from calibrated L/min; MQ-135 remains raw-only with no ppm claim.
- Migration 012 disables turbidity and flow alerts until an administrator has
  physical calibration evidence and deliberately enables each gate.
- Firmware 0.6.0 compiled at 973,988 bytes flash and 50,208 bytes RAM, uploaded
  only to COM6, passed its calculation self-test, connected to Wi-Fi, and ran
  sensor-absent without reboot or fake telemetry.
- See [the calibration register](../docs/CALIBRATION.md) and
  [Phase 6 report](PHASE6-TEST-REPORT.md).

Physical calibration is still required; Phase 6 status is **PARTIAL**.

## Phase 7 reliability implementation

- Wi-Fi uses explicit disconnected/connecting/retry-wait/connected states,
  15-second attempt limits, and 5/10/30-second backoff.
- A fixed eight-record RAM FIFO captures a new valid snapshot every 15 seconds
  while older records wait for the API. It drains oldest first every 2.5
  seconds after recovery and drops the oldest record if full.
- Each record keeps one UUID across retries. PHP's existing UUID/payload-hash
  check returns the existing row when a response is lost and retried.
- Network failure, read timeout, 4xx, 5xx, 429, and success are counted and
  handled separately. A permanent 4xx is discarded after one attempt; 429,
  network failures, timeouts, and 5xx retain the record.
- ENS160/AHT2x errors are independent and trigger paced I2C recovery. Analog
  rail faults invalidate SEN0189/MQ-135 rather than generating zero units.
- Startup/runtime diagnostics report queue memory/depth/high-water/drops,
  network transitions, API outcomes, sensor errors, ultrasonic timeouts, I2C
  errors, I2C recoveries, uptime, and minimum heap.

The queue is volatile. Eight 15-second records are about two minutes of
temporary buffering before drop-oldest overflow, and reset/power loss clears
everything unsent. There is still no persistent offline storage, microSD,
OLED, buzzer, or approved backup-battery circuit.

Software/build/runtime and regression evidence is in the
[Phase 7 report](PHASE7-TEST-REPORT.md); design details are in
[RELIABILITY.md](../docs/RELIABILITY.md). At the time of the Phase 7 run, the
sensors and final power were absent; physical router/server outage,
latency/load, power-cycle, and long-duration tests remain pending. Hardware
Phase 7 is **PARTIAL**. Phase
8 is final physical assembly, enclosure, installation, and end-to-end field
testing after those blockers clear.

The 2026-10-18 COM6 sensor-absent diagnostic reached 206.243 seconds with no
observed reset or brownout. Two controlled Wi-Fi disconnects both recovered
automatically; the measured reconnect case took 31.2 seconds while rotating
past an unavailable configured network. Free heap stayed between 219,128 and
219,140 bytes during the captured run. These results do not replace the pending
full-sensor, final-power, latency, stress, and several-hour tests.

## Final Phase 8 audit

Phase 8 is the final hardware phase. The 2026-10-18 audit retained firmware
0.7.0 because the modules were unavailable at that time. After the modules were
connected, firmware 0.7.1 added a safe local hardware profile and the DS18B20
development pull-up fix. The locked GPIO map, calibration gates, backend
thresholds, and Phase 7 recovery behavior remain unchanged.

Firmware 0.8.0 then renamed the Arduino target to `AquaSenseSensors` and
renewed initialization for the complete six-sensor codebase without changing
the backend contract or claiming that pending physical calibration has passed.

The COM7 follow-up provides connected bench evidence for the sensor set but not
final installation acceptance. The final wiring/placement plan, low-voltage
assembly sequence, moisture rules, inventory, actuation gaps, and readiness
decision are in [Final assembly](../docs/FINAL_ASSEMBLY.md). The original exact
72-item audit and its post-audit update are in the
[Phase 8 report](PHASE8-TEST-REPORT.md).

Physical latch/diversion and backup power remain blocked because no approved
actuator/driver/GPIO/power/fail-safe design or backup-power circuit exists.
Final assembled bench demo, capstone hardware demo, and controlled field test
are **not ready**. A labelled software/USB diagnostic demonstration remains
available. Do not create Phase 9.

Available software validation passed 504 sequential PHP/backend checks, Flutter
static analysis, and all 113 Flutter tests. These results verify the supported
software path only and do not pass any missing physical test.
