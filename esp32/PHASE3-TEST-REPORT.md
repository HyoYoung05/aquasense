# ESP32 Hardware Phase 3 test report

Date: 2026-10-16
Board port: COM6 only
Firmware: 0.3.1
Overall result: **SOFTWARE/NETWORK/BACKEND PASS; PHYSICAL TELEMETRY DEFERRED**

The latest paper's Phase 3 acceptance architecture is the local path used here:
ESP32 -> local Wi-Fi router -> local Apache/PHP server -> MariaDB -> website and
Flutter APIs. Public hosting, Tailscale, and public HTTPS are not acceptance
dependencies for this phase.

| Requirement | Result | Evidence |
| --- | --- | --- |
| Preserve Phase 2 HC-SR04 | PASS | GPIO5/GPIO18, five samples, median, timeout, and invalid states retained |
| Inspect actual backend | PASS | Existing endpoint and `phase3_ingest_telemetry` contract used; no endpoint created |
| AQS-001 identity | PASS | Active database device; active grease trap 12 assignment |
| Credential outside Git | PASS | Ignored `secrets.h`; committed placeholder only; stored hash matches local key |
| ESP32 never accesses MySQL | PASS | Firmware contains Wi-Fi/HTTP only; POST targets PHP endpoint |
| Exact telemetry fields | PASS | `device_id`, `grease_trap_id`, `ultrasonic_distance`, `reading_uuid` |
| Invalid ultrasonic not sent | PASS on COM6 | `NO_ECHO` produced zero POST attempts and explicit skip messages |
| Wi-Fi timeout/reconnect | PASS | Forced loss, 15-second alternate timeout, primary recovery, no reboot |
| HTTP timeout | PASS (implementation) | Connect/read timeout fixed at 5 seconds |
| Controlled retry | PASS (implementation/backend) | Retry-stable UUID; 5/10/30-second backoff; 429 and configuration handling |
| API failure handling | PASS (backend tests) / physical pending | 32 checks cover 4xx/429/idempotency; valid-sensor transport failure remains deferred |
| PHP insert and derived state | PASS using isolated fixtures | Backend suite stored distance, calculated fill/state, and cleaned fixtures |
| `last_seen_at` update | PASS using isolated fixtures | Backend suite verified update transactionally |
| Website monitoring/history | PASS using isolated fixtures | Backend suite verified latest reading, history, page, and chart path |
| Flutter monitoring/history API | PASS using isolated fixtures | All 50 owner mobile API checks passed |
| Physical target movement | DEFERRED | HC-SR04 is not available |
| Real AQS-001 insert | DEFERRED | No valid physical median exists; database intentionally unchanged |
| Website/Flutter physical refresh | DEFERRED | Requires a real AQS-001 insert |

## Build and runtime evidence

- Public build: 942,088 bytes flash; 48,440 bytes static RAM.
- Private build: 942,648 bytes flash; 48,440 bytes static RAM.
- Upload: COM6 at 115200; flash hashes verified.
- LAN API health: HTTP 200 `{"status":"ok"}`.
- Wi-Fi: primary connected; forced disconnect recovered after the configured
  fallback timeout.
- Runtime: uptime reached 66 seconds after the commanded reset; no additional
  boot marker; settled free heap approximately 224,984 bytes.
- Invalid-data protection: no `[API] POST` entry and no AQS-001 database change.
- Reverification on 2026-10-16 upgraded firmware 0.3.1 to COM6 with verified
  hashes after the scope correction. It connected at `192.168.50.165`, reached
  16 seconds uptime with stable 224,844-byte free heap, and skipped the upload
  opportunity because all sensor-absent samples were invalid.
- Local API health returned HTTP 200 after the local database service restarted;
  all 32 device/backend checks and all 50 owner mobile API checks passed again.

## AQS-001 database guard

Before and after COM6 verification:

- reading count: 335
- latest reading ID: 409
- `last_seen_at`: 2026-09-30 12:17:26 UTC

This unchanged baseline is expected and proves sensor absence did not become
fake telemetry.

## Physical closeout when the sensor is available

1. Verify the HC-SR04 1 kOhm / 2 kOhm Echo divider with power disconnected.
2. Confirm real median distance changes at approximately 10, 20, and 30 cm.
3. Confirm a COM6 HTTP 201 response using the physical median.
4. Verify one new AQS-001 row and a newer `last_seen_at`.
5. Verify the same server-derived fill/state on website Monitoring and Flutter
   Monitoring/history.
6. Stop Apache during a valid pending reading, observe controlled retry without
   reboot, restore Apache, and confirm one idempotent insert.

No Phase 4 work is included.

## Requested completion report

1. Previous firmware: 0.3.0 (the initial Phase 3 implementation).
2. Current firmware: 0.3.1.
3. Files created: `config.h`, `private_config.h`, `secrets.example.h`,
   `wifi_manager.h/.cpp`, `telemetry.h/.cpp`, and this report.
4. Files modified: the sketch now named `AquaSenseSensors.ino`, firmware/root README and version files,
   API documentation, pinout, and ignore rules.
5. Device: active `AQS-001`.
6. Local server: configured once in ignored `secrets.h`; current test host is
   `http://192.168.50.138` on the local network.
7. Endpoint: `/AQUASENSE+/aquasense-web/api/device/telemetry.php`.
8. Method: HTTP POST with JSON.
9. Authentication: `X-Device-Key`; plaintext key stays in ignored secrets and
   only its SHA-256 hash is stored by the backend.
10. Payload: `device_id`, `grease_trap_id`, `ultrasonic_distance`, and
    retry-stable `reading_uuid`.
11. Success contract: HTTP 201 for a new reading; HTTP 200 with
    `duplicate: true` for the same idempotent retry. This was verified by the
    isolated backend suite, not claimed as physical-sensor evidence.
12. Storage table: `sensor_readings`; `devices.last_seen_at` updates in the same
    accepted communication path.
13. Latest retained development row is ID 409, distance 15.00 cm, backend fill
    60.00%, MEDIUM, explicitly marked simulated/test. It is not physical proof.
14. Fill source: firmware has a temporary diagnostic calculation; backend
    calibration and thresholds are authoritative for stored/application state.
15. Website: monitoring/history integration passes with isolated fixtures;
    physical AQS-001 refresh is deferred.
16. Flutter: owner-scoped monitoring/history integration passes 50 checks;
    physical AQS-001 refresh is deferred.
17. Wi-Fi loss: forced disconnect and automatic recovery passed without reboot.
18. Server outage: bounded failure/retry exists; outage-to-success with a valid
    pending physical reading is deferred because the sensor is absent.
19. Backend/schema: no duplicate endpoint/table needed; the existing contract
    requires the device's active grease-trap ID, confirmed as 12.
20. Threshold logic: temporary ESP32 fill is diagnostic only; official fill,
    overflow state, and alerts remain server-authoritative until Phase 6.
21. Phase 3 status: **PARTIAL under the paper's physical completion criteria**.
22. Blocker: HC-SR04 and its divider are not connected, so a real target change,
    HTTP acceptance, database insert, last-seen update, website/Flutter refresh,
    and valid-reading server-outage recovery cannot be demonstrated yet. The
    next task is the six-step physical closeout above; Phase 4 must not start
    before that closeout or an explicit scope change.
