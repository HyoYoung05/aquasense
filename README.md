# AQUASENSE+

## Project Overview

AQUASENSE+ is an IoT-based Waste Cooking Oil Monitoring and Overflow Prevention System for grease traps in small food establishments. This repository contains the **Barangay Administrative Website and PHP backend**, the Flutter Owner application, and the ESP32 test firmware.

The administrative website and PHP backend have completed **Phase 8 Final System Hardening and Production Preparation**. Version 1.3.3 adds verified local IntelliSense configuration for the renamed six-sensor ESP32 sketch while preserving calibrated-only sensor and alert gates, the full-access Admin role, and limited Barangay Administrator/Environmental Staff navigation.

## Current Version

The repository includes the final [ESP32 Hardware Phase 8](esp32/README.md)
firmware **0.8.0** in `esp32/AquaSenseSensors`. It preserves HC-SR04, Wi-Fi, and
retry-safe PHP telemetry, then adds independently valid drivers for DS18B20,
SEN0189, YF-S201, the ENS160+AHT2x combination module, and MQ-135. Raw ADC
values use dedicated database fields and are never mislabeled as NTU or gas
ppm. The 2026-10-22 COM7 follow-up proved live physical telemetry through PHP,
MySQL, website monitoring, and the Flutter monitoring model. Phase 6 centralizes calibration state, keeps
uncalibrated NTU/L/min/ppm unavailable, and gates turbidity/flow alerts until
physical calibration and threshold approval. Hardware Phases 4–8 remain
PARTIAL because YF-S201 is not safely verified, ENS160 is not detected, and
several connected signals require wiring/power stabilization and calibration.
Phase 7 adds nonblocking Wi-Fi backoff, an eight-record bounded RAM FIFO,
explicit HTTP failure classes, retry-stable UUIDs, I2C recovery, fault
counters, and analog rail-fault rejection. The queue covers only brief powered
outages; it is cleared by reset or power loss and drops the oldest record when
full.
COM3 must not be used. There is no microSD, OLED,
buzzer, or DHT22. The Flutter source is tracked under
[`mobile/`](mobile/README.md) at version **0.8.4+18**. Generated APKs,
local API settings, Wi-Fi passwords, and temporary network credentials remain excluded.
The current Android development build is verified with Flutter **3.41.6**.

Hardware Phase 8 keeps the revised paper's local acceptance path: sensors to
ESP32, then a
local Wi-Fi router, then Apache/PHP, MariaDB, website, and Flutter APIs. The
same configurable endpoint supports the separate production HTTPS target.

Phase 8 is the final ESP32 hardware phase. Firmware **0.8.0** keeps the safe local
hardware profile and a DS18B20 development pull-up fix after the six modules
were connected, and applies the useful initialization details from the supplied
combined-sensor sketch without adopting its obsolete GPIO27 flow pin or
uncalibrated engineering-unit formulas. COM7 sends authenticated AQS-001
telemetry to the PHP API.
HC-SR04, DS18B20, SEN0189 raw ADC, AHT2x, and valid MQ-135 raw samples have
reached MySQL; ENS160 and YF-S201 remain isolated pending correction.

The locked GPIO map and target placements are documented without claiming
installation. Physical latch/oil diversion is blocked because the paper has no
approved actuator, driver, GPIO, power, or fail-safe circuit. Automated grease
extraction and backup power are not implemented, and external SMS/push providers
are not configured. See [Final assembly and installation](docs/FINAL_ASSEMBLY.md)
and the [Phase 8 completion report](esp32/PHASE8-TEST-REPORT.md).

Final assembled bench, capstone hardware, and controlled field-test readiness
are **NO** until the physical blockers and required calibration, power,
end-to-end, recovery, and long-run tests are completed. Phase 8 is **PARTIAL**;
there is no Phase 9.

Phase 8 software validation passed 504 sequential PHP/backend checks, Flutter
static analysis, and all 113 Flutter tests. These results do not replace any
physical installation or field-acceptance item.

See the [connected-sensor verification](esp32/PHYSICAL-VERIFICATION-2026-10-22.md)
for the measured results and remaining corrections.

**Version: 1.3.3**

The website reads its version from `APP_VERSION` in `config/config.php`. See [VERSION.md](VERSION.md) for release notes.

## Production Architecture

XAMPP is only the local development environment. Production deploys this same PHP code and its MySQL/MariaDB database to an always-on public PHP host or VPS:

```text
ESP32 devices -> HTTPS -> PHP API -> MySQL/MariaDB
                                ^
                                |-- Barangay administrative website
                                `-- Carinderia Flutter application
```

Server secrets come from `AQUASENSE_*` environment variables or ignored `config/local.php`; environment variables take precedence. Production mode requires an explicit canonical HTTPS URL, database and private storage settings. Browser requests redirect to HTTPS; credential-bearing APIs fail closed on HTTP. Upload/log locations and rate ceilings are configurable, and Flutter receives one HTTPS API root through `API_BASE_URL`. See [Deployment](docs/DEPLOYMENT.md) and the [Production Checklist](docs/PRODUCTION_CHECKLIST.md).

A Tailscale network or temporary tunnel remains useful for development testing, but it is not production hosting because availability would still depend on the development computer.

## GitHub Repository

Repository: [HyoYoung05/aquasense](https://github.com/HyoYoung05/aquasense) (private; requires access).

The source, SQL imports, artwork, tests, and development-only credential reference are versioned. `.gitignore` excludes this machine's `config/local.php`, runtime logs, and uploaded surrender photos. XAMPP services, database contents, Windows startup tasks, and the global phpMyAdmin configuration are not uploaded; follow the installation instructions when setting up another machine. GitHub stores the source code and does not run the PHP/MySQL website through GitHub Pages.

To clone into a new XAMPP installation, run this from `C:\xampp\htdocs` after signing in to GitHub:

```powershell
git clone https://github.com/HyoYoung05/aquasense.git aquasense-web
```

Import the SQL files using the steps below. `.gitattributes` keeps text line endings consistent across machines and preserves the PNG artwork as binary.

## Current Development Phase

**Phase 8 — Final System Hardening and Production Preparation.** The repository is the 1.0.0 production/capstone release package. A real launch still requires an authorized host, domain, TLS certificate, secrets, official thresholds/policy, client builds, physical-device verification, backups, and checklist approval.

### Website access roles

| Role | Sidebar and route access |
| --- | --- |
| Admin | Dashboard, Administrative Core, Operations, and Administration |
| Barangay Administrator | Dashboard and Operations; may perform operational review actions |
| Barangay Environmental Staff | Dashboard and Operations with read-only operational access |
| Carinderia Owner | Flutter owner app only; no administrative website access |

The server enforces the same boundaries on direct URLs. Hiding a sidebar item is not used as an authorization control.

### Phase 2 administrative features

- Dashboard summary cards, grease-trap status, recent alerts, account activity, and vanilla-JavaScript charts use current database values only.
- Admin can create, edit, activate, and deactivate establishments, grease traps, and devices. The interface retains records instead of deleting them.
- Barangay Administrator and Environmental Staff do not receive Administrative Core navigation or direct-route access.
- Threshold validation enforces `low < medium < high < critical <= 100`.
- Device assignment history is retained, and cross-establishment selections are rejected.
- Missing sensor measurements say **No data yet**, **Awaiting device**, or **Awaiting telemetry**.
- Important changes are recorded in `audit_logs` without sensitive form values.

### Phase 3 monitoring features

- `POST api/device/telemetry.php` accepts HC-SR04 distance plus optional future sensor values using a hashed per-device credential.
- Raw ultrasonic distance is authoritative; PHP calculates fill percentage and level state from each grease trap's empty/full calibration and saved thresholds.
- Valid readings update `last_seen_at`; configured freshness determines ONLINE/OFFLINE state throughout the website.
- UUID or sequence identifiers prevent duplicate inserts when an ESP32 retries the same reading.
- Authenticated Monitoring provides live polling every eight seconds, summary/detail views, bounded history filters, pagination, and actual-data charts.
- The Flutter owner API exposes bearer-scoped current monitoring and telemetry
  history without exposing other establishments, calibration settings, or
  device secrets. Owner history is limited to 50 readings per page and supports
  Last Hour through 30 Days plus a 31-day custom range.
- Admin can generate or rotate a device credential. Plaintext appears once; only its SHA-256 hash is stored.
- The development-only Admin simulator calls the same HTTP telemetry endpoint and marks its records as simulated.
- See [ESP32 API contract](docs/ESP32_API.md) for headers, payloads, responses, retry behavior, and interval guidance.

### Phase 4 alert features

- The reusable alert engine evaluates backend-derived level, temperature, turbidity, flow, and device-heartbeat conditions only after telemetry validation.
- Supported types are HIGH_LEVEL, CRITICAL_LEVEL, OVERFLOW_WARNING, OVERFLOW, HIGH_TEMPERATURE, EMULSION_WARNING, HIGH_TURBIDITY, ABNORMAL_FLOW, and DEVICE_OFFLINE.
- One ACTIVE or ACKNOWLEDGED record is retained per assignment and alert type; later readings update its value, timestamp, and trigger count instead of inserting duplicates.
- Recovered sensor conditions are resolved automatically without erasing acknowledgment history. Admin and Barangay Administrator can acknowledge or resolve alerts with an optional note; Environmental Staff retain read-only access.
- Alert Settings manages global temperature, turbidity, flow, overflow, and offline thresholds. Grease-trap level thresholds remain per trap and enforce Low < Medium < High < Critical.
- `php scripts/check_offline_devices.php` performs the database-based offline check and is suitable for cron.
- Dashboard, Monitoring, grease-trap details, device details, authenticated admin APIs, and the owner-safe mobile dashboard expose actual alert records.
- The owner mobile API exposes read-only, bearer-scoped alert summaries,
  25-record history pages, owned detail records, and one active Monitoring
  preview without administrative actions or notes.
- Development simulation still passes through the device telemetry API and exercises the same alert pipeline.
- Push, email, and SMS remain outside Phase 4. The retained Phase 4 suite now passes 35 alert, authorization, CSRF, Compliance Ledger, and integration checks.

The 2026-09-30 AQS-001 development simulation used the real HTTP telemetry endpoint. A normal 60% reading created no alert; 80% created HIGH_LEVEL; 92% created CRITICAL_LEVEL and OVERFLOW_WARNING; a repeated critical reading updated those records; 42 °C created EMULSION_WARNING. The CLI offline check created DEVICE_OFFLINE after the configured timeout, and resumed normal telemetry resolved it. Readings 404 through 409 and their resolved alert history remain marked as simulated.

### Phase 5 oil surrender features

- Authenticated owners submit positive quantities in `L` or `kg` with a UUID idempotency key, optional notes/trap selection, and mandatory multipart photo evidence.
- JPEG, PNG, and WEBP files are checked by upload status, byte limit, extension, MIME detection, and decoded image metadata. Random names are saved under the configurable private storage root.
- Owner history, detail, and photo routes enforce token identity and establishment ownership. Direct access to the upload directory remains denied.
- The Barangay review queue supports status, establishment, date, reviewer, unit, and text filters. Admin and Barangay Administrator can move PENDING submissions to UNDER_REVIEW and then manually APPROVE or REJECT them; rejection requires a reason.
- Hybrid Verification displays owner data, protected photo evidence, and telemetry within a configurable window before and after submission. Missing telemetry is reported honestly and never causes automatic rejection.
- Conditional versioned updates reject stale conflicting decisions. Audit and compliance records are written transactionally for submission, photo, review start, approval, and rejection.
- Approved records become eligible for deliberate Phase 6 incentive processing; approval alone does not award rice.
- See [Owner Mobile API](docs/MOBILE_API.md) and [Hybrid Verification](docs/HYBRID_VERIFICATION.md). The Phase 5 suite passes 39 workflow, upload, ownership, concurrency, and security checks.

### Phase 6 incentive and compliance features

- Admin configures effective-dated `L` or `kg` oil rules with `kg` or `g` rice rewards. No official Sana Oil conversion rate is seeded or assumed.
- `FIXED_PER_THRESHOLD` uses complete threshold blocks and never rounds upward. `FIXED_TRANSACTION` grants one configured reward after the minimum is met.
- Only `APPROVED` oil surrenders are processed. Row locking, an application check, and a unique database constraint prevent duplicate rewards during retries or concurrent actions.
- Each incentive stores its rule and result snapshot, so later rule edits never rewrite historical rewards.
- Distribution requires explicit Admin or Barangay Administrator confirmation and records the distributor, timestamp, optional notes, audit event, and Compliance Ledger event. Phase 6 provides no casual reversal.
- Owner Bearer clients can read unit-grouped earned, distributed, and pending totals plus their own transaction history through `GET /api/mobile/incentives.php`.
- The append-only staff Compliance Ledger records registration, alert, surrender, and incentive lifecycle events and supports event, date, establishment, device, grease-trap, and transaction searches without edit or delete controls.
- Dashboard and establishment details use actual incentive records and preserve unlike units separately.
- See [Incentive processing](docs/INCENTIVES.md), [Compliance Ledger](docs/COMPLIANCE_LEDGER.md), and [Owner Mobile API](docs/MOBILE_API.md).
- The Phase 6 suite passes 41 rule, workflow, authorization, concurrency, rollback, ledger, API, and page integration checks.

### Phase 7 reporting and export features

- The central Reports workspace provides compliance overview, establishment detail, grease-trap monitoring, telemetry, alerts, oil surrender and evidence, incentive, rice distribution, rule history, device, Compliance Ledger, and technical Audit Log reports.
- Report periods use the configured application timezone and support Today, Last 7 Days, Monday–Sunday week, Last 30 Days, This Month, and validated custom ranges.
- Common establishment, trap, device, user, status/event, search, page-size, and whitelisted sort filters persist through pagination and export links.
- CSV exports use standards-based escaping and neutralize spreadsheet formulas. PDF exports use pinned Dompdf 3.1.6 with remote resources and embedded PHP disabled.
- Large HTML results paginate at 25, 50, or 100 rows. CSV is capped at 10,000 rows and PDF detail at 500 rows, with clear truncation notices.
- Oil and rice quantities remain grouped by unit; missing sensor data is shown honestly; downloaded files are point-in-time snapshots and are streamed without permanent storage.
- `admin/compliance-report.php` provides a formal print view, while `admin/audit-logs.php` remains separate from the environmental Compliance Ledger.
- Administrators and environmental staff may read reports. Owners cannot access global reporting or direct export routes. Successful exports are audited.
- See [Reporting and exports](docs/REPORTING.md). The Phase 7 suite passes 34 reporting, validation, authorization, filter, pagination, export, unit, and audit checks.
### Phase 8 release hardening

- Added `development`, `test`, and `production` environments; canonical app URL, timezone, log level, storage, CORS, telemetry, export, and upload controls are configurable without hardcoded production hosts.
- Production uses HTTPS-only browser/API behavior, Secure sessions, HSTS, CSP, Permissions-Policy, proxy allow-listing, exact Flutter Web origins, generic errors, and protected logs.
- Added a minimal database health endpoint, guarded first-administrator CLI, protected account directory, report/upload throttles, and production-safe role reference data with no sample users.
- Reviewed role boundaries, owner isolation, device authentication, uploads, alert/incentive/ledger integrity, query indexes, responsive layout, keyboard focus, reduced motion, empty states, and development-tool gating.
- Added [Architecture](docs/ARCHITECTURE.md), [Security](docs/SECURITY.md), [Deployment](docs/DEPLOYMENT.md), [Backup/Restore](docs/BACKUP_RESTORE.md), [Production Checklist](docs/PRODUCTION_CHECKLIST.md), [Release Notes](docs/RELEASE_NOTES_1.0.0.md), and [Capstone Demo](docs/CAPSTONE_DEMO.md).

## Technology Stack

- XAMPP and Apache
- PHP with PDO and native PHP sessions
- MySQL/MariaDB and phpMyAdmin
- HTML5, CSS3, vanilla JavaScript, and Composer-managed Dompdf 3.1.6

No framework, frontend build pipeline, or additional application server is required. Run `composer install` for the server-side PDF dependency. All browser assets are local; there are no third-party fonts, analytics, or CDN dependencies.

## Requirements

- Windows 10 or Windows 11
- XAMPP with PHP 8.1 or newer and MariaDB 10.4 or newer
- PHP extensions: `pdo_mysql`, `fileinfo`; `curl` for integration tests
- Composer 2 for installing the locked Dompdf PDF dependency
- A modern browser
- Git (optional)
- Apache `AllowOverride All` (the usual XAMPP htdocs configuration) so the included `.htaccess` protections apply

Verified here with Apache 2.4.58, PHP 8.5.5, and XAMPP MariaDB 10.4.32. Browser visual verification is still outstanding because no browser connection was available.

## Installation

**Current computer:** the website is located at `C:\xampp\htdocs\AQUASENSE+\aquasense-web\`. Open **http://localhost/AQUASENSE+/aquasense-web/**. The ignored `config/local.php` sets `base_path` to `/AQUASENSE+/aquasense-web` for this nested location. The standard steps below install directly under `htdocs` and use `/aquasense-web`. The database remains named `aquasense` in both cases.

For a plain-text walkthrough, open [SETUP-INSTRUCTIONS.txt](SETUP-INSTRUCTIONS.txt). It covers downloading the project, XAMPP startup, SQL imports, local settings, both types of login, verification, updates, and troubleshooting.

These are the standard steps for a healthy XAMPP installation. See **Current machine database workaround** below for the existing database problem on the development machine used for this release.

1. Install XAMPP.
2. Copy or clone the project into `C:\xampp\htdocs\aquasense-web\`.
3. Open XAMPP Control Panel and start **Apache**.
4. Start **MySQL**.
5. Open [phpMyAdmin](http://localhost/phpmyadmin/).
6. Create a database named **aquasense**, using `utf8mb4_unicode_ci`.
7. Select **aquasense**, choose **Import**, select `database/schema.sql`, and click **Import/Go**. The SQL creates tables in the database selected by the importer, so production hosts may use their assigned database name without editing the schema.
8. Apply migrations `001-mobile-tokens.sql` through `010-separate-odor-reading.sql` in numeric order.
9. For local development, import `database/sample-data.sql`. For a production-style empty database, import `database/reference-data.sql` instead; it creates roles but no users or passwords.
10. Run `composer install` in the project directory to install the locked PDF dependency.
11. If your local database credentials differ, copy `config/local.example.php` to `config/local.php` and adjust the settings. Set every development database value in the ignored file; the committed application has no database username, database name, or password defaults.
12. Open [AQUASENSE+](http://localhost/aquasense-web/) and sign in with a development account below. For production, follow the first-administrator procedure in [Deployment](docs/DEPLOYMENT.md).

Import each SQL file **once into a fresh installation**. Imports intentionally do not drop tables or overwrite existing accounts. Back up existing data before migrations; rerunning `schema.sql` against an installed schema will report that tables already exist. The sample import uses a transaction to prevent partially seeded data.

## Development Login

Open [credentials.txt](credentials.txt) locally in your editor for the development account emails, login identifiers, passwords, and separate phpMyAdmin credentials. Apache blocks this file from browser access. The website uses email addresses rather than separate usernames; this reference does not create accounts or update passwords.

**DEVELOPMENT ACCOUNT ONLY — do not use these accounts or credentials in production.** All names and business details are fictional; `.test` email addresses do not receive mail.

| Role | Email | Password |
| --- | --- | --- |
| Barangay Administrator | `admin@aquasense.test` | `AquaSense!2026` |
| Barangay Environmental Staff | `staff@aquasense.test` | `AquaSense!2026` |
| Admin | `systemadmin@aquasense.test` | `AquaSense!2026` |

`sample-data.sql` contains only PHP `password_hash()` output, not plaintext passwords in user records. Login uses `password_verify()` and rehashes passwords when the configured PHP default changes. The optional mobile development helper creates `owner@aquasense.test` with password `AquaSense!2026` and links fictional Demo Kusina. Owners cannot access the administrative website.

For an existing development database, apply migrations 009-012 and provision or
repair the demo Admin account with:

```powershell
C:\xampp\php\php.exe database\website-development.php --allow-demo-data
```

The helper refuses non-development environments. It does not create production users.

## Owner mobile API and Flutter app

The backend provides owner authentication, dashboard, monitoring, telemetry
history, alerts, protected oil-surrender evidence, and incentive APIs under
`api/mobile/`. See the [mobile API contract](docs/MOBILE_API.md). The tracked
[Flutter Owner app](mobile/README.md) implements Mobile Phases 1 through 5,
including authenticated oil surrender submission and review-state history.

For an existing or fresh installation, select the `aquasense` database and import migrations 001 through 010 in order. Do not rerun the full schema on an existing database.

For local development only, run from the website directory:

```powershell
C:\xampp\php\php.exe database\mobile-development.php --allow-demo-data --add-reading
```

This also applies the migration, creates the fictional owner only if absent, links only unclaimed Demo Kusina, and optionally inserts a clearly simulated reading. It refuses conflicting ownership and never resets passwords. Existing production records must not use this demo helper. The helper is CLI-only and the database directory remains blocked by Apache.

Bearer tokens are random, stored only as hashes in MariaDB, and default to a 24-hour expiry (`mobile_token_lifetime_seconds` in PHP configuration). Active owner role, password fingerprint and current establishment ownership are enforced on each request. Staff session authentication remains separate. Only the four named endpoint files are exposed; the API root and documentation remain blocked.

The dashboard derives status and freshness from database configuration. It returns only owned active establishments and their traps, marks simulated/stale records, and never fabricates telemetry when no readings exist. Owner surrender and incentive history are available through documented owner-scoped APIs. Local debug HTTP must be replaced with HTTPS for deployment.

Verify the new API with `C:\xampp\php\php.exe tests\mobile-api.php --allow-local-fixtures` (30 checks); existing website tests still pass (54 checks plus session expiry). Temporary test users, establishments, devices, readings and tokens are removed after the tests.

## Database

- **Name:** `aquasense`
- **Structure:** [database/schema.sql](database/schema.sql), 17 InnoDB tables with foreign keys, indexes, uniqueness constraints, timestamps, and numeric checks.
- **Development records:** [database/sample-data.sql](database/sample-data.sql), four roles, three website accounts, one fictional establishment, one trap, one simulated device, one assignment, configurable settings, and setup ledger/audit entries.
- **Configuration:** `config/config.php`; optional ignored overrides in `config/local.php`.
- **Connection:** `config/database.php`, PDO, `utf8mb4`, exceptions, and native prepared statements.
- **Time:** stored timestamps use UTC; the interface displays Asia/Manila time.

Environment overrides: `AQUASENSE_DB_HOST`, `AQUASENSE_DB_PORT`, `AQUASENSE_DB_NAME`, `AQUASENSE_DB_USER`, and `AQUASENSE_DB_PASSWORD`. Server environment variables take precedence over `config/local.php`. Production secrets must not be committed to Git.

### Tables and relationships

| Table | Purpose and relationships |
| --- | --- |
| `roles` | One role has many users; full Admin, Barangay Administrator, Environmental Staff, and owner. |
| `users` | Each user has one role. Referenced by submissions, reviews, distributions, account activity, and record authorship. Deactivate rather than delete users with history. |
| `establishments` | One establishment has many grease traps and oil surrenders. Optional `owner_user_id` links the owner account; recorded owner details and Phase 2 notes support registration before an account exists. |
| `grease_traps` | Each trap belongs to an establishment and stores capacity, ordered thresholds, and optional empty/full ultrasonic calibration. |
| `devices` | Unique human-readable device code, hashed API credential, firmware, activation state, and telemetry-based last-seen time. Plaintext credentials are displayed only when generated. |
| `device_assignments` | Links devices to traps over time. Unique generated columns allow only one current device per trap and one current trap per device. End an assignment before reassigning; retain historical rows. |
| `sensor_readings` | Structured distance, derived/reported fill, optional future sensors, state, device/server times, simulation flags, and retry identifiers. Assignment history identifies the device, trap, and establishment consistently. |
| `alerts` | Belongs to an assignment and optionally a reading; includes type, severity, status, and acknowledging/resolving users. Offline alerts need not have a reading. |
| `oil_surrenders` | Owner-scoped submission with UUID retry protection, optional trap/device and related telemetry, controlled review status, reviewer attribution, decision timestamps, remarks, and concurrency version. |
| `oil_surrender_photos` | Protected evidence metadata: randomized relative path, safe original name, verified MIME, byte size, uploader, and upload time. Images are streamed only after authorization. |
| `incentive_rules` | Effective-dated, unit-aware oil-to-rice rules. No conversion rate is seeded or assumed as program policy. |
| `incentive_transactions` | References a surrender and rule and stores the awarded amount. A unique surrender foreign key prevents a second reward for the same surrender. |
| `compliance_ledger` | Preserves business events with optional establishment, author, and related record reference. No editing or deletion interface. |
| `audit_logs` | Separate administrative audit trail. Login, failed login, and logout are recorded now; future modules must add their own events. |
| `system_settings` | Named configurable defaults, including an initial emulsion temperature of 40°C. The settings editor is operational. |
| `password_resets` | Future single-use reset token hashes, expiry, and use time. Token issuance, redemption, and delivery are not implemented. |
| `mobile_tokens` | Additive migration: hashed mobile bearer sessions, password fingerprint and expiry; each token belongs to a user. |
| `login_attempts` | Shared server-side sign-in throttling by hashed email or client IP within a configured window. |

Business-record foreign keys preserve history and do not cascade-delete records. The additive mobile_tokens table cascades token deletion when its user is removed. Ledger/audit `record_type` + `record_id` pairs are generic references, not foreign keys; future service functions must validate them. These generic columns each contain one value, not bundled records.

Future business logic must additionally enforce same-establishment evidence, reading/alert assignment consistency, effective rule selection, immutable rule versions once used, approved-only incentives, and transactional approval + reward + ledger writes. SQL uniqueness prevents duplicate rewards but does not itself perform approval. Device connectivity and present trap condition will be derived from timestamps and the latest reading. Historical reading classifications will be preserved when thresholds change.

Initial temperature, flow, turbidity, and level defaults are development configuration, not field-validated sensor limits. There are **no sensor readings, generated alerts, surrender transactions, or rice awards** in the seed. A simulated device record is not a connected device.

## Project Structure

```text
aquasense-web/
  .htaccess                    directory and private-file protections
  .gitignore                   excludes secrets, runtime logs, uploaded photos
  index.php                    main localhost entry point
  README.md
  VERSION.md
  SETUP-INSTRUCTIONS.txt       plain-text installation and troubleshooting guide
  credentials.txt              local development login reference; blocked over HTTP
  config/
    config.php                 application version and configuration
    database.php               reusable PDO connection
    local.example.php          optional local settings template
    local.php                  ignored machine-specific overrides, if needed
  database/
    schema.sql                 full website database foundation
    sample-data.sql            fictional development seed
    migrations/003-...sql      additive Phase 2 establishment notes migration
    migrations/004-...sql      Phase 3 telemetry/calibration migration
    migrations/005-...sql      Phase 4 alert migration
    migrations/006-...sql      Phase 5 surrender/review migration
    migrations/007-...sql      Phase 6 incentives/ledger migration
    migrations/008-...sql      Phase 7 reporting index migration
    migrations/009-...sql      full Admin role migration
    migrations/010-...sql      separate odor telemetry migration
  docs/ESP32_API.md            ESP32 request, authentication, and retry contract
  public/
    index.php                  public entry redirect
    login.php                  login and validation
    logout.php                 CSRF-protected POST logout
    forgot-password.php        account assistance interface
    reset-password.php         clearly unavailable recovery scaffold
  admin/
    index.php                  protected redirect
    dashboard.php              real-data operational summary and account activity
    establishments.php         searchable establishment management
    establishment-view.php     establishment details and related records
    grease-traps.php           trap registration and threshold management
    grease-trap-view.php       trap details and basic telemetry history
    devices.php                device registration and assignment management
    device-view.php            device details and assignment history
    monitoring.php             live summary, history, and telemetry charts
    telemetry-simulator.php    development-only API-backed simulator
    placeholder.php            protected later-phase scope notices
  includes/
    bootstrap.php              configuration, sessions, security headers, errors
    auth.php                   authentication and authorization helpers
    functions.php              escaping, CSRF, URLs, icons, and audit helpers
    admin-core.php              Phase 2 validation and transactional services
    telemetry.php               device validation, calibration, ingestion, credentials
    monitoring.php              latest and bounded historical telemetry readers
    header.php / sidebar.php / footer.php
    auth-header.php / auth-footer.php / error.php
  assets/
    css/style.css              responsive layout and component styles
    js/app.js                  password visibility, navigation, confirmations
    js/admin-dashboard.js      database-backed summary charts
    js/admin-forms.js          establishment/trap assignment filtering
    js/monitoring.js           live polling and actual-data history charts
    images/                    reserved local assets
  api/mobile/                  owner login/profile/dashboard/logout JSON endpoints
  api/device/telemetry.php     authenticated ESP32 ingestion endpoint
  api/admin/                   authenticated monitoring/history JSON endpoints
  uploads/surrender-photos/    reserved and blocked from direct HTTP access
  logs/                        protected runtime errors, excluded from Git
  tests/
    foundation.php             HTTP and relational integrity checks
    phase2.php                 Phase 2 authorization and data-integrity checks
    phase3.php                 Phase 3 ingestion and monitoring checks
    session-expiry.php         session inactivity verification
```

Protected directories have their own `.htaccess` files. Later modules use one protected, whitelisted scope-notice page; it performs no unfinished workflow action. Keep future operational pages behind `includes/bootstrap.php` followed by `require_staff()`, and full-access pages behind `require_system_admin()`, before emitting HTML. Hiding menu items is not an authorization control.

## Features

### Completed

- XAMPP-compatible project structure and main localhost entry point.
- Full relational database foundation and fictional development seed.
- PDO connection and separated local configuration.
- Admin, Barangay Administrator, and Environmental Staff sign in with generic invalid-login feedback.
- Session regeneration on login, inactivity expiry, and current database role/activity checks on protected requests.
- Secure POST logout with CSRF checks, session destruction, and old-session rejection.
- Login throttling and account authentication audit events.
- Protected reusable dashboard, header, sidebar, and version footer.
- Responsive desktop, tablet, and mobile CSS; menu toggle, password visibility control, labels, keyboard focus, and skip link.
- Full-page teal/green authentication background with centered, semi-transparent water artwork. The login card uses a 38%-opaque teal surface and a wide, two-column landscape layout, stacking on narrow screens while keeping text fully opaque.
- Compact, centered login layout capped at 1320px on wide monitors, with fluid spacing and typography. The story and card stack at viewport widths of 1024px or less; the card's inner columns stack at 680px or less.
- Screen-edge authentication header and footer: brand at the upper left, administrative tag at the upper center, Barangay information at the lower left, and authorization text at the lower right. Reserved content padding and mobile rows keep these fixed elements clear of the form.
- Dashboard setup status, account details, and current-user authentication history.
- Database-backed operational totals, trap/device states, recent alerts, and summary charts without fabricated readings.
- Admin management for establishments, grease traps, devices, activation state, and device assignment history.
- Barangay Administrator and Environmental Staff access is limited to Dashboard and Operations; operational writes remain limited by role.
- Ordered threshold validation, cross-establishment assignment protection, CSRF checks, escaped output, prepared statements, and management audit events.
- Production-capable device telemetry, hashed credentials, server-side calibration, future-sensor columns, idempotency, rate control, and heartbeat updates.
- Authenticated monitoring/history APIs, live polling, date filters, bounded tables, actual-data charts, device/trap telemetry integration, and development simulation through the real API.
- Honest account-recovery interface and reset schema scaffold.
- Installation, security, versioning, and test documentation.

### In Progress / Verification Remaining

- Browser visual review of desktop, tablet, mobile, and keyboard interaction. Browser automation was unavailable in this session.
- Recovery of this machine's pre-existing default XAMPP MariaDB instance; an isolated XAMPP MariaDB instance supports the working local site in the meantime.

### Planned — not implemented

- **Phase 4 (completed):** configurable thresholds, warning generation, acknowledgment/resolution, and offline detection.
- **Phase 5 (completed):** surrender submissions, secure photo uploads and authorized delivery, human evidence comparison, approval/rejection.
- **Phase 6 (completed):** configurable incentive rules, atomic reward processing, distribution, owner summaries, and Compliance Ledger interface.
- **Phase 7 (completed):** report filters, daily/weekly/monthly/custom reporting, audit review, and CSV/PDF/print exports over indexed operational records.
- **Phase 8 (completed):** security, integration, deployment, performance, accessibility, backup/restore, documentation, and capstone release validation.
- The Flutter Owner app is a Mobile Phase 7 release candidate with functional Authentication, Dashboard, Monitoring, Alerts, Oil Surrender, Incentives, and Profile modules. Final public-domain, production-signing, installed-device, and physical-hardware acceptance remain incomplete.

### Current limitations

- The actual production Sana Oil conversion policy must be supplied and configured by authorized Barangay personnel.
- PDF detail is limited to 500 rows and CSV detail to 10,000 rows; narrower filters are required for complete exports beyond those limits.
- The Flutter Owner app is version `0.8.4+18`. Dashboard Quick Actions appear
  at the top of the owner dashboard, and swiping down refreshes its data.
  Android back gestures return through previously visited tabs before exiting
  from Home.
  Reward calculations and distribution controls remain exclusively in the PHP
  backend/Barangay site.
  See the [mobile production checklist](docs/MOBILE_PRODUCTION_CHECKLIST.md).

## Sensor Integration

The sample-data import creates a fictional device assignment without telemetry.
The device API accepts HC-SR04 distance plus independently valid Phase 4 fields
for DS18B20, SEN0189, YF-S201, ENS160+AHT2x, and MQ-135. Ultrasonic distance is
the authoritative source for PHP-derived fill and status. Raw ADC, calibrated
engineering units, wastewater temperature, ambient temperature, and humidity
remain distinct nullable values. Monitoring/history expose the same database
record to the website and owner API.

## Security

- Password hashes and `password_verify()`; automatic hash upgrades on successful login.
- Native PDO prepared statements for user-supplied values; fixed SQL for static schema/introspection operations.
- Strict cookie-only PHP sessions, `HttpOnly`, `SameSite=Lax`, and `Secure` when served over HTTPS. Local `http://localhost` necessarily uses a non-Secure cookie.
- Session ID regeneration after login and a 30-minute configurable inactivity limit.
- Database-backed active-account and role checks for every administrative request.
- CSRF tokens on login, logout, and every Phase 2 management action; state changes use POST. Invalid tokens return HTTP 403.
- Admin-only establishment, trap, and device access is enforced on the server.
- Device telemetry uses a separate hashed 64-hex credential, active-device and assignment checks, strict JSON/range validation, duplicate protection, configurable rate control, and production HTTPS.
- Monitoring JSON accepts Admin, Barangay Administrator, or Environmental Staff sessions. The simulator requires Admin access and returns 404 in production.
- Server-side validation and HTML output escaping, including account names and submitted email values.
- Five sign-in attempts per hashed email or direct client IP within a 15-minute default window; successful attempts remove their own attempt entry. Older entries no longer count; scheduled retention cleanup is a future maintenance task.
- Content Security Policy, no framing, MIME-sniffing protection, and no-store account responses.
- `.htaccess` denial of configuration, includes, SQL, logs, tests, and uploads; directory listing disabled.
- Generic service errors; detailed diagnostics only in protected `logs/php-error.log`.
- Login/logout audit records; logout still ends access if database auditing fails, with an explanatory user notice and protected server log.

Version 1.0.0 retains protected evidence storage and transactional incentive, audit, and compliance writes while hardening configuration, HTTPS, headers, rate limits, health monitoring, account bootstrap, and deployment procedures. The code is portable to a TLS-enabled PHP host. Actual go-live requires the human production steps in the checklist.

## Running AQUASENSE+

Start Apache and MySQL from XAMPP Control Panel, then open **http://localhost/aquasense-web/**. The root directs unauthenticated visitors to sign in. Successful staff login opens `/admin/dashboard.php`. Use the sign-out icon in the top bar to end the session. Returning to the dashboard afterward must redirect to login.

No `npm`, Composer, PHP development server, or other application server is needed.

## Tests

From PowerShell in the project directory, with Apache and the configured database running:

```powershell
& C:\xampp\php\php.exe tests\foundation.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\session-expiry.php
& C:\xampp\php\php.exe tests\phase2.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\phase3.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\phase4.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\phase5.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\phase6.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\phase7.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\mobile-api.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\mobile-cors.php
& C:\xampp\php\php.exe tests\production-config.php
& C:\xampp\php\php.exe tests\ultrasonic-api.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\phase8.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\fresh-install.php --allow-temporary-database
& C:\xampp\php\php.exe tests\performance.php --allow-local-fixtures
```

`foundation.php` is **development-only**. It creates temporary accounts and a test rate-limit entry in the configured database, rolls back relational constraint fixtures, and removes its test accounts, their audit entries, and throttling entries in `finally`. Anonymous failed-login audit events may remain as a truthful record of the tests. Do not run against production or while other people are actively testing sign-in from the same IP. A forcibly terminated test may require removal of the explicitly named `foundation-*` fixtures.

The 54 foundation checks cover the database, Apache routes, authentication, CSRF, sessions, escaping, throttling, private paths, and relational constraints. The 28 Phase 2 checks cover management and authorization. The 31 Phase 3 checks cover device authentication, validation, calibration, storage, optional sensors, last-seen updates, duplicate protection, monitoring/history, real API-backed simulation, production blocking, credential rotation, and role enforcement. The separate expiry check verifies the inactivity boundary.

PHP syntax checks:

```powershell
rg --files -g '*.php' | ForEach-Object { & C:\xampp\php\php.exe -l $_ }
```

Before deployment, manually inspect authentication, management, monitoring, history charts, credential display, and responsive tables at desktop, tablet, and mobile widths. HTTP checks do not substitute for rendered visual review.

## Troubleshooting

| Issue | What to check |
| --- | --- |
| Apache will not start | Read XAMPP Apache logs and check whether another service already uses port 80 or 443. Stop or reconfigure the conflicting service deliberately. |
| MySQL will not start | Read XAMPP MySQL logs and check its configured port. Back up data before attempting database repair. |
| Port conflict | Match the database port in `config/local.php`; match Apache's configured port in the browser URL. The documented URL assumes Apache port 80. |
| Database not found / missing tables | Create `aquasense`, then import the schema followed by sample data into the same server used by the PHP configuration. |
| Database connection error / service unavailable | Check MySQL is running, host/port/user/password in local settings, and protected `logs/php-error.log`. Raw database errors are intentionally hidden from browser users. |
| Incorrect project directory / 404 | Ensure the root `index.php` is at `C:\xampp\htdocs\aquasense-web\index.php`, not an extra nested folder. Adjust `base_path` if deliberately installed elsewhere. |
| Login failure | Import sample data, use the documented email and case-sensitive password, and ensure the account is active and has a staff role. |
| Too many sign-in attempts | Wait for the configured 15-minute window. Failed attempts share an IP budget even across different browser sessions. |
| Form expired | Reload the login/dashboard page and retry. A stale tab or another sign-out may have invalidated its CSRF token. |
| SQL import reports existing tables | The schema has already been imported or a previous import was partial. Do not overwrite a database containing useful records. Inspect and back up before choosing a fresh database. |
| Private SQL/configuration files accessible through HTTP | Apache is not honoring `.htaccess`. Enable the relevant htdocs override and deny access before exposing the website. |
| No data in monitoring | Verify device assignment, calibration, API URL/key, HTTPS, telemetry response, and the offline checker. Missing data is never fabricated. |

### Current machine database workaround

On 2026-09-14, the already-running MariaDB on port **3306** reported pre-existing InnoDB corruption ("log sequence number … is in the future"). The schema import stalled while creating `roles`. The import client was stopped and its query cancellation requested. No existing database files or unrelated databases were repaired, removed, or replaced. An empty/partial `aquasense` database may remain on that original instance; do not assume it is a successful install.

The working website uses **XAMPP's own MariaDB binary** with a separate fresh data directory:

- Binary: `C:\xampp\mysql\bin\mysqld.exe`
- Data: `C:\xampp\tmp\aquasense-mariadb`
- Server configuration: `C:\xampp\tmp\aquasense-mariadb\my.ini`
- Address: `127.0.0.1:3307`, bound to loopback only
- Local development database: `aquasense`, user `root`, empty local password
- PHP override: ignored `config/local.php` selects host `127.0.0.1` and port `3307`

Both SQL files were imported and the integration checks passed on that fresh MariaDB instance. Apache continues serving the requested **http://localhost/aquasense-web/** URL. A Windows Task Scheduler task named **AQUASENSE Local Database** now starts the isolated database when the current Windows user signs in. It runs with normal user permissions and a hidden PowerShell window, independently of the assistant's process lifetime. It is not a Windows service. The XAMPP Control Panel MySQL button controls the original instance, not this task.

The task starts automatically at Windows sign-in. If it has been stopped, start the already-initialized isolated database using PowerShell:

```powershell
Start-ScheduledTask -TaskName 'AQUASENSE Local Database'
```

If phpMyAdmin reports `HY000/2002` or "target machine actively refused it" while
the **AQUASENSE+ (working database - port 3307)** server is selected, Apache is
running but the isolated database is not listening. The XAMPP MySQL button does
not start this port-3307 instance. Start the scheduled task above, wait a few
seconds, then refresh phpMyAdmin. On 2026-09-26 this exact condition was verified:
the task had been disabled, port 3307 was unreachable, and the original port-3306
instance continued logging its existing InnoDB corruption. The task was enabled,
the isolated server was started, and direct access to `aquasense.roles` succeeded.

On 2026-09-30 the same refusal recurred because the scheduled task was enabled but
its MariaDB process was no longer running. The existing task was started again and
verified in the `Running` state, listening only on `127.0.0.1:3307`. A direct query
opened the `aquasense` database and returned all 19 tables, and phpMyAdmin responded
successfully. No database files were recreated or imported.

Do not reinitialize the data directory or repeat the imports. To shut down **only this isolated database**:

```powershell
& C:\xampp\mysql\bin\mysqladmin.exe -h 127.0.0.1 -P 3307 -u root shutdown
```

On this machine, phpMyAdmin now defaults to server entry **1**, labeled **AQUASENSE+ (working database - port 3307)**. Entry **2** is an alternate link to the same working server so previously shared links still work. The original port 3306 configuration is preserved as entry **3**, labeled **Original XAMPP (port 3306 - database repair needed)**. Open **http://localhost/phpmyadmin/index.php?server=1&db=aquasense**, and use the local database username `root` with the password left empty. These are database credentials, not the website's administrator login. Refresh old tabs after changing connections. The tables and sample data on port 3307 already exist; do not repeat the imports. The original phpMyAdmin configuration was backed up under `C:\xampp\tmp\aquasense-config-backups` before each change. This connection selects the healthy database; it does not repair the damaged original instance. Command-line access to the working copy is also available:

```powershell
& C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root aquasense
```

On a healthy XAMPP installation use the standard import workflow above and omit this machine-specific override. Once the original database environment is repaired and the intended data migrated, update/remove `config/local.php` deliberately to return to port 3306. Repairing the existing XAMPP databases is separate work.

## Development Status

Phase 8 Final System Hardening and Production Preparation is complete in backend v1.0.0. No Phase 9 is defined in this repository.

## Versioning

Use Semantic Versioning: **MAJOR.MINOR.PATCH**. Major versions represent architectural/production-level changes, minor versions add features or complete phases, and patch versions fix defects or small improvements. Versions below 1.0.0 are prototypes.

For every meaningful completed release, update `APP_VERSION` in `config/config.php`, this README's current version, and `VERSION.md` with the date, additions, changes, fixes, and known issues. All PHP pages display the constant; do not hardcode page-specific versions. Do not release 1.0.0 before the defined capstone scope is implemented and tested.

## Flutter Web development CORS (0.2.3)

For local development, set `'mobile_allow_local_web_preview' => true` in the ignored `config/local.php` file. The committed default is `false`. Development then accepts only exact origins matching `http://localhost:<port>` or `http://127.0.0.1:<port>`, where the port is 1 through 65535. The browser may call Apache through the computer's LAN address; origin validation does not depend on the client's source IP.

Allowed preflights return HTTP 204 before database access, bearer authentication, endpoint method checks, or JSON parsing. Responses echo the allowed origin and advertise `GET, POST, OPTIONS` plus `Content-Type, Authorization, Accept`, with `Vary: Origin`. Browser credential cookies and wildcard origins are not enabled.

Production ignores the local-preview switch and accepts only explicitly configured HTTPS origins from `AQUASENSE_MOBILE_WEB_ORIGINS`. Native Android calls have no browser Origin header and are unaffected.

Mobile 0.1.5+6 requires an explicit `API_BASE_URL` for every target. A Flutter Web debug build now identifies likely API URL or CORS/OPTIONS failures without showing server internals; release builds keep a generic connection message.

Verification: `php tests/mobile-cors.php` passed 36 policy and integration checks; the mobile API suite passed 30 checks. Manual preflight from `http://localhost:49840` returned HTTP 204 with the exact required headers, owner login/logout succeeded through the same browser origin, and Flutter Web compiled and launched in headless Chrome on port 49840 with the configured LAN API root. See `api/README.md` for the endpoint contract.
## ESP32 Hardware Phase 7 reliability and fault recovery (0.7.0)

The tracked `esp32/AquaSenseSensors` Arduino IDE sketch is mirrored to the
ignored private build directory without copying its secrets into Git. Firmware
sends valid sensor snapshots to PHP; it never receives database credentials or
connects directly to MySQL. The contract is in
[the ESP32 API guide](docs/ESP32_API.md).

`POST api/device/telemetry.php` is available in development and production. Production HTTPS enforcement applies. Send `Content-Type: application/json`, `X-Device-Key`, device code, assigned trap ID, and ultrasonic distance. Existing Bearer-header firmware remains compatible. PHP validates the credential, active assignment, configurable range, optional fields, timestamp window, and rate limit. It calculates fill and state from grease-trap calibration, records UTC device/server timestamps, and updates `devices.last_seen_at` transactionally.

Migration 011 adds nullable turbidity raw ADC, flow pulses, MQ-135 raw ADC,
AHT2x ambient temperature/humidity, and ENS160 AQI/TVOC/eCO2 fields. Raw ADC is
never stored as calibrated NTU or gas ppm. Migration 012 keeps turbidity and
flow alerts disabled until calibration and threshold approval. History requests remain bounded to
100 rows per page and at most 31 custom days.

On this computer, AQS-001 remains assigned to test trap 12. Sensor-absent Phase
6 verification preserved its baseline and sent no
fake reading. The external-supply and full-array physical tests remain pending.

Firmware 0.7.0 declares USB development mode until external DC is verified. It
reports reset reason, minimum heap, Wi-Fi transitions, and telemetry outcomes,
but does not pretend to measure rail voltage. The approved primary architecture
and blocked test record are in [POWER.md](docs/POWER.md). Calibration status,
procedures, and blank evidence tables are in [CALIBRATION.md](docs/CALIBRATION.md).

Wi-Fi connection attempts are state-based, time-limited, and backed off at
5/10/30 seconds while sensor tasks continue. Valid snapshots enter a fixed
eight-record RAM queue. The oldest queued UUID is retried first; 429 and
temporary network/5xx failures retain it, while a permanent 4xx rejection is
discarded after one attempt and paced before another upload. PHP's existing
`reading_uuid` and payload-hash logic prevents duplicate database inserts after
a lost response.

The queue is not persistent storage. At the 15-second capture interval it holds
about two minutes before its drop-oldest policy begins, and any reset or power
loss clears it. No microSD, OLED, buzzer, or unspecified battery circuit was
introduced. Individual sensor validity remains isolated, I2C recovery is
paced, and invalid readings never become fake zero telemetry or alerts. See
[RELIABILITY.md](docs/RELIABILITY.md) and the
[Phase 7 report](esp32/PHASE7-TEST-REPORT.md). Physical outage, latency,
stress, power-cycle, and multi-hour tests remain pending, so Phase 7 is
**PARTIAL**. Phase 8 is final physical assembly, enclosure, installation, and
end-to-end field testing after those blockers clear.

On 2026-10-18, firmware 0.7.0 was hash-verified and uploaded to the authorized
COM6 ESP32. A sensor-absent diagnostic reached 206.243 seconds with no observed
reset, automatically recovered from two controlled Wi-Fi disconnects, and kept
free heap stable. One measured reconnect took 31.2 seconds because the manager
first exhausted the timeout for an unavailable configured network. Physical
sensor, final-power, queue-drain, latency, stress, and long-run acceptance tests
remain pending.

The administrator simulator is available only in development and requires a device credential. Device keys can be generated or rotated from Device Details, appear once, and are never stored as plaintext. The simulator calls the HTTP endpoint and does not insert into MySQL directly.

Validation includes the retained Phase 1–7, mobile, CORS, session, production configuration, and ultrasonic suites plus `php tests/phase8.php --allow-local-fixtures` and `php tests/fresh-install.php --allow-temporary-database`. See [TEST_REPORT.md](docs/TEST_REPORT.md) for the exact release run. Test credentials remain excluded from Git.
