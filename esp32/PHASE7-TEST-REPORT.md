# ESP32 Hardware Phase 7 completion report

Date: 2026-10-18
Authorized hardware port: COM6 only  
Status: **PARTIAL**

The user confirmed the physical sensors are not available. This report
separates compiled/software evidence from tests that require the complete
sensor array, router interruption, final power hardware, or valid live
telemetry. No unavailable result is presented as a pass.

1. **Previous firmware version:** 0.6.0.
2. **New firmware version:** 0.7.0.
3. **Files created:** `telemetry_queue.h`, `telemetry_queue.cpp`,
   `docs/RELIABILITY.md`, and this report.
4. **Files modified:** the main sketch, configuration, telemetry, Wi-Fi,
   I2C/environment, analog sensor validation, root/ESP32 README files,
   root/ESP32 version histories, and ESP32 API guide.

## Network

5. **Wi-Fi disconnect result:** PASS for the sensor-absent COM6 diagnostic. Two
   forced disconnects entered `RETRY_WAIT`; one-second HC-SR04 acquisition
   continued throughout, the board remained responsive, and no reboot occurred.
   Full-array acquisition during an outage remains pending.
6. **Wi-Fi reconnect time:** 31.2 seconds in the measured COM6 diagnostic. The
   first configured network was unavailable, timed out after 15 seconds, and
   the state machine rotated to the available network after controlled 5/10
   second backoff. It connected without manual reset.
7. **Router outage result:** NOT TESTED; physical router interruption with the
   complete sensor system is required.
8. **Local server outage result:** NOT TESTED with valid ESP32 telemetry because
   HC-SR04 is absent.
9. **Server recovery result:** NOT TESTED with a filled hardware queue.

## API

10. **HTTP timeout behavior:** connect/read timeout is finite at five seconds;
    the record remains queued, a timeout counter increments, and sensing resumes.
11. **4xx behavior:** 408 is a retryable timeout; 429 retains the record and
    honors `Retry-After`; other 4xx responses count and discard the rejected
    record after one attempt, followed by a 30-second pause.
12. **5xx behavior:** record retained as a temporary server fault; controlled
    retry, no reboot.
13. **API retry strategy:** 5, 10, then 30 seconds; success resets backoff.

## Queue

14. **RAM queue implemented:** YES.
15. **Queue capacity:** 8 fixed records; exact structure/reserved bytes print
    at startup.
16. **Queue overflow policy:** drop oldest unsent, retain newest eight.
17. **Maximum queue usage observed:** algorithm self-test reaches 8/8; real
    outage usage pending valid physical HC-SR04 readings.
18. **Behavior after reconnection:** FIFO oldest first with a 2.5-second drain
    interval; physical reconnection/drain remains pending.
19. **Duplicate-record result:** backend UUID/payload-hash idempotency is
    covered by the telemetry regression suite; lost-response ESP32 testing is
    pending.

## Sensors

20. **HC-SR04 fault result:** sensor-absent runs preserve INVALID/UNKNOWN/ERROR
    and produce no fake telemetry; physical connected-then-disconnected test pending.
21. **DS18B20 fault result:** software clears invalid/sentinel readings and
    cannot send zero or trigger an alert; physical test pending.
22. **SEN0189 fault result:** rail-fault gate and calibrated-validity gate are
    implemented; physical disconnect/range test pending.
23. **YF-S201 fault result:** ISR remains minimal and other tasks are
    independent. A single signal wire cannot prove every disconnect versus
    real zero flow; physical test pending.
24. **ENS160 fault result:** independent error state and paced reinitialization
    implemented; physical test pending.
25. **AHT2x fault result:** independent error state and paced reinitialization
    implemented; physical test pending.
26. **MQ-135 fault result:** warming/raw-only state and ADC rail-fault rejection
    implemented; physical test pending.
27. **I2C recovery result:** error/recovery counters and a 30-second-controlled
    bus reinitialization exist; held-low and recovered-bus tests pending.

## Power

28. **Controlled power-cycle result:** NOT TESTED with the final Phase 5 power
    system because it is unavailable.
29. **Automatic boot recovery result:** USB boot path initializes sensors before
    network. Final full-array boot recovery remains pending.
30. **Backup battery status:** NOT IMPLEMENTED / SPECIFICATION INCOMPLETE.

## Performance

31. **Network latency measured:** NOT MEASURED; no valid physical telemetry is
    available. No latency value is fabricated.
32. **Stress-test conditions:** backend regression requests can be concurrent,
    but the required simultaneous physical telemetry/admin/Flutter scenario was
    NOT TESTED.
33. **Stress-test result:** pending full integrated hardware.

## Long test

34. **Total continuous runtime:** 206.243 seconds in the captured sensor-absent
    COM6 run; the required several-hour/overnight full system run is pending.
35. **Unexpected reset count:** 0 during the short captured run; extended count pending.
36. **Brownout count:** 0 during the short USB run; final-power count pending.
37. **Wi-Fi disconnect count:** 2 controlled diagnostic disconnects.
38. **Wi-Fi reconnect count:** 2 automatic reconnects.
39. **API failure count:** 0; the invalid HC-SR04 correctly prevented requests.
40. **Sensor error count:** 205 HC-SR04 timeout samples caused by the absent
    sensor; each remained `INVALID / UNKNOWN / ERROR`.
41. **I2C error count:** 0; I2C modules remain disabled pending hardware.
42. **Starting free heap:** 219,128 bytes at the first captured diagnostic.
43. **Ending free heap:** 219,140 bytes at 206.243 seconds; minimum observed
    heap was 219,128 bytes, with no downward trend in this short run.

## Software

44. **MySQL result:** schema/idempotency and telemetry transactions verified by
    regression tests; no Phase 7 fake AQS-001 record inserted.
45. **Website result:** existing `last_seen_at` freshness remains authoritative;
    regression result recorded below.
46. **Flutter result:** API `device_status`/`is_stale` remain authoritative;
    regression result recorded below.
47. **Alert-system regression result:** invalid optional readings remain absent,
    and calibrated-only/backend-authoritative gates are regression tested.

## Limitations

48. **Persistent offline storage available:** NO.
49. **Reasonable maximum buffered outage:** about two minutes at the 15-second
    capture interval before the first overflow; it is a record-count bound,
    not a time guarantee.
50. **Remaining data-loss conditions:** reset/power loss clears RAM; outages
    beyond eight captured records drop oldest; permanent 4xx rejects are
    discarded; invalid HC-SR04 readings create no telemetry record.
51. **Paper mismatch:** the paper requests preservation of the last few
    readings but approves no persistent local storage. Phase 7 uses bounded
    volatile RAM and documents the power-loss limitation. Backup circuitry is
    also mentioned but not defined by the approved schematic.
52. **Phase 7:** PARTIAL.
53. **Exact blockers:** six physical modules, safe finished wiring/dividers,
    final trap, approved final power system, router/server interruption window,
    latency/load setup, and several-hour runtime are unavailable.
54. **Ready for Phase 8 after blockers clear:** final physical assembly,
    enclosure, installation, and end-to-end field testing. Phase 8 must not
    start automatically.

## Verification evidence

- Arduino CLI compiled the private Phase 7 sketch for `esp32:esp32:esp32`.
  Reported usage was 977,808 bytes (74%) of flash and 51,088 bytes (15%) of
  static RAM, leaving 276,592 bytes for local variables. The flashable binary
  image was 977,952 bytes.
- The image was uploaded only to the authorized CH340 device on COM6. All flash
  regions passed esptool hash verification.
- The controlled reconnect sample measured 31.2 seconds from forced disconnect
  to the `Connected` event after one unavailable configured network timed out.
  Sampling continued every second.
- The longer captured uptime reached 206.243 seconds with zero observed resets,
  two controlled disconnects, two automatic reconnects, and stable heap.
- The absent HC-SR04 consistently produced `Distance: INVALID`, `Fill: UNKNOWN`,
  and `Sensor: ERROR`; no fake reading was queued or posted.
- Phase 3, Phase 4, mobile API, fresh-install, and Phase 8 backend suites passed
  195 checks. Flutter analysis passed and 49 focused tests passed.
- AQS-001 remained at its pre-test database baseline because invalid physical
  readings are intentionally not transmitted.
