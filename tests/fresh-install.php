<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-temporary-database', $argv, true)) {
    fwrite(STDERR, "Usage: php tests/fresh-install.php --allow-temporary-database\nCreates and removes a temporary database.\n");
    exit(1);
}
$config = require dirname(__DIR__) . '/config/config.php';
$checks = 0;
function fresh_check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['db_host'], $config['db_port']),
    $config['db_user'], $config['db_password'], $options);
$database = 'aquasense_fresh_' . bin2hex(random_bytes(5));
$root = dirname(__DIR__);
try {
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $database),
        $config['db_user'], $config['db_password'], $options);
    $pdo->exec((string) file_get_contents($root . '/database/schema.sql'));
    fresh_check(count($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) === 17, 'Base schema imports into an empty database');
    $migrations = glob($root . '/database/migrations/*.sql') ?: [];
    sort($migrations, SORT_STRING);
    foreach ($migrations as $migration) $pdo->exec((string) file_get_contents($migration));
    fresh_check(count($migrations) === 8, 'All eight numbered migrations were discovered in order');
    fresh_check(count($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) === 19, 'Migrations produce the complete 19-table schema');
    $pdo->exec((string) file_get_contents($root . '/database/reference-data.sql'));
    fresh_check((int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 3, 'Production-safe role reference data imports');
    fresh_check((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'Fresh production sequence creates no sample accounts');
    $engines = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND engine<>'InnoDB'")->fetchColumn();
    fresh_check((int) $engines === 0, 'Every production table uses InnoDB');
    $foreignKeys = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE()")->fetchColumn();
    fresh_check($foreignKeys >= 25, 'Fresh schema retains foreign-key relationships');
    foreach (['idx_reading_assignment_time', 'idx_alert_status_time', 'idx_surrender_review_queue',
        'idx_incentive_status_processed', 'idx_ledger_event_time', 'idx_audit_created_at'] as $index) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND index_name=?');
        $q->execute([$index]);
        fresh_check((int) $q->fetchColumn() >= 1, 'Required query index exists: ' . $index);
    }

    $environment = [
        'AQUASENSE_IGNORE_LOCAL_CONFIG' => '1', 'AQUASENSE_APP_ENV' => 'test',
        'AQUASENSE_DB_HOST' => (string) $config['db_host'], 'AQUASENSE_DB_PORT' => (string) $config['db_port'],
        'AQUASENSE_DB_NAME' => $database, 'AQUASENSE_DB_USER' => (string) $config['db_user'],
        'AQUASENSE_DB_PASSWORD' => (string) $config['db_password'],
        'AQUASENSE_BOOTSTRAP_ADMIN_PASSWORD' => 'FreshTest!2026Strong',
    ];
    $previous = [];
    foreach ($environment as $key => $value) { $previous[$key] = getenv($key); putenv($key . '=' . $value); }
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/create-admin.php')
        . ' --email=' . escapeshellarg('release-admin@example.test') . ' --name=' . escapeshellarg('Release Administrator');
    exec($command . ' 2>&1', $output, $exitCode);
    foreach ($previous as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
    fresh_check($exitCode === 0, 'First-administrator CLI provisions a fresh database');
    fresh_check((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Bootstrap creates exactly one account');
    fresh_check((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='BOOTSTRAP_ADMIN_CREATED'")->fetchColumn() === 1, 'Bootstrap administrator creation is audited');
    echo PHP_EOL . $checks . " fresh-install checks passed.\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS `$database`");
}
