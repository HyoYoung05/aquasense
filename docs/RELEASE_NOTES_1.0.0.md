# AQUASENSE+ 1.0.0 Release Notes

Released: 2026-10-02  
Release stage: Phase 8 final website/PHP backend hardening

AQUASENSE+ 1.0.0 consolidates the Barangay administrative website, ESP32 telemetry
API, owner mobile API, alert workflow, oil surrender and Hybrid Verification,
Sana Oil incentive processing, Compliance Ledger, reporting, CSV/PDF exports, and
technical audit history into a deployment-ready PHP/MySQL package.

Phase 8 adds production/test/development configuration, canonical HTTPS handling,
security and Permissions-Policy headers, API-safe HTTPS failure behavior, a minimal
database health route, per-user export throttling, per-owner upload throttling,
a guarded first-administrator CLI, an administrator-only account directory,
production-safe role reference data, keyboard/focus/reduced-motion improvements,
fresh-install validation, and final operating documentation.

No Phase 8 database schema migration is required. New installations import
`schema.sql`, migrations 001-008, then `reference-data.sql`. Existing 0.9.0
installations retain their data and may safely reapply migration 008.

Known operational limits:

- Public hosting, DNS, TLS, production database credentials, official thresholds,
  official incentive policy, real owner accounts, Android signing, and device
  provisioning require authorized human input.
- Email/SMS/push delivery and password-reset delivery are not implemented.
- CSV export is capped at 10,000 rows and PDF detail at 500 rows.
- Sensor reliability still depends on physical placement, waterproofing, calibration,
  power, connectivity, and field testing.
- A retention period must be formally approved before sustained telemetry growth.
