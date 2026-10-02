# AQUASENSE+ Capstone Demo

## Preparation

Use a staging/development environment containing only fictional records. Start PHP,
MariaDB, the offline checker, one ESP32 with the ultrasonic sensor, and the Android
owner app. Confirm `GET /api/health.php` is OK. Keep a second network available to
show that deployed HTTPS access does not depend on the developer LAN.

## Suggested 15-minute sequence

1. Show the architecture: ESP32 and Flutter send HTTPS to PHP; only PHP accesses
   MySQL/MariaDB. State that XAMPP is the local tool, not production architecture.
2. Sign in as Barangay administrator and show the role-protected dashboard, user
   directory, establishments, grease traps, devices, and threshold configuration.
3. Register or open a fictional establishment. Show its trap calibration and unique
   assigned device.
4. Move a surface under the HC-SR04. Show raw/filtered Serial readings, the HTTPS
   response, database-backed Monitoring update, and history chart.
5. Cross warning/critical thresholds. Show active alert creation, repeat-trigger
   count, acknowledgment, resolution, and Compliance Ledger/audit separation.
6. Stop telemetry long enough for the scheduled offline checker, then resume it and
   show recovery.
7. Sign in to Android as the fictional owner over a separate internet connection.
   Show owner-scoped dashboard data and submit fictional oil evidence.
8. In the staff website, open Hybrid Verification. Explain that telemetry supports
   human review and missing telemetry is reported rather than invented.
9. Approve the surrender, apply a clearly labelled test incentive rule, confirm rice
   distribution, and show the immutable calculation snapshot and ledger events.
10. Filter a report, download spreadsheet-safe CSV and PDF, and show the technical
    Audit Log.
11. Close with the production checklist, encrypted backup/restore process, health
    monitoring, and known limitations.

## Evidence to capture

Capture the device serial response, telemetry row, alert transition, owner isolation
test, surrender/photo authorization, incentive snapshot, ledger events, CSV/PDF,
complete automated-test summary, production config check, and fresh-install result.
Do not expose real passwords, bearer/device keys, environment values, private paths,
or owner personal data in screenshots or presentation recordings.

## Failure fallback

If the physical sensor or network fails, explain the actual failure and use only the
development simulator, visibly labelled as simulated. Do not present simulated or
sample records as live field evidence.
