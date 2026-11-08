-- Keep working sensors reporting when ultrasonic distance is unavailable.
-- Existing readings are preserved. Apply before deploying the PHP/ESP32 update.
ALTER TABLE sensor_readings
    MODIFY waste_level_percent DECIMAL(5,2) NULL,
    MODIFY level_status ENUM('NORMAL','LOW','MEDIUM','HIGH','CRITICAL','OVERFLOW','WARNING','UNKNOWN') NOT NULL;
