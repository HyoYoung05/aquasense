# AQUASENSE+ Deployment

This release is portable to a standard Apache/Nginx PHP host or VPS. XAMPP is used
only for local development. Production does not require the developer computer,
Tailscale, a LAN address, Windows Task Scheduler, or phpMyAdmin.

## Requirements

- public domain and valid TLS certificate
- PHP 8.1+ with PDO MySQL, mbstring, JSON, fileinfo, DOM, and cURL
- MariaDB 10.4+ or compatible MySQL with InnoDB
- Composer 2, cron (or host scheduler), and persistent private file storage
- Apache with overrides enabled, or equivalent Nginx deny/routing rules

## Fresh installation

1. Create a dedicated empty database and least-privilege database account.
2. Import `database/schema.sql`.
3. Import migrations `001` through `008` in numeric order.
4. Import `database/reference-data.sql`. It creates only the three role labels.
5. Do **not** import `database/sample-data.sql` or run development helpers.
6. Run `composer install --no-dev --classmap-authoritative`.
7. Create private upload/log directories and grant the PHP worker write access.
8. Configure the environment variables below.
9. Run the first administrator command from the application directory:

   ```sh
   export AQUASENSE_BOOTSTRAP_ADMIN_PASSWORD=''replace-in-secret-shell''
   php scripts/create-admin.php --email=''real-admin@example.gov.ph'' --name=''Real Administrator''
   unset AQUASENSE_BOOTSTRAP_ADMIN_PASSWORD
   ```

   It works only when no administrator exists and writes an audit event.
10. Configure cron, web-server denials, TLS, monitoring, backups, Flutter, and ESP32.
11. Complete `docs/PRODUCTION_CHECKLIST.md` on staging and again on production.

## Upgrade from 0.9.0

Back up the database and private evidence together, deploy the 1.0.0 files, run
`composer install --no-dev --classmap-authoritative`, and reapply migration 008
(it is idempotent). Phase 8 adds no schema migration. Add the new environment
variables, update web-server rules for `api/health.php`, clear PHP opcode cache,
run smoke checks, then enable traffic. Roll back code to the recorded commit if
smoke checks fail; restore the backup only if data was changed outside the normal
application workflow.

## Environment variables

| Variable | Required | Purpose |
| --- | --- | --- |
| `AQUASENSE_APP_ENV` | yes | `production` |
| `AQUASENSE_APP_URL` | yes | canonical public HTTPS app URL, including subpath |
| `AQUASENSE_BASE_PATH` | yes | empty at domain root or leading-slash subpath |
| `AQUASENSE_TIMEZONE` | yes | display timezone, normally `Asia/Manila` |
| `AQUASENSE_DB_HOST/PORT/NAME/USER/PASSWORD` | yes | dedicated database connection |
| `AQUASENSE_STORAGE_PATH` | yes | absolute private persistent evidence directory |
| `AQUASENSE_LOG_PATH` | yes | protected writable PHP log file |
| `AQUASENSE_LOG_LEVEL` | yes | `error`, `warning`, or `info` |
| `AQUASENSE_TRUSTED_PROXY_IPS` | when proxied | comma-separated controlled proxy IPs |
| `AQUASENSE_MOBILE_WEB_ORIGINS` | Flutter Web only | comma-separated exact HTTPS origins |
| telemetry variables | optional | intervals, body size, clock skew, distance bounds |
| `AQUASENSE_REPORT_EXPORT_MAX_PER_MINUTE` | optional | per-user export ceiling; default 10 |
| `AQUASENSE_UPLOAD_MAX_SUBMISSIONS_PER_HOUR` | optional | per-owner upload ceiling; default 20 |

The app does not load dotenv files. Use the host secret manager/process environment,
or copy `config/production.example.php` to ignored `config/local.php`.

## Web server and storage

Preserve all included Apache `.htaccess` files. For Nginx, deny dotfiles,
`config`, `database`, `includes`, `tests`, `logs`, `uploads`, Composer
metadata, Markdown/text/SQL/log/backup files, and `vendor`; then allow only named
API PHP files. Set the document root so source/private folders cannot be downloaded.
The health route is `GET /api/health.php` and returns only `{"status":"ok"}` or
`{"status":"unavailable"}`.

Set PHP `upload_max_filesize` and `post_max_size` above the application evidence
limit. Keep evidence outside the document root. Schedule:
`php scripts/check_offline_devices.php`
at least once per minute or faster than the configured offline threshold.

## Client configuration

Build Flutter with its one ignored production JSON file containing the public HTTPS
API base URL. Configure ESP32 firmware through its one local config header with the
same public API host, device code, trap ID, Wi-Fi, and a per-device key. Neither
client receives MySQL credentials. Validate Android and a physical ESP32 from a
network unrelated to the server before launch.
