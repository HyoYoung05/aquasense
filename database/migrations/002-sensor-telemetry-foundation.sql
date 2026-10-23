-- AQUASENSE+ sensor telemetry foundation.
-- Apply once after 001-mobile-tokens.sql. Back up before migrating.
-- Sensor fields remain nullable when a module has no valid reading.
ALTER TABLE sensor_readings
    MODIFY temperature_c DECIMAL(6,2) NULL,
    MODIFY level_status ENUM('NORMAL','LOW','MEDIUM','HIGH','CRITICAL','OVERFLOW','WARNING') NOT NULL,
    ADD COLUMN IF NOT EXISTS is_test BOOLEAN NOT NULL DEFAULT FALSE AFTER is_simulated;
