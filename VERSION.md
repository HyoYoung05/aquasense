# AQUASENSE+ Version History

## Current Version

0.9.0

## 0.9.0

Date: 2026-10-02

Development Phase: Phase 7 — Reporting, Export, and Audit Review

### Added

- Added the central Reports interface with compliance overview, establishment,
  grease-trap, telemetry, alert, surrender, incentive, rice distribution, rule,
  device, Compliance Ledger, and Audit Log reports.
- Added Today, Last 7 Days, Monday–Sunday week, Last 30 Days, This Month, and
  validated custom date periods using the configured application timezone.
- Added common filters, whitelisted sorting, 25/50/100 row pagination, unit-aware
  summaries, telemetry aggregates, honest missing-data states, and filter persistence.
- Added streamed CSV export with spreadsheet formula neutralization and streamed PDF
  export through pinned Dompdf 3.1.6 with remote resources and embedded PHP disabled.
- Added formal compliance and print layouts, export auditing, migration 008, reporting
  documentation, and a 34-check Phase 7 regression suite.

### Changed

- Dashboard and navigation now link directly to reporting and the monthly compliance
  view. Application and displayed version advanced from 0.8.0 to 0.9.0.
- Production deployment now installs Composer dependencies for PDF generation.

### Security and integrity

- Reports and direct export routes independently require administrator or
  environmental-staff authentication. Owner and anonymous access remains denied.
- Report type, identifiers, dates, page sizes, and ordering are validated or
  whitelisted. SQL values use prepared statements.
- CSV/PDF files are generated in memory and streamed; no report contents or secrets
  are persisted. Audit and Compliance Ledger data remain separate and read-only.

### Database and validation

- Backed up the development database before migration 008 to
  `C:\xampp\tmp\aquasense-config-backups\pre-phase7-20261002-131029.sql`.
- Migration 008 adds the justified date-only `audit_logs(created_at)` index. Existing
  telemetry, alert, surrender, incentive, and ledger reporting indexes were retained
  because equivalent indexes already exist.
- Phase 7 passes 34 dedicated checks. The retained Phase 1–6, mobile, CORS, session,
  production configuration, and ESP32 tests are rerun for the release.

### Known Issues

- PDF detail is intentionally limited to 500 rows and CSV to 10,000 rows; narrow the
  period or filters for a complete large raw dataset.
- Final security hardening, end-to-end/load testing, accessibility and UI review,
  deployment checks, documentation cleanup, and capstone release preparation remain
  Phase 8.
- Physical sensor coverage and final production hosting may still be incomplete.

## 0.8.0

Date: 2026-10-01

Development Phase: Phase 6 — Sana Oil Incentives and Digital Compliance Ledger

### Added

- Added administrator-managed, effective-dated incentive rules with explicit test
  labels, overlap validation, `FIXED_PER_THRESHOLD` and `FIXED_TRANSACTION` modes.
- Added atomic APPROVED-surrender processing with stored rule snapshots, visible
  incentive codes, application/database duplicate protection, and row locking.
- Added explicit rice distribution confirmation with distributor, UTC timestamp,
  notes, optimistic concurrency protection, audit history, and no reversal UI.
- Added owner-only `GET /api/mobile/incentives.php` summaries and transaction history,
  with unlike rice units retained as separate totals.
- Added staff incentive queue/list/detail pages, rule management, establishment
  incentive history, dashboard metrics, and append-only Compliance Ledger views.
- Extended registration and alert workflows to append major environmental events
  to the same immutable ledger without duplicating technical audit logs.
- Added migration 007, `docs/INCENTIVES.md`, `docs/COMPLIANCE_LEDGER.md`, updated
  mobile API documentation, and the Phase 6 regression suite.

### Changed

- Dashboard now reports approved oil awaiting processing, rewards pending
  distribution, and actual distributed rice grouped by unit.
- Establishment details now show eligible approved oil, reward state totals, and
  transaction history.
- Application and displayed version advanced from 0.7.0 to 0.8.0.

### Security and integrity

- Browser mutations retain staff authorization, administrator-only management,
  CSRF checks, server validation, prepared statements, and explicit confirmation.
- Incentive creation and distribution each commit the business record, audit event,
  and deduplicated compliance event in one database transaction.
- Owner responses derive identity from the Bearer token and omit internal notes.

### Database and validation

- Backed up the working development database before migration 007 to
  `C:\xampp\tmp\aquasense-config-backups\pre-phase6-20261001-161135.sql`.
- Migration 007 extends the existing incentive and ledger tables without dropping
  Phase 1-5 records and applies safely more than once.
- No official or sample conversion rule is inserted automatically.
- The Phase 6 suite passes 41 calculation, authorization, owner isolation,
  concurrency, rollback, audit, ledger, API, and staff-page checks; all retained
  foundation, Phase 2-5, mobile, CORS, session, production, and ESP32 suites pass.

### Known Issues

- The actual production Sana Oil conversion rule requires official Barangay policy
  and administrator configuration.
- Full reporting, CSV export, and PDF reporting remain Phase 7.
- The separate Flutter UI may still need updates to display all owner incentive data.

## 0.7.0

Date: 2026-09-30

Development Phase: Phase 5 — Oil Surrender and Hybrid Verification

### Added

- Added authenticated owner multipart submission, UUID idempotency, owner history,
  record detail, and protected photo streaming APIs.
- Added JPEG, PNG, and WEBP evidence validation using upload state, byte limit,
  extension, detected MIME type, and decoded image metadata.
- Added the Barangay review queue, protected evidence preview, configurable sensor
  evidence window, before/after telemetry comparison, and compliance history.
- Added controlled PENDING, UNDER_REVIEW, APPROVED, and REJECTED transitions with
  reviewer attribution, timestamps, required rejection reasons, and optimistic
  concurrency protection.
- Added migration 006, owner mobile API documentation, Hybrid Verification
  documentation, and a 39-check Phase 5 suite.

### Changed

- Dashboard now shows pending, under-review, approved, rejected, unit-separated
  submitted quantities, and recent oil surrenders from actual records.
- Establishment and grease-trap details now link to relevant surrender history.
- System Settings now controls the evidence upload limit and telemetry review window.
- Application and displayed version advanced from 0.6.0 to 0.7.0.

### Fixed

- Submission retry can no longer create duplicate records for the same owner UUID.
- Owners cannot select or view another owner's establishment, surrender, or photo.
- A stale reviewer cannot overwrite a final decision made from another session.
- Failed submission transactions remove any evidence file already moved to storage.

### Security

- Evidence storage remains configurable and directly denied over HTTP; authorized
  PHP controllers stream files after owner or staff checks.
- Filenames are random, paths reject traversal, executable/disguised files are
  rejected, and raw PHP/SQL errors never enter API responses.
- Submission, photo, review, approval, rejection, and settings actions are audited;
  major workflow events also enter the compliance ledger without photo binary data.

### Known Issues

- Rice incentive calculation and rice distribution are not implemented; they remain Phase 6.
- Photo verification is manual and contains no AI authenticity decision.
- Sensor evidence supports review but does not prove the claimed oil quantity.
- Flutter UI changes are outside this website/backend phase.

### Database and validation

- Backed up the working development database before migration 006 to
  `C:\xampp\tmp\aquasense-config-backups\pre-phase5-20260930-204522.sql`.
- Migration 006 extends existing surrender/photo tables and preserves all earlier data.
- Migration 006 applies safely more than once on the development MariaDB instance.
- Phase 5 passes 39 upload, authorization, idempotency, review, telemetry, audit,
  compliance, and concurrency checks. Existing suites are retained for regression.

## 0.6.0

Date: 2026-09-30

Development Phase: Phase 4 — Alert and Threshold Management

### Added

- Added reusable alert evaluation for level, overflow, temperature, emulsion,
  turbidity, flow, and device-offline conditions.
- Added deduplicated ACTIVE, ACKNOWLEDGED, and RESOLVED state with trigger counts,
  current values, thresholds, attribution, resolution notes, and preserved history.
- Added threshold settings, alert list/detail pages, protected admin APIs, monitoring
  badges, detail integrations, and a cron-compatible offline checker.
- Added migration 005 and a 32-check Phase 4 suite.

### Changed

- Valid telemetry evaluates alerts transactionally after storage and heartbeat update.
- Dashboard, Monitoring, device, grease-trap, and owner-safe mobile data now use
  actual alert records.

### Fixed

- Repeated critical or offline evaluations no longer create duplicate active alerts.
- Returning telemetry resolves DEVICE_OFFLINE automatically.

### Known Issues

- Flutter push, email, and SMS notifications are not implemented.
- Physical turbidity, flow, and gas sensors may not yet be connected.
- Production must schedule the offline checker with cron or an equivalent scheduler.
- Full oil surrender and photo verification remain Phase 5.

### Database and validation

- Backed up the working database before migration 005 to
  `C:\xampp\tmp\aquasense-config-backups\pre-phase4-20260930-194630.sql`.
- Migration 005 preserves Phase 1-3 records and extends the existing alerts table.
- AQS-001 manual API cases passed for normal, high, critical, repeated critical,
  emulsion, offline, and resumed conditions. Resume resolved DEVICE_OFFLINE; after
  the device again exceeded the timeout with no new reports, the checker correctly
  restored one active DEVICE_OFFLINE alert.
- Phase 4 passes 32 checks. Production, foundation, session, CORS, mobile,
  ultrasonic, Phase 2, and Phase 3 regression suites also pass.

## 0.5.0

Date: 2026-09-27

Development Phase: Phase 3 — Sensor Telemetry and Monitoring

### Added

- Added a production-capable ESP32 telemetry endpoint with `X-Device-Key` or
  compatible Bearer authentication, active assignment checks, strict JSON/range
  validation, configurable rate control, safe errors, and transactional storage.
- Added per-grease-trap empty/full ultrasonic calibration. Raw HC-SR04 distance is
  authoritative; PHP calculates the stored fill percentage and level state.
- Added optional temperature, turbidity, flow, and gas ingestion while allowing
  absent future sensors to remain NULL.
- Added device/server timestamps, device-reported diagnostic percentage, UUID or
  sequence idempotency, payload conflict detection, and telemetry indexes.
- Added session-protected latest-monitoring and historical JSON endpoints, an
  eight-second polling page, summary/detail views, bounded date filters,
  pagination, and actual-data waste-level/distance charts.
- Added device credential generation/rotation with one-time plaintext display,
  latest reading/count/credential status on device details, and current telemetry,
  calibration, and assignment-spanning history on grease-trap details.
- Added an administrator-only development simulator that calls the same HTTP API
  used by ESP32 devices and is unavailable in production.
- Added `docs/ESP32_API.md`, migration 004, and a 31-check Phase 3 suite.

### Changed

- Dashboard device states now use the configured database freshness timeout and
  latest real telemetry. Monitoring values are never fabricated.
- The prior development ultrasonic endpoint is now the portable Phase 3 endpoint;
  production uses the same PHP code behind mandatory HTTPS.
- AQS-001 calibration was preserved on trap 12. The manual 12.4 cm API-path test
  created simulated reading 354 at 70.4% MEDIUM and updated its heartbeat.
- Application, sidebar, footer, setup guide, API guide, README, and schema now
  identify version 0.5.0 and the completed Phase 3 scope.

### Fixed

- Removed firmware-calculated percentage/status as the source of truth, preventing
  inconsistent results across firmware versions.
- Duplicate ESP32 retries with the same UUID or sequence no longer insert a second
  reading; conflicting reuse returns HTTP 409.
- Grease-trap history now spans prior assignments instead of reading only the
  currently assigned device.

### Database and validation

- Backed up the working database before migration 004 to
  `C:\xampp\tmp\aquasense-config-backups\pre-phase3-20260927-002828.sql`.
- Migration 004 preserves Phase 1/2 records and adds no development account.
- Final regression passed: production configuration 12 checks; administrative
  foundation 54 checks; session expiry; mobile CORS 36 checks; mobile API 30
  checks; ultrasonic/device API 52 checks; Phase 2 28 checks; Phase 3 31 checks.
- PHP lint passed for all 22 changed PHP files, monitoring JavaScript syntax passed,
  and `git diff --check` reported no errors.

### Known Issues

- HC-SR04 is the first targeted physical sensor. The Phase 3 manual reading is
  explicitly simulated; a new physical end-to-end measurement remains outstanding.
- Temperature, turbidity, flow, and gas fields are ready but may have no connected
  hardware or data.
- Full alert creation, acknowledgement, escalation, and notification delivery are
  Phase 4. Push, email, and SMS notifications are not part of Phase 3.
- Telemetry has indexes and bounded readers, but its long-term retention/archive
  policy must be finalized before high-frequency large-scale deployment.
- Rendered responsive/keyboard visual review remains outstanding because browser
  control was unavailable in this session.
- The damaged original XAMPP MariaDB instance on port 3306 remains unchanged; this
  computer uses the isolated working instance on port 3307.

## Local database availability update - 2026-09-30

- Fixed phpMyAdmin `HY000/2002` by starting the existing enabled
  `AQUASENSE Local Database` scheduled task after its MariaDB process had stopped.
- Verified that the task is running, `127.0.0.1:3307` is listening, MariaDB opens
  the `aquasense` database, and all 19 application tables are available.
- Verified HTTP access to phpMyAdmin and the AQUASENSE+ website. No database files,
  credentials, schema, or application code were changed, so version 0.5.0 remains
  unchanged.

## 0.4.0

Date: 2026-09-27

Development Phase: Phase 2 — Administrative Core

### Added

- Added a database-backed administrative dashboard with operational totals,
  grease-trap/device state tables, recent alerts, recent account activity, and
  vanilla-JavaScript charts. Missing measurements remain visibly unavailable.
- Added searchable, paginated establishment, grease-trap, and device lists plus
  detail screens and administrator-only create, edit, activate, and deactivate
  actions. Historical records are retained instead of being deleted.
- Added grease-trap capacity, installation, cleaning, and ordered threshold
  validation; device metadata and assignment management; and assignment history.
- Added server-side protection against duplicate current assignments and against
  assigning a device to a trap under a different selected establishment.
- Added protected later-phase navigation notices, reusable Phase 2 validation and
  persistence services, management audit events, confirmation prompts, and
  responsive form/table/detail styles.
- Added additive migration `003-phase2-administrative-core.sql` for nullable
  establishment notes and a 28-check Phase 2 integration suite.

### Changed

- Environmental staff can read Phase 2 operational records; only administrators
  can submit management actions. Direct unauthorized POST requests return HTTP 403.
- Updated the sidebar, footer, setup guide, database documentation, and application
  version to reflect the completed Phase 2 scope.
- The existing AQS-001 one-sensor test records remain in the same database. Its
  stale last-seen timestamp is represented as OFFLINE; this release inserts no
  fake current telemetry.

### Fixed

- Replaced the dashboard's Phase 1 setup-only summary with current database values.
- Replaced dead future navigation links with a protected, whitelisted scope notice.

### Database and validation

- Backed up the working isolated database before applying migration 003 to
  `C:\xampp\tmp\aquasense-config-backups\pre-phase2-20260927-000744.sql`.
- Production configuration: 12 checks; administrative foundation: 54 checks;
  session expiry: passed; mobile CORS: 36 checks; mobile API: 30 checks;
  ultrasonic API: 51 checks; Phase 2: 28 checks.
- Phase 2 fixtures were removed after validation and no development sample account
  is created automatically by migration 003.

### Known Issues

- Rendered desktop, tablet, mobile, and keyboard review remains outstanding because
  no browser-control connection was available in this implementation session.
- Full monitoring history, production sensor ingestion, automatic alerts, surrender
  processing, incentives, reporting, and password-reset delivery remain later phases.
- Live sensor fields can show No data yet, Awaiting device, Awaiting telemetry, or
  OFFLINE until valid data arrives. Device heartbeat policy is not yet final.
- The original corrupt XAMPP MariaDB instance on port 3306 remains unchanged. This
  computer continues to use the isolated working instance on loopback port 3307.
- This is a portable pre-1.0 prototype, not a configured production deployment.

## Local database availability update - 2026-09-26

- Diagnosed phpMyAdmin `HY000/2002`: Apache was available, but the isolated
  AQUASENSE+ MariaDB server was not listening on `127.0.0.1:3307`.
- Re-enabled the existing `AQUASENSE Local Database` scheduled task and started
  the isolated server without changing or reinitializing its data directory.
- Verified port 3307, MariaDB 10.4.32, the `aquasense` database, and three rows
  in `roles` using the configured local `root` account with an empty password.
- Documented recovery steps in README.md. The original corrupt port-3306 data
  remains unchanged, and application version 0.3.0 is unchanged.

## Repository packaging update - 2026-09-21

- Added `esp32/` with ultrasonic firmware 0.1.1, GPIO5/GPIO18 configuration,
  wiring/setup documentation, and VS Code IntelliSense support.
- Included the PHP telemetry and mobile API source needed for the test flow.
- Excluded machine-specific headers, device keys, Wi-Fi credentials, generated
  compiler databases, and firmware binaries; only configuration templates are shared.
- PHP remains 0.3.0. ESP32 compilation passed; valid physical telemetry is still unverified.

## 0.3.0

Date: 2026-09-21

Development Phase: One-sensor ESP32 ultrasonic bench test

- Added a development-only JSON POST device telemetry endpoint with separate hashed device credentials, assignment checks, size/range validation, saved calibration, and per-device rate limiting.
- Added migration 002 for nullable temperature, explicit test readings, WARNING status, and per-device ultrasonic calibration in the existing database.
- Added CLI provisioning for a separate temporary AQS-001 device/trap without replacing existing demo assignments.
- Updated the owner API to return distance/test metadata and preserve absent temperature as NULL; Flutter 0.1.6+7 handles that payload.
- Added integration coverage for calculations, credential boundaries, assignment restrictions, stored readings, stale data, and owner API compatibility.
- Sensor model/wiring, upload, and physical end-to-end readings remain unverified. No other sensors, battery system, automation, or production ingestion added.
- Temporary configuration and test records remain until the user explicitly requests removal.


Verification on 2026-09-21:
- Ultrasonic device integration: 51 checks passed; synthetic fixture readings removed afterward.
- Existing PHP suites: production configuration 12, mobile API 30, CORS 36, foundation 54, plus session expiry passed.
- PHP syntax: 39 files passed. Git whitespace check passed.
- Production-mode CLI probe returned the disabled-test message before any database access.
- Private firmware configuration/metadata returned HTTP 403 and matched Git exclusion patterns.
- Flutter analysis passed and all 28 tests passed. Updated debug APK assembled successfully.
- Physical sensor wiring, calibration, upload, and live end-to-end measurements remain unverified.
## 0.2.3

Date: 2026-09-15

Development Phase: Flutter Web CORS preflight correction

### Fixed

- Flutter Web development origins on dynamic localhost and 127.0.0.1 ports are accepted when the API is reached through the development PC's LAN address.
- OPTIONS is completed with HTTP 204 before the database layer, bearer authentication, endpoint method checks, and JSON body parsing.
- CORS advertises Content-Type, Authorization, and Accept while echoing only an allowed exact origin.

### Security

- Production permits only explicitly configured HTTPS origins, forces localhost preview off, and never returns a wildcard origin or browser credential cookies.
- Invalid ports, deceptive localhost hostnames, arbitrary LAN/external origins, unsupported methods, and unsupported request headers remain rejected.
- Development-only CORS logging records method, origin, allow decision, and OPTIONS handling without request bodies, passwords, tokens, or database credentials.

### Verification

- Manual LAN OPTIONS from `http://localhost:49840`: HTTP 204 with the exact origin, methods, headers, and Vary response.
- Manual development owner POST after preflight: HTTP 200; logout: HTTP 200.
- CORS policy/integration suite: 36 checks passed.
- Existing mobile API: 30 checks; administrative website/database: 54 checks; production configuration: 12 checks.
- Flutter 0.1.5+6 analysis passed and all 25 tests passed, including debug-web-only CORS diagnostics.
- Flutter Web compiled and launched in headless Chrome on port 49840 with the configured LAN API root.

### Compatibility

- Native Android requests without an Origin header are unchanged.
- No schema, database credential, website CSRF, bearer-token, or Apache access-rule changes were required.
## 0.2.2

Date: 2026-09-15

Development Phase: Portable production deployment configuration

### Added

- Environment-driven development/production configuration, exact trusted proxy and Flutter-web origin lists, and an ignored production configuration template.
- HTTPS enforcement, secure production sessions, HSTS, configurable private upload/log paths, and storage traversal protection.
- Production deployment guides for PHP/MySQL hosting, Flutter release builds, database setup, and future ESP32 clients.
- A 12-check production configuration test that runs without production credentials or a live database.

### Changed

- Server environment variables take precedence over local machine settings; committed PHP configuration no longer supplies database identity or credentials.
- Flutter 0.1.3 requires one explicit API_BASE_URL on every platform and release builds reject non-HTTPS URLs.
- Companion Flutter 0.1.4+5 catches startup configuration failures and paints a safe error screen instead of remaining black.
- Development sample data is documented as manual development-only data, and the mobile fixture helper refuses production mode.
- Documentation and text/Markdown files are denied over Apache alongside existing private artifacts.

### Verification

- PHP syntax passed for every PHP file.
- Production configuration: 12 checks passed.
- Mobile API: 30 checks; browser-origin API: 15 checks; website/database: 54 checks; session expiry passed.
- Flutter analysis passed, all 20 tests passed, and a release APK assembled with the HTTPS configuration template.

### Deployment status

- The source is ready to configure on a standard PHP host or VPS without XAMPP paths or code rewrites.
- A real production hostname, hosting account, TLS certificate, database credentials, storage paths, monitoring, backups, and real user provisioning are still deployment inputs and are not stored in Git.

## 0.2.1

Date: 2026-09-15

Development Phase: Mobile Phase 1 local browser connection fix

### Fixed

- Local Flutter browser previews can complete CORS preflights and authenticated API requests when mobile_allow_local_web_preview is enabled.
- Localhost/127.0.0.1 origins are accepted only from a loopback connection. Other origins, unsupported methods and unsupported headers are rejected.
- Default configuration keeps browser preview disabled; this machine's ignored local.php enables it, and local.example.php documents the development option.

### Verification

- 15 browser-origin HTTP checks and 30 existing mobile API checks passed.
- PHP syntax check passed. No schema changes or credential changes required.

## 0.2.0

Date: 2026-09-14

Development Phase: Shared backend support for Mobile Phase 1. Administrative UI remains Phase 1.

### Added

- Owner-only JSON login, profile, dashboard and logout endpoints in the existing PHP API.
- Hashed bearer tokens, bounded expiry, password-change/role/deactivation checks, shared login throttling and audit events.
- Dashboard scope enforced from database ownership; configurable backend status and freshness with simulated/no-data/stale labels.
- Additive mobile_tokens migration and CLI-only guarded demo owner/linkage/telemetry helper.
- 30 API integration checks covering account isolation, authorization, token lifecycle and status boundaries.

### Changed

- API .htaccess exposes only four mobile endpoint files and forwards bearer authorization to PHP.
- Existing foundation test accepts additive tables while checking every original required table.
- README, setup instructions and credentials describe the mobile app connection and optional development owner.

### Verification

- All 30 mobile API integration checks passed against local Apache/MariaDB.
- Existing website: 54 checks plus the session-expiry test passed.
- Flutter app is versioned independently at 0.1.0 in sibling aquasense_mobile.

### Known limitations

- Development telemetry is simulated; hardware integration and later mobile/admin modules remain unimplemented.
- Existing local MariaDB uses port 3307; original 3306 database recovery is outside this change.
- Production HTTPS and mobile release distribution are not configured.

## 0.1.11

Date: 2026-09-14

Development Phase: Phase 1 — Renamed website folder

### Added

- Documentation for the current nested installation at `C:\xampp\htdocs\AQUASENSE+\aquasense-web`.

### Changed

- Default application base path and configuration example now use `/aquasense-web`.
- The ignored local configuration uses `/AQUASENSE+/aquasense-web` to match this computer's actual directory.
- Setup and credential references use the new folder name; cloning instructions specify `aquasense-web` as the destination folder.

### Fixed

- Redirects, assets, form submissions, and session cookie paths no longer point at the removed `/aquasense` directory.

### Known Issues

- Database name and GitHub repository remain `aquasense`; renaming the folder does not require a SQL import.
- Existing local database-recovery and browser-verification limitations remain unchanged.

## 0.1.10

Date: 2026-09-14

Development Phase: Phase 1 — Plain-text setup guide

### Added

- `SETUP-INSTRUCTIONS.txt` with cloning/ZIP download, XAMPP startup, ordered SQL imports, local configuration, development logins, verification, safe updates, and troubleshooting.

### Changed

- README links to the new guide and includes it in the project structure.
- Setup instructions distinguish a standard port 3306 installation from the original computer's port 3307 workaround.

### Fixed

- None.

### Known Issues

- Documentation does not install or repair local services. Existing database-recovery and browser-verification limitations remain unchanged.

## 0.1.9

Date: 2026-09-14

Development Phase: Phase 1 — GitHub source repository

### Added

- Git repository preparation for the private `HyoYoung05/aquasense` repository.
- `.gitattributes` for consistent text line endings and binary artwork handling.
- README repository and cloning instructions, including which local configuration and runtime data are excluded.

### Changed

- Versioned the current Phase 1 source, documentation, SQL seed, development credential reference, and artwork together.

### Fixed

- None.

### Validation

- PHP syntax checks and Git whitespace checks passed before upload.
- The previously passing integration suite could not be rerun for this upload because the configured local MariaDB connection on port 3307 was refused. The live database and its startup task are not part of the GitHub upload.

### Known Issues

- GitHub contains source files, not a hosted PHP/MySQL deployment or a backup of the live local database.
- Existing browser-verification and original XAMPP database-recovery limitations remain unchanged.

## 0.1.8

Date: 2026-09-14

Development Phase: Phase 1 — Development credentials reference

### Added

- `credentials.txt` containing the seeded administrator and environmental staff login details and separate local phpMyAdmin credentials.
- Apache access denial for the credentials reference file.

### Changed

- README links to the local reference and explains that website sign-in uses email addresses rather than separate usernames.

### Fixed

- None.

### Known Issues

- The file documents development defaults only and does not synchronize with future password changes. Existing browser-verification and database-recovery limitations remain unchanged.

## 0.1.7

Date: 2026-09-14

Development Phase: Phase 1 — Screen-edge authentication branding

### Added

- Shared fixed header and footer independent of the centered authentication content.

### Changed

- Anchored the brand at the upper left, the administrative tag at the upper center, the Barangay information at the lower left, and the authorization text at the lower right.
- Kept the version centered in the footer and local-development label at the upper right.
- Narrow screens use additional header/footer rows and retain the Barangay information instead of hiding it.

### Fixed

- Branding and footer placement no longer follow the constrained story/form columns.
- Reserved page padding keeps the fixed header and footer clear of the form; translucent teal surfaces maintain readability while scrolling.

### Known Issues

- Browser visual verification remains outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.6

Date: 2026-09-14

Development Phase: Phase 1 — Compact responsive login spacing

### Added

- Fluid spacing and typography with a centered 1320px maximum content width for large monitors.

### Changed

- Brought the story inward from the left edge and reduced the gap between the story and landscape login card.
- Vertically centered story copy between the brand and community footer; removed the login story's fixed minimum height.
- Stacked outer columns at 1024px, inner card columns at 680px, and reduced padding on screens up to 480px wide.
- Kept the full-page teal palette, centered background artwork, and transparent card styling.

### Fixed

- Excessive horizontal spread on wide monitors and oversized spacing on shorter displays.

### Known Issues

- Rendered browser checks at the target monitor sizes remain outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.5

Date: 2026-09-14

Development Phase: Phase 1 — Transparent landscape login card

### Added

- Login-specific two-column layout with introduction on the left and sign-in fields on the right, up to 800px wide.

### Changed

- Reduced login card background opacity from approximately 91% to 38%, keeping text and controls fully opaque.
- Adjusted the surrounding layout to accommodate the wider card; narrow screens stack the card sections vertically.
- Preserved the teal/green page background and centered water artwork.

### Fixed

- Wider desktop login content no longer remains constrained to the previous 440px portrait card.

### Known Issues

- Browser visual verification remains outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.4

Date: 2026-09-14

Development Phase: Phase 1 — Full-page teal background

### Added

- A full-page gradient using the existing forest teal and emerald green palette.

### Changed

- The transparent water artwork is centered in the browser viewport rather than in the left panel.
- Both authentication columns share the same background. The form uses a translucent dark teal card, light text, and matching input colors.
- The artwork scales to fit desktop and mobile viewports and remains behind interactive content.

### Fixed

- Removed the white half-page background so the color scheme spans the entire authentication page.

### Known Issues

- Browser visual verification remains outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.3

Date: 2026-09-14

Development Phase: Phase 1 — Centered water-art background

### Added

- A responsive background layer for the water-drop artwork on authentication screens.

### Changed

- Water artwork is centered horizontally and vertically within the teal panel, with 22% opacity on desktop and 18% on small screens.
- The original teal panel color remains visible through the transparent image. Branding, copy, and footer stay above the artwork.
- Removed the artwork's orbit rings and floating labels for a clear background treatment.

### Fixed

- Artwork no longer depends on negative margins, a 48% vertical offset, or a separate lower content block for placement.

### Known Issues

- Browser visual verification remains outstanding; existing database-recovery limitations are unchanged.

## 0.1.2

Date: 2026-09-14

Development Phase: Phase 1 — Water-drop artwork update

### Added

- Transparent teal and mint water-drop splash artwork in `assets/images/water-drop-splash.png`, generated with the built-in ImageGen tool from the supplied visual reference.
- Generation prompt recorded in `assets/images/water-drop-splash.prompt.txt`.

### Changed

- Login illustration, dashboard droplet, and both AQUASENSE+ logo marks use the new shared artwork.
- Existing teal/green page colors are preserved; decorative rounded droplet backgrounds are replaced by the transparent splash silhouette.

### Fixed

- Stylesheet URLs now include the application version so updated artwork sizing is loaded after a release.

### Known Issues

- Existing database-recovery and browser-verification limitations remain. No later-phase modules were added.

## 0.1.1

Date: 2026-09-14

Development Phase: Phase 1 — Local database reliability fix

### Added

- A current-user Windows startup task, `AQUASENSE Local Database`, to keep the existing isolated MariaDB instance available independently of the assistant session and start it at sign-in.

### Changed

- This machine's phpMyAdmin entries 1 and 2 both select the working database on port 3307. The original port 3306 configuration is preserved as entry 3.
- README instructions now use the startup task and explain the two compatible working links.

### Fixed

- Temporary database processes stopped between sessions, leaving the working database unavailable.
- Older phpMyAdmin links selected the damaged original database and repeatedly returned error 1932.

### Known Issues

- Original XAMPP MariaDB on port 3306 still requires separate recovery; its database files were not repaired or removed.
- The startup task and phpMyAdmin entries are machine-specific. New installations on healthy XAMPP use the standard SQL import workflow.
- Phase 1's previously documented browser verification and future-module limitations remain.

## 0.1.0

Date: 2026-09-14

Development Phase: Phase 1 — Core Website Foundation

### Added

- Plain PHP project running under XAMPP Apache at `/aquasense/`.
- Separated configuration, local override template, and prepared PDO connection.
- Importable 17-table MySQL/MariaDB schema covering the eventual website modules.
- Fictional development administrator/staff accounts and linked establishment/trap/device seed.
- Role and active-account checks, session expiry/regeneration, login throttling, CSRF-protected login/logout, and password hashing/verification.
- Login, account-help interface, and explicitly unavailable password reset scaffold.
- Reusable responsive sidebar, top bar, authentication layout, and version footer.
- Protected Phase 1 dashboard with account information and authentication activity.
- Authentication audit events, protected error logging, output escaping, security headers, and private-directory restrictions.
- 54 HTTP/database integration checks and a separate inactivity expiry check.
- README covering setup, schema relationships, security, test procedures, later phases, and local troubleshooting.

### Changed

- None; initial release.

### Fixed

- During initial verification, replaced a nonstandard expired-form status with HTTP 403 for XAMPP Apache compatibility.
- Preserved explicit private/no-store response headers instead of letting PHP's session cache limiter replace them.

### Validation

- `schema.sql` and `sample-data.sql` imported successfully with XAMPP MariaDB 10.4.32 in a fresh isolated data directory.
- 54 functional/integrity checks passed through the requested localhost Apache URL.
- Inactivity expiry check and PHP syntax validation passed.

### Known Issues

- Existing default XAMPP MariaDB on port 3306 has pre-existing InnoDB corruption. This machine uses an isolated XAMPP MariaDB instance on loopback port 3307 via ignored local configuration; see README for restart instructions. The original database has not been repaired.
- Browser connection unavailable during implementation; rendered desktop/mobile and keyboard usability review remain outstanding.
- Password recovery has no email delivery, token issuance, or redemption yet.
- No operational monitoring, telemetry API, simulation utility, alerts, management CRUD, photo uploads, surrender approval, incentive calculation, ledger interface, or reporting yet; schema support only.
- ESP32 hardware is not connected. Future telemetry will be simulated during website development; no readings are currently seeded.
- This is a local foundation prototype, not a production deployment or complete capstone release.

## Versioning Policy

Use MAJOR.MINOR.PATCH. Major: architectural or production-level changes. Minor: features or completed development phases. Patch: bug fixes, security fixes, and small improvements. Versions below 1.0.0 indicate prototype development. Update the application constant, README, and this history together for each release.
