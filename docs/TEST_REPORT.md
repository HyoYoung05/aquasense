# AQUASENSE+ 1.0.0 Test Report

Date: 2026-10-02  
Environment: Windows development workstation, Apache 2.4.58, PHP 8.5.5,
MariaDB 10.4.32 on isolated loopback port 3307  
Scope: Phase 8 website/PHP backend release candidate

## Automated result

All **470 checks passed**:

| Suite | Checks |
| --- | ---: |
| Foundation/auth/database/private routes | 54 |
| Session expiry boundary | 1 |
| Phase 2 administrative core | 29 |
| Phase 3 telemetry/monitoring | 31 |
| Phase 4 alerts/offline lifecycle | 35 |
| Phase 5 surrender/upload/Hybrid Verification | 39 |
| Phase 6 incentives/Compliance Ledger | 41 |
| Phase 7 reports/CSV/PDF/audit | 34 |
| Owner mobile API/isolation/tokens | 30 |
| Flutter Web CORS/preflight | 36 |
| Production configuration | 13 |
| ESP32 ultrasonic API/data path | 52 |
| Phase 8 hardening/integration/routes/indexes | 51 |
| Disposable fresh installation/bootstrap | 16 |
| Performance history/polling/report pagination | 8 |
| **Total** | **470** |

All 92 PHP files passed `php -l`. `composer validate --strict` passed and
`composer audit --locked` reported no known advisories. `git diff --check`
passed. No tracked dotenv/local config, private-key, certificate, or dump-pattern
files were found.

## End-to-end coverage

The retained suites exercise administrator/staff/owner authentication and denial,
CSRF/logout/session expiry, CRUD and assignment integrity, ESP32 credential and
telemetry ingestion, calibration and server-derived status, current/history
monitoring, threshold alerts, acknowledgment/resolution, offline/recovery, owner
token lifecycle and isolation, protected evidence upload/read, Hybrid Verification,
manual surrender decisions, incentive rules/calculation/distribution, immutable
snapshots, Compliance Ledger, Audit Log, report filters/pagination, formula-safe CSV,
valid PDF output, CORS preflight, production config, and private-route denial.

The Phase 8 suite additionally requests all principal authenticated web routes,
health and static assets through Apache; verifies administrator-only User Accounts;
checks security headers; tests export/upload budgets; confirms key query indexes and
latest-telemetry EXPLAIN selection; reruns migration 008 without changing user rows;
and verifies that production reference data has no accounts or passwords.

The fresh-install suite creates a random temporary database, imports `schema.sql`,
migrations 001-008 and `reference-data.sql`, verifies 19 InnoDB tables and foreign
keys/indexes, confirms zero sample users, provisions exactly one administrator with
the guarded CLI, verifies its audit record, then drops the database.

## Performance sample

A disposable test inserted 10,000 telemetry rows and 2,000 resolved alerts for one
isolated assignment, then removed all fixtures.

| Operation | Local elapsed time |
| --- | ---: |
| 100 repeated latest-reading polls | 0.0195 s |
| First page/count over 10,000 telemetry rows | 0.0464 s |
| Deep telemetry page (page 100) | 0.0445 s |
| First page/count over 2,000 alert rows | 0.0155 s |

The latest-reading plan selected the assignment/time index. These local numbers are
regression evidence, not a production capacity guarantee. The selected host must be
load-tested with its real CPU, storage, network, PHP worker count, database settings,
device count, polling interval, and retention volume.

## Not executable without production inputs

This workstation run cannot prove public DNS/TLS renewal, hosting availability,
off-host backups, production cron/monitoring, Android signing/distribution, behavior
over a real public network, official threshold/incentive policy, or final physical
ESP32 placement/power/waterproofing/calibration. Those are explicit human acceptance
items in `PRODUCTION_CHECKLIST.md`.
