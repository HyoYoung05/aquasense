-- Phase 6 Sana Oil incentive processing and Digital Compliance Ledger.
-- Additive and data preserving; does not seed an official conversion rule.
SET time_zone = '+00:00';

ALTER TABLE incentive_rules
  MODIFY oil_unit ENUM('L','kg') NOT NULL,
  MODIFY rice_unit ENUM('kg','g') NOT NULL DEFAULT 'kg',
  ADD COLUMN IF NOT EXISTS calculation_type ENUM('FIXED_PER_THRESHOLD','FIXED_TRANSACTION') NOT NULL DEFAULT 'FIXED_PER_THRESHOLD' AFTER rice_unit,
  ADD COLUMN IF NOT EXISTS is_test BOOLEAN NOT NULL DEFAULT FALSE AFTER is_active,
  ADD INDEX IF NOT EXISTS idx_rule_unit_active_dates (oil_unit,is_active,effective_date,end_date);

ALTER TABLE incentive_transactions
  MODIFY rice_unit ENUM('kg','g') NOT NULL DEFAULT 'kg',
  MODIFY status ENUM('PENDING','CALCULATED','APPROVED_FOR_DISTRIBUTION','DISTRIBUTED','CANCELLED') NOT NULL DEFAULT 'CALCULATED',
  ADD COLUMN IF NOT EXISTS transaction_code VARCHAR(40) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS establishment_id BIGINT UNSIGNED NULL AFTER oil_surrender_id,
  ADD COLUMN IF NOT EXISTS owner_user_id BIGINT UNSIGNED NULL AFTER establishment_id,
  ADD COLUMN IF NOT EXISTS oil_quantity DECIMAL(10,3) NULL AFTER rule_id,
  ADD COLUMN IF NOT EXISTS oil_unit ENUM('L','kg') NULL AFTER oil_quantity,
  ADD COLUMN IF NOT EXISTS rule_name_snapshot VARCHAR(150) NULL AFTER oil_unit,
  ADD COLUMN IF NOT EXISTS oil_threshold_snapshot DECIMAL(10,3) NULL AFTER rule_name_snapshot,
  ADD COLUMN IF NOT EXISTS rule_reward_snapshot DECIMAL(10,3) NULL AFTER oil_threshold_snapshot,
  ADD COLUMN IF NOT EXISTS calculation_type_snapshot ENUM('FIXED_PER_THRESHOLD','FIXED_TRANSACTION') NULL AFTER rule_reward_snapshot,
  ADD COLUMN IF NOT EXISTS qualifying_blocks INT UNSIGNED NOT NULL DEFAULT 1 AFTER calculation_type_snapshot,
  ADD COLUMN IF NOT EXISTS processed_at DATETIME NULL AFTER calculated_by,
  ADD COLUMN IF NOT EXISTS distribution_notes VARCHAR(2000) NULL AFTER distributed_at,
  ADD COLUMN IF NOT EXISTS status_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER distribution_notes,
  ADD UNIQUE INDEX IF NOT EXISTS uq_incentive_transaction_code (transaction_code),
  ADD INDEX IF NOT EXISTS idx_incentive_establishment_status (establishment_id,status),
  ADD INDEX IF NOT EXISTS idx_incentive_status_processed (status,processed_at),
  ADD INDEX IF NOT EXISTS idx_incentive_processed_at (processed_at),
  ADD INDEX IF NOT EXISTS idx_incentive_distributed_at (distributed_at);

UPDATE incentive_transactions it
JOIN oil_surrenders os ON os.id=it.oil_surrender_id
JOIN incentive_rules ir ON ir.id=it.rule_id
SET it.transaction_code=COALESCE(it.transaction_code,CONCAT('INC-LEGACY-',LPAD(it.id,8,'0'))),
    it.establishment_id=COALESCE(it.establishment_id,os.establishment_id),
    it.owner_user_id=COALESCE(it.owner_user_id,os.submitted_by),
    it.oil_quantity=COALESCE(it.oil_quantity,os.oil_quantity),
    it.oil_unit=COALESCE(it.oil_unit,os.oil_unit),
    it.rule_name_snapshot=COALESCE(it.rule_name_snapshot,ir.name),
    it.oil_threshold_snapshot=COALESCE(it.oil_threshold_snapshot,ir.minimum_oil_quantity),
    it.rule_reward_snapshot=COALESCE(it.rule_reward_snapshot,ir.rice_reward_quantity),
    it.calculation_type_snapshot=COALESCE(it.calculation_type_snapshot,ir.calculation_type),
    it.processed_at=COALESCE(it.processed_at,it.created_at),
    it.status=IF(it.status='PENDING','CALCULATED',it.status);

ALTER TABLE incentive_transactions
  DROP FOREIGN KEY IF EXISTS fk_incentive_establishment,
  DROP FOREIGN KEY IF EXISTS fk_incentive_owner;

ALTER TABLE incentive_transactions
  MODIFY transaction_code VARCHAR(40) NOT NULL,
  MODIFY establishment_id BIGINT UNSIGNED NOT NULL,
  MODIFY owner_user_id BIGINT UNSIGNED NOT NULL,
  MODIFY oil_quantity DECIMAL(10,3) NOT NULL,
  MODIFY oil_unit ENUM('L','kg') NOT NULL,
  MODIFY rule_name_snapshot VARCHAR(150) NOT NULL,
  MODIFY oil_threshold_snapshot DECIMAL(10,3) NOT NULL,
  MODIFY rule_reward_snapshot DECIMAL(10,3) NOT NULL,
  MODIFY calculation_type_snapshot ENUM('FIXED_PER_THRESHOLD','FIXED_TRANSACTION') NOT NULL,
  MODIFY processed_at DATETIME NOT NULL,
  MODIFY status ENUM('CALCULATED','APPROVED_FOR_DISTRIBUTION','DISTRIBUTED','CANCELLED') NOT NULL DEFAULT 'CALCULATED';

ALTER TABLE incentive_transactions
  ADD CONSTRAINT fk_incentive_establishment FOREIGN KEY IF NOT EXISTS (establishment_id) REFERENCES establishments(id),
  ADD CONSTRAINT fk_incentive_owner FOREIGN KEY IF NOT EXISTS (owner_user_id) REFERENCES users(id);

ALTER TABLE compliance_ledger
  ADD COLUMN IF NOT EXISTS event_code VARCHAR(40) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS grease_trap_id BIGINT UNSIGNED NULL AFTER establishment_id,
  ADD COLUMN IF NOT EXISTS device_id BIGINT UNSIGNED NULL AFTER grease_trap_id,
  ADD COLUMN IF NOT EXISTS event_timestamp DATETIME NULL AFTER description,
  ADD COLUMN IF NOT EXISTS dedupe_key VARCHAR(190) NULL AFTER event_timestamp,
  ADD UNIQUE INDEX IF NOT EXISTS uq_ledger_event_code (event_code),
  ADD UNIQUE INDEX IF NOT EXISTS uq_ledger_dedupe_key (dedupe_key),
  ADD INDEX IF NOT EXISTS idx_ledger_related_record (related_record_type,related_record_id),
  ADD INDEX IF NOT EXISTS idx_ledger_device_time (device_id,event_timestamp),
  ADD INDEX IF NOT EXISTS idx_ledger_trap_time (grease_trap_id,event_timestamp);

UPDATE compliance_ledger
SET event_code=COALESCE(event_code,CONCAT('LED-LEGACY-',LPAD(id,8,'0'))),
    event_timestamp=COALESCE(event_timestamp,created_at);

ALTER TABLE compliance_ledger
  DROP INDEX IF EXISTS idx_ledger_establishment_time,
  DROP INDEX IF EXISTS idx_ledger_event_time,
  ADD INDEX idx_ledger_establishment_time (establishment_id,event_timestamp),
  ADD INDEX idx_ledger_event_time (event_type,event_timestamp);

ALTER TABLE compliance_ledger
  DROP FOREIGN KEY IF EXISTS fk_ledger_grease_trap,
  DROP FOREIGN KEY IF EXISTS fk_ledger_device;

ALTER TABLE compliance_ledger
  MODIFY event_code VARCHAR(40) NOT NULL,
  MODIFY event_timestamp DATETIME NOT NULL;

ALTER TABLE compliance_ledger
  ADD CONSTRAINT fk_ledger_grease_trap FOREIGN KEY IF NOT EXISTS (grease_trap_id) REFERENCES grease_traps(id),
  ADD CONSTRAINT fk_ledger_device FOREIGN KEY IF NOT EXISTS (device_id) REFERENCES devices(id);
