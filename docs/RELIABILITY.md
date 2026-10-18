# ESP32 reliability and recovery

Hardware Phase 7 status: **PARTIAL — physical sensors and final power system
are unavailable**

Firmware 0.7.0 keeps sensor acquisition, Wi-Fi recovery, telemetry capture,
and telemetry delivery on independent schedules. It never reboots merely for
a sensor, Wi-Fi, HTTP, PHP, or database failure. The main loop contains no
infinite reconnect loop; HTTP connect/read timeouts are five seconds.

## Data path and failure boundary

```text
Six sensor modules
        -> ESP32
        -> local Wi-Fi
        -> PHP telemetry API
        -> MySQL/MariaDB
        -> website / Flutter
```

The ESP32 has no MySQL credentials. A database outage is visible to it only as
an HTTP/API failure. PHP remains responsible for validation, server-derived
fill/status, transactions, `last_seen_at`, and backend-authoritative alerts.

## Wi-Fi and router recovery

Wi-Fi uses `DISCONNECTED`, `CONNECTING`, `RETRY_WAIT`, and `CONNECTED` states.
Each connection attempt has a 15-second limit. Failed attempts use 5, 10, then
30-second backoff and alternate between the two privately configured networks
when both exist. A successful connection resets the backoff. Sensor updates
continue while disconnected, and credentials are never printed.

The Serial `W` diagnostic requests a disconnect without resetting the ESP32.
Counters track attempts, disconnects, successful connections, and reconnects.
An actual router power-off/restoration test remains pending.

## API failure classification

| Result | Queue behavior | Next action |
| --- | --- | --- |
| HTTP 200/201 | Remove oldest record | Drain the next FIFO item after 2.5 seconds |
| Wi-Fi offline | Retain oldest record | Controlled 5/10/30-second retry |
| Network failure | Retain oldest record | Controlled 5/10/30-second retry |
| Connect/read timeout | Retain oldest record | Controlled 5/10/30-second retry |
| HTTP 408 | Retain oldest record | Treat as timeout and back off |
| HTTP 429 | Retain oldest record | Honor `Retry-After`, otherwise back off |
| HTTP 5xx | Retain oldest record | Controlled backoff |
| Other HTTP 4xx | Discard rejected record | Count and pause 30 seconds |
| Local JSON/TLS configuration fault | Retain record | Count and pause 30 seconds |

A permanently rejected 4xx record is attempted once, logged without secrets,
and discarded so it cannot loop hundreds of times. A 5xx, packet failure, or
lost response is considered temporary. The same `reading_uuid` stays attached
to a queued record for every retry.

## Fixed RAM queue

The queue is an eight-record, compile-time fixed FIFO. `PendingTelemetry` size
is printed at startup, together with the exact reserved byte count. At the
15-second capture interval, eight records represent roughly two minutes of
samples before overflow if nothing can upload. Recovery drains at no faster
than one record every 2.5 seconds, above the backend's two-second rate limit.

When full, the queue **drops the oldest unsent record** and preserves the most
recent eight. It reports depth, high-water mark, and dropped-record count.
This policy bounds memory and favors current monitoring after a long outage.

This queue is volatile RAM. A reset, brownout, or complete power loss clears
it. There is no microSD, flash spool, SD card, or other persistent offline
telemetry. MySQL remains long-term storage. Therefore firmware 0.7.0 does not
promise lossless telemetry during a power interruption or an outage longer
than its small RAM window.

The PHP backend already enforces idempotency with `reading_uuid` and the
canonical payload hash. A retry with the same UUID and payload returns the
existing reading instead of inserting another. Firmware deliberately does not
send a reboot-resetting device sequence as a second identity system.

## Sensor isolation and recovery

Every sensor keeps an independent validity/state flag. Invalid fields are
omitted from telemetry; they never become fake zero values.

- HC-SR04 no-Echo, timeout, range, sample, and calibration failures remain
  `INVALID` / `UNKNOWN` / `ERROR`. They cannot produce zero distance or a fake
  fill level. Other sensor and network tasks continue.
- DS18B20 disconnect/sentinel/range failures clear temperature validity and
  cannot generate an Emulsion Warning. Reinitialization is paced at 30 seconds.
- SEN0189 and MQ-135 ADC values within four counts of either rail are treated
  as electrical input faults. No NTU or ppm is fabricated. Physical
  disconnection cannot be detected reliably for every possible floating ADC
  value, so final plausibility limits require real hardware evidence.
- YF-S201 uses a minimal pulse-count ISR. With the present one-wire signal it
  cannot distinguish a stopped flow from every possible disconnected-sensor
  condition. Zero pulses remain raw evidence; calibrated L/min stays gated.
- ENS160 and AHT2x have separate validity states. A failed transaction marks
  only the affected IC unavailable, records an I2C error, and schedules a
  controlled reinitialization. The bus recovery call is limited to the same
  30-second retry interval; SDA/SCL faults cannot create a reboot storm.
- MQ-135 preserves its warm-up state and raw-only semantics. Invalid rail
  readings do not become gas ppm.

Backend alert evaluation receives only valid optional values. Overflow remains
derived from a valid ultrasonic distance; temperature, turbidity, flow, and gas
faults cannot generate alerts from invented measurements.

## Startup, power, and watchdog policy

Startup initializes sensors before starting Wi-Fi. An unavailable optional
sensor or server does not prevent the remaining tasks from running. The reset
reason is printed, with explicit brownout/power-glitch warnings. Firmware does
not add an aggressive custom watchdog because the reconnect path is already
state-based and bounded; a watchdog must not hide blocking defects.

The approved backup-battery circuit is still unspecified and is not
implemented. Phase 7 adds no MT3608, TP4056, battery circuit, microSD, OLED, or
buzzer. Controlled final-supply interruption and automatic full recovery must
be measured when the approved power hardware exists.

## Website and Flutter freshness

The website uses database `devices.last_seen_at` and the configured offline
timeout to show `ONLINE`, `OFFLINE`, or existing not-connected/inactive states.
Flutter uses the same API timestamp, freshness window, device status, and
`is_stale` flag. It does not calculate a competing live state locally. Queued
records update `last_seen_at` only when PHP accepts them.

## Measurements and remaining tests

The compile, queue algorithm, backend idempotency, API regressions, website
freshness logic, and Flutter stale-data logic can be verified without sensors.
The following require the missing physical modules and/or approved final power
system and are not claimed:

- actual router power-off and restoration;
- actual server outage while valid sensor records fill and drain the queue;
- each sensor disconnect and I2C held-low test;
- ESP32 send-to-response and sensor-to-database/UI latency;
- simultaneous ESP32, administrator, and Flutter traffic;
- controlled final-supply power cycle and sensor reinitialization;
- several-hour or overnight full-array run, heap trend, and reset counts.

Record those results in `esp32/PHASE7-TEST-REPORT.md`. Phase 8 must not treat
the software-only Phase 7 result as completed field reliability evidence.

## Phase 8 final-assembly status

The final physical assembly is unavailable, so the Phase 7 measurements remain
the only device evidence: a 206.243-second USB run, two controlled automatic
Wi-Fi recoveries, no observed reset/brownout, and stable short-run heap. Phase 8
did not repeat router loss, server loss with valid queued records, sensor
disconnects, final-power interruption, concurrent load, or the several-hour
run. Those checks remain mandatory after assembly and are tracked in
`esp32/PHASE8-TEST-REPORT.md`; none is inferred from software tests.
