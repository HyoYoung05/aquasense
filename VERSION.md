# AQUASENSE+ Version History

## Current Version

0.3.0

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
