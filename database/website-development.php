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

audit('DEVELOPMENT_ADMIN_PROVISIONED', $userId, 'users', $userId);
fwrite(STDOUT, "Development Admin and migrations 009-012 are ready.\n");
