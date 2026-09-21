-- Apply once after 001-mobile-tokens.sql. Back up before migrating.
-- No fabricated temperature for a distance-only sensor.
ALTER TABLE sensor_readings
    MODIFY temperature_c DECIMAL(6,2) NULL,
    MODIFY level_status ENUM('NORMAL','LOW','MEDIUM','HIGH','CRITICAL','OVERFLOW','WARNING') NOT NULL,
    ADD COLUMN is_test BOOLEAN NOT NULL DEFAULT FALSE AFTER is_simulated;
CREATE TABLE device_ultrasonic_test_config (
    device_id BIGINT UNSIGNED PRIMARY KEY,
    empty_distance_cm DECIMAL(10,2) NOT NULL,
    full_distance_cm DECIMAL(10,2) NOT NULL,
    warning_percent DECIMAL(5,2) NOT NULL,
    critical_percent DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    CHECK (full_distance_cm >= 2 AND empty_distance_cm > full_distance_cm AND empty_distance_cm <= 400),
    CHECK (warning_percent > 0 AND warning_percent < critical_percent AND critical_percent < 100)
) ENGINE=InnoDB;
