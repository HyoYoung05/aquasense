<?php
declare(strict_types=1);
// Explicit CLI-only setup for the existing fictional Demo Kusina fixture.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!in_array('--allow-demo-data', $argv, true)) {
    exit("Usage: php database/mobile-development.php --allow-demo-data [--add-reading]\n");
}
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/mobile-monitoring.php';
if ($config['environment'] !== 'development') {
    throw new RuntimeException('Development sample accounts are disabled outside development.');
}
$pdo = db();
$pdo->exec(file_get_contents(__DIR__ . '/migrations/001-mobile-tokens.sql'));
$pdo->beginTransaction();
try {
    $site = $pdo->query("SELECT * FROM establishments WHERE registration_code = 'DEMO-EST-001' FOR UPDATE")->fetch();
    if (!$site || $site['business_name'] !== 'Demo Kusina') {
        throw new RuntimeException('Expected fictional Demo Kusina fixture; no owner records changed.');
    }
    $query = $pdo->prepare('SELECT id, full_name, role_id FROM users WHERE email = ?');
    $query->execute(['owner@aquasense.test']);
    $owner = $query->fetch();
    $roleId = $pdo->query("SELECT id FROM roles WHERE slug = 'owner'")->fetchColumn();
    if ($owner && ($owner['full_name'] !== 'Taylor Cruz (Demo)' || (int) $owner['role_id'] !== (int) $roleId)) {
        throw new RuntimeException('Owner email already belongs to a different account; no records changed.');
    }
    if ($site['owner_user_id'] && (!$owner || (int) $site['owner_user_id'] !== (int) $owner['id'])) {
        throw new RuntimeException('Demo establishment already has another owner; no records changed.');
    }
    if (!$owner) {
        $pdo->prepare('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)')
            ->execute([$roleId, 'Taylor Cruz (Demo)', 'owner@aquasense.test', password_hash('AquaSense!2026', PASSWORD_DEFAULT)]);
        $owner = ['id' => $pdo->lastInsertId()];
    }
    $pdo->prepare('UPDATE establishments SET owner_user_id = ? WHERE id = ?')->execute([$owner['id'], $site['id']]);
    if (in_array('--add-reading', $argv, true)) {
        $query = $pdo->prepare("SELECT g.*, a.id AS assignment_id FROM grease_traps g
            JOIN device_assignments a ON a.grease_trap_id = g.id AND a.ended_at IS NULL
            JOIN devices d ON d.id = a.device_id
            WHERE g.establishment_id = ? AND g.trap_code = 'DEMO-GT-001' AND d.device_type = 'SIMULATED'");
        $query->execute([$site['id']]);
        $trap = $query->fetch();
        if (!$trap) {
            throw new RuntimeException('Expected assigned SIMULATED demo device; no readings inserted.');
        }
        $settings = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $reading = ['waste_level_percent' => 32, 'temperature_c' => 30.4];
        $status = mobile_reading_status($trap, $reading, $settings);
        $pdo->prepare('INSERT INTO sensor_readings (device_assignment_id, waste_level_percent, temperature_c, level_status, is_simulated, recorded_at)
            VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())')->execute([$trap['assignment_id'], 32, 30.4, $status]);
    }
    $pdo->commit();
    echo "Mobile migration and fictional owner linkage ready. Existing passwords unchanged.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}
