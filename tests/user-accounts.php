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

    $query->execute([(int) $roles['administrator'], 'Account Test Barangay Admin',
        $emailPrefix . '-barangay-admin@example.test', password_hash('Temporary!2026', PASSWORD_DEFAULT)]);
    $barangayActorId = (int) $pdo->lastInsertId();
    $ids['users'][] = $barangayActorId;

    account_check(array_key_exists('system_admin', user_account_assignable_roles(['role_slug' => 'system_admin']))
        && !array_key_exists('system_admin', user_account_assignable_roles(['role_slug' => 'administrator'])),
        'Only System Admin can assign the System Admin role');
    try {
        user_account_create($pdo, [
            'full_name' => 'Unauthorized System Admin', 'email' => $emailPrefix . '-elevated@example.test',
            'contact_number' => '+639000000101',
            'role' => 'system_admin', 'password' => 'ElevatedTemporary!2026',
            'password_confirmation' => 'ElevatedTemporary!2026',
        ], $barangayActorId);
        account_check(false, 'Barangay Administrator cannot create a System Admin');
    } catch (UserAccountValidationException $error) {
        account_check(str_contains(implode(' ', $error->errors), 'valid account role'),
            'Barangay Administrator cannot create a System Admin');
    }

    $query = $pdo->prepare('INSERT INTO establishments (registration_code,business_name,owner_name,address,registration_date,created_by) VALUES (?,?,?,?,CURRENT_DATE(),?)');
    $query->execute(['UAT-' . strtoupper(bin2hex(random_bytes(4))), 'Account Test Kitchen', 'Account Test Owner',
        'Temporary automated test address', $actorId]);
    $establishmentId = (int) $pdo->lastInsertId();
    $ids['establishment'] = $establishmentId;

    try {
        user_account_create($pdo, [
            'full_name' => 'Weak Password User', 'email' => $emailPrefix . '-weak@example.test',
            'contact_number' => '+639000000102',
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

    $contactOnlyId = user_account_create($pdo, [
        'full_name' => 'Contact Only Staff', 'email' => '', 'contact_number' => '+63 900 000 0103',
        'role' => 'environmental_staff', 'password' => 'Eight!8A',
        'password_confirmation' => 'Eight!8A',
    ], $actorId);
    $ids['users'][] = $contactOnlyId;
    $query = $pdo->prepare('SELECT email,contact_number FROM users WHERE id=?');
    $query->execute([$contactOnlyId]);
    $contactOnly = $query->fetch();
    account_check($contactOnly && $contactOnly['email'] === null
        && $contactOnly['contact_number'] === '+639000000103',
        'Admin can create a contact-number account without email using an 8-character password');

    $query = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action IN ('USER_ACCOUNT_CREATED','OWNER_ACCOUNT_LINKED')");
    $query->execute([$actorId]);
    account_check((int) $query->fetchColumn() === 3, 'Account creation and owner link are audited');

    user_account_update($pdo, $contactOnlyId, [
        'full_name' => 'Updated Contact Staff', 'email' => '',
        'contact_number' => '+63 900 000 0103', 'role' => 'environmental_staff',
        'status' => 'active',
    ], $actorId);
    $query = $pdo->prepare('SELECT full_name,is_active FROM users WHERE id=?');
    $query->execute([$contactOnlyId]);
    $updatedAccount = $query->fetch();
    account_check($updatedAccount && $updatedAccount['full_name'] === 'Updated Contact Staff'
        && (bool) $updatedAccount['is_active'], 'Admin can edit an account and retain Active status');

    user_account_set_status($pdo, $contactOnlyId, false, $actorId);
    $query->execute([$contactOnlyId]);
    $inactiveAccount = $query->fetch();
    account_check($inactiveAccount && !(bool) $inactiveAccount['is_active'],
        'Admin can change an account status to Inactive');

    user_account_delete($pdo, $contactOnlyId, $actorId);
    $query->execute([$contactOnlyId]);
    account_check($query->fetch() === false, 'Delete permanently removes an unreferenced account');

    try {
        user_account_create($pdo, [
            'full_name' => 'Duplicate Owner', 'email' => $ownerEmail, 'role' => 'environmental_staff',
            'contact_number' => '+639000000104',
            'password' => 'AnotherTemporary!2026', 'password_confirmation' => 'AnotherTemporary!2026',
        ], $actorId);
        account_check(false, 'Duplicate email addresses are rejected');
    } catch (UserAccountValidationException $error) {
        account_check(str_contains(implode(' ', $error->errors), 'already uses'), 'Duplicate email addresses are rejected');
    }

    user_account_delete($pdo, $ownerId, $actorId);
    $query = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id=?');
    $query->execute([$ownerId]);
    account_check((int) $query->fetchColumn() === 0
        && $pdo->query('SELECT owner_user_id FROM establishments WHERE id=' . $establishmentId)->fetchColumn() === null,
        'Deleting an Owner account unlinks its establishment and permanently removes the user');

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
