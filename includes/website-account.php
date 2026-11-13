<?php
declare(strict_types=1);

require_once __DIR__ . '/user-accounts.php';

/** Update only the signed-in staff member; never accept a target user ID from a form. */
function website_account_update(PDO $pdo, int $userId, array $input): void
{
    $section = isset($input['section']) && is_string($input['section']) ? $input['section'] : '';
    if (!in_array($section, ['contact', 'password'], true)) {
        throw new InvalidArgumentException('Choose a valid settings section.');
    }
    $attempts = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=?
        AND action='WEBSITE_ACCOUNT_VERIFY_FAILED'
        AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)");
    $attempts->execute([$userId]);
    if ((int) $attempts->fetchColumn() >= 5) {
        throw new InvalidArgumentException('Too many password checks. Try again in 10 minutes.');
    }
    $read = static fn(string $key): string => isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
    $currentPassword = $read('current_password');
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare("SELECT u.id,u.password_hash FROM users u JOIN roles r ON r.id=u.role_id
            WHERE u.id=? AND u.is_active=1 AND r.slug IN ('system_admin','administrator','environmental_staff') FOR UPDATE");
        $query->execute([$userId]);
        $account = $query->fetch();
        if (!$account) throw new InvalidArgumentException('This account is no longer available.');
        if ($currentPassword === '' || strlen($currentPassword) > 1024
            || !password_verify($currentPassword, $account['password_hash'])) {
            $pdo->rollBack();
            audit('WEBSITE_ACCOUNT_VERIFY_FAILED', $userId, 'users', $userId);
            throw new InvalidArgumentException('Your current password is incorrect.');
        }
        if ($section === 'contact') {
            $email = strtolower(trim($read('email')));
            $contact = normalize_contact_number(trim($read('contact_number')));
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
                throw new InvalidArgumentException('Enter a valid email address, or leave it empty.');
            }
            if ($contact === null) throw new InvalidArgumentException('Enter a contact number with 7 to 20 digits.');
            $query = $pdo->prepare('SELECT id FROM users WHERE id<>? AND (email=? OR contact_number=?) LIMIT 1');
            $query->execute([$userId, $email === '' ? null : $email, $contact]);
            if ($query->fetchColumn() !== false) {
                throw new InvalidArgumentException('That email address or contact number is already in use.');
            }
            $pdo->prepare('UPDATE users SET email=?,contact_number=? WHERE id=?')
                ->execute([$email === '' ? null : $email, $contact, $userId]);
            audit('WEBSITE_CONTACT_CHANGED', $userId, 'users', $userId);
        } else {
            $password = $read('new_password');
            if ($error = user_account_password_error($password)) throw new InvalidArgumentException($error);
            if (!hash_equals($password, $read('password_confirmation'))) {
                throw new InvalidArgumentException('The new passwords do not match.');
            }
            if (password_verify($password, $account['password_hash'])) {
                throw new InvalidArgumentException('Choose a password different from your current password.');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash)) throw new RuntimeException('Password could not be saved.');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $userId]);
            $pdo->prepare('DELETE FROM mobile_tokens WHERE user_id=?')->execute([$userId]);
            audit('WEBSITE_PASSWORD_CHANGED', $userId, 'users', $userId);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && $error->getCode() === '23000') {
            throw new InvalidArgumentException('That email address or contact number is already in use.');
        }
        throw $error;
    }
}
