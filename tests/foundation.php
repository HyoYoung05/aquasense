<?php
declare(strict_types=1);

// Run against the local development installation only. Fixtures are removed in finally.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!in_array('--allow-local-fixtures', $argv, true)) {
    fwrite(STDERR, "Usage: php tests/foundation.php --allow-local-fixtures\nCreates temporary test users in the configured development database.\n");
    exit(1);
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$base = 'http://localhost' . rtrim($config['base_path'], '/');
$checks = 0;
$fixtureIds = [];
$fixtureEmails = [];
$password = 'TestOnly!' . bin2hex(random_bytes(12));

function check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}

function client(): CurlHandle
{
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
    return $handle;
}

function request(CurlHandle $client, string $path, ?array $data = null): array
{
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($data === null) {
        curl_setopt($client, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = curl_exec($client);
    if ($response === false) {
        throw new RuntimeException('HTTP request failed: ' . curl_error($client));
    }
    $headerSize = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
}

function token(array $response): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $match);
    if (!isset($match[1])) {
        throw new RuntimeException('Expected a CSRF form token.');
    }
    return $match[1];
}

function login(CurlHandle $client, string $email, string $password): array
{
    $form = request($client, '/public/login.php');
    return request($client, '/public/login.php', ['csrf_token' => token($form), 'email' => $email, 'password' => $password]);
}

function expect_constraint(string $sql, array $values, string $label): void
{
    try {
        db()->prepare($sql)->execute($values);
    } catch (PDOException $exception) {
        check(in_array($exception->getCode(), ['23000', 'HY000'], true), $label);
        return;
    }
    check(false, $label);
}

try {
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    check(count($tables) === 17, 'Schema has all 17 foundation and future-module tables');
    $sample = $pdo->prepare('SELECT password_hash FROM users WHERE email = ?');
    $sample->execute(['admin@aquasense.test']);
    check(password_verify('AquaSense!2026', (string) $sample->fetchColumn()), 'Development administrator hash verifies');
    check((int) $pdo->query("SELECT COUNT(*) FROM roles WHERE slug IN ('administrator','environmental_staff','owner')")->fetchColumn() === 3, 'All three roles exist');
    check((float) $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'emulsion_temperature_threshold'")->fetchColumn() === 40.0, 'Initial emulsion threshold is stored as 40 degrees Celsius');

    $roles = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['administrator', 'environmental_staff', 'owner'] as $role) {
        $email = 'foundation-' . $role . '-' . bin2hex(random_bytes(5)) . '@example.test';
        $fixtureEmails[$role] = $email;
        $statement = $pdo->prepare('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)');
        $statement->execute([$roles[$role], '<script>alert(1)</script> Test User', $email, password_hash($password, PASSWORD_DEFAULT)]);
        $fixtureIds[$role] = (int) $pdo->lastInsertId();
    }

    $anonymous = client();
    foreach (['/', '/public/index.php', '/admin/', '/admin/dashboard.php'] as $path) {
        $response = request($anonymous, $path);
        check($response['status'] === 303 && str_contains($response['headers'], '/public/login.php'), 'Anonymous access redirects: ' . $path);
    }
    $form = request($anonymous, '/public/login.php');
    check($form['status'] === 200 && str_contains($form['body'], 'Welcome back.'), 'Login renders through XAMPP Apache');
    $freshForm = request(client(), '/public/login.php');
    check(str_contains(strtolower($freshForm['headers']), 'httponly') && str_contains(strtolower($freshForm['headers']), 'samesite=lax'), 'Session cookie has HttpOnly and SameSite attributes');
    check(str_contains(strtolower($form['headers']), 'content-security-policy:') && str_contains(strtolower($form['headers']), 'cache-control: no-store'), 'CSP and private no-store response headers');
    $response = request($anonymous, '/public/login.php', ['email' => $fixtureEmails['administrator'], 'password' => $password]);
    check($response['status'] === 403, 'Missing login CSRF token is rejected');
    $response = request($anonymous, '/public/login.php', ['csrf_token' => token($form), 'email' => '<script>alert(1)</script>', 'password' => 'invalid']);
    check(str_contains($response['body'], 'Enter a valid email address.') && !str_contains($response['body'], '<script>alert(1)</script>'), 'Invalid input is validated and safely escaped');
    $response = login($anonymous, "' OR 1=1 --@example.test", 'invalid');
    check($response['status'] !== 303, 'SQL injection input cannot authenticate');
    $response = login($anonymous, $fixtureEmails['administrator'], 'wrong-password');
    check(str_contains($response['body'], 'Unable to sign in.'), 'Invalid credentials receive a generic error');

    $beforeCookies = curl_getinfo($anonymous, CURLINFO_COOKIELIST);
    $response = login($anonymous, $fixtureEmails['administrator'], $password);
    check($response['status'] === 303 && str_contains($response['headers'], '/admin/dashboard.php'), 'Administrator login succeeds');
    check($beforeCookies !== curl_getinfo($anonymous, CURLINFO_COOKIELIST), 'Session identifier regenerates after login');
    $dashboard = request($anonymous, '/admin/dashboard.php');
    check($dashboard['status'] === 200 && str_contains($dashboard['body'], 'AQUASENSE+ v' . APP_VERSION), 'Protected dashboard renders the single version constant');
    check(str_contains($dashboard['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($dashboard['body'], '<script>alert(1)</script>'), 'Stored account names are escaped');
    check(str_contains($dashboard['body'], 'Signed in to workspace'), 'Login audit is visible in account activity');
    check(str_contains($dashboard['body'], 'ADMINISTRATION'), 'Administrator sees administration navigation');
    check(request($anonymous, '/public/logout.php')['status'] === 405, 'GET cannot log a user out');
    check(request($anonymous, '/public/logout.php', ['csrf_token' => 'forged'])['status'] === 403, 'Forged logout token is rejected');
    check(request($anonymous, '/admin/dashboard.php')['status'] === 200, 'Rejected logout leaves the valid session intact');
    $authenticatedCookies = curl_getinfo($anonymous, CURLINFO_COOKIELIST);
    $response = request($anonymous, '/public/logout.php', ['csrf_token' => token($dashboard)]);
    check($response['status'] === 303, 'Valid POST logout redirects');
    check(request($anonymous, '/admin/dashboard.php')['status'] === 303, 'Dashboard is protected after logout');
    $replay = client();
    foreach ($authenticatedCookies as $cookie) {
        curl_setopt($replay, CURLOPT_COOKIELIST, $cookie);
    }
    check(request($replay, '/admin/dashboard.php')['status'] === 303, 'A replayed logged-out session cannot access the dashboard');

    $staffClient = client();
    check(login($staffClient, $fixtureEmails['environmental_staff'], $password)['status'] === 303, 'Environmental staff login succeeds');
    $staffPage = request($staffClient, '/admin/dashboard.php');
    check($staffPage['status'] === 200 && !str_contains($staffPage['body'], 'ADMINISTRATION'), 'Staff have dashboard access with administration navigation hidden');
    $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roles['owner'], $fixtureIds['environmental_staff']]);
    check(request($staffClient, '/admin/dashboard.php')['status'] === 303, 'A role change revokes existing administrative access');
    $ownerClient = client();
    check(login($ownerClient, $fixtureEmails['owner'], $password)['status'] !== 303, 'Future owner role cannot sign in to the administrative website');
    check(request($ownerClient, '/admin/dashboard.php')['status'] === 303, 'Owner is denied direct dashboard access');

    $activeClient = client();
    check(login($activeClient, $fixtureEmails['administrator'], $password)['status'] === 303, 'Administrator can sign in again after logout');
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$fixtureIds['administrator']]);
    check(request($activeClient, '/admin/dashboard.php')['status'] === 303, 'Deactivation revokes an existing session');
    check(login(client(), $fixtureEmails['administrator'], $password)['status'] !== 303, 'Deactivated accounts cannot authenticate');

    $rateEmail = 'foundation-rate-' . bin2hex(random_bytes(5)) . '@example.test';
    $fixtureEmails['rate'] = $rateEmail;
    $insert = $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_address) VALUES (?, ?)');
    for ($i = 0; $i < (int) $config['login_max_attempts']; $i++) {
        $insert->execute([hash('sha256', $rateEmail), '192.0.2.123']);
    }
    check(str_contains(login(client(), $rateEmail, 'invalid')['body'], 'Too many sign-in attempts.'), 'Server-side sign-in rate limiting is enforced');

    foreach (['/config/config.php', '/config/local.php', '/database/schema.sql', '/database/sample-data.sql', '/includes/auth.php', '/logs/', '/uploads/surrender-photos/', '/tests/foundation.php', '/api/', '/.gitignore'] as $path) {
        check(request(client(), $path)['status'] === 403, 'Private path is blocked: ' . $path);
    }
    check(str_contains(request(client(), '/public/forgot-password.php')['body'], 'No reset email will be sent.'), 'Recovery screen accurately discloses the unavailable feature');
    check(request(client(), '/public/reset-password.php')['status'] === 501, 'Reset placeholder does not process tokens');
    check(request(client(), '/assets/css/style.css')['status'] === 200 && request(client(), '/assets/js/app.js')['status'] === 200, 'Local CSS and JavaScript are served');

    $pdo->beginTransaction();
    expect_constraint('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)',
        [255, 'Invalid relationship', 'constraint@example.test', 'irrelevant'], 'Foreign key rejects a nonexistent role');
    expect_constraint('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)',
        [$roles['administrator'], 'Duplicate', $fixtureEmails['administrator'], 'irrelevant'], 'Email uniqueness is enforced');
    expect_constraint('UPDATE grease_traps SET high_threshold = 99, critical_threshold = 50 WHERE id = ?', [1], 'Out-of-order thresholds are rejected');
    expect_constraint('INSERT INTO device_assignments (device_id, grease_trap_id, started_at) VALUES (?, ?, UTC_TIMESTAMP())', [1, 1], 'Duplicate current device assignment is rejected');
    $pdo->prepare("INSERT INTO incentive_rules (name, minimum_oil_quantity, oil_unit, rice_reward_quantity, effective_date) VALUES (?, 1, 'L', 1, CURRENT_DATE())")->execute(['Automated test only']);
    $ruleId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO oil_surrenders (transaction_code, establishment_id, submitted_by, surrendered_at, oil_quantity, status) VALUES (?, 1, ?, UTC_TIMESTAMP(), 1, 'APPROVED')")
        ->execute(['TEST-' . bin2hex(random_bytes(6)), $fixtureIds['administrator']]);
    $surrenderId = (int) $pdo->lastInsertId();
    $sql = 'INSERT INTO incentive_transactions (oil_surrender_id, rule_id, rice_quantity, calculated_by) VALUES (?, ?, 1, ?)';
    $values = [$surrenderId, $ruleId, $fixtureIds['administrator']];
    $pdo->prepare($sql)->execute($values);
    expect_constraint($sql, $values, 'Same surrender cannot receive two incentives');
    $pdo->rollBack();

    echo PHP_EOL . $checks . " checks passed.\n";
} finally {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    foreach ($fixtureIds as $id) {
        db()->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    $fixtureEmails[] = "' or 1=1 --@example.test";
    foreach ($fixtureEmails as $email) {
        db()->prepare('DELETE FROM login_attempts WHERE email_hash = ?')->execute([hash('sha256', strtolower($email))]);
    }
}
