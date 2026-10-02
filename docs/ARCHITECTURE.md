# AQUASENSE+ Production Architecture

## Runtime topology

```text
ESP32 devices ---- HTTPS JSON ----+
                                  |
Flutter owner app -- HTTPS JSON --+--> PHP 8.1+ application --> MySQL/MariaDB
                                  |
Staff browser ------ HTTPS -------+
                                             |
                                             +--> private evidence storage
                                             +--> private application logs
                                             +--> cron: offline-device check
```

The PHP application is the only database client exposed to devices and apps. ESP32
firmware and Flutter contain an API URL and their own revocable API credentials;
they never contain database credentials. The administrative website uses secure
server sessions. The owner API uses expiring bearer tokens stored only as hashes.
Each ESP32 uses a distinct random credential stored only as a SHA-256 hash.

## Application boundaries

- `public/` provides staff authentication.
- `admin/` provides administrator and environmental-staff pages.
- `api/device/telemetry.php` accepts authenticated ESP32 telemetry.
- `api/mobile/` provides the owner-scoped Flutter API.
- `api/admin/` supports authenticated monitoring actions.
- `api/health.php` checks PHP and database availability without returning secrets.
- `includes/` contains shared authorization and business services.
- `database/` contains the clean schema, numbered migrations, production-safe role
  reference data, and separately labelled development sample data.
- private storage holds surrender evidence; authenticated controllers stream it.
- `scripts/check_offline_devices.php` is the portable cron entry point.

## Data flow and trust

Device ingestion authenticates before parsing business data, limits the request
body, validates identifiers/ranges/time, calculates the authoritative fill state
on the server, preserves idempotency identifiers, writes telemetry, updates last
seen, and evaluates alerts transactionally. Owner endpoints derive identity from
the bearer token and repeat establishment ownership checks on every read/write.
Staff pages validate the database-backed role on every request.

All database timestamps are UTC. The interface converts them to
`AQUASENSE_TIMEZONE`. Oil and rice units stay explicit and are never silently
converted. Compliance Ledger events are business records; audit logs are the
separate technical trail.

## Environments

`development` permits local HTTP, the simulator, and explicitly invoked fixture
tools. `test` disables development simulation while allowing isolated automated
checks. `production` requires an HTTPS canonical URL, explicit private storage,
strong database credentials, exact HTTPS Flutter Web origins, secure cookies, and
HSTS. Native Flutter has no CORS requirement.

## Growth and retention

At one reading every 30 seconds, one device produces about 2,880 readings per day
or 1.05 million per year; ten devices produce about 10.5 million per year. Indexes
cover assignment/time, global time, receive time, active alerts, surrender queues,
incentive processing, ledger history, and audit dates.

Before launch, the Barangay must approve a retention schedule. A practical starting
point is 12 months of hot telemetry, followed by encrypted archival and verified
restore before batch deletion. Compliance Ledger, audit, surrender, incentive, and
evidence retention must follow the approved legal/records policy. Database rows and
evidence files must be backed up, archived, and restored as one consistent set.
