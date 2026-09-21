# AQUASENSE+ Production Deployment

## Target architecture

Deploy the existing PHP application and MariaDB/MySQL database to an always-on
internet-accessible PHP host or VPS. XAMPP remains a development tool only.

ESP32 devices, the administrative website and the Flutter owner app will all use
the same HTTPS PHP API and shared database. A VPN or tunnel on a developer PC is
not production hosting because the system stops when that PC, tunnel, internet
connection or local database stops.

## Server requirements

- PHP 8.1+ with PDO MySQL and JSON; MariaDB 10.4+ or compatible MySQL.
- Apache with .htaccess enabled, or equivalent Nginx access rules.
- A public domain, valid TLS certificate and HTTPS-only traffic.
- A dedicated least-privilege database account with a strong password.
- A private writable upload directory outside the document root.
- A private writable PHP log path, scheduled database backups and an always-on
  process/service manager supplied by the host.

The application uses paths relative to its own directory and configurable
storage/log paths. It does not need XAMPP or a Windows filesystem layout.

## Configuration

Preferred: set server environment variables through the hosting control panel,
Apache/Nginx/PHP-FPM configuration, container secrets or service manager:

| Variable | Purpose |
| --- | --- |
| AQUASENSE_APP_ENV | Must be production |
| AQUASENSE_BASE_PATH | Empty at domain root, or the deployment subdirectory beginning with / |
| AQUASENSE_DB_HOST / PORT / NAME / USER / PASSWORD | Production database connection |
| AQUASENSE_STORAGE_PATH | Absolute private writable upload directory |
| AQUASENSE_LOG_PATH | Absolute private writable application log file |
| AQUASENSE_TRUSTED_PROXY_IPS | Optional comma-separated reverse-proxy IPs |
| AQUASENSE_MOBILE_WEB_ORIGINS | Optional exact comma-separated HTTPS Flutter-web origins |

If the host cannot set environment variables, copy
`config/production.example.php` to ignored `config/local.php` on the server and
replace every placeholder. Never commit that file or send it with public source.

Production mode requires explicit database and storage configuration, forces
HTTPS, disables localhost browser preview, marks session cookies Secure, and
sends HSTS. Forwarded HTTPS is trusted only from explicitly configured proxy IPs.

Flutter Web CORS in production uses exact origins from `AQUASENSE_MOBILE_WEB_ORIGINS`.
Each configured value must use HTTPS. Localhost patterns, wildcard origins, and browser
credential cookies are rejected in production; native Android requests are unaffected.

## Database and data

Create an empty production database, then import `database/schema.sql` and
`database/migrations/001-mobile-tokens.sql`, then `database/migrations/002-ultrasonic-test.sql`. Do not import
`database/sample-data.sql` and do not run `database/mobile-development.php`.
The helper refuses to run when AQUASENSE_APP_ENV is production.

Create real administrator, staff and owner accounts through an approved
provisioning process before launch. Remove all development accounts from any
database copied from testing.

## Web server

Deploy the repository contents without `config/local.php`, runtime logs, uploaded
files, development APKs or database dumps. Preserve the included denial rules for
configuration, database, includes, tests, logs and uploads. For Nginx, reproduce
those denials explicitly because Nginx does not read .htaccess.

Restrict phpMyAdmin or other database tools to administrators; they are not part
of the public application. Ensure the PHP API can write only to the configured
private storage/log locations.

Run `php tests/production-config.php` during the release build. Run integration
tests only against a separate staging database, never the live production data.

## Flutter release

Copy `aquasense_mobile/config/api.production.example.json` to the ignored
`config/api.production.json`, set the deployed HTTPS API URL, then:

```powershell
flutter test
flutter analyze
flutter build apk --release --dart-define-from-file=config/api.production.json
```

The release build fails closed when the URL is missing and rejects HTTP.
Flutter contains no database credentials and communicates only with PHP.

## ESP32 preparation

Future device ingestion endpoints must use the same deployed HTTPS API. Provision
one revocable device credential per device, store only credential hashes, validate
telemetry server-side, apply rate limits and bind each device to its current
assignment. Device firmware receives only its API URL and device credential; it
must never contain database credentials.

## Release checklist

1. Back up and test database restore.
2. Confirm production environment validation passes.
3. Confirm HTTP requests are rejected and HTTPS certificate validation succeeds.
4. Confirm private files and directories return 403/404.
5. Test owner isolation, staff authorization, logout and token revocation on staging.
6. Build Flutter with the one production configuration file and test on a real phone.
7. Configure monitoring for uptime, TLS expiry, errors, storage capacity and backups.

BACKEND 0.3.0 SCHEMA UPDATE
Apply database/migrations/002-ultrasonic-test.sql once after migration 001, including
when deploying the updated owner dashboard reader. Back up first. This preserves
existing readings and supports absent temperature. The new device telemetry test
endpoint is disabled outside development. Do not provision test fixtures in production.
See the sibling aquasense-esp32/README.md for local one-sensor testing.
