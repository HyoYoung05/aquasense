-- Phase 4 alert state and threshold management. Additive; preserves Phase 1-3 data.
SET time_zone = '+00:00';

ALTER TABLE alerts
  MODIFY alert_type ENUM('HIGH_LEVEL','CRITICAL_LEVEL','OVERFLOW_WARNING','OVERFLOW','HIGH_TEMPERATURE','EMULSION_WARNING','HIGH_TURBIDITY','ABNORMAL_FLOW','DEVICE_OFFLINE') NOT NULL,
  MODIFY status ENUM('OPEN','ACTIVE','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'ACTIVE';
UPDATE alerts SET status='ACTIVE' WHERE status='OPEN';
ALTER TABLE alerts MODIFY status ENUM('ACTIVE','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'ACTIVE';

ALTER TABLE alerts
  ADD COLUMN IF NOT EXISTS sensor_name VARCHAR(40) NULL AFTER severity,
  ADD COLUMN IF NOT EXISTS threshold_value DECIMAL(12,3) NULL AFTER sensor_value,
  ADD COLUMN IF NOT EXISTS first_triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status,
  ADD COLUMN IF NOT EXISTS last_triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER first_triggered_at,
  ADD COLUMN IF NOT EXISTS trigger_count INT UNSIGNED NOT NULL DEFAULT 1 AFTER last_triggered_at,
  ADD COLUMN IF NOT EXISTS resolution_note VARCHAR(1000) NULL AFTER resolved_at,
  ADD COLUMN IF NOT EXISTS active_alert_key VARCHAR(180)
    AS (IF(status IN ('ACTIVE','ACKNOWLEDGED'), CONCAT(device_assignment_id,':',alert_type), NULL)) STORED,
  ADD UNIQUE INDEX IF NOT EXISTS uq_alert_active_type (active_alert_key),
  ADD INDEX IF NOT EXISTS idx_alert_assignment_history (device_assignment_id, last_triggered_at),
  ADD INDEX IF NOT EXISTS idx_alert_severity_status (severity, status);

UPDATE alerts
SET first_triggered_at=COALESCE(first_triggered_at,created_at),
    last_triggered_at=COALESCE(last_triggered_at,updated_at,created_at);

INSERT INTO system_settings (setting_key,setting_value,description) VALUES
 ('emulsion_temperature_threshold','40','Temperature in degrees Celsius that triggers an emulsion warning.'),
 ('high_temperature_threshold','45','Temperature in degrees Celsius that triggers a high-temperature warning.'),
 ('high_turbidity_threshold','500','Turbidity threshold in NTU; use only with a calibrated sensor.'),
 ('flow_rate_min','0','Minimum expected flow in liters per minute.'),
 ('flow_rate_max','10','Maximum expected flow in liters per minute.'),
 ('overflow_threshold','100','Fill percentage that represents overflow.'),
 ('device_offline_timeout_minutes','10','Minutes without telemetry before a connected active device is offline.')
ON DUPLICATE KEY UPDATE description=VALUES(description);
