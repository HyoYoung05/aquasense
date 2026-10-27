<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--allow-demo-data', $argv, true)) {
    fwrite(STDERR, "Usage: php database/website-development.php --allow-demo-data\n");
    exit(1);
}

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($config['environment'] ?? '') !== 'development') {
    throw new RuntimeException('Development website accounts cannot be provisioned outside development mode.');
}

$pdo = db();
$pdo->exec((string) file_get_contents(__DIR__ . '/migrations/009-system-admin-role.sql'));
$pdo->exec((string) file_get_contents(__DIR__ . '/migrations/010-separate-odor-reading.sql'));
$pdo->exec((string) file_get_contents(__DIR__ . '/migrations/011-phase4-sensor-fields.sql'));

$roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug='system_admin' LIMIT 1")->fetchColumn();
$email = 'systemadmin@aquasense.test';
$query = $pdo->prepare('SELECT id FROM users WHERE email=?');
$query->execute([$email]);
$userId = (int) $query->fetchColumn();
$values = [$roleId, 'AQUASENSE+ Admin (Demo)', password_hash('AquaSense!2026', PASSWORD_DEFAULT)];

if ($userId > 0) {
    $pdo->prepare('UPDATE users SET role_id=?,full_name=?,password_hash=?,is_active=1 WHERE id=?')
        ->execute([...$values, $userId]);
} else {
    $pdo->prepare('INSERT INTO users(role_id,full_name,email,password_hash,is_active) VALUES(?,?,?,?,1)')
        ->execute([$values[0], $values[1], $email, $values[2]]);
    $userId = (int) $pdo->lastInsertId();
}

$fixture = $pdo->query("SELECT a.id AS assignment_id,d.id AS device_id FROM device_assignments a
    JOIN devices d ON d.id=a.device_id
    JOIN grease_traps g ON g.id=a.grease_trap_id
    JOIN establishments e ON e.id=g.establishment_id
    WHERE e.registration_code='DEMO-EST-001' AND g.trap_code='DEMO-GT-001'
      AND d.device_code='DEMO-AQS-001' AND a.ended_at IS NULL LIMIT 1")->fetch();
if ($fixture) {
    $assignmentId = (int) $fixture['assignment_id'];
    // The simulated fixture has no physical heartbeat, so it must not create a
    // second misleading offline notification during notification UI tests.
    $pdo->prepare('UPDATE devices SET last_seen_at=NULL WHERE id=?')->execute([(int) $fixture['device_id']]);
    $pdo->prepare("UPDATE alerts SET status='RESOLVED',resolved_at=UTC_TIMESTAMP(),
        resolution_note='Retired automatic offline alert for the simulated notification fixture.'
        WHERE device_assignment_id=? AND alert_type='DEVICE_OFFLINE' AND status IN ('ACTIVE','ACKNOWLEDGED')")
        ->execute([$assignmentId]);
    $pdo->prepare("INSERT INTO sensor_readings
        (device_assignment_id,waste_level_percent,level_status,is_simulated,is_test,reading_uuid,recorded_at)
        VALUES (?,92,'CRITICAL',1,1,'00000000-0000-4000-8000-000000000001',UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),recorded_at=UTC_TIMESTAMP(),received_at=UTC_TIMESTAMP()")
        ->execute([$assignmentId]);
    $readingId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO alerts
        (device_assignment_id,sensor_reading_id,alert_type,severity,sensor_name,sensor_value,threshold_value,message,status,first_triggered_at,last_triggered_at)
        VALUES (?,?,'CRITICAL_LEVEL','CRITICAL','waste_level_percent',92,90,
          'Simulated development alert for website and mobile notification testing.','ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE sensor_reading_id=VALUES(sensor_reading_id),severity='CRITICAL',sensor_value=92,
          threshold_value=90,message=VALUES(message),last_triggered_at=UTC_TIMESTAMP(),trigger_count=trigger_count+1")
        ->execute([$assignmentId, $readingId]);
    audit('DEVELOPMENT_NOTIFICATION_READY', $userId, 'sensor_readings', $readingId);
}

audit('DEVELOPMENT_ADMIN_PROVISIONED', $userId, 'users', $userId);
fwrite(STDOUT, "One website demo Admin is ready; existing user accounts were left unchanged.\n");
fwrite(STDOUT, !$fixture
    ? "Demo notification was skipped because the Demo Kusina assignment was not found.\n"
    : "The shared simulated notification fixture is ready.\n");
