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

function user_account_password_error(string $password): ?string
{
    if (strlen($password) < 12 || strlen($password) > 128
        || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must be 12 to 128 characters and include uppercase, lowercase, number, and symbol.';
    }
    return null;
}

function user_account_create(PDO $pdo, array $input, int $actorId): int
{
    $readString = static fn (string $key): string => isset($input[$key]) && is_string($input[$key])
        ? trim($input[$key]) : '';
    $fullName = $readString('full_name');
    $email = strtolower($readString('email'));
    $contactNumber = $readString('contact_number');
    $roleSlug = $readString('role');
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    $passwordConfirmation = isset($input['password_confirmation']) && is_string($input['password_confirmation'])
        ? $input['password_confirmation'] : '';
    $establishmentId = filter_var($input['establishment_id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    $roles = user_account_roles();
    $errors = [];

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
        $errors[] = 'Full name must contain 2 to 150 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($contactNumber !== '' && (mb_strlen($contactNumber) > 30
        || !preg_match('/^[0-9+().\-\s]+$/', $contactNumber))) {
        $errors[] = 'Contact number may contain only numbers, spaces, +, -, parentheses, and periods.';
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
        $query = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $query->execute([$email]);
        if ($query->fetchColumn() !== false) {
            throw new UserAccountValidationException(['An account already uses this email address.']);
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
            $contactNumber === '' ? null : $contactNumber]);
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
            throw new UserAccountValidationException(['An account already uses this email address.']);
        }
        throw $error;
    } finally {
        $password = '';
        $passwordConfirmation = '';
    }
}
