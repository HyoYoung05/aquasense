# AQUASENSE+ Carinderia Owner App

Flutter owner application for AQUASENSE+. Version **0.6.0+12** completes Mobile
Phase 5: authenticated oil surrender submission, photo evidence, history,
protected detail viewing, and Barangay review-state tracking. It preserves the
Phase 1 authentication foundation, Phase 2 Dashboard, Phase 3 Monitoring, and
Phase 4 Alerts.

The app communicates only with the PHP API. It contains no SQL, MySQL package,
or database credential.

## Production architecture

```text
Flutter Owner App -> HTTPS -> public AQUASENSE+ PHP API -> MySQL/MariaDB
```

XAMPP, LAN addresses, the developer computer, Tailscale, and Tailscale Funnel
are development tools only. A production APK must point to an always-on public
HTTPS deployment and continues working when the developer computer is off.

## Requirements

- Flutter 3.41.6 or a compatible stable release
- Dart 3.11.0 or newer within Dart 3
- Android SDK and Java 17 for Android builds
- AQUASENSE+ PHP backend with its existing mobile API and database migrations
- An explicit API root for each configured build

Run the foundation checks with:

```powershell
flutter pub get
flutter analyze
flutter test
```

## API configuration

`API_BASE_URL` is the only server root used by the app. Include
`/api/mobile` and omit endpoint filenames. The source has no default
production host.

The app accepts explicit HTTP or HTTPS addresses in debug builds. Release
builds require HTTPS. The validator rejects missing hosts, embedded
credentials, query strings, fragments, unsafe endpoint paths, and release HTTP;
it also normalizes trailing slashes.

Create ignored local settings from the examples:

```powershell
Copy-Item config/api.development.example.json config/api.local.json
Copy-Item config/api.production.example.json config/api.production.json
```

Development run:

```powershell
flutter run --dart-define-from-file=config/api.local.json
```

Local Android debug APK:

```powershell
flutter build apk --debug --dart-define=API_BASE_URL=http://<LOCAL-IP>/AQUASENSE+/aquasense-web/api/mobile
```

Production release:

```powershell
flutter build apk --release --dart-define-from-file=config/api.production.json
```

A build without `API_BASE_URL` is valid as an installation artifact and opens
the visible **Server configuration error** screen. It does not attempt a
fallback connection and cannot produce the previous startup black screen.

## Authentication and session flow

The app uses the finalized PHP routes:

- `POST login.php` with email and password
- `GET profile.php` to validate or restore a bearer session
- `GET dashboard.php` for server-assigned establishments, grease traps,
  devices, latest telemetry, and active alerts
- `GET monitoring.php` for owner-scoped current device and sensor monitoring
- `GET telemetry-history.php` for owner-authorized, ranged, paginated history
- `GET alerts.php` for owner-authorized alert counts and filtered 25-record pages
- `GET alert.php?id=...` for an owner-authorized, read-only alert detail
- `GET oil-surrenders.php` for owner surrender history and status filtering
- `POST oil-surrender.php` for authenticated multipart surrender submission
- `GET oil-surrender.php?id=...` for an owner-authorized surrender detail
- `GET oil-surrender-photo.php?id=...` for protected evidence retrieval
- `GET incentives.php` for grouped incentive totals and latest status
- `POST logout.php` for best-effort server token revocation

Passwords are never persisted. Bearer tokens use `flutter_secure_storage` and
a key scoped to the validated backend URL:

```text
aquasense_owner_token:<validated-server>
```

At startup, no token opens Login. A valid token is checked through
`profile.php`. HTTP 401 clears the token and returns to Login with a
session-expired message. A temporary network failure preserves the token and
shows Retry and Sign out. Local sign-out completes even when server revocation
cannot be confirmed.

The backend remains authoritative for owner identity and establishment
assignment. Flutter never supplies a user or establishment selector to widen
access.

## Shared API and error behavior

`ApiService` owns GET/POST JSON behavior, authenticated multipart uploads,
authenticated binary evidence retrieval, standard headers, timeouts,
response-envelope parsing, and safe debug diagnostics. Its application errors
distinguish:

- `CONFIGURATION_ERROR`
- `NETWORK_ERROR`
- `SERVER_UNAVAILABLE`
- `UNAUTHORIZED`
- `FORBIDDEN`
- `VALIDATION_ERROR`
- `RATE_LIMITED`
- `SERVER_ERROR`

Safe backend validation messages are retained. Raw HTML, PHP warnings, SQL
errors, stack traces, passwords, request bodies, image bytes, and tokens are not
shown or logged.

## Owner Dashboard

Home loads one in-memory snapshot from the authenticated PHP API and displays:

- Owner name and each server-assigned establishment
- Every returned grease trap and assigned device code
- Backend-derived condition and device connectivity
- Latest waste percentage, ultrasonic distance, and temperature when present
- Last sensor update, stale/offline context, test-data and simulation labels
- Active alert count, highest severity, and latest active alert
- Latest oil surrender, status, and pending count
- Incentive pending/distributed totals grouped by unit
- Quick actions for Monitoring, Alerts, Oil Surrender, Incentives, and Profile

Missing telemetry displays **No sensor data has been received yet** and never
turns into a fabricated 0%. A stored zero reading displays as 0%. Optional
temperature and ultrasonic values display **Not available** when absent.

The PHP backend remains authoritative for percentages, threshold conditions,
online/offline state, alerts, surrender status, incentive values, and owner
scope. Flutter performs formatting only.

Pull-to-refresh and the refresh button request a new snapshot. A refresh failure
keeps the last successful snapshot visible with a non-destructive warning.
The dashboard itself does not continuously poll or maintain a persistent
offline cache.

## Mobile Phase 3 Monitoring

Monitoring is available from the dashboard Quick Actions and the authenticated
navigation shell. It supports every owner-authorized grease trap returned by
the API and displays:

- Establishment, grease-trap name, assigned device, firmware when present,
  device state, last seen, and latest telemetry time
- Backend-derived waste percentage, current condition, and stale/offline state
- Ultrasonic distance in centimeters
- Temperature in degrees Celsius when connected
- Calibrated turbidity in NTU when returned by the backend
- Flow rate in L/min when returned by the backend
- Gas sensor values labeled as raw readings rather than an invented ppm value
- Test and simulation disclosure for development records

Missing readings, valid zeroes, unconnected optional sensors, stale readings,
and offline devices remain different states. Last-known values stay visible
with an offline/stale explanation. Flutter does not calculate threshold
conditions or independently decide whether a device is online.

The monitoring screen refreshes current status every 20 seconds while visible.
It cancels its single timer on disposal or while the application is inactive,
resumes on return, and also supports pull-to-refresh and a refresh button.
Failed refreshes keep the last successful values and valid secure session.
History is not downloaded by the timer.

Telemetry history supports Last Hour, Today, Last 24 Hours, Last 7 Days, Last
30 Days, and a custom range of at most 31 days. The PHP API returns 50 newest
records per page. Owners can load subsequent pages without downloading an
unbounded lifetime dataset.

`fl_chart` renders one selected sensor chart at a time. Available chart choices
come from non-null backend history fields: waste level, ultrasonic distance,
temperature, turbidity, flow, and raw gas. Null points remain chart gaps and
are never changed to zero. A textual history list and latest/minimum/maximum/
average presentation accompany the chart for accessibility.

## Mobile Phase 4 Alerts

Alerts is available from Dashboard and the authenticated navigation shell. It
shows backend-provided unresolved counts, critical/warning totals, ACTIVE,
ACKNOWLEDGED, and RESOLVED records, and readable labels for all current alert
types. Unknown future types or severities remain displayable without crashing.

Owners can filter by status, severity, Today, 7 Days, 30 Days, a custom range of
at most 31 days, and an authorized grease trap. The PHP API returns 25 newest
records per page. Alert detail includes owner-safe site, device, sensor, trigger,
threshold, count, and lifecycle timestamps. It does not expose administrative
notes or acknowledge/resolve/reopen/delete controls.

Pull-to-refresh and one 20-second timer refresh Alerts only while the screen is
active. The timer stops in the background and on disposal. Failed refreshes keep
the last successful page and valid session. Monitoring shows only its most
important active alert banner and opens the owned detail by backend alert ID.
Push notifications remain outside this release.

## Mobile Phase 5 Oil Surrender

Oil Surrender is available from the Dashboard card, Quick Actions, and the
authenticated navigation shell. Its main screen shows the latest transaction,
active PENDING/UNDER_REVIEW count, newest-first history, and All, Pending,
Under Review, Approved, and Rejected filters. Pull-to-refresh updates the
backend review result and preserves the last successful history if refresh
fails.

The New Surrender form accepts a positive oil quantity, backend-supported `L`
or `kg` unit, optional notes of up to 2,000 characters, an authorized grease
trap, and one required JPEG, PNG, or WEBP photo. A single trap is selected
automatically; multiple traps require an explicit selection. The app never
sends an arbitrary establishment or owner ID.

Camera and gallery selection use `image_picker`. Selected images are resized to
at most 2048 by 2048 pixels at quality 88 where the platform picker supports
processing, previewed before upload, and may be changed or removed. Client-side
validation rejects empty, unsupported, or larger-than-5-MiB files with safe
messages. The backend remains authoritative for MIME, extension, size,
executable-file rejection, ownership, and random storage filenames.

Submission uses authenticated `multipart/form-data` through the shared API
service. The exact fields are `submission_uuid`, `oil_quantity`, `oil_unit`,
optional `grease_trap_id`, optional `notes`, and `photo`. One RFC 4122 version 4
UUID is retained for the logical form submission. A retry after an uncertain
network result reuses that UUID, allowing the backend to return the existing
record rather than create a duplicate. The Submit button is disabled while the
request is active, and the form and photo remain available after a failure.

Detail is read-only and displays PENDING, UNDER_REVIEW, APPROVED, REJECTED,
owner notes, owner-visible Barangay remarks, review timestamps, and the evidence
photo. Photo bytes are retrieved through the protected endpoint with the Bearer
header; tokens are not placed in public image URLs. There are no Owner controls
for approval, rejection, review status, reviewer identity, incentives, or rice
distribution.

Hybrid Verification remains a human Barangay workflow. The backend/website may
compare the Owner submission and photo with available IoT telemetry, while the
mobile app only submits evidence and presents the stored result. It performs no
AI photo decision, sensor-based approval, oil-to-rice conversion, or local
reward calculation.

## Owner navigation

The responsive authenticated shell uses a drawer on narrow screens and a
navigation rail on wider displays:

- Home: Phase 2 Owner Dashboard
- Monitoring: Mobile Phase 3 current sensors, history, and charts
- Alerts: Mobile Phase 4 active/history list and read-only details
- Oil Surrender: Mobile Phase 5 submission, evidence, history, and detail
- Incentives: Mobile Phase 6 placeholder
- Profile: owner name, email, establishments, app version, and logout

The shell uses centralized authentication state, reusable loading/error/empty
panels, manual retry, and root replacement on sign-out. The Login screen remains
scrollable with the keyboard open and blocks repeated submission.

## Android configuration

- App label: `AQUASENSE+ Owner`
- Current application ID: `com.example.aquasense_mobile`
- `INTERNET` permission: enabled
- `CAMERA` permission: enabled for Phase 5 evidence capture
- Debug manifest: permits explicit local cleartext HTTP
- Main/release manifest: does not enable cleartext traffic

The existing application ID was preserved to avoid breaking installed builds.
It is still a placeholder identity and must be deliberately migrated before
store distribution. Release signing also still uses the debug key and must be
replaced with a protected production signing configuration.

## Development account

The local development database uses owner email addresses; there is no separate
owner username field.

- Email: `owner@aquasense.test`
- Password: `AquaSense!2026`

These sample credentials are for local development only. Do not provision them
in production.

## Temporary network test material

Keep `testing/` and `temporary-network-testing/` until the user explicitly
requests removal. Their private Wi-Fi and Tailscale files are ignored by Git
and denied through Apache. They are temporary references and are not consumed
by production Flutter code. A temporary Tailscale APK still requires the
computer, Apache, MariaDB, and both Tailscale clients to stay online.

## Verification for 0.6.0+12

- `flutter analyze`: no issues
- `flutter test`: 96 passed, 0 failed, 0 skipped
- PHP owner mobile integration suite: 50 checks passed
- PHP Phase 5 end-to-end suite: 39 checks passed, including multipart image
  upload, MIME/size rejection, idempotent replay, owner isolation, protected
  photo access, Hybrid Verification data, staff review, approval, and rejection
- Debug APK without `API_BASE_URL`: built successfully; startup widget tests
  verify the visible configuration error screen
- Debug APK with the reachable local API:
  `build/verification/aquasense-owner-v0.6.0+12-local-debug.apk`
- Configuration-error APK:
  `build/verification/aquasense-owner-v0.6.0+12-config-error-debug.apk`
- HTTPS release configuration check:
  `build/verification/aquasense-owner-v0.6.0+12-https-config-check.apk`
- The isolated backend fixture completed full Owner submission and Barangay
  review flows through the actual local HTTP endpoints and removed its random
  fixtures afterward. A persistent submission using the shared development
  owner was not created.

The verification APK directory is under ignored build output. No Android phone
or emulator was connected for installed-device interaction.

## Current limitations and next phase

Mobile Phase 6 is the exact next task: read-only Owner incentive summary and
transaction history sourced from backend-generated incentive records. The app
must not calculate rewards or expose Barangay distribution controls. AI image
verification is not implemented. Final production signing, the real HTTPS API
host, application-ID migration, installed-phone camera/gallery acceptance, and
release hardening remain pending. Push notifications remain future work, and
physical HC-SR04 acceptance still requires the ESP32 and an Android device.

See [DEPLOYMENT.md](DEPLOYMENT.md), [VERSION.md](VERSION.md), and
`../aquasense-web/docs/MOBILE_API.md` for deployment and backend contract
details.
