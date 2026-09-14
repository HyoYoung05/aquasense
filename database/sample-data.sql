-- DEVELOPMENT DATA ONLY. Import once, after schema.sql, into a fresh installation.
-- Both fictional staff accounts use AquaSense!2026, hashed with PHP password_hash().
-- No real residents, sensor measurements, or incentive conversion rules are seeded.
USE aquasense;
SET time_zone = '+00:00';
START TRANSACTION;

INSERT INTO roles (id, slug, name) VALUES
    (1, 'administrator', 'Barangay Administrator'),
    (2, 'environmental_staff', 'Barangay Environmental Staff'),
    (3, 'owner', 'Carinderia Owner');

INSERT INTO users (id, role_id, full_name, email, password_hash) VALUES
    (1, 1, 'Alex Santos (Demo)', 'admin@aquasense.test', '$2y$12$ArYeuNFsn1OIprnoIGNZvOtBMyOFL6elYdjrPt5EE4YAhx0Ag8w6e'),
    (2, 2, 'Jamie Reyes (Demo)', 'staff@aquasense.test', '$2y$12$ArYeuNFsn1OIprnoIGNZvOtBMyOFL6elYdjrPt5EE4YAhx0Ag8w6e');

INSERT INTO establishments (id, registration_code, business_name, owner_name, address, registration_date, created_by)
VALUES (1, 'DEMO-EST-001', 'Demo Kusina', 'Taylor Cruz (Fictional)', 'Demo Street, Barangay San Antonio (fictional address)', '2026-09-14', 1);

INSERT INTO grease_traps (id, establishment_id, trap_code, name, capacity_liters,
    low_threshold, medium_threshold, high_threshold, critical_threshold, installation_date)
VALUES (1, 1, 'DEMO-GT-001', 'Demo kitchen grease trap', 50, 20, 50, 75, 90, '2026-09-14');

INSERT INTO devices (id, device_code, name, device_type, installation_date)
VALUES (1, 'DEMO-AQS-001', 'Demo monitoring device', 'SIMULATED', '2026-09-14');

INSERT INTO device_assignments (id, device_id, grease_trap_id, started_at)
VALUES (1, 1, 1, '2026-09-14 00:00:00');

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
    ('application_name', 'AQUASENSE+', 'Application display name; settings editor planned for Phase 4.'),
    ('emulsion_temperature_threshold', '40', 'Initial emulsion warning temperature in degrees Celsius.'),
    ('high_temperature_threshold', '45', 'Initial high temperature warning in degrees Celsius; requires field validation.'),
    ('default_low_threshold', '20', 'Initial low waste level threshold (%).'),
    ('default_medium_threshold', '50', 'Initial medium waste level threshold (%).'),
    ('default_high_threshold', '75', 'Initial high waste level threshold (%).'),
    ('default_critical_threshold', '90', 'Initial critical waste level threshold (%).'),
    ('overflow_threshold', '100', 'Initial overflow waste level threshold (%).'),
    ('device_offline_timeout_minutes', '10', 'Minutes without telemetry before a device is considered offline.'),
    ('high_turbidity_threshold', '500', 'Initial turbidity warning in NTU; requires sensor calibration.'),
    ('flow_rate_min', '0', 'Initial minimum flow in liters per minute; requires field validation.'),
    ('flow_rate_max', '10', 'Initial maximum flow in liters per minute; requires field validation.'),
    ('notifications_enabled', '1', 'Enable in-app notifications when the alerts module is implemented.'),
    ('default_oil_unit', 'L', 'Default oil quantity unit.'),
    ('default_rice_unit', 'kg', 'Default rice quantity unit.');

INSERT INTO compliance_ledger (event_type, establishment_id, related_record_type, related_record_id, description, created_by)
VALUES ('DEVELOPMENT_SETUP', 1, 'establishments', 1, 'Fictional establishment, grease trap, and device added for local development. No actual monitoring or surrender activity.', 1);

INSERT INTO audit_logs (user_id, action, record_type, record_id)
VALUES (1, 'DEVELOPMENT_SETUP', 'users', 1);

COMMIT;
