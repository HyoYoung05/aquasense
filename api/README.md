# Shared API (backend 1.0.2)

Owner endpoints, device telemetry, staff monitoring, Phase 4 alerts, Phase 5 oil surrender endpoints, and the Phase 6 owner incentive endpoint are implemented. Existing PDO/configuration, users,
roles, establishments, readings, audit and rate-limit records are reused.
Root API access stays denied. `api/mobile` exposes the owner account, monitoring, and surrender routes; `api/device` exposes only telemetry; and `api/admin` exposes monitoring, history, alert history, and administrator-only alert actions.

The API root is deployment-configured. Production clients use the host's HTTPS
URL ending in `/api/mobile`; no server hostname is embedded in PHP or Flutter
source. Native Android requests do not need browser CORS.

| Method | Endpoint | Request | Success data |
| --- | --- | --- | --- |
| POST | login.php | JSON email/password | token, expires_at (UTC), user |
| GET | profile.php | Bearer authorization | user |
| GET | dashboard.php | Bearer authorization | user, establishments, generated_at, freshness_seconds |
| GET | monitoring.php | Bearer authorization | owned traps, current device/sensor state |
| GET | telemetry-history.php?grease_trap_id= | Bearer; range/page query | owned bounded telemetry history |
| GET | alerts.php | Bearer; status/severity/range/trap/page query | owned counts and bounded alert list |
| GET | alert.php?id= | Bearer | owned read-only alert detail |
| POST | logout.php | Bearer authorization | message |
| POST | oil-surrender.php | Bearer + multipart submission/photo | surrender, idempotent_replay |
| GET | oil-surrenders.php | Bearer; optional status query | owned surrender history |
| GET | oil-surrender.php?id= | Bearer | owned surrender detail |
| GET | oil-surrender-photo.php?id= | Bearer | protected owned image stream |

JSON envelope: {"success":true,"data":{...}} or
{"success":false,"message":"Friendly message"}.

User: id, full_name, email. Establishment: id, business_name, traps.
Trap: id, name, status, device_code (nullable), device_status, is_stale, reading.
Reading is null or contains waste_level_percent, temperature_c, is_simulated,
recorded_at (ISO 8601 UTC). Multiple owned active establishments/traps are returned.

All owner and establishment identity comes from bearer authentication and database
relationships; arbitrary user_id/establishment_id query fields are ignored.
Surrender, incentive, monitoring, and history responses are owner-scoped. Administrative monitoring/history require the existing staff session.

Authentication: 32 random bytes encoded as hex; only SHA-256 token hashes stored.
A SHA-256 fingerprint of the password hash invalidates tokens on password changes.
Tokens default to 86400 seconds (PHP mobile_token_lifetime_seconds); no refresh
endpoint yet. Login shares website login_attempts throttling by email hash/IP.
Logout removes the token and is idempotent, including expired/disabled accounts.
Every protected request checks account activation, owner role and token expiry.

Statuses are derived in PHP from trap thresholds plus system settings. High/critical
levels take priority over emulsion warnings. Offline/no-data status never implies a
safe reading. The configured freshness_seconds is returned so Flutter can withdraw
freshness consistently. No status/threshold formulas
are duplicated in Flutter. Snapshot state is not a substitute for live alarms.

HTTP status codes: 200 success, 400 malformed JSON, 401 invalid authentication,
405 wrong method, 413 oversized payload, 415 wrong content type, 422 invalid input,
429 login throttled, 503 unexpected backend failure. No raw exception details are
returned; they are logged through the existing PHP log configuration.

Database upgrade: import database/migrations/001-mobile-tokens.sql into the existing
aquasense database. Fresh development installs import schema.sql and sample-data.sql first. Apply migration 002-ultrasonic-test.sql once after migration 001 for the current reader.
The token migration is additive and repeatable. Do not reimport the full schema.

Development owner: php database/mobile-development.php --allow-demo-data
Optional --add-reading adds a simulated reading for the guarded Demo Kusina fixture.
Login: owner@aquasense.test / AquaSense!2026 (development only; not a production account).
Tests: php tests/mobile-api.php --allow-local-fixtures

Backend 0.2.1: local browser preview
----------------------------------
config/local.php can enable mobile_allow_local_web_preview for local Flutter web.
Only http://localhost[:port] and http://127.0.0.1[:port] origins from loopback clients
are permitted. JSON/Authorization headers and GET/POST preflights are supported.
No wildcard origin or credentialed cookies. Disable this option for deployment.
Verification: php tests/mobile-cors.php (15 checks).

Production configuration (backend 0.2.2)
----------------------------------------
Set `AQUASENSE_APP_ENV=production`, explicit database and private storage values,
and serve the API through HTTPS. Environment variables take precedence over the
ignored `config/local.php`; no database credentials belong in Git. Production
rejects HTTP and disables local browser preview. Optional Flutter-web origins must
be exact HTTPS origins. See `../DEPLOYMENT.md` and run
`php ../tests/production-config.php` during release validation.

Flutter Web CORS correction (backend 0.2.3)
-------------------------------------------
Development local preview accepts exact `http://localhost:<port>` and
`http://127.0.0.1:<port>` origins for ports 1-65535 without requiring the API
connection itself to arrive from loopback. Valid preflights return 204 before
database/authentication logic and advertise Content-Type, Authorization and Accept.
Production accepts only explicitly configured HTTPS origins; wildcard CORS and browser
credential cookies are never enabled. Safe CORS decision logging runs only in development.
Verification: `php tests/mobile-cors.php --allow-local-fixtures` (36 checks).

## Phase 5 owner surrender API

Oil surrender submission uses normal multipart upload, a required UUID idempotency
key, server-derived ownership, and protected photo storage. Valid types are JPEG,
PNG, and WEBP; the initial limit is 5 MiB. Records start as PENDING, and owners
cannot send or change review status. See `../docs/MOBILE_API.md` for the exact
contract and `../docs/HYBRID_VERIFICATION.md` for the human review model.
Phase 3 device and monitoring APIs (backend 0.5.0)
-------------------------------------------------
POST `device/telemetry.php` works in development and production; production requires
HTTPS. Prefer `X-Device-Key`; compatible Bearer credentials remain accepted. Required
JSON fields are device_id, grease_trap_id, and ultrasonic_distance. Optional fields
cover diagnostic device percentage, device timestamp, UUID/sequence idempotency, and
future temperature/turbidity/flow/gas sensors. The server verifies the active assignment,
calculates percentage/status from trap calibration, stores structured telemetry, updates
last_seen_at, and prevents duplicate retries. See `../docs/ESP32_API.md`.

GET `admin/monitoring.php` returns the latest record for registered traps. GET
`admin/history.php` requires grease_trap_id and supports 1h, today, 24h, 7d, 30d, or a
custom range of at most 31 days with bounded pagination. Both require a current
administrator or environmental-staff session and return 401 when unauthenticated.

Mobile Phase 3 uses `mobile/monitoring.php` and
`mobile/telemetry-history.php`. These routes use the bearer-token owner,
validate any grease-trap selector against that owner's active establishments,
omit calibration/admin fields, and limit history to 50 records per page.

Migration 004 is required after migrations 001-003. The development simulator is a
website page, requires an administrator, returns 404 in production, and sends through
the real device API rather than writing to MySQL directly.
## Phase 4 alert API

`GET /api/admin/alerts.php` returns alert history for an authenticated Barangay
administrator or environmental staff session. `POST /api/admin/alert-action.php`
requires an administrator session, JSON, and the current CSRF token for acknowledge
or resolve actions. Owners receive only active alerts joined to their own
establishments through the existing mobile dashboard response and receive no alert
management controls. Alert state is always calculated by PHP from sensor values.

## Phase 6 owner incentive API

`GET mobile/incentives.php` returns only the authenticated owner's incentive
summary and transaction history. Totals are calculated by PHP and grouped by rice
unit. The endpoint accepts no client reward, rule, status, owner, or establishment
selector and exposes no distribution mutation. See `../docs/MOBILE_API.md` and
`../docs/INCENTIVES.md`.

## Release health endpoint

`GET` or `HEAD` on `api/health.php` checks PHP routing and database connectivity. It returns only `{"status":"ok"}` (200) or `{"status":"unavailable"}` (503); unsupported methods return 405. Production requires HTTPS. Do not use it as an authentication bypass or expose database/version details.

Report generation is limited per staff user and oil-surrender submissions are limited per owner using server-side audit history. A limit response is HTTP 429 with `Retry-After`. Device telemetry retains its minimum-interval rate control. All production client URLs must use the one configured public HTTPS API root.
