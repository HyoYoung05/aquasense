<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
api_method('POST');
$data = api_body();
$email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
$password = is_string($data['password'] ?? null) ? $data['password'] : '';
if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 1024) {
    api_fail('Enter your registered email and password.', 422);
}
$emailHash = hash('sha256', $email);
$cutoff = gmdate('Y-m-d H:i:s', time() - $config['login_window_minutes'] * 60);
$query = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= ? AND (email_hash = ? OR ip_address = ?)');
$query->execute([$cutoff, $emailHash, client_ip()]);
if ((int) $query->fetchColumn() >= $config['login_max_attempts']) {
    header('Retry-After: ' . ($config['login_window_minutes'] * 60));
    api_fail('Too many sign-in attempts. Please try again later.', 429);
}
$query = db()->prepare('INSERT INTO login_attempts (email_hash, ip_address) VALUES (?, ?)');
$query->execute([$emailHash, client_ip()]);
$attemptId = db()->lastInsertId();
$query = db()->prepare('SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?');
$query->execute([$email]);
$user = $query->fetch();
$valid = password_verify($password, $user['password_hash'] ?? '$2y$12$ArYeuNFsn1OIprnoIGNZvOtBMyOFL6elYdjrPt5EE4YAhx0Ag8w6e');
if (!$user || !$valid || !$user['is_active'] || $user['role_slug'] !== 'owner') {
    audit('MOBILE_LOGIN_FAILED');
    api_fail('Incorrect email or password, or this account cannot access the owner app.', 401);
}
$pdo = db();
$pdo->beginTransaction();
try {
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$user['password_hash'], $user['id']]);
    }
    $token = bin2hex(random_bytes(32));
    $expires = gmdate('Y-m-d H:i:s', time() + (int) $config['mobile_token_lifetime_seconds']);
    $pdo->prepare('DELETE FROM mobile_tokens WHERE user_id = ? AND expires_at <= UTC_TIMESTAMP()')->execute([$user['id']]);
    $pdo->prepare('INSERT INTO mobile_tokens (user_id, token_hash, password_fingerprint, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$user['id'], hash('sha256', $token), hash('sha256', $user['password_hash']), $expires]);
    $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$user['id']]);
    $pdo->prepare('DELETE FROM login_attempts WHERE id = ?')->execute([$attemptId]);
    audit('MOBILE_LOGIN', (int) $user['id'], 'users', (int) $user['id']);
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}
api_ok(['token' => $token, 'expires_at' => str_replace(' ', 'T', $expires) . 'Z',
    'user' => ['id' => (int) $user['id'], 'full_name' => $user['full_name'], 'email' => $user['email']]]);
