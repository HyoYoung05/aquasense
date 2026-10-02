<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$options = getopt('', ['email:', 'name:']);
$email = strtolower(trim((string) ($options['email'] ?? '')));
$name = trim((string) ($options['name'] ?? ''));
$password = getenv('AQUASENSE_BOOTSTRAP_ADMIN_PASSWORD');
putenv('AQUASENSE_BOOTSTRAP_ADMIN_PASSWORD');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    fwrite(STDERR, "Provide --email with a valid administrator email address.\n");
    exit(2);
}
if ($name === '' || mb_strlen($name) > 150) {
    fwrite(STDERR, "Provide --name with 1 to 150 characters.\n");
    exit(2);
}
if (!is_string($password) || strlen($password) < 12
    || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
    || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    fwrite(STDERR, "Set AQUASENSE_BOOTSTRAP_ADMIN_PASSWORD to a unique 12+ character password containing upper, lower, number, and symbol.\n");
    exit(2);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $roleId = $pdo->query("SELECT id FROM roles WHERE slug='administrator' LIMIT 1")->fetchColumn();
    if ($roleId === false) throw new RuntimeException('Import database/schema.sql before creating the administrator.');
    $administratorCount = (int) $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrator'")->fetchColumn();
    if ($administratorCount !== 0) throw new RuntimeException('An administrator already exists; bootstrap provisioning is disabled.');
    $statement = $pdo->prepare('INSERT INTO users (role_id,full_name,email,password_hash,is_active) VALUES (?,?,?,?,1)');
    $statement->execute([(int) $roleId, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare("INSERT INTO audit_logs (user_id,action,record_type,record_id,ip_address) VALUES (?,'BOOTSTRAP_ADMIN_CREATED','users',?,'127.0.0.1')");
    $statement->execute([$id, $id]);
    $pdo->commit();
    fwrite(STDOUT, "Administrator account created. Remove the password environment variable from shell history and server configuration.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Administrator was not created: " . $error->getMessage() . "\n");
    exit(1);
} finally {
    $password = null;
}
