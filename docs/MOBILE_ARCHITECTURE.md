# AQUASENSE+ Owner App Architecture

Version: `0.8.0+14` (Mobile Phase 7 release candidate)

## Production data path

```text
ESP32 sensors ---- HTTPS ----> Device PHP API ----> MySQL/MariaDB
                                                    ^
                                                    |
Flutter Owner App ---- HTTPS ----> Owner PHP API ---+
Barangay Website  ---- HTTPS ----> Web/PHP layer ---+
```

The Flutter app communicates only with the public PHP API. It has no MySQL
driver, database connection string, database password, device key, or direct
database access.

XAMPP is a development environment only. Tailscale and Tailscale Funnel are
optional development/testing tools only. A production installation requires an
always-on public PHP/MySQL deployment with a valid HTTPS certificate; it does
not require the developer computer, a LAN address, XAMPP, or Tailscale.

## Configuration boundary

The build-time `API_BASE_URL` value is the one API root. Production code has no
hidden host fallback. Release builds accept only HTTPS. Debug builds may use an
explicit HTTP URL for local testing.

Expected production shape:

```text
https://YOUR-PRODUCTION-DOMAIN/api/mobile
```

The repository intentionally does not invent or commit the final hostname.

## Mobile modules

- **Authentication** signs in an Owner, stores only the bearer token in secure
  platform storage, validates restored sessions, and clears local state on
  logout.
- **Dashboard** presents owner, establishment, trap, device, latest telemetry,
  alert, surrender, and incentive summaries returned by the backend.
- **Monitoring** presents current values and paginated/ranged history. It polls
  current values with one lifecycle-aware timer; history is loaded separately.
- **Alerts** presents owner-visible active/history records and read-only detail.
- **Oil Surrender** sends an authenticated multipart submission with an
  idempotency UUID and protected evidence photo.
- **Incentives** presents backend-calculated reward summaries, history, and
  distribution state. It performs no reward calculation.
- **Profile** presents owner-safe identity and establishment data and logout.

## Trust boundaries

The PHP backend is authoritative for ownership, establishment/trap/device
relationships, telemetry, thresholds, alert state, device state, surrender
review, incentive calculation, and distribution. The app formats returned data
for display and never accepts an arbitrary owner identity from the UI.

Owner isolation is enforced by the bearer token and backend queries. Flutter
handles `401`, `403`, and `404` without exposing internal errors, but client UI
restrictions are not treated as an authorization control.

## Reliability model

Missing configuration, invalid URLs, release HTTP URLs, server failure, expired
sessions, empty datasets, and invalid responses all render visible states.
Temporary transport failure preserves a valid stored session. A backend `401`
clears it. Monitoring and Alerts stop timers while inactive and resume with a
single safe refresh.

## Timestamp and measurement contract

The shared timestamp utility parses backend timestamps once and displays them
in local time. Sensor units come from the API contract: distance in `cm`, waste
level in `%`, temperature in `°C`, calibrated turbidity in `NTU`, flow in
`L/min`, and uncalibrated gas as a raw reading. Null sensor data remains absent
and is never converted to zero.
