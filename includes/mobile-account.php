<?php
declare(strict_types=1);

require_once __DIR__ . '/user-accounts.php';

final class MobileAccountException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

function mobile_account_string(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

function mobile_account_check_attempts(int $ownerId): void
{
    $query = db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=?
        AND action IN ('OWNER_EMAIL_CHANGE_FAILED','OWNER_PASSWORD_CHANGE_FAILED')
        AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)");
    $query->execute([$ownerId]);
    if ((int) $query->fetchColumn() >= 5) {
        throw new MobileAccountException('Too many account verification attempts. Try again in 10 minutes.', 429);
    }
}

function mobile_account_owner_for_update(PDO $pdo, int $ownerId): array
{
    $query = $pdo->prepare("SELECT u.id,u.full_name,u.email,u.password_hash
        FROM users u JOIN roles r ON r.id=u.role_id
        WHERE u.id=? AND u.is_active=1 AND r.slug='owner' FOR UPDATE");
    $query->execute([$ownerId]);
    $owner = $query->fetch();
    if (!$owner) {
        throw new MobileAccountException('Your account is no longer available.', 401);
    }
    return $owner;
}

function mobile_account_verify_password(array $owner, string $password): void
{
    if ($password === '' || strlen($password) > 1024 || !password_verify($password, (string) $owner['password_hash'])) {
        throw new MobileAccountException('The current password is incorrect.');
    }
}

function mobile_account_change_email(int $ownerId, array $input): array
{
    mobile_account_check_attempts($ownerId);
    $email = strtolower(mobile_account_string($input, 'email'));
    $currentPassword = isset($input['current_password']) && is_string($input['current_password'])
        ? $input['current_password'] : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        throw new MobileAccountException('Enter a valid email address.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $owner = mobile_account_owner_for_update($pdo, $ownerId);
        mobile_account_verify_password($owner, $currentPassword);
        if (hash_equals(strtolower((string) $owner['email']), $email)) {
            throw new MobileAccountException('Enter a different email address.');
        }
        $query = $pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
        $query->execute([$email, $ownerId]);
        if ($query->fetchColumn() !== false) {
            throw new MobileAccountException('An account already uses this email address.');
        }
        $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email, $ownerId]);
        audit('OWNER_EMAIL_CHANGED', $ownerId, 'users', $ownerId);
        $pdo->commit();
        return ['id' => $ownerId, 'full_name' => $owner['full_name'], 'email' => $email];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof MobileAccountException && $error->getMessage() === 'The current password is incorrect.') {
            audit('OWNER_EMAIL_CHANGE_FAILED', $ownerId, 'users', $ownerId);
        }
        if ($error instanceof MobileAccountException) throw $error;
        if ($error instanceof PDOException && $error->getCode() === '23000') {
            throw new MobileAccountException('An account already uses this email address.');
        }
        throw $error;
    } finally {
        $currentPassword = '';
    }
}

function mobile_account_change_password(int $ownerId, array $input): void
{
    mobile_account_check_attempts($ownerId);
    $currentPassword = isset($input['current_password']) && is_string($input['current_password'])
        ? $input['current_password'] : '';
    $newPassword = isset($input['new_password']) && is_string($input['new_password'])
        ? $input['new_password'] : '';
    $confirmation = isset($input['password_confirmation']) && is_string($input['password_confirmation'])
        ? $input['password_confirmation'] : '';
    if ($passwordError = user_account_password_error($newPassword)) {
        throw new MobileAccountException($passwordError);
    }
    if (!hash_equals($newPassword, $confirmation)) {
        throw new MobileAccountException('Password confirmation does not match.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $owner = mobile_account_owner_for_update($pdo, $ownerId);
        mobile_account_verify_password($owner, $currentPassword);
        if (password_verify($newPassword, (string) $owner['password_hash'])) {
            throw new MobileAccountException('The new password must be different from the current password.');
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash)) throw new RuntimeException('Password hashing failed.');
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $ownerId]);
        $pdo->prepare('DELETE FROM mobile_tokens WHERE user_id=?')->execute([$ownerId]);
        audit('OWNER_PASSWORD_CHANGED', $ownerId, 'users', $ownerId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof MobileAccountException && $error->getMessage() === 'The current password is incorrect.') {
            audit('OWNER_PASSWORD_CHANGE_FAILED', $ownerId, 'users', $ownerId);
        }
        throw $error;
    } finally {
        $currentPassword = $newPassword = $confirmation = '';
    }
}
