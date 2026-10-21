# AQUASENSE+ ESP32 Telemetry API

Firmware 0.6.0 sends structured sensor telemetry through PHP. The ESP32 never connects to MySQL.

## Endpoint

- Method: `POST`
- Path: `/api/device/telemetry.php`
- Local example: `http://LAN-IP/AQUASENSE+/aquasense-web/api/device/telemetry.php`
- Production example: `https://YOUR-DOMAIN/api/device/telemetry.php`

Configure the complete URL once in the firmware's private configuration. Do not hardcode a developer LAN address throughout the sketch. Production must use HTTPS.

Firmware 0.6.0 stores the URL, Wi-Fi credentials, AQS-001 device key, and
optional trusted CA in ignored `esp32/AquaSenseSensors/secrets.h`. The committed
`secrets.example.h` contains placeholders. It rejects loopback endpoint URLs,
never prints secrets, and never uses insecure TLS.

## Headers

```http
Content-Type: application/json
Accept: application/json
X-Device-Key: <64-character device credential>
```

`Authorization: Bearer <device credential>` remains supported for existing test firmware. `X-Device-Key` is preferred. Generate or rotate a credential from the administrator Device Details page. AQUASENSE+ displays a new credential once and stores only its SHA-256 hash.

Firmware 0.6.0 uses `X-Device-Key`.

## Request

Required fields:

- `device_id`: registered human-readable code, such as `AQS-001`
- `grease_trap_id`: positive integer for the device's current assignment
- `ultrasonic_distance`: JSON number in centimeters, currently 2–400

Optional fields:

- `waste_level_percent`: device-calculated value from 0–100, stored only for diagnostics
- `temperature`: DS18B20 wastewater temperature in degrees Celsius
- `turbidity`: calibrated SEN0189 NTU; Phase 6 only
- `turbidity_raw`: uncalibrated SEN0189 ADC integer from 0–4095
- `flow_rate`: calibrated YF-S201 liters per minute; omitted until known-volume calibration
- `flow_pulse_count`: YF-S201 pulse count for the current sampling interval
- `ambient_temperature`: AHT2x ambient degrees Celsius
- `humidity`: AHT2x relative humidity percent
- `aqi`: ENS160 AQI from 1–5
- `tvoc_ppb`: ENS160 TVOC
- `eco2_ppm`: ENS160 equivalent CO2
- `gas_value`: calibrated gas value; Phase 6 only
- `gas_raw`: uncalibrated MQ-135 ADC integer from 0–4095
- `odor_value`: separate odor sensor reading
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
- Treat HTTP 400/401/403/404/409/413/415/422 as configuration or payload problems that need correction.

Raw SEN0189 ADC is never stored as NTU, raw MQ-135 ADC is never stored as gas
ppm/value, and AHT2x ambient temperature never overwrites DS18B20 wastewater
temperature. Invalid optional subsystems are omitted instead of sent as zero.

Telemetry retention must be finalized before high-frequency large-scale deployment. Bounded website history queries do not delete readings automatically.

## Firmware 0.7.0 reliability, power, and calibration diagnostics

The ESP32 samples the HC-SR04 independently of the 15-second telemetry schedule.
It creates a request only from a valid filtered physical distance. No Echo,
out-of-range data, insufficient samples, or invalid calibration skips the
request without substituting zero, full, or safe values.

Wi-Fi attempts time out after 15 seconds and use state-based 5/10/30-second
backoff. HTTP connect/read operations time out after five seconds. Valid
snapshots enter an eight-record RAM FIFO and keep their UUID across retries.
HTTP 429 honors `Retry-After`; network/timeouts/5xx retain the oldest record.
HTTP 408 is retryable as a timeout. Other 4xx responses discard the permanently
rejected record after one attempt and pause for 30 seconds rather than causing
a reboot or tight loop.

Phase 7 also reports the declared power mode, reset reason, minimum free heap,
Wi-Fi transition counts, API result classes, queue depth/high-water/drops,
sensor errors, ultrasonic timeouts, and I2C errors/recoveries. The queue is
volatile and is lost on reset/power loss. Firmware has no rail-voltage monitor
and never prints invented 5 V or 3.3 V measurements.
