<?php
declare(strict_types=1);

final class UserAccountValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'The account could not be created.');
    }
}

function user_account_roles(): array
{
    return [
        'system_admin' => 'Admin',
        'administrator' => 'Barangay Administrator',
        'environmental_staff' => 'Environmental Staff',
        'owner' => 'Karinderya Owner',
    ];
}

function user_account_assignable_roles(array $actor): array
{
    $roles = user_account_roles();
    return match ($actor['role_slug'] ?? '') {
        'system_admin' => $roles,
        'administrator' => array_diff_key($roles, ['system_admin' => true]),
        default => [],
    };
}

function user_account_password_error(string $password): ?string
{
    if (strlen($password) < 8 || strlen($password) > 128
        || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must be 8 to 128 characters and include uppercase, lowercase, number, and symbol.';
    }
    return null;
}

function user_account_create(PDO $pdo, array $input, int $actorId): int
{
    $readString = static fn (string $key): string => isset($input[$key]) && is_string($input[$key])
        ? trim($input[$key]) : '';
    $fullName = $readString('full_name');
    $emailInput = strtolower($readString('email'));
    $email = $emailInput === '' ? null : $emailInput;
    $contactInput = $readString('contact_number');
    $contactNumber = normalize_contact_number($contactInput);
    $roleSlug = $readString('role');
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    $passwordConfirmation = isset($input['password_confirmation']) && is_string($input['password_confirmation'])
        ? $input['password_confirmation'] : '';
    $establishmentId = filter_var($input['establishment_id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    $actorQuery = $pdo->prepare('SELECT r.slug AS role_slug FROM users u
        JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1 LIMIT 1');
    $actorQuery->execute([$actorId]);
    $actorRole = $actorQuery->fetchColumn();
    $roles = user_account_assignable_roles([
        'role_slug' => is_string($actorRole) ? $actorRole : '',
    ]);
    $errors = [];

    if ($roles === []) {
        $errors[] = 'This account cannot create user accounts.';
    }

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
        $errors[] = 'Full name must contain 2 to 150 characters.';
    }
    if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($contactNumber === null) {
        $errors[] = 'Enter a valid contact number containing 7 to 20 digits.';
    }
    if (!isset($roles[$roleSlug])) {
        $errors[] = 'Select a valid account role.';
    }
    if ($passwordError = user_account_password_error($password)) {
        $errors[] = $passwordError;
    }
    if (!hash_equals($password, $passwordConfirmation)) {
        $errors[] = 'Password confirmation does not match.';
    }
    if ($roleSlug === 'owner' && $establishmentId === false) {
        $errors[] = 'Select the establishment that belongs to this owner.';
    }
    if ($roleSlug !== 'owner' && $establishmentId !== false) {
        $errors[] = 'Only an Owner account can be linked to an establishment.';
    }
    if ($errors) {
        throw new UserAccountValidationException($errors);
    }

    $pdo->beginTransaction();
    try {
        if ($email !== null) {
            $query = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $query->execute([$email]);
            if ($query->fetchColumn() !== false) {
                throw new UserAccountValidationException(['An account already uses this email address.']);
            }
        }
        $query = $pdo->prepare('SELECT id FROM users WHERE contact_number=? LIMIT 1');
        $query->execute([$contactNumber]);
        if ($query->fetchColumn() !== false) {
            throw new UserAccountValidationException(['An account already uses this contact number.']);
        }

        $query = $pdo->prepare('SELECT id FROM roles WHERE slug=? LIMIT 1');
        $query->execute([$roleSlug]);
        $roleId = $query->fetchColumn();
        if ($roleId === false) {
            throw new UserAccountValidationException(['The selected role is not available.']);
        }

        if ($roleSlug === 'owner') {
            $query = $pdo->prepare('SELECT id FROM establishments WHERE id=? AND is_active=1 AND owner_user_id IS NULL FOR UPDATE');
            $query->execute([(int) $establishmentId]);
            if ($query->fetchColumn() === false) {
                throw new UserAccountValidationException(['The selected establishment is inactive, unavailable, or already linked to an owner.']);
            }
        }

        $query = $pdo->prepare('INSERT INTO users (role_id,full_name,email,password_hash,contact_number,is_active) VALUES (?,?,?,?,?,1)');
        $query->execute([(int) $roleId, $fullName, $email, password_hash($password, PASSWORD_DEFAULT),
            $contactNumber]);
        $userId = (int) $pdo->lastInsertId();

        if ($roleSlug === 'owner') {
            $query = $pdo->prepare('UPDATE establishments SET owner_user_id=? WHERE id=? AND owner_user_id IS NULL');
            $query->execute([$userId, (int) $establishmentId]);
            if ($query->rowCount() !== 1) {
                throw new UserAccountValidationException(['The establishment was linked by another request. Reload and try again.']);
            }
            audit('OWNER_ACCOUNT_LINKED', $actorId, 'establishments', (int) $establishmentId);
        }

        audit('USER_ACCOUNT_CREATED', $actorId, 'users', $userId);
        $pdo->commit();
        return $userId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof UserAccountValidationException) {
            throw $error;
        }
        if ($error instanceof PDOException && $error->getCode() === '23000') {
            throw new UserAccountValidationException(['An account already uses this email address or contact number.']);
        }
        throw $error;
    } finally {
        $password = '';
        $passwordConfirmation = '';
    }
}

function user_account_update(PDO $pdo, int $userId, array $input, int $actorId): void
{
    $read = static fn (string $key): string => isset($input[$key]) && is_string($input[$key])
        ? trim($input[$key]) : '';
    $targetQuery = $pdo->prepare('SELECT u.*,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $targetQuery->execute([$userId]);
    $target = $targetQuery->fetch();
    $actorQuery = $pdo->prepare('SELECT r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1');
    $actorQuery->execute([$actorId]);
    $actorRole = (string) $actorQuery->fetchColumn();
    if (!$target || !in_array($actorRole, ['system_admin', 'administrator'], true)) {
        throw new UserAccountValidationException(['The selected account is not available.']);
    }
    if ($target['role_slug'] === 'system_admin' && $actorRole !== 'system_admin') {
        throw new UserAccountValidationException(['Only a System Admin can edit this account.']);
    }

    $fullName = $read('full_name');
    $emailInput = strtolower($read('email'));
    $email = $emailInput === '' ? null : $emailInput;
    $contact = normalize_contact_number($read('contact_number'));
    $roleSlug = $read('role');
    $active = (bool) $target['is_active'];
    $roles = user_account_assignable_roles(['role_slug' => $actorRole]);
    $errors = [];
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) $errors[] = 'Full name must contain 2 to 150 characters.';
    if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) $errors[] = 'Enter a valid email address.';
    if ($contact === null) $errors[] = 'Enter a valid contact number containing 7 to 20 digits.';
    if (!isset($roles[$roleSlug])) $errors[] = 'Select a valid account role.';
    if ($roleSlug !== $target['role_slug']) $errors[] = 'Account roles cannot be changed from the edit screen.';
    if ($userId === $actorId && $roleSlug !== $target['role_slug']) $errors[] = 'You cannot change the role of your own account.';
    if ($errors) throw new UserAccountValidationException($errors);

    $duplicate = $pdo->prepare('SELECT id FROM users WHERE id<>? AND (email=? OR contact_number=?) LIMIT 1');
    $duplicate->execute([$userId, $email, $contact]);
    if ($duplicate->fetchColumn() !== false) throw new UserAccountValidationException(['Another account already uses this email address or contact number.']);
    $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE slug=?');
    $roleQuery->execute([$roleSlug]);
    $roleId = $roleQuery->fetchColumn();
    if ($roleId === false) throw new UserAccountValidationException(['The selected role is not available.']);

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE users SET full_name=?,email=?,contact_number=?,role_id=?,is_active=? WHERE id=?');
        $update->execute([$fullName, $email, $contact, (int) $roleId, $active ? 1 : 0, $userId]);
        audit('USER_ACCOUNT_UPDATED', $actorId, 'users', $userId);
        if ((bool) $target['is_active'] !== $active) {
            audit($active ? 'USER_ACCOUNT_ACTIVATED' : 'USER_ACCOUNT_DEACTIVATED', $actorId, 'users', $userId);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function user_account_set_status(PDO $pdo, int $userId, bool $active, int $actorId): void
{
    if ($userId === $actorId && !$active) throw new UserAccountValidationException(['You cannot deactivate your own account.']);
    $query = $pdo->prepare('SELECT r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $query->execute([$userId]);
    $targetRole = $query->fetchColumn();
    $query->execute([$actorId]);
    $actorRole = $query->fetchColumn();
    if ($targetRole === false || !in_array($actorRole, ['system_admin', 'administrator'], true)
        || ($targetRole === 'system_admin' && $actorRole !== 'system_admin')) {
        throw new UserAccountValidationException(['This account status cannot be changed by your role.']);
    }
    $update = $pdo->prepare('UPDATE users SET is_active=? WHERE id=?');
    $update->execute([$active ? 1 : 0, $userId]);
    audit($active ? 'USER_ACCOUNT_ACTIVATED' : 'USER_ACCOUNT_DEACTIVATED', $actorId, 'users', $userId);
}

function user_account_delete(PDO $pdo, int $userId, int $actorId): void
{
    if ($userId === $actorId) throw new UserAccountValidationException(['You cannot delete your own account.']);
    $query = $pdo->prepare('SELECT r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $query->execute([$userId]);
    $targetRole = $query->fetchColumn();
    $query->execute([$actorId]);
    $actorRole = $query->fetchColumn();
    if ($targetRole === false || !in_array($actorRole, ['system_admin', 'administrator'], true)
        || ($targetRole === 'system_admin' && $actorRole !== 'system_admin')) {
        throw new UserAccountValidationException(['This account cannot be deleted by your role.']);
    }
    $pdo->beginTransaction();
    try {
        $unlink = $pdo->prepare('UPDATE establishments SET owner_user_id=NULL WHERE owner_user_id=?');
        $unlink->execute([$userId]);
        $delete = $pdo->prepare('DELETE FROM users WHERE id=?');
        $delete->execute([$userId]);
        if ($delete->rowCount() !== 1) throw new UserAccountValidationException(['The account was not found.']);
        audit('USER_ACCOUNT_DELETED', $actorId, 'users', $userId);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error->getCode() === '23000') {
            throw new UserAccountValidationException(['This account is referenced by protected historical records and cannot be permanently deleted.']);
        }
        throw $error;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
