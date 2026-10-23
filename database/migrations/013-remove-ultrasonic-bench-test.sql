-- AQUASENSE+ 1.3.7: remove the obsolete one-sensor bench-test identity.
-- Back up the database, then apply once to an existing installation.

UPDATE sensor_readings r
JOIN device_assignments a ON a.id = r.device_assignment_id
JOIN devices d ON d.id = a.device_id
SET r.is_test = FALSE
WHERE d.device_code = 'AQS-001'
  AND d.device_type LIKE '%ULTRASONIC_TEST%';

UPDATE grease_traps g
JOIN device_assignments a ON a.grease_trap_id = g.id
JOIN devices d ON d.id = a.device_id
SET g.trap_code = CONCAT('AQS-GT-', LPAD(g.id, 3, '0')),
    g.name = 'AQUASENSE+ monitored grease trap'
WHERE d.device_code = 'AQS-001'
  AND d.device_type LIKE '%ULTRASONIC_TEST%'
  AND g.trap_code = 'ULTRASONIC-TEST-001';

UPDATE devices
SET name = 'AQUASENSE+ six-sensor node',
    device_type = 'ESP32_SIX_SENSOR'
WHERE device_code = 'AQS-001'
  AND device_type LIKE '%ULTRASONIC_TEST%';

DROP TABLE IF EXISTS device_ultrasonic_test_config;
