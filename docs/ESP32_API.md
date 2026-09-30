# AQUASENSE+ ESP32 Telemetry API

Phase 3 accepts structured ESP32 telemetry through PHP. The ESP32 never connects to MySQL.

## Endpoint

- Method: `POST`
- Path: `/api/device/telemetry.php`
- Local example: `http://LAN-IP/AQUASENSE+/aquasense-web/api/device/telemetry.php`
- Production example: `https://YOUR-DOMAIN/api/device/telemetry.php`

Configure the complete URL once in the firmware's private configuration. Do not hardcode a developer LAN address throughout the sketch. Production must use HTTPS.

## Headers

```http
Content-Type: application/json
Accept: application/json
X-Device-Key: <64-character device credential>
```

`Authorization: Bearer <device credential>` remains supported for existing test firmware. `X-Device-Key` is preferred. Generate or rotate a credential from the administrator Device Details page. AQUASENSE+ displays a new credential once and stores only its SHA-256 hash.

## Request

Required fields:

- `device_id`: registered human-readable code, such as `AQS-001`
- `grease_trap_id`: positive integer for the device's current assignment
- `ultrasonic_distance`: JSON number in centimeters, currently 2–400

Optional fields:

- `waste_level_percent`: device-calculated value from 0–100, stored only for diagnostics
- `temperature`: degrees Celsius
- `turbidity`: NTU
- `flow_rate`: liters per minute
- `gas_value`: future gas/odor sensor reading
- `recorded_at`: ISO 8601 device timestamp; omit until device time is trustworthy
- `reading_uuid`: standard UUID for retry-safe duplicate protection
- `sequence_number`: non-negative per-device sequence value for duplicate protection
- `status`: accepted for older firmware but the server calculates the authoritative state

Ultrasonic-only example:

```json
{
  "device_id": "AQS-001",
  "grease_trap_id": 12,
  "ultrasonic_distance": 12.4,
  "reading_uuid": "123e4567-e89b-42d3-a456-426614174000"
}
```

The server uses the grease trap's saved empty/full distances:

```text
fill % = ((empty distance - current distance) / (empty distance - full distance)) × 100
```

It clamps the result to 0–100 and applies the trap thresholds. Raw distance is the authoritative input. A device-reported percentage does not replace the backend result.

## Success response

New reading: HTTP `201`. An identical retry with the same UUID or sequence returns HTTP `200` and `duplicate: true`.

```json
{
  "success": true,
  "data": {
    "duplicate": false,
    "reading_id": 123,
    "device_id": "AQS-001",
    "grease_trap_id": 12,
    "ultrasonic_distance": 12.4,
    "waste_level_percent": 70.4,
    "status": "MEDIUM",
    "received_at": "2026-09-27T02:30:00+00:00"
  }
}
```

Stored timestamps are UTC. Website pages format them for Asia/Manila.

## Errors

| Status | Meaning |
| --- | --- |
| 400 | Malformed JSON object. |
| 401 | Missing or invalid device credential. |
| 403 | Inactive device/site/trap or mismatched assignment. |
| 404 | Unknown device or grease trap. |
| 409 | Missing calibration or conflicting idempotency key. |
| 413 | Request body exceeds the configured limit. |
| 415 | Content type is not `application/json`. |
| 422 | Invalid field, sensor value, timestamp, UUID, or sequence. |
| 429 | Reading arrived sooner than the configured minimum interval. Respect `Retry-After`. |
| 500 | Unexpected server failure; retry later. |

Error responses contain `success: false` and a safe message. They never expose SQL, paths, credentials, or stack traces.

## Retry and interval guidance

- A normal test interval is 5–30 seconds.
- Include `reading_uuid` or `sequence_number` on every reading and reuse it when retrying that same reading.
- Create a new identifier only after taking a new sensor sample.
- On timeout or HTTP 500, retry with exponential backoff.
- On HTTP 429, wait at least the `Retry-After` duration.
- Do not reboot continuously because the server is unavailable.
- Treat HTTP 401/403/404/409/422 as configuration or payload problems that need correction.

Telemetry retention must be finalized before high-frequency large-scale deployment. Phase 3 adds indexes and bounded website history queries but does not delete readings automatically.
