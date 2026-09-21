# AQUASENSE+

## Project Overview

AQUASENSE+ is an IoT-based Waste Cooking Oil Monitoring and Overflow Prevention System for grease traps in small food establishments. This repository currently focuses on the **Barangay Administrative Website** for Barangay San Antonio officials and environmental staff.

The administrative website remains at its Phase 1 foundation. Backend version 0.3.0 adds a development-only ultrasonic ingestion endpoint for the sibling ESP32 bench-test sketch. The owner API is used by Flutter (`../aquasense_mobile`, version 0.1.6+7). Physical hardware validation, administrative monitoring modules, and incentive processing remain incomplete.

## Current Version

**Version: 0.3.0**

The website reads its version from `APP_VERSION` in `config/config.php`. See [VERSION.md](VERSION.md) for release notes.

## Production Architecture

XAMPP is only the local development environment. Production deploys this same PHP code and its MySQL/MariaDB database to an always-on public PHP host or VPS:

```text
ESP32 devices -> HTTPS -> PHP API -> MySQL/MariaDB
                                ^
                                |-- Barangay administrative website
                                `-- Carinderia Flutter application
```

Server secrets come from `AQUASENSE_*` environment variables or ignored `config/local.php`; environment variables take precedence. Production mode requires explicit database/storage settings, rejects HTTP, uses secure session cookies, and accepts proxy HTTPS headers only from configured proxy IPs. Upload and log locations are configurable, and the Flutter release receives one HTTPS API root through `API_BASE_URL`. See [DEPLOYMENT.md](DEPLOYMENT.md) for the server, database, Flutter, and future ESP32 release procedure.

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

**Phase 1 — Core Website Foundation.** Authentication, the complete relational schema, and a protected administrative layout are implemented. Later navigation items are labeled “Soon”; they are not working modules.

## Technology Stack

- XAMPP and Apache
- PHP with PDO and native PHP sessions
- MySQL/MariaDB and phpMyAdmin
- HTML5, CSS3, and vanilla JavaScript

No framework, package installation, build pipeline, or additional application server is required. All assets are local; there are no third-party fonts, analytics, or CDN dependencies.

## Requirements

- Windows 10 or Windows 11
- XAMPP with PHP 8.1 or newer and MariaDB 10.4 or newer
- PHP extensions: `pdo_mysql`; `curl` for integration tests
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
8. Import `database/sample-data.sql` the same way, after the schema import succeeds.
9. If your local database credentials differ, copy `config/local.example.php` to `config/local.php` and adjust the settings. Set every development database value in the ignored file; the committed application has no database username, database name, or password defaults.
10. Open [AQUASENSE+](http://localhost/aquasense-web/) and sign in with a development account below.

Import each SQL file **once into a fresh installation**. Imports intentionally do not drop tables or overwrite existing accounts. Back up existing data before migrations; rerunning `schema.sql` against an installed schema will report that tables already exist. The sample import uses a transaction to prevent partially seeded data.

## Development Login

Open [credentials.txt](credentials.txt) locally in your editor for the development account emails, login identifiers, passwords, and separate phpMyAdmin credentials. Apache blocks this file from browser access. The website uses email addresses rather than separate usernames; this reference does not create accounts or update passwords.

**DEVELOPMENT ACCOUNT ONLY — do not use these accounts or credentials in production.** All names and business details are fictional; `.test` email addresses do not receive mail.

| Role | Email | Password |
| --- | --- | --- |
| Barangay Administrator | `admin@aquasense.test` | `AquaSense!2026` |
| Barangay Environmental Staff | `staff@aquasense.test` | `AquaSense!2026` |

`sample-data.sql` contains only PHP `password_hash()` output, not plaintext passwords in user records. Login uses `password_verify()` and rehashes passwords when the configured PHP default changes. The optional mobile development helper creates `owner@aquasense.test` with password `AquaSense!2026` and links fictional Demo Kusina. Owners cannot access the administrative website.

## Owner mobile API (backend 0.2.3)

The existing backend now provides `api/mobile/login.php`, `profile.php`, `dashboard.php`, and `logout.php`. See [API contract](api/README.md). The Flutter project remains in the sibling `aquasense_mobile` folder; it is not inside this Git repository.

For an existing or fresh installation, select the `aquasense` database and import `database/migrations/001-mobile-tokens.sql` after the original schema. It adds the token table. Then apply `database/migrations/002-ultrasonic-test.sql` once for the current dashboard reader (19 tables after both migrations). Do not rerun the full schema on an existing database.

For local development only, run from the website directory:

```powershell
C:\xampp\php\php.exe database\mobile-development.php --allow-demo-data --add-reading
```

This also applies the migration, creates the fictional owner only if absent, links only unclaimed Demo Kusina, and optionally inserts a clearly simulated reading. It refuses conflicting ownership and never resets passwords. Existing production records must not use this demo helper. The helper is CLI-only and the database directory remains blocked by Apache.

Bearer tokens are random, stored only as hashes in MariaDB, and default to a 24-hour expiry (`mobile_token_lifetime_seconds` in PHP configuration). Active owner role, password fingerprint and current establishment ownership are enforced on each request. Staff session authentication remains separate. Only the four named endpoint files are exposed; the API root and documentation remain blocked.

The dashboard derives status and freshness from database configuration. It returns only owned active establishments and their traps, marks simulated/stale records, and never fabricates telemetry when no readings exist. Full monitoring history, alert management, surrender and incentives are future work. Local debug HTTP must be replaced with HTTPS for deployment.

Verify the new API with `C:\xampp\php\php.exe tests\mobile-api.php --allow-local-fixtures` (30 checks); existing website tests still pass (54 checks plus session expiry). Temporary test users, establishments, devices, readings and tokens are removed after the tests.

## Database

- **Name:** `aquasense`
- **Structure:** [database/schema.sql](database/schema.sql), 17 InnoDB tables with foreign keys, indexes, uniqueness constraints, timestamps, and numeric checks.
- **Development records:** [database/sample-data.sql](database/sample-data.sql), three roles, two staff accounts, one fictional establishment, one trap, one simulated device, one assignment, configurable settings, and setup ledger/audit entries.
- **Configuration:** `config/config.php`; optional ignored overrides in `config/local.php`.
- **Connection:** `config/database.php`, PDO, `utf8mb4`, exceptions, and native prepared statements.
- **Time:** stored timestamps use UTC; the interface displays Asia/Manila time.

Environment overrides: `AQUASENSE_DB_HOST`, `AQUASENSE_DB_PORT`, `AQUASENSE_DB_NAME`, `AQUASENSE_DB_USER`, and `AQUASENSE_DB_PASSWORD`. Server environment variables take precedence over `config/local.php`. Production secrets must not be committed to Git.

### Tables and relationships

| Table | Purpose and relationships |
| --- | --- |
| `roles` | One role has many users; administrator, environmental staff, and owner. |
| `users` | Each user has one role. Referenced by submissions, reviews, distributions, account activity, and record authorship. Deactivate rather than delete users with history. |
| `establishments` | One establishment has many grease traps and oil surrenders. Optional `owner_user_id` links the owner account; the recorded owner name supports registration before an account exists. |
| `grease_traps` | Each trap belongs to an establishment and stores capacity and ordered, configurable thresholds. |
| `devices` | Unique device code, optional hashed future API credential, firmware, and last-seen time. No API credentials or hardware are enabled yet. |
| `device_assignments` | Links devices to traps over time. Unique generated columns allow only one current device per trap and one current trap per device. End an assignment before reassigning; retain historical rows. |
| `sensor_readings` | Many readings per assignment. The assignment identifies the device, trap, and establishment without duplicating potentially inconsistent identifiers. Stores measurement time, level classification, and a simulation flag. |
| `alerts` | Belongs to an assignment and optionally a reading; includes type, severity, status, and acknowledging/resolving users. Offline alerts need not have a reading. |
| `oil_surrenders` | Belongs to an establishment and submitting user; optionally references a sensor reading and reviewing user. |
| `oil_surrender_photos` | Many photos per surrender. Stores randomized resulting paths, associated surrender, uploader, and timestamp. Actual upload code is not implemented. |
| `incentive_rules` | Effective-dated, unit-aware oil-to-rice rules. No conversion rate is seeded or assumed as program policy. |
| `incentive_transactions` | References a surrender and rule and stores the awarded amount. A unique surrender foreign key prevents a second reward for the same surrender. |
| `compliance_ledger` | Preserves business events with optional establishment, author, and related record reference. No editing or deletion interface. |
| `audit_logs` | Separate administrative audit trail. Login, failed login, and logout are recorded now; future modules must add their own events. |
| `system_settings` | Named configurable defaults, including an initial emulsion temperature of 40°C. The settings editor is a later phase. |
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
  public/
    index.php                  public entry redirect
    login.php                  login and validation
    logout.php                 CSRF-protected POST logout
    forgot-password.php        account assistance interface
    reset-password.php         clearly unavailable recovery scaffold
  admin/
    index.php                  protected redirect
    dashboard.php              protected Phase 1 overview and account activity
  includes/
    bootstrap.php              configuration, sessions, security headers, errors
    auth.php                   authentication and authorization helpers
    functions.php              escaping, CSRF, URLs, icons, and audit helpers
    header.php / sidebar.php / footer.php
    auth-header.php / auth-footer.php / error.php
  assets/
    css/style.css              responsive layout and component styles
    js/app.js                  password visibility and mobile navigation
    images/                    reserved local assets
  api/mobile/                  owner login/profile/dashboard/logout JSON endpoints
  uploads/surrender-photos/    reserved and blocked from direct HTTP access
  logs/                        protected runtime errors, excluded from Git
  tests/
    foundation.php             HTTP and relational integrity checks
    session-expiry.php         session inactivity verification
```

Protected directories have their own `.htaccess` files. No placeholder PHP endpoints for later modules are published. Keep any future administrative page behind `includes/bootstrap.php` followed by `require_staff()` or `require_roles(['administrator'])` before emitting HTML. Hiding menu items is not an authorization control.

## Features

### Completed

- XAMPP-compatible project structure and main localhost entry point.
- Full relational database foundation and fictional development seed.
- PDO connection and separated local configuration.
- Administrator and environmental staff sign-in with generic invalid-login feedback.
- Session regeneration on login, inactivity expiry, and current database role/activity checks on protected requests.
- Secure POST logout with CSRF checks, session destruction, and old-session rejection.
- Login throttling and account authentication audit events.
- Protected reusable dashboard, header, sidebar, and version footer.
- Responsive desktop, tablet, and mobile CSS; menu toggle, password visibility control, labels, keyboard focus, and skip link.
- Full-page teal/green authentication background with centered, semi-transparent water artwork. The login card uses a 38%-opaque teal surface and a wide, two-column landscape layout, stacking on narrow screens while keeping text fully opaque.
- Compact, centered login layout capped at 1320px on wide monitors, with fluid spacing and typography. The story and card stack at viewport widths of 1024px or less; the card's inner columns stack at 680px or less.
- Screen-edge authentication header and footer: brand at the upper left, administrative tag at the upper center, Barangay information at the lower left, and authorization text at the lower right. Reserved content padding and mobile rows keep these fixed elements clear of the form.
- Dashboard setup status, account details, and current-user authentication history.
- Honest account-recovery interface and reset schema scaffold.
- Installation, security, versioning, and test documentation.

### In Progress / Verification Remaining

- Browser visual review of desktop, tablet, mobile, and keyboard interaction. Browser automation was unavailable in this session.
- Recovery of this machine's pre-existing default XAMPP MariaDB instance; an isolated XAMPP MariaDB instance supports the working local site in the meantime.

### Planned — not implemented

- **Phase 2:** operational summary dashboard; establishment, trap, and device management.
- **Phase 3:** authenticated sensor API, telemetry insertion utility, simulated readings, monitoring history, and charts.
- **Phase 4:** configurable thresholds, warning generation, acknowledgment/resolution, and offline detection.
- **Phase 5:** surrender submissions, secure photo uploads and authorized delivery, human evidence comparison, approval/rejection.
- **Phase 6:** configurable incentive rules, atomic reward processing, distribution, and compliance ledger interface.
- **Phase 7:** report filters, daily/weekly/monthly reports, CSV export, modular future PDF export, and broader audit coverage.
- **Phase 8:** full security, integration, and usability review; account management and secure password recovery delivery.
- The sibling Flutter app now implements Mobile Phase 1. The sibling ESP32 sketch now supports one ultrasonic bench test; physical sensor validation and calibration remain incomplete.

## Sensor Integration

The original sample-data import creates a fictional device assignment without telemetry. The optional CLI mobile-development helper can now add a simulated reading. The owner dashboard reads these shared records, while development ultrasonic ingestion and separate device credentials are now implemented. Administrative monitoring/history, production ingestion, and alert persistence remain future work. Four owner API routes and one development-only device route are exposed.

## Security

- Password hashes and `password_verify()`; automatic hash upgrades on successful login.
- Native PDO prepared statements for user-supplied values; fixed SQL for static schema/introspection operations.
- Strict cookie-only PHP sessions, `HttpOnly`, `SameSite=Lax`, and `Secure` when served over HTTPS. Local `http://localhost` necessarily uses a non-Secure cookie.
- Session ID regeneration after login and a 30-minute configurable inactivity limit.
- Database-backed active-account and role checks for every administrative request.
- CSRF tokens on login and logout; state changes use POST. Invalid tokens return HTTP 403.
- Server-side validation and HTML output escaping, including account names and submitted email values.
- Five sign-in attempts per hashed email or direct client IP within a 15-minute default window; successful attempts remove their own attempt entry. Older entries no longer count; scheduled retention cleanup is a future maintenance task.
- Content Security Policy, no framing, MIME-sniffing protection, and no-store account responses.
- `.htaccess` denial of configuration, includes, SQL, logs, tests, and uploads; directory listing disabled.
- Generic service errors; detailed diagnostics only in protected `logs/php-error.log`.
- Login/logout audit records; logout still ends access if database auditing fails, with an explanatory user notice and protected server log.

No upload handler exists yet. The configurable private storage root and direct-access denial are ready for that future handler; full MIME/extension/size validation, randomized filenames, and authenticated photo serving must be implemented together in Phase 5. The code is portable to a TLS-enabled PHP host, but an actual production launch still requires a selected host/domain, least-privilege database account, backups, monitoring, real accounts, and deployment verification.

## Running AQUASENSE+

Start Apache and MySQL from XAMPP Control Panel, then open **http://localhost/aquasense-web/**. The root directs unauthenticated visitors to sign in. Successful staff login opens `/admin/dashboard.php`. Use the sign-out icon in the top bar to end the session. Returning to the dashboard afterward must redirect to login.

No `npm`, Composer, PHP development server, or other application server is needed.

## Tests

From PowerShell in the project directory, with Apache and the configured database running:

```powershell
& C:\xampp\php\php.exe tests\foundation.php --allow-local-fixtures
& C:\xampp\php\php.exe tests\session-expiry.php
```

`foundation.php` is **development-only**. It creates temporary accounts and a test rate-limit entry in the configured database, rolls back relational constraint fixtures, and removes its test accounts, their audit entries, and throttling entries in `finally`. Anonymous failed-login audit events may remain as a truthful record of the tests. Do not run against production or while other people are actively testing sign-in from the same IP. A forcibly terminated test may require removal of the explicitly named `foundation-*` fixtures.

The 54 integration checks cover SQL seed and constraints, real Apache routes, administrator/staff access, owner/inactive rejection, account revocation, invalid login, CSRF, session regeneration, logout/session replay, escaped output, throttling, private directories, recovery disclosures, and static assets. The separate expiry check verifies the inactivity boundary without waiting 30 minutes.

PHP syntax checks:

```powershell
rg --files -g '*.php' | ForEach-Object { & C:\xampp\php\php.exe -l $_ }
```

Before Phase 2, manually inspect login and dashboard at desktop, tablet, and mobile widths, toggle password visibility, open/close mobile navigation, check keyboard focus, and test sign-out in a real browser. HTTP checks do not substitute for rendered visual review.

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
| No data in monitoring / inactive menu items | Expected in Phase 1. These modules are scheduled for later development. |

### Current machine database workaround

On 2026-09-14, the already-running MariaDB on port **3306** reported pre-existing InnoDB corruption (“log sequence number … is in the future”). The schema import stalled while creating `roles`. The import client was stopped and its query cancellation requested. No existing database files or unrelated databases were repaired, removed, or replaced. An empty/partial `aquasense` database may remain on that original instance; do not assume it is a successful install.

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

The Phase 1 implementation and automated functional checks are complete. Browser visual/usability review remains unverified, and the original XAMPP database problem remains unresolved. Use backend v0.3.0 as a portable foundation prototype, not a completed monitoring system. Recommended next task: verify the rendered Phase 1 screens in a browser and settle the database setup, then implement **Phase 2 — Administrative Core** when authorized.

## Versioning

Use Semantic Versioning: **MAJOR.MINOR.PATCH**. Major versions represent architectural/production-level changes, minor versions add features or complete phases, and patch versions fix defects or small improvements. Versions below 1.0.0 are prototypes.

For every meaningful completed release, update `APP_VERSION` in `config/config.php`, this README's current version, and `VERSION.md` with the date, additions, changes, fixes, and known issues. All PHP pages display the constant; do not hardcode page-specific versions. Do not release 1.0.0 before the defined capstone scope is implemented and tested.

## Flutter Web development CORS (0.2.3)

For local development, set `'mobile_allow_local_web_preview' => true` in the ignored `config/local.php` file. The committed default is `false`. Development then accepts only exact origins matching `http://localhost:<port>` or `http://127.0.0.1:<port>`, where the port is 1 through 65535. The browser may call Apache through the computer's LAN address; origin validation does not depend on the client's source IP.

Allowed preflights return HTTP 204 before database access, bearer authentication, endpoint method checks, or JSON parsing. Responses echo the allowed origin and advertise `GET, POST, OPTIONS` plus `Content-Type, Authorization, Accept`, with `Vary: Origin`. Browser credential cookies and wildcard origins are not enabled.

Production ignores the local-preview switch and accepts only explicitly configured HTTPS origins from `AQUASENSE_MOBILE_WEB_ORIGINS`. Native Android calls have no browser Origin header and are unaffected.

Mobile 0.1.5+6 requires an explicit `API_BASE_URL` for every target. A Flutter Web debug build now identifies likely API URL or CORS/OPTIONS failures without showing server internals; release builds keep a generic connection message.

Verification: `php tests/mobile-cors.php` passed 36 policy and integration checks; the mobile API suite passed 30 checks. Manual preflight from `http://localhost:49840` returned HTTP 204 with the exact required headers, owner login/logout succeeded through the same browser origin, and Flutter Web compiled and launched in headless Chrome on port 49840 with the configured LAN API root. See `api/README.md` for the endpoint contract.
## Single ultrasonic bench test (0.3.0)

The separate `../aquasense-esp32/` project contains an Arduino IDE sketch, HC-SR04 reference wiring, formula, and setup instructions. The physical sensor model still needs confirmation. This test implements only distance/fill telemetry; no extra sensors, battery system, actuator, or administrative monitoring UI was added.

`POST api/device/telemetry.php` accepts the five documented JSON fields with a separate 64-hex bearer device key. Only a registered active device may write to its active assigned trap. PHP validates ranges and the saved calibration, computes the authoritative percentage/status, records server UTC time, and limits successful writes to at most one per two seconds. The endpoint returns 404 outside development mode.

Apply `database/migrations/002-ultrasonic-test.sql` **once**, after migration 001, to the existing database. It makes absent temperature nullable, adds the test flag and WARNING status, and creates `device_ultrasonic_test_config` (19 tables after both migrations). Fresh installations also require both migrations. No separate database or MySQL connection from the ESP32 is used.

On this computer, AQS-001 is assigned to a separate temporary test trap **12** under Demo Kusina. Existing demo readings are preserved. Calibration defaults are empty 30cm, full 5cm, warning 75%, critical 90%; synchronize the firmware and saved server calibration when changing them. Physical samples store `is_test=1`, `is_simulated=0`, and NULL for absent sensors. No physical sensor reading has been claimed; regression fixtures are deleted after tests.

The owner API returns distance, percentage, test marker, and nullable temperature. Flutter 0.1.6+7 supports this payload. The shared PHP reader can be used by the website later. Rebuild older mobile APKs before testing these records.

For another development installation with the demo owner present, provision once with `php database/ultrasonic-test-setup.php --allow-test-fixture --output=../aquasense-esp32/device.local.json`. It creates the separate device/trap and refuses to overwrite an existing device or credential file. Do not rerun it here. Read the ESP32 README for wiring and upload instructions.

Validation: `php tests/ultrasonic-api.php --allow-local-fixtures`. Test credentials stay in ignored files; the firmware folder denies Apache HTTP access. All temporary firmware files, credentials, and test records must remain until the user explicitly requests cleanup. Production deployment must also apply migration 002 for the updated dashboard reader, but must not provision test fixtures.
Verification on 2026-09-21:
- Ultrasonic device integration: 51 checks passed; synthetic fixture readings removed afterward.
- Existing PHP suites: production configuration 12, mobile API 30, CORS 36, foundation 54, plus session expiry passed.
- PHP syntax: 39 files passed. Git whitespace check passed.
- Production-mode CLI probe returned the disabled-test message before any database access.
- Private firmware configuration/metadata returned HTTP 403 and matched Git exclusion patterns.
- Flutter analysis passed and all 28 tests passed. Updated debug APK assembled successfully.
- Physical sensor wiring, calibration, upload, and live end-to-end measurements remain unverified.
