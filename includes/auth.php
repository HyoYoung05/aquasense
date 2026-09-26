<?php
declare(strict_types=1);

function clear_login(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function current_user(): ?array
{
    global $config;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if (time() - (int) ($_SESSION['last_activity'] ?? 0) >= $config['session_timeout']) {
        clear_login();
        $_SESSION['notice'] = 'Your session has expired. Please sign in again.';
        return null;
    }
    $statement = db()->prepare('SELECT u.id, u.full_name, u.email, u.is_active, u.last_login_at,
        r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user || !(bool) $user['is_active']) {
        clear_login();
        return null;
    }
    $_SESSION['last_activity'] = time();
    return $user;
}

function require_roles(array $roles): array
{
    $user = current_user();
    if (!$user || !in_array($user['role_slug'], $roles, true)) {
        if ($user) {
            clear_login();
            $_SESSION['notice'] = 'This account does not have access to the administrative website.';
        }
        redirect('public/login.php');
    }
    return $user;
}

function require_staff(): array
{
    return require_roles(['administrator', 'environmental_staff']);
}

function require_administrator(): array
{
    return require_roles(['administrator']);
}

function attempt_login(string $email, string $password): ?string
{
    global $config;
    $emailHash = hash('sha256', strtolower($email));
    $cutoff = gmdate('Y-m-d H:i:s', time() - $config['login_window_minutes'] * 60);
    $statement = db()->prepare('SELECT COUNT(*) FROM login_attempts
        WHERE attempted_at >= ? AND (email_hash = ? OR ip_address = ?)');
    $statement->execute([$cutoff, $emailHash, client_ip()]);
    if ((int) $statement->fetchColumn() >= $config['login_max_attempts']) {
        return 'Too many sign-in attempts. Please try again in ' . (int) $config['login_window_minutes'] . ' minutes.';
    }

    // Count before verifying so concurrent attempts also consume the shared budget.
    $statement = db()->prepare('INSERT INTO login_attempts (email_hash, ip_address) VALUES (?, ?)');
    $statement->execute([$emailHash, client_ip()]);
    $attemptId = (int) db()->lastInsertId();
    $statement = db()->prepare('SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?');
    $statement->execute([$email]);
    $user = $statement->fetch();
    // A real hash also makes unknown-email attempts perform a password check.
    $dummyHash = '$2y$12$ArYeuNFsn1OIprnoIGNZvOtBMyOFL6elYdjrPt5EE4YAhx0Ag8w6e';
    $valid = password_verify($password, $user['password_hash'] ?? $dummyHash);
    if (!$user || !$valid || !(bool) $user['is_active']
        || !in_array($user['role_slug'], ['administrator', 'environmental_staff'], true)) {
        audit('LOGIN_FAILED');
        return 'Unable to sign in. Check your email and password, or contact your administrator.';
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $statement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $statement->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $statement = $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?');
        $statement->execute([$user['id']]);
        $statement = $pdo->prepare('DELETE FROM login_attempts WHERE id = ?');
        $statement->execute([$attemptId]);
        audit('LOGIN', (int) $user['id'], 'users', (int) $user['id']);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $user['id'],
        'last_activity' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    return null;
}
