<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    exit("Usage: php tests/user-accounts.php --allow-local-fixtures\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/user-accounts.php';

if ($config['environment'] !== 'development') {
    throw new RuntimeException('User-account fixtures are allowed only in development.');
}

$pdo = db();
$checks = 0;
$ids = ['users' => [], 'establishment' => null];
$emailPrefix = 'account-test-' . bin2hex(random_bytes(5));

function account_check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}

try {
    $roles = $pdo->query('SELECT slug,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $query = $pdo->prepare('INSERT INTO users (role_id,full_name,email,password_hash) VALUES (?,?,?,?)');
    $query->execute([(int) $roles['system_admin'], 'Account Test Admin', $emailPrefix . '-admin@example.test',
        password_hash('Temporary!2026', PASSWORD_DEFAULT)]);
    $actorId = (int) $pdo->lastInsertId();
    $ids['users'][] = $actorId;

    $query = $pdo->prepare('INSERT INTO establishments (registration_code,business_name,owner_name,address,registration_date,created_by) VALUES (?,?,?,?,CURRENT_DATE(),?)');
    $query->execute(['UAT-' . strtoupper(bin2hex(random_bytes(4))), 'Account Test Kitchen', 'Account Test Owner',
        'Temporary automated test address', $actorId]);
    $establishmentId = (int) $pdo->lastInsertId();
    $ids['establishment'] = $establishmentId;

    try {
        user_account_create($pdo, [
            'full_name' => 'Weak Password User', 'email' => $emailPrefix . '-weak@example.test',
            'role' => 'environmental_staff', 'password' => 'weak', 'password_confirmation' => 'weak',
        ], $actorId);
        account_check(false, 'Weak passwords are rejected');
    } catch (UserAccountValidationException $error) {
        account_check(str_contains(implode(' ', $error->errors), 'Password must be'), 'Weak passwords are rejected');
    }

    $ownerEmail = $emailPrefix . '-owner@example.test';
    $ownerId = user_account_create($pdo, [
        'full_name' => 'Account Test Owner', 'email' => $ownerEmail, 'contact_number' => '+63 900 000 0000',
        'role' => 'owner', 'establishment_id' => (string) $establishmentId,
        'password' => 'OwnerTemporary!2026', 'password_confirmation' => 'OwnerTemporary!2026',
    ], $actorId);
    $ids['users'][] = $ownerId;

    $query = $pdo->prepare('SELECT r.slug,u.password_hash,u.is_active FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $query->execute([$ownerId]);
    $created = $query->fetch();
    account_check($created && $created['slug'] === 'owner' && (bool) $created['is_active'], 'Admin creates an active Owner account');
    account_check(password_verify('OwnerTemporary!2026', (string) $created['password_hash']), 'Created password is securely hashed');
    account_check((int) $pdo->query('SELECT owner_user_id FROM establishments WHERE id=' . $establishmentId)->fetchColumn() === $ownerId,
        'Owner account is linked to the selected establishment');

    $query = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action IN ('USER_ACCOUNT_CREATED','OWNER_ACCOUNT_LINKED')");
    $query->execute([$actorId]);
    account_check((int) $query->fetchColumn() === 2, 'Account creation and owner link are audited');

    try {
        user_account_create($pdo, [
            'full_name' => 'Duplicate Owner', 'email' => $ownerEmail, 'role' => 'environmental_staff',
            'password' => 'AnotherTemporary!2026', 'password_confirmation' => 'AnotherTemporary!2026',
        ], $actorId);
        account_check(false, 'Duplicate email addresses are rejected');
    } catch (UserAccountValidationException $error) {
        account_check(str_contains(implode(' ', $error->errors), 'already uses'), 'Duplicate email addresses are rejected');
    }

    echo PHP_EOL . $checks . " user-account checks passed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($ids['establishment']) {
        $pdo->prepare('UPDATE establishments SET owner_user_id=NULL WHERE id=?')->execute([$ids['establishment']]);
    }
    if ($ids['users']) {
        $marks = implode(',', array_fill(0, count($ids['users']), '?'));
        $pdo->prepare("DELETE FROM audit_logs WHERE user_id IN ($marks) OR (record_type='users' AND record_id IN ($marks))")
            ->execute(array_merge($ids['users'], $ids['users']));
    }
    if ($ids['establishment']) {
        $pdo->prepare('DELETE FROM establishments WHERE id=?')->execute([$ids['establishment']]);
    }
    foreach (array_reverse($ids['users']) as $id) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
}
