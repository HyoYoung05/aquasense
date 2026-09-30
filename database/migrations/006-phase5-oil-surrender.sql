-- Phase 5 oil surrender and Hybrid Verification workflow. Additive and data preserving.
SET time_zone = '+00:00';

ALTER TABLE oil_surrenders
  ADD COLUMN IF NOT EXISTS submission_uuid CHAR(36) NULL AFTER transaction_code,
  ADD COLUMN IF NOT EXISTS grease_trap_id BIGINT UNSIGNED NULL AFTER establishment_id,
  ADD COLUMN IF NOT EXISTS device_id BIGINT UNSIGNED NULL AFTER grease_trap_id,
  ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER oil_unit,
  ADD COLUMN IF NOT EXISTS review_started_at DATETIME NULL AFTER verification_status,
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER reviewed_at,
  ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL AFTER approved_at,
  ADD COLUMN IF NOT EXISTS review_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER remarks,
  ADD UNIQUE INDEX IF NOT EXISTS uq_surrender_submission_uuid (submission_uuid),
  ADD INDEX IF NOT EXISTS idx_surrender_review_queue (status, surrendered_at),
  ADD INDEX IF NOT EXISTS idx_surrender_trap_date (grease_trap_id, surrendered_at),
  ADD CONSTRAINT fk_surrender_grease_trap FOREIGN KEY IF NOT EXISTS (grease_trap_id) REFERENCES grease_traps(id),
  ADD CONSTRAINT fk_surrender_device FOREIGN KEY IF NOT EXISTS (device_id) REFERENCES devices(id);

ALTER TABLE oil_surrender_photos
  ADD COLUMN IF NOT EXISTS original_filename VARCHAR(255) NULL AFTER file_path,
  ADD COLUMN IF NOT EXISTS mime_type VARCHAR(50) NULL AFTER original_filename,
  ADD COLUMN IF NOT EXISTS file_size BIGINT UNSIGNED NULL AFTER mime_type,
  ADD COLUMN IF NOT EXISTS uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER uploaded_by,
  ADD INDEX IF NOT EXISTS idx_surrender_photo_parent (oil_surrender_id, created_at);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
 ('oil_surrender_max_upload_bytes','5242880','Maximum oil-surrender evidence upload size in bytes.'),
 ('oil_surrender_telemetry_window_hours','6','Hours before and after surrender used for Hybrid Verification telemetry evidence.')
ON DUPLICATE KEY UPDATE description=VALUES(description);
