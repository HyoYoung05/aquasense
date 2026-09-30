-- AQUASENSE+ 0.5.0 Phase 3: production telemetry and monitoring.
-- Apply once after migrations 001-003. Existing records are preserved.
SET time_zone = '+00:00';

ALTER TABLE grease_traps
    ADD COLUMN IF NOT EXISTS empty_distance_cm DECIMAL(10,2) NULL AFTER critical_threshold,
    ADD COLUMN IF NOT EXISTS full_distance_cm DECIMAL(10,2) NULL AFTER empty_distance_cm;

-- Preserve the calibration already used by the development AQS-001 test.
UPDATE grease_traps g
JOIN device_assignments a ON a.grease_trap_id = g.id AND a.ended_at IS NULL
JOIN device_ultrasonic_test_config c ON c.device_id = a.device_id
SET g.empty_distance_cm = COALESCE(g.empty_distance_cm, c.empty_distance_cm),
    g.full_distance_cm = COALESCE(g.full_distance_cm, c.full_distance_cm);

ALTER TABLE sensor_readings
    ADD COLUMN IF NOT EXISTS device_reported_percent DECIMAL(5,2) NULL AFTER waste_level_percent,
    ADD COLUMN IF NOT EXISTS reading_uuid CHAR(36) NULL AFTER is_test,
    ADD COLUMN IF NOT EXISTS sequence_number BIGINT UNSIGNED NULL AFTER reading_uuid,
    ADD COLUMN IF NOT EXISTS payload_hash CHAR(64) NULL AFTER sequence_number,
    ADD COLUMN IF NOT EXISTS received_at DATETIME NULL AFTER recorded_at;

UPDATE sensor_readings
SET received_at = COALESCE(received_at, created_at)
WHERE received_at IS NULL;

ALTER TABLE sensor_readings
    MODIFY received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE INDEX IF NOT EXISTS uq_reading_assignment_uuid (device_assignment_id, reading_uuid),
    ADD UNIQUE INDEX IF NOT EXISTS uq_reading_assignment_sequence (device_assignment_id, sequence_number),
    ADD INDEX IF NOT EXISTS idx_reading_received (received_at);

SET @calibration_check_sql = IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='grease_traps' AND constraint_name='chk_phase3_ultrasonic_calibration')=0,
    'ALTER TABLE grease_traps ADD CONSTRAINT chk_phase3_ultrasonic_calibration CHECK ((empty_distance_cm IS NULL AND full_distance_cm IS NULL) OR (full_distance_cm >= 2 AND empty_distance_cm > full_distance_cm AND empty_distance_cm <= 400))',
    'SELECT 1'
);
PREPARE phase3_statement FROM @calibration_check_sql;
EXECUTE phase3_statement;
DEALLOCATE PREPARE phase3_statement;

SET @reported_check_sql = IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='sensor_readings' AND constraint_name='chk_phase3_reported_percent')=0,
    'ALTER TABLE sensor_readings ADD CONSTRAINT chk_phase3_reported_percent CHECK (device_reported_percent IS NULL OR device_reported_percent BETWEEN 0 AND 100)',
    'SELECT 1'
);
PREPARE phase3_statement FROM @reported_check_sql;
EXECUTE phase3_statement;
DEALLOCATE PREPARE phase3_statement;
