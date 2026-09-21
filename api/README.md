# Shared API (backend 0.3.0)

Phase 1 owner endpoints and a development ultrasonic test endpoint are implemented. Existing PDO/configuration, users,
roles, establishments, readings, audit and rate-limit records are reused.
Root API access stays denied. api/mobile/.htaccess exposes four owner routes; api/device/.htaccess exposes only telemetry.php.

The API root is deployment-configured. Production clients use the host's HTTPS
URL ending in `/api/mobile`; no server hostname is embedded in PHP or Flutter
source. Native Android requests do not need browser CORS.

| Method | Endpoint | Request | Success data |
| --- | --- | --- | --- |
| POST | login.php | JSON email/password | token, expires_at (UTC), user |
| GET | profile.php | Bearer authorization | user |
| GET | dashboard.php | Bearer authorization | user, establishments, generated_at, freshness_seconds |
| POST | logout.php | Bearer authorization | message |

JSON envelope: {"success":true,"data":{...}} or
{"success":false,"message":"Friendly message"}.

User: id, full_name, email. Establishment: id, business_name, traps.
Trap: id, name, status, device_code (nullable), device_status, is_stale, reading.
Reading is null or contains waste_level_percent, temperature_c, is_simulated,
recorded_at (ISO 8601 UTC). Multiple owned active establishments/traps are returned.

All owner and establishment identity comes from bearer authentication and database
relationships; arbitrary user_id/establishment_id query fields are ignored.
No administrative API, history, surrender, or rewards endpoint is exposed. Device ingestion is limited to the development test route described below.

Authentication: 32 random bytes encoded as hex; only SHA-256 token hashes stored.
A SHA-256 fingerprint of the password hash invalidates tokens on password changes.
Tokens default to 86400 seconds (PHP mobile_token_lifetime_seconds); no refresh
endpoint yet. Login shares website login_attempts throttling by email hash/IP.
Logout removes the token and is idempotent, including expired/disabled accounts.
Every protected request checks account activation, owner role and token expiry.

Statuses are derived in PHP from trap thresholds plus system settings. High/critical
levels take priority over emulsion warnings. Offline/no-data status never implies a
safe reading. The configured freshness_seconds is returned so Flutter can withdraw
freshness while a screen stays open without polling. No status/threshold formulas
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
Ultrasonic test device API (backend 0.3.0)
----------------------------------------
POST `device/telemetry.php` is development-only and returns 404 in production.
Use `Content-Type: application/json` and a separate `Authorization: Bearer <device key>`.
Exact body fields: device_id (registered code), grease_trap_id (integer),
ultrasonic_distance (cm, 2-400), waste_level_percent (0-100), status
(NORMAL/WARNING/CRITICAL). No owner login token or browser CSRF token is used.
The server verifies the credential and assignment and recomputes fill/status
from device_ultrasonic_test_config. Calibration mismatches return 422.
Success is 201; 401/403 authentication/assignment, 413 size, 415 content type,
429 frequency limit, 503 server failure. JSON uses the existing success/data envelope.
Migration 002 is required. Temperature is NULL for an ultrasonic-only reading;
is_test distinguishes bench testing from simulated readings. Mobile dashboard
responses now include ultrasonic_distance_cm and is_test; clients must accept
nullable temperature_c. See ../README.md and ../../aquasense-esp32/README.md.
