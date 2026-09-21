<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['allow-test-fixture', 'output:']);
if (!array_key_exists('allow-test-fixture', $options) || !isset($options['output'])) {
    exit("Usage: php database/ultrasonic-test-setup.php --allow-test-fixture --output=<private device.local.json>\n");
}
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/config/database.php';
if ($config['environment'] !== 'development') throw new RuntimeException('Development only.');
$output = (string) $options['output'];
if (basename($output) !== 'device.local.json' || file_exists($output) || !is_dir(dirname($output))) {
    throw new RuntimeException('Use a new device.local.json in a private existing folder; do not overwrite credentials.');
}
$pdo = db();
if (!$pdo->query("SHOW TABLES LIKE 'device_ultrasonic_test_config'")->fetchColumn()) {
    throw new RuntimeException('Apply migration 002-ultrasonic-test.sql first.');
}
$key = bin2hex(random_bytes(32));
$pdo->beginTransaction();
$written = false;
try {
    $site = $pdo->query("SELECT id, owner_user_id FROM establishments
        WHERE registration_code = 'DEMO-EST-001' AND business_name = 'Demo Kusina' AND is_active = 1 FOR UPDATE")->fetch();
    if (!$site || !$site['owner_user_id']) throw new RuntimeException('Set up the existing demo owner first.');
    $q = $pdo->prepare('SELECT id FROM devices WHERE device_code = ?');
    $q->execute(['AQS-001']);
    if ($q->fetch()) throw new RuntimeException('AQS-001 already exists. Keep its existing credential; provision no duplicates.');
    $pdo->prepare("INSERT INTO grease_traps
        (establishment_id, trap_code, name, capacity_liters, low_threshold, medium_threshold, high_threshold, critical_threshold)
        VALUES (?, 'ULTRASONIC-TEST-001', 'Ultrasonic bench test (temporary)', 1, 20, 50, 75, 90)")->execute([$site['id']]);
    $trapId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO devices (device_code, name, device_type, api_key_hash, firmware_version)
        VALUES ('AQS-001', 'Ultrasonic bench test (temporary)', 'ESP32_ULTRASONIC_TEST', ?, '0.1.0')")
        ->execute([hash('sha256', $key)]);
    $deviceId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO device_assignments (device_id, grease_trap_id, started_at) VALUES (?, ?, UTC_TIMESTAMP())')
        ->execute([$deviceId, $trapId]);
    $pdo->prepare('INSERT INTO device_ultrasonic_test_config
        (device_id, empty_distance_cm, full_distance_cm, warning_percent, critical_percent) VALUES (?, 30, 5, 75, 90)')
        ->execute([$deviceId]);
    $private = ['device_id' => 'AQS-001', 'grease_trap_id' => $trapId, 'device_api_key' => $key,
        'empty_distance_cm' => 30, 'full_distance_cm' => 5, 'warning_percent' => 75, 'critical_percent' => 90];
    $handle = fopen($output, 'x');
    if ($handle === false) throw new RuntimeException('Could not create private configuration.');
    $written = true;
    $json = json_encode($private, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    $bytes = fwrite($handle, $json);
    fclose($handle);
    if ($bytes !== strlen($json)) throw new RuntimeException('Private configuration write failed.');
    $pdo->commit();
    echo "Temporary device AQS-001 provisioned for test trap " . $trapId . ". Private credential saved; not printed.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($written) unlink($output);
    throw $error;
}
