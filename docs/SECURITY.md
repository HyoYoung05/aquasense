# AQUASENSE+ Security

## Controls in this release

- Passwords use PHP `password_hash()` and `password_verify()`; login responses do
  not reveal whether an email exists. Attempts are throttled in the database.
- Staff sessions use strict cookie mode, HttpOnly, SameSite=Lax, Secure over HTTPS,
  inactivity expiry, login/logout ID rotation, and CSRF protection for state changes.
- Roles are reloaded from the database on each request. Administrators manage
  records; environmental staff have the documented read/read-only access; owners
  cannot enter the staff website.
- Mobile tokens are random, hashed at rest, expiring, owner-only, and invalidated by
  account deactivation or password change. Every owner query enforces ownership.
- Device credentials are random per device, shown only when generated, hashed at
  rest, revocable, rate limited, and never logged.
- SQL values use prepared statements. IDs, dates, enumerations, sorting, report
  formats, uploads, and telemetry bodies are validated or allow-listed.
- Evidence accepts only decoded JPEG/PNG/WebP files within the configured size,
  assigns a random server filename, stores it privately, and streams it only after
  authorization. Original names never select storage paths.
- Successful exports and major business/security actions are audited. Report export
  and owner upload submissions have server-side rate limits.
- Production enforces HTTPS, secure cookies, HSTS, CSP, frame denial, MIME sniffing
  denial, a same-origin referrer policy, Permissions-Policy, and no-store responses.
- CORS is limited to exact configured HTTPS Flutter Web origins. Native apps send no
  browser Origin. Wildcards and credential cookies are not enabled.
- Exceptions are logged to a protected path; clients receive generic messages.

## Secrets and deployment

Use server-managed environment variables or the ignored `config/local.php`.
Never commit database passwords, device keys, bearer tokens, TLS private keys,
backup archives, production owner data, or production API config. The repository
ignores local configuration, dotenv files, common private-key formats, dumps,
runtime logs, evidence, and ESP32 local headers.

AQUASENSE+ reads environment variables but does not parse a `.env` file. If a
platform uses a dotenv mechanism, keep that file outside Git and let the process
manager export the values.

Terminate TLS at the application server or a trusted reverse proxy. Configure
`AQUASENSE_TRUSTED_PROXY_IPS` only with proxy addresses you control; client
forwarding headers are otherwise ignored. APIs reject plaintext HTTP instead of
redirecting credential-bearing requests. Browser pages use a 308 redirect to the
canonical HTTPS application URL.

## Operating practices

- Give the application database account only the rights it needs on its one schema.
- Restrict phpMyAdmin and SSH/control-panel access with strong authentication.
- Rotate a device credential if firmware or provisioning material is exposed.
- Remove sample accounts and fixture data before deployment.
- Review failed logins, device rejection categories, export volume, privileged
  changes, file-storage growth, backup results, and health/uptime alerts.
- Apply OS, web-server, PHP, Composer, and database security updates on staging first.
- Run `composer audit --locked` and the complete test suite before every release.
- Treat device data as operational evidence; define access and retention with the
  Barangay data owner.
