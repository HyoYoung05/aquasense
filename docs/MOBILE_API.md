# AQUASENSE+ Owner Mobile API

Backend version: **0.7.0**

The API root is deployment-configured. Production clients must use HTTPS. Every
oil-surrender route requires the existing owner Bearer token:

```http
Authorization: Bearer <64-character-token>
Accept: application/json
```

Tokens identify the owner. The server derives allowed establishments from the
database and never trusts a client-supplied owner or establishment ID.

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

Phase 5 does not calculate or distribute rice incentives.

## Errors

Responses use `{"success":false,"message":"..."}`. Common status codes are 401
for authentication, 404 for inaccessible records, 409 for conflicts, 413 for a
request rejected by the server upload limit, 415 for a non-multipart submission,
422 for validation, and 503 for an unexpected service failure. Raw PHP and SQL
errors are never returned.

Browser CORS preserves the configured exact-origin policy. Preflight supports
GET/POST with `Authorization`, `Content-Type`, and `Accept`; production never uses
a wildcard origin.
