-- DEVELOPMENT DATA ONLY. Never import automatically or into production.
-- Import manually once, after schema.sql, into a fresh development installation.
-- The single fictional website Admin uses AquaSense!2026, hashed with PHP password_hash().
-- No real residents, sensor measurements, or incentive conversion rules are seeded.
SET time_zone = '+00:00';
START TRANSACTION;

INSERT INTO roles (slug, name) VALUES
    ('administrator', 'Barangay Administrator'),
    ('environmental_staff', 'Barangay Environmental Staff'),
    ('owner', 'Carinderia Owner'),
    ('system_admin', 'Admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET @system_admin_role_id = (SELECT id FROM roles WHERE slug = 'system_admin');

INSERT INTO users (role_id, full_name, email, password_hash) VALUES
    (@system_admin_role_id, 'AQUASENSE+ Admin (Demo)', 'systemadmin@aquasense.test', '$2y$12$ArYeuNFsn1OIprnoIGNZvOtBMyOFL6elYdjrPt5EE4YAhx0Ag8w6e')
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    is_active = 1;

SET @system_admin_user_id = (SELECT id FROM users WHERE email = 'systemadmin@aquasense.test');

INSERT INTO establishments (id, registration_code, business_name, owner_name, address, registration_date, created_by)
VALUES (1, 'DEMO-EST-001', 'Demo Kusina', 'Taylor Cruz (Fictional)', 'Demo Street, Barangay San Antonio (fictional address)', '2026-09-14', @system_admin_user_id);

INSERT INTO grease_traps (id, establishment_id, trap_code, name, capacity_liters,
    low_threshold, medium_threshold, high_threshold, critical_threshold, installation_date)
VALUES (1, 1, 'DEMO-GT-001', 'Demo kitchen grease trap', 50, 20, 50, 75, 90, '2026-09-14');

INSERT INTO devices (id, device_code, name, device_type, installation_date)
VALUES (1, 'DEMO-AQS-001', 'Demo monitoring device', 'SIMULATED', '2026-09-14');

INSERT INTO device_assignments (id, device_id, grease_trap_id, started_at)
VALUES (1, 1, 1, '2026-09-14 00:00:00');

-- Simulated development-only event shared by the website bell and Owner app.
INSERT INTO sensor_readings (device_assignment_id,waste_level_percent,level_status,is_simulated,is_test,reading_uuid,recorded_at)
VALUES (1, 92, 'CRITICAL', 1, 1, '00000000-0000-4000-8000-000000000001', UTC_TIMESTAMP());
SET @demo_notification_reading_id = LAST_INSERT_ID();

INSERT INTO alerts (device_assignment_id,sensor_reading_id,alert_type,severity,sensor_name,sensor_value,threshold_value,message,status,first_triggered_at,last_triggered_at)
VALUES (1,@demo_notification_reading_id,'CRITICAL_LEVEL','CRITICAL','waste_level_percent',92,90,'Simulated development alert for website and mobile notification testing.','ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP());

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
    ('application_name', 'AQUASENSE+', 'Application display name.'),
    ('emulsion_temperature_threshold', '40', 'Initial emulsion warning temperature in degrees Celsius.'),
    ('high_temperature_threshold', '45', 'Initial high temperature warning in degrees Celsius; requires field validation.'),
    ('default_low_threshold', '20', 'Initial low waste level threshold (%).'),
    ('default_medium_threshold', '50', 'Initial medium waste level threshold (%).'),
    ('default_high_threshold', '75', 'Initial high waste level threshold (%).'),
    ('default_critical_threshold', '90', 'Initial critical waste level threshold (%).'),
    ('overflow_threshold', '100', 'Initial overflow waste level threshold (%).'),
    ('device_offline_timeout_minutes', '5', 'Minutes without telemetry before a device is considered offline.'),
    ('high_turbidity_threshold', '500', 'Initial turbidity warning in NTU; requires sensor calibration.'),
    ('turbidity_alert_enabled', '0', 'Enable only after SEN0189 NTU calibration and threshold approval.'),
    ('flow_rate_min', '0', 'Initial minimum flow in liters per minute; requires field validation.'),
    ('flow_rate_max', '10', 'Initial maximum flow in liters per minute; requires field validation.'),
    ('flow_alert_enabled', '0', 'Enable only after YF-S201 flow calibration and threshold approval.'),
    ('notifications_enabled', '1', 'Enable in-app notifications when the alerts module is implemented.'),
    ('default_oil_unit', 'L', 'Default oil quantity unit.'),
    ('oil_surrender_max_upload_bytes', '5242880', 'Maximum oil-surrender evidence upload size in bytes.'),
    ('oil_surrender_telemetry_window_hours', '6', 'Hours before and after surrender used for Hybrid Verification telemetry evidence.'),
    ('default_rice_unit', 'kg', 'Default rice quantity unit.')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description = VALUES(description);

INSERT INTO compliance_ledger (event_code, event_type, establishment_id, related_record_type, related_record_id, description, event_timestamp, dedupe_key, created_by)
VALUES ('LED-DEVELOPMENT-SETUP', 'DEVELOPMENT_SETUP', 1, 'establishments', 1, 'Fictional establishment, grease trap, simulated notification, and device added for local development. No actual surrender or incentive activity.', UTC_TIMESTAMP(), 'DEVELOPMENT_SETUP:1', @system_admin_user_id);

INSERT INTO audit_logs (user_id, action, record_type, record_id)
VALUES (@system_admin_user_id, 'DEVELOPMENT_SETUP', 'users', @system_admin_user_id);

COMMIT;
