-- AQUASENSE+ Phase 7 reporting index.
-- Additive and safe to run more than once on the supported MariaDB version.
ALTER TABLE audit_logs
  ADD INDEX IF NOT EXISTS idx_audit_created_at (created_at);
