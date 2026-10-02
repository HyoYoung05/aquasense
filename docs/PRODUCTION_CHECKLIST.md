# AQUASENSE+ Production Checklist

Record operator, date, host, release commit, evidence links, and pass/fail for every
item. Do not launch with an unexplained failure.

## Hosting and TLS

- [ ] Always-on public PHP/MySQL hosting is provisioned; developer PC is unnecessary.
- [ ] Domain resolves correctly and has a valid trusted certificate.
- [ ] HTTP browser requests redirect to the canonical HTTPS URL.
- [ ] Credential-bearing API HTTP requests fail closed; HTTPS API calls succeed.
- [ ] TLS renewal, uptime, certificate-expiry, disk, PHP-error, and database alerts work.
- [ ] Tailscale, localhost, XAMPP, LAN IPs, and Windows Task Scheduler are absent from
      the production dependency chain.

## Configuration and secrets

- [ ] Environment is `production`; canonical URL, base path, timezone, log level,
      database, storage, CORS, and rate variables are reviewed.
- [ ] Dedicated database password is strong and stored outside Git/document root.
- [ ] Private upload and log paths persist across deployments and are writable only
      by the required service account.
- [ ] Trusted proxy IPs and Flutter Web CORS origins are exact and minimal.
- [ ] No `.env`, `config/local.php`, keys, dumps, tokens, device credentials, or
      APK signing secrets are tracked or publicly downloadable.
- [ ] Development sample accounts, sample businesses, simulation data, and test
      incentive rules are absent.

## Database and recovery

- [ ] Schema, migrations 001-008, and reference data imported successfully.
- [ ] Foreign keys, unique constraints, and required indexes match the release.
- [ ] Thresholds/calibration and official incentive rules were approved and entered.
- [ ] Encrypted off-host database plus evidence backup completed.
- [ ] Restore drill completed and recovery time/data-loss window recorded.
- [ ] Telemetry/evidence/audit retention policy and capacity alerts approved.

## Application security and behavior

- [ ] Security headers appear over HTTPS; private paths return 403/404.
- [ ] First administrator exists; bootstrap password variable was removed.
- [ ] Administrator permissions, environmental-staff read-only limits, session expiry,
      CSRF, logout, login throttling, and owner staff-login denial were verified.
- [ ] Owner A cannot read Owner B profile/dashboard/surrenders/photos/incentives.
- [ ] Device key rotation/revocation, invalid key, oversize body, bad range, retry,
      stale/future timestamp, and rate-limit behavior were verified.
- [ ] Upload MIME/size/image checks and export/upload rate limits were verified.
- [ ] Simulator and development helpers refuse production execution.
- [ ] Error pages/API errors contain no stack traces, paths, SQL, or secrets.

## End-to-end operations

- [ ] Staff registration creates the expected audit and Compliance Ledger records.
- [ ] Physical ESP32 sends calibrated HTTPS telemetry and updates Monitoring/history.
- [ ] Normal, warning, critical, overflow, and offline/recovery paths were observed.
- [ ] Android owner signs in through the deployed API on an unrelated network.
- [ ] Owner evidence submission is visible only to that owner and authorized staff.
- [ ] Hybrid Verification shows honest telemetry evidence and missing-data states.
- [ ] Manual approval/rejection, incentive calculation, rice distribution, and
      immutable historical snapshots were verified.
- [ ] Reports filter/page correctly; CSV is spreadsheet-safe; PDF/print render.
- [ ] Audit Log remains separate from the environmental Compliance Ledger.
- [ ] Health check, cron offline check, backup job, and monitoring alerts are active.

## Release decision

- [ ] Complete automated suite passed against disposable staging data.
- [ ] Desktop, tablet, mobile, keyboard-only, and supported browser checks passed.
- [ ] Flutter analyze/test/release build passed with production configuration.
- [ ] Known limitations and incident/rollback contacts were accepted by the Barangay.
- [ ] Release owner approved go-live.
