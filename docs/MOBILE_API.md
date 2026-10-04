# AQUASENSE+ Owner Mobile API

Backend version: **1.0.2**

The API root is deployment-configured. Production clients must use HTTPS. Every
oil-surrender route requires the existing owner Bearer token:

```http
Authorization: Bearer <64-character-token>
Accept: application/json
```

Tokens identify the owner. The server derives allowed establishments from the
database and never trusts a client-supplied owner or establishment ID.

## Current monitoring

`GET /api/mobile/monitoring.php`

Returns every active grease trap under the bearer-token owner's active
establishments. Each entry contains the establishment/trap labels, assigned
device metadata, backend-derived device and sensor state, stale flag, last seen,
and the latest reading when one exists.

Latest readings may contain `waste_level_percent`,
`ultrasonic_distance_cm`, `temperature_c`, `turbidity_ntu`,
`flow_rate_lpm`, and `gas_value`. Optional uninstalled sensors remain `null`.
Gas values are raw sensor values unless a later calibrated backend contract
states otherwise. Status and percentage rules remain server-side.

## Telemetry history

`GET /api/mobile/telemetry-history.php`

Required query: `grease_trap_id=<positive integer>`. The requested trap must
belong to the authenticated owner; inaccessible traps return 404.

Supported ranges are `1h`, `today`, `24h`, `7d`, `30d`, and `custom`. Custom
ranges require `from=YYYY-MM-DD` and `to=YYYY-MM-DD`, use Asia/Manila calendar
boundaries, and may span no more than 31 days. `page` defaults to 1. Responses
contain at most 50 newest records plus `page`, `pages`, and `total` metadata.
No lifetime/unbounded query is available.

History timestamps use ISO 8601 UTC. Clients parse UTC and format local display
time; they must not add a fixed offset manually. Missing sensor points remain
null and must not be plotted as zero.

## Owner alerts

`GET /api/mobile/alerts.php`

Returns only alerts joined to the bearer owner's active establishments and
grease traps. Filters are `status=UNRESOLVED|ALL|ACTIVE|ACKNOWLEDGED|RESOLVED`,
`severity=ALL|INFO|WARNING|CRITICAL`, `range=today|7d|30d|custom`, optional owned
`grease_trap_id`, and `page`. Custom dates use `from`/`to` in `YYYY-MM-DD` and
may span at most 31 days. Each page contains at most 25 records, server summary
counts, pagination metadata, and the owner's selectable traps.

`GET /api/mobile/alert.php?id=<positive-alert-id>`

Returns one owner-authorized alert with its backend type, label, severity,
status, message, establishment, grease trap, device code, sensor/value,
owner-safe threshold, trigger count, and lifecycle timestamps. Inaccessible
IDs return 404. Both alert routes are GET-only and expose no acknowledge,
resolve, reopen, delete, severity, threshold, device-secret, staff-identity, or
internal resolution-note fields.

Current monitoring entries may include a single `active_alert` preview chosen
by backend severity and recency. Flutter uses its ID to open the detail route;
it does not derive alert state from sensor values.

## Submit an oil surrender

`POST /api/mobile/oil-surrender.php`

Content type: `multipart/form-data`

| Field | Required | Description |
| --- | --- | --- |
| `submission_uuid` | Yes | A new RFC 4122 version 4 UUID retained for retries. |
| `oil_quantity` | Yes | Decimal value greater than zero. |
| `oil_unit` | Yes | `L` or `kg`; no automatic conversion is performed. |
| `photo` | Yes | Valid JPEG, PNG, or WEBP evidence. |
| `notes` | No | Owner note, maximum 2,000 characters. |
| `grease_trap_id` | Conditional | Must belong to the authenticated owner. Required to identify the site when the owner has multiple establishments. |

The photo limit is configured by `oil_surrender_max_upload_bytes`; the initial
value is 5,242,880 bytes (5 MiB). Flutter should compress large images before
upload, but the server always enforces its own limit.

Success returns HTTP 201:

```json
{
  "success": true,
  "data": {
    "surrender": {
      "id": 42,
      "transaction_code": "OS-20260930-A1B2C3D4",
      "oil_quantity": 5,
      "oil_unit": "L",
      "status": "PENDING"
    },
    "idempotent_replay": false
  }
}
```

Retry the same logical submission with the same `submission_uuid`. The server
returns the existing record with HTTP 200 and `idempotent_replay: true` instead
of creating a duplicate.

## Surrender history

`GET /api/mobile/oil-surrenders.php`

Optional query: `status=PENDING`, `UNDER_REVIEW`, `APPROVED`, or `REJECTED`.
Only records submitted by the authenticated owner and still joined to that
owner's establishment are returned.

## Surrender detail

`GET /api/mobile/oil-surrender.php?id=<record-id>`

Returns quantity, unit, owner notes, status, owner-visible review remarks,
timestamps, establishment/trap labels, and a protected photo reference. Changing
the numeric ID cannot expose another owner's record; inaccessible IDs return 404.

## Protected photo

`GET /api/mobile/oil-surrender-photo.php?id=<photo-id>`

The request requires the owner's Bearer token. The PHP controller verifies
ownership before streaming the image with a private, no-store response. Storage
filenames are never used as public URLs.

## Status meanings

- `PENDING`: submitted and awaiting Barangay review.
- `UNDER_REVIEW`: a Barangay reviewer has started Hybrid Verification.
- `APPROVED`: manually approved and eligible for future Phase 6 processing.
- `REJECTED`: manually rejected; the owner-safe response includes review remarks.

## Incentive summary and history

`GET /api/mobile/incentives.php`

Requires the owner Bearer token and accepts no owner or establishment selector.
The backend derives ownership and calculates totals from stored transactions.

```json
{
  "success": true,
  "data": {
    "summary": [
      {"unit": "kg", "earned": 4, "distributed": 0, "pending": 4}
    ],
    "transactions": [
      {
        "transaction_code": "INC-20261001-A1B2C3D4",
        "surrender_code": "OS-20261001-E5F6A7B8",
        "business_name": "Sample Karinderya",
        "oil_quantity": 10,
        "oil_unit": "L",
        "rice_quantity": 4,
        "rice_unit": "kg",
        "status": "CALCULATED",
        "processed_at": "2026-10-01T08:00:00Z",
        "distributed_at": null
      }
    ]
  }
}
```

Summary entries are grouped by reward unit; `kg` and `g` are never combined.
Transaction statuses are `CALCULATED`, `APPROVED_FOR_DISTRIBUTION`,
`DISTRIBUTED`, or `CANCELLED`. Administrative distribution notes and other
owners' records are never returned. This route is read-only; POST and other
state-changing methods return 405.

## Errors

Responses use `{"success":false,"message":"..."}`. Common status codes are 401
for authentication, 404 for inaccessible records, 409 for conflicts, 413 for a
request rejected by the server upload limit, 415 for a non-multipart submission,
422 for validation, and 503 for an unexpected service failure. Raw PHP and SQL
errors are never returned.

Browser CORS preserves the configured exact-origin policy. Preflight supports
GET/POST with `Authorization`, `Content-Type`, and `Accept`; production never uses
a wildcard origin.
