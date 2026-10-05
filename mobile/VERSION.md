# AQUASENSE+ Carinderia App Version History

## Current Version

0.6.0+12

## 0.6.0+12 - 2026-10-05

Development Phase: Mobile Phase 5 Owner Oil Surrender

### Added

- Functional Oil Surrender screen with active count, latest submission,
  newest-first history, status filters, pull-to-refresh, and read-only detail.
- New surrender form with positive quantity validation, `L`/`kg` selection,
  authorized grease-trap selection, optional notes, and confirmation.
- Camera/gallery evidence selection, preview, change/remove controls, picker
  resizing/compression, and JPEG/PNG/WEBP plus 5-MiB client validation.
- Authenticated multipart upload and protected binary photo retrieval in the
  shared API/authentication services.
- Stable UUID v4 idempotency for retries, active-request tap protection,
  progress, safe uncertain-result messaging, and success confirmation.
- PENDING, UNDER_REVIEW, APPROVED, and REJECTED presentation, owner-visible
  review remarks, review timestamps, and Hybrid Verification explanation.
- Phase 5 model, service, form, history, detail, image, multipart, idempotency,
  network-failure, responsive navigation, and regression tests.

### Changed

- Increased the application version from `0.5.0+11` to `0.6.0+12`.
- Dashboard Oil Surrender card and authenticated navigation now open the
  functional Phase 5 module and refresh Dashboard data after submission.
- Added the maintained `image_picker` dependency and Android camera permission.

### Fixed

- An uncertain upload response can now be retried with the same logical
  submission UUID instead of creating an independent transaction.
- Repeated Submit taps are blocked while multipart upload is active.
- Failed history refreshes retain prior records; failed submissions retain the
  completed form and selected photo.
- Evidence uses authenticated bytes rather than an unsafe public storage URL.
- Owner forms expose only backend-authorized grease traps and never submit an
  arbitrary owner or establishment ID.

### Known Issues

- Incentive transaction history is Mobile Phase 6. Rice reward calculation and
  distribution remain backend/Barangay responsibilities.
- AI image verification is not implemented; Hybrid Verification is a human
  Barangay decision supported by stored evidence and telemetry.
- Final production/release hardening is Mobile Phase 7. A real HTTPS host,
  production signing, application-ID migration, and physical-phone
  camera/gallery acceptance remain pending.

### Verification

- `flutter analyze`: no issues.
- `flutter test`: 96 passed, 0 failed, 0 skipped.
- PHP Phase 5 end-to-end suite: 39 checks passed.
- PHP owner mobile integration suite: 50 checks passed.
- Configuration-error debug, reachable local debug, and placeholder-HTTPS
  release configuration-check APKs built successfully.
- The Phase 5 backend fixture verified real multipart upload, safe photo
  storage/retrieval, owner isolation, idempotent replay, all review states,
  owner-visible rejection remarks, and Barangay Hybrid Verification. It removed
  its temporary records and files after completion.

## 0.5.0+11 - 2026-10-04

Development Phase: Mobile Phase 4 Owner Alerts

### Added

- Active Alerts, bounded Alert History, and owner-safe Alert Detail screens.
- Typed alert pages, summary counts, authorized grease-trap filters, and
  tolerant future alert type/severity handling.
- Status, severity, date, and trap filters with 25-record backend pagination.
- Dashboard latest-alert detail navigation and Monitoring active-alert banners.
- Pull-to-refresh and one lifecycle-aware 20-second Alerts refresh timer.
- Owner alert API integration and Phase 4 model, UI, lifecycle, responsive,
  null, unknown-value, and Monitoring-navigation tests.

### Changed

- Increased the application version from `0.4.0+10` to `0.5.0+11`.
- Replaced the Alerts placeholder with the functional owner read-only module.
- Dashboard alert summaries now open Alerts or the selected alert detail.

### Fixed

- Unknown alert values and absent device/sensor/threshold fields no longer fail
  parsing or rendering.
- Refresh failures retain previously loaded alerts and valid authentication.
- Polling stops while backgrounded and after disposal, avoiding duplicate timers.
- Monitoring alert navigation now uses an owner-validated backend alert ID.

### Known Issues

- Push notifications are not implemented; Phase 4 provides in-app alerts.
- Alert acknowledgment and resolution remain Barangay website actions.
- Oil Surrender submission is Mobile Phase 5; incentive transaction history is
  Mobile Phase 6.
- Installed-phone and fresh physical AQS-001 threshold tests require available
  hardware; automated and local API verification cover the implemented flow.

### Verification

- `flutter analyze`: no issues.
- `flutter test`: 84 passed, 0 failed, 0 skipped.
- PHP owner mobile API suite: 50 checks passed.
- Configuration-error debug, reachable local debug, and HTTPS release
  configuration-check APKs built successfully.
- Local AQS-001 history returned seven stored alerts and one current
  DEVICE_OFFLINE alert through both Alerts and Monitoring. No fresh physical
  threshold crossing or installed-phone interaction was available.

## 0.4.0+10 - 2026-10-04

Development Phase: Mobile Phase 3 Monitoring and Sensor History

### Added

- Detailed owner Monitoring screen reachable from both Dashboard Quick Actions
  and the authenticated navigation shell.
- Typed current-monitoring and telemetry-history models for multiple authorized
  grease traps, nullable sensors, device metadata, bounded pages, and ranges.
- Current waste level, ultrasonic distance, temperature, turbidity, flow, and
  honestly labeled raw gas sensor presentation.
- Last Hour, Today, 24 Hours, 7 Days, 30 Days, and validated custom ranges of up
  to 31 days.
- `fl_chart` history charts with a sensor selector, chart gaps for null values,
  textual records, and presentation-only summary statistics.
- Owner-safe `monitoring.php` and `telemetry-history.php` PHP routes backed by
  the existing database and bearer-token ownership.
- Fifty-record backend pagination and Load more handling.
- Pull/manual refresh and one lifecycle-aware 20-second current-status timer;
  history is reloaded only on trap/range/manual changes.
- Phase 3 model, UI, range, chart, pagination, error, 401, lifecycle, zero,
  missing-sensor, offline, and stale-data regression tests.

### Changed

- Increased the application version from `0.3.0+9` to `0.4.0+10`.
- Replaced the Monitoring placeholder with the functional Phase 3 screen.
- Preserved the Phase 2 Dashboard summary and made both Monitoring navigation
  entry points open the detailed screen.
- Added `fl_chart 1.2.0` as the single chart dependency.
- Extended shared timestamp formatting for short and multi-day chart axes.

### Fixed

- Optional sensor nulls no longer risk becoming fabricated zero chart points.
- Valid 0% and 0 cm readings remain visible as real measurements.
- Stale/offline values are explicitly labeled as last-known readings.
- Refresh failures retain prior readings and do not clear a valid session.
- Polling is cancelled when Monitoring is disposed or the app is inactive,
  preventing duplicate background timers.
- Owner history requests now reject unowned traps before reading telemetry.

### Verification

- `flutter analyze`: no issues.
- `flutter test`: 73 passed, 0 failed, 0 skipped.
- PHP owner mobile integration suite: 39 checks passed, including bearer-owner
  isolation, another-owner history rejection, missing data, current sensors,
  Last Hour history, and bounded results.
- Live development owner monitoring returned two owned traps. AQS-001 returned
  its last known 60% / 15 cm reading as stale and OFFLINE.
- Its 30-day history returned 335 readings over seven pages, limited to 50 per
  response.
- Android debug and HTTPS release-configuration builds completed successfully.
- No Android device/emulator or physical HC-SR04 was available for a fresh
  distance-change acceptance test.

### Known Issues

- Full Alerts history/details and push-notification work belong to Mobile Phase
  4.
- Oil Surrender submission/photo handling belongs to Mobile Phase 5.
- Full Incentive history belongs to Mobile Phase 6.
- Production still needs a public HTTPS host, production signing, and physical
  ESP32/Android field acceptance.

## 0.3.0+9 - 2026-10-03

Development Phase: Mobile Phase 2 Owner Dashboard

### Added

- Owner Dashboard backed by the existing owner-isolated PHP endpoints.
- Typed active-alert, oil-surrender, incentive, and complete dashboard snapshot
  models.
- Establishment and grease-trap summaries with assigned device code,
  backend-derived device state, condition, and last update.
- Latest waste level, ultrasonic distance, and temperature presentation with
  explicit missing-data behavior.
- Active alert count, highest severity, and latest-alert summary.
- Latest oil surrender and pending-submission summary.
- Incentive totals kept separate by reward unit and latest incentive status.
- Pull-to-refresh, refresh action, in-memory snapshot retention, and a
  non-destructive refresh-failure warning.
- Critical-condition banner and quick navigation actions.
- Mobile Phase 2 parsing, rendering, refresh, owner-isolation, 401, offline,
  responsive, empty-state, and future-placeholder regression tests.

### Changed

- Increased the app version from `0.2.0+8` to `0.3.0+9`.
- Replaced the Phase 1 Home placeholder with the responsive summary dashboard.
- Updated the navigation placeholders with their exact future mobile phases.
- Updated Profile with owner identity, establishments, version, and logout.
- Changed date/time display to a readable local format and kept measurement
  formatting presentation-only.
- Expanded dashboard loading from `dashboard.php` with the existing
  `oil-surrenders.php` and `incentives.php` owner routes.

### Fixed

- Missing telemetry no longer risks appearing as a zero reading.
- Valid 0% readings remain distinguishable from missing telemetry.
- Nullable ultrasonic and temperature values render as Not available without
  crashing.
- Offline/stale readings are visibly identified as last-known values.
- Refresh failures preserve the previously loaded dashboard and valid session.
- Reusable empty/error panels now scroll on short landscape screens and with
  large text.

### Verification

- `flutter analyze`: no issues.
- `flutter test`: 59 passed, 0 failed.
- Debug APKs with and without an explicit API URL built successfully.
- HTTPS release-configuration APK built successfully.
- Local PHP authentication and owner dashboard calls returned the correct
  owner, Demo Kusina, two grease traps, backend-derived OFFLINE states, one
  active device-offline alert, and empty surrender/incentive collections.
- AQS-001's stored test reading reached the PHP API/database/dashboard path,
  but it was stale and OFFLINE during verification; no new physical reading was
  generated in this phase.
- No Android device or emulator was connected for installed-device interaction.

### Known Issues

- Detailed sensor monitoring and history are Mobile Phase 3.
- Full Alerts are Mobile Phase 4.
- Oil Surrender submission and photo handling are Mobile Phase 5.
- Full Incentive history is Mobile Phase 6.
- Production still needs a real HTTPS domain, deliberate application-ID
  migration, production signing, and physical-device acceptance testing.

## 0.2.0+8 - 2026-10-03

Development Phase: Mobile Phase 1 foundation completion

### Added

- Typed authentication result and owner/establishment context models based on
  the finalized PHP mobile API.
- Consistent API error categories for configuration, network, unavailable,
  unauthorized, forbidden, validation, rate limiting, and server failures.
- Responsive authenticated shell with Home, Monitoring, Alerts, Oil Surrender,
  Incentives, and Profile destinations.
- Reusable loading, error, and empty-state presentation.
- Safe development diagnostics containing only endpoint path, status, duration,
  and error category.
- Regression coverage for API headers and errors, timeout, invalid JSON,
  token/log secrecy, typed parsing, backend-specific token keys, retry,
  repeated login submission, shell navigation, and profile display.

### Changed

- Increased the app version from `0.1.6+7` to `0.2.0+8`.
- Set the Dart compatibility floor to 3.11 so the installed Flutter stable SDK
  can resolve, analyze, test, and build the project.
- Hardened base and endpoint URL validation, including safe trailing-slash
  normalization and rejection of endpoint traversal or absolute URLs.
- Preserved safe backend validation messages while preventing raw PHP, HTML,
  SQL, and exception output from reaching users.
- Centralized GET/POST request behavior and kept architecture ready for a
  future multipart method.
- Reduced the authenticated UI to Phase 1 owner and establishment basics.
  Telemetry dashboard UI is deferred to Mobile Phase 2.
- Added an expired-session notice after HTTP 401 and retained valid tokens
  during temporary network failures.

### Fixed

- Added the missing Android Kotlin Gradle plugin so debug and release APKs
  compile with the existing Kotlin `MainActivity`.
- Kept local cleartext HTTP permission in the debug manifest only.
- Prevented malformed endpoint paths from escaping the configured API root.
- Prevented repeated authentication work while login or session restoration is
  already running.
- Preserved the startup guarantee that missing, invalid, or release-HTTP
  configuration renders a visible error screen instead of a black screen.

### Verification

- `flutter analyze`: no issues.
- `flutter test`: 44 passed, 0 failed.
- Debug APK without `API_BASE_URL`: built successfully. Startup widget tests
  confirm the visible configuration error state.
- Debug APK with the reachable local API URL: built successfully.
- Release APK with an HTTPS configuration: built successfully. HTTP release
  rejection and HTTPS acceptance are covered by automated configuration tests.
- Local PHP API login, profile retrieval, owner establishment context, and
  logout passed; the issued test token was revoked.
- No Android device or emulator was connected, so installed-device interaction
  was not performed.

### Known Issues

- The Owner Dashboard is Mobile Phase 2.
- Sensor monitoring is Mobile Phase 3.
- Alerts are Mobile Phase 4.
- Oil surrender is Mobile Phase 5.
- Incentives are Mobile Phase 6.
- Production still needs a real HTTPS domain, deliberate application-ID
  migration, production signing, and physical-device acceptance testing.

## 0.1.6+7 - 2026-09-21

- Added nullable temperature and optional distance/test metadata to the reading model.
- Show No data for absent temperature, explicitly label ultrasonic bench readings, and style WARNING as a warning.
- Added model compatibility and warning-display tests. Backend 0.3.0 and migration 002 are required for the new ingestion flow.
- No other sensors or automation added. Firmware wiring/physical readings remain unverified.


Verification for 0.1.6+7: Flutter analysis passed and all 28 tests passed, including nullable temperature, older payload compatibility, and WARNING/test display. The updated temporary APK was built successfully at `temporary-network-testing/aquasense-owner-ultrasonic-v0.1.6+7.apk` using the existing Tailscale API configuration. Keep Tailscale connected on the phone and computer when using that APK. Physical device installation and ultrasonic readings remain unverified.
## 2026-09-20 - Additional temporary network reference (app remains 0.1.5+6)

- Added temporary-network-testing/ with a private C++ header containing the supplied Wi-Fi constants and a separate setup/status text file.
- Excluded the credential header from Git and denied Apache HTTP access to the new folder.
- User selected temporary Tailscale access with the computer staying on. Added a separate ignored API configuration and debug APK build instructions using the existing registered computer address.
- Re-enabled the existing database task and reconnected the existing Tailscale account. Both registered devices report online; a Tailscale ping to Android succeeded.
- Built the separate temporary debug APK successfully. Verified profile HTTP 401 and owner login/dashboard/logout through the Tailscale address on this computer; test token revoked. Installation and app login on the phone from another network remain unverified.
- Retain both temporary testing folders until the user explicitly requests removal. No mobile runtime source was changed; the temporary build uses a separate API address.

## 2026-09-20 - Local testing documentation (app remains 0.1.5+6)

- User clarification: the Wi-Fi testing setup is temporary and must remain until the user explicitly requests removal. No automatic cleanup is authorized.

- Added a separate testing folder with a private Wi-Fi credential reference for the two supplied networks and manual phone/computer setup instructions.
- Excluded the local credential file from Git and denied Apache HTTP access to the testing folder.
- Documented API address updates and debug APK rebuilding after changing networks; Wi-Fi credentials do not configure API_BASE_URL.
- Updated README.md. This documentation-only change does not rebuild or change the application package version.
- Actual connectivity on the supplied Wi-Fi networks remains unverified.

## 0.1.5+6

Date: 2026-09-15

Development Phase: Flutter Web CORS diagnostics

### Fixed

- Flutter Web debug connection failures now identify the two relevant setup checks: `API_BASE_URL` and the server CORS/OPTIONS response.
- Release builds and native targets retain a generic connection message without internal server or deployment details.
- Companion backend 0.2.3 now completes valid local browser preflights before database or authentication work.

### Security

- Development allows only exact `http://localhost:<valid-port>` and `http://127.0.0.1:<valid-port>` browser origins when local preview is enabled.
- Production permits only explicitly configured HTTPS origins and never enables wildcard origins or browser credential cookies.

### Verification

- Dart formatting is clean and Flutter analysis reports no issues.
- All 25 Flutter tests pass, including debug web, release web, and native diagnostic behavior.
- Backend suites pass 12 production configuration, 30 mobile API, 36 CORS, and 54 website/database checks plus session expiry.
- Manual preflight from `http://localhost:49840` returned HTTP 204 with the exact origin and required CORS headers; owner login and logout returned HTTP 200.
- Flutter Web compiled and launched in headless Chrome on port 49840 with the configured LAN API root.

### Deployment status

- Dynamic localhost ports are development-only. Production still requires the deployed HTTPS PHP API URL and exact production web origins.

## 0.1.4+5

Date: 2026-09-15

Development Phase: Startup configuration resilience

### Fixed

- Prevented the blank/black Android screen when `API_BASE_URL` is missing or invalid.
- Added a visible startup configuration error screen with safe rebuild guidance and system light/dark theme support.
- Preserved release HTTPS enforcement and debug-only HTTP support.
- Removed the later `ApiConfig.baseUrl` lookup from secure token storage while preserving one token namespace per validated backend URL.

### Changed

- Startup validates configuration before constructing the normal owner app and always supplies a widget to `runApp()`.
- API service construction now rejects missing, malformed, or release-HTTP configuration immediately.
- Displayed and package versions are synchronized at `0.1.4+5`.

### Tests

- Added startup widget coverage for valid HTTPS configuration, missing configuration, malformed configuration, dark-mode rendering, secret-free messaging, and a valid but unreachable backend.
- Existing tests continue to cover release HTTP rejection, debug HTTP acceptance, no-token login restoration, logout, protected-screen removal, failures, and responsive layouts.

### Verification

- Flutter analyze: no issues.
- Flutter test: all 24 tests passed.
- The Windows debug target compiled and launched against the working local XAMPP API; its unauthenticated profile endpoint returned the expected HTTP 401 response.
- A release APK with no `API_BASE_URL` compiled successfully; widget tests verify the configuration screen because no Android device was connected.
- A release APK with a placeholder HTTPS API URL compiled successfully. Backend connectivity was not claimed.

### Known issues

- No Android device was connected for an installed-APK visual check; the successful debug launch used the Windows target.
- The optional headless Chrome debug launch was blocked before compilation by a Flutter 3.44.1 web-tool crash caused by conflicting Flutter SDK locations in this computer's PATH. Android builds, analysis, and widget tests are unaffected.
- Production still requires the real deployed HTTPS API URL and production signing.

## 0.1.3

Date: 2026-09-15

Development Phase: Portable production API configuration

### Changed

- Removed all runtime API host defaults. Every development or production build now receives one explicit `API_BASE_URL`.
- Release builds require HTTPS; missing, malformed, credential-bearing, query-bearing, or fragment-bearing API roots fail before networking.
- API services accept an injected base URL for isolated tests without weakening real app configuration.
- Added ignored JSON build-setting paths plus development and production examples.
- Added deployment instructions for an always-on PHP host/VPS that works through each phone's own internet connection.

### Verification

- Dart formatting is clean and Flutter analysis reports no issues.
- All 20 configuration, authentication, session, failure-state, and responsive widget tests pass.
- A release APK assembled successfully with the HTTPS configuration template; it must be rebuilt with the real deployed API URL before installation.
- Shared backend 0.2.2 passed 12 production configuration checks, 30 mobile API checks, 15 browser-origin checks, 54 website/database checks, and session expiry.

### Deployment status

- The app no longer depends on a developer PC, local Wi-Fi, Tailscale, localhost, emulator aliases, or a fixed LAN address in production source.
- An actual production API hostname, hosting account, TLS certificate, Android signing/release process, and real owner accounts still need to be supplied for deployment.

## 0.1.2

Date: 2026-09-15

Development Phase: Phase 1 physical-phone connection fix

### Fixed

- Release HTTP configuration failures now explain that an HTTPS server or local-testing APK is required, instead of incorrectly reporting an unreadable server response.
- Invalid API addresses have a separate actionable configuration error.
- Prepared a debug local-testing APK for this computer's LAN API at 192.168.50.144; physical phones cannot use the emulator-only 10.0.2.2 address.
- Added phone installation/network instructions and clarified the development owner email.

### Verification

- LAN HTTP login, Demo Kusina dashboard and logout verified using the development owner account.
- Screenshot email carienderia@aquasense.test is rejected as invalid credentials; correct email is owner@aquasense.test.
- Configuration regression tests cover release HTTP rejection, debug LAN access, HTTPS release access and malformed addresses.
- Flutter analyze reports no issues; all 22 Flutter tests pass.
- Debug APK built successfully with the LAN API override; saved as aquasense-owner-local-v0.1.2.apk.

### Limitations

- The local-testing APK requires this computer's Apache/database services and the same local network. Rebuild it if this computer's IP changes.
- Release builds still require HTTPS. Physical-device installation and connection cannot be verified from this workspace.

## 0.1.1

Date: 2026-09-15

Development Phase: Phase 1 connection fix

### Fixed

- Chrome/web and desktop previews now default to localhost instead of the Android-only 10.0.2.2 alias.
- Native Android retains its emulator default; explicit API_BASE_URL overrides still work for physical phones and deployment.
- Shared PHP backend 0.2.1 supports explicitly enabled localhost browser preflights and bearer requests; unrelated origins remain blocked.
- Setup instructions include a stable Chrome preview port and restarting the app after configuration changes.

### Verification

- 15 HTTP browser-origin checks and 30 existing API checks passed, including owner login/dashboard/logout.
- Flutter analyze: no issues. All 19 Flutter tests passed. Debug web build succeeded.
- Existing Chrome session UI cannot be controlled by the available tools; restart and sign in to load this fix.

### Limitations

- Physical phones still require a LAN API_BASE_URL; deployed apps require HTTPS.
- Local browser access is an opt-in development setting, not a production CORS policy.

## 0.1.0

Date: 2026-09-14

Development Phase: Mobile Application Foundation (Phase 1 only)

### Added

- Configurable existing PHP API connection, typed Phase 1 models and reusable services.
- Teal/mint responsive login and basic owner dashboard using the existing water-drop artwork.
- Password validation/visibility, secure token storage, authenticated root navigation, session restoration and logout.
- Owner/business identity, backend-derived status, waste level, temperature, device state and last reading time.
- Simulation/staleness disclosure, retry, loading and empty states, and About version.
- Session and responsive widget tests; setup/account/API/security documentation.

### Shared backend changes

- Website 0.2.0 adds owner-only login/profile/dashboard/logout PHP endpoints in the existing backend.
- Additive mobile_tokens migration and guarded CLI development owner/telemetry helper.
- API account-isolation and token lifecycle integration tests.

### Verification

- Shared PHP API: 30 integration checks passed; website: 54 checks and session expiry passed.
- Flutter analysis passed, 15 tests passed, and the Android debug APK built successfully.
- Emulator startup encountered ADB authorization and resource-related stalls; full native interaction verification remains pending.

### Known issues / planned work

- Real ESP32 telemetry, historical monitoring, alert history/push, surrender/photos, verification, rewards and fuller profiles are not implemented.
- Development data is simulated and becomes stale after the configured backend timeout.
- Offline logout cannot immediately confirm server revocation; tokens have a bounded server expiry.
- Android release signing and HTTPS deployment are not configured. Physical-device verification remains pending.
- Proceed to Phase 2 only after Phase 1 build and functionality checks pass.
