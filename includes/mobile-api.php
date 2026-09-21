<?php
declare(strict_types=1);

// Mobile bearer authentication is independent of staff browser sessions.
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mobile-cors.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
set_exception_handler(function (Throwable $error): void {
    error_log('Mobile API: ' . $error->getMessage());
    api_fail('The service is temporarily unavailable. Please try again.', 503);
});

if ($config['require_https'] && !request_is_https()) {
    api_fail('AQUASENSE+ API requires HTTPS.', 426);
}

// Handle browser CORS before loading the database layer, authentication, method
// validation or request parsing. Native bearer clients send no Origin header.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($origin !== '') {
    header('Vary: Origin');
    $originAllowed = mobile_origin_allowed($origin, $config);
    if (!$originAllowed) {
        mobile_log_cors($config, $requestMethod, $origin, false, false);
        api_fail('This browser origin is not enabled for the owner app.', 403);
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
    if ($requestMethod === 'OPTIONS') {
        $method = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '';
        $headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        if (!mobile_preflight_allowed($method, $headers)) {
            mobile_log_cors($config, $requestMethod, $origin, true, false);
            api_fail('This browser request is not supported.', 403);
        }
        mobile_log_cors($config, $requestMethod, $origin, true, true);
        http_response_code(204);
        exit;
    }
    mobile_log_cors($config, $requestMethod, $origin, true, false);
}

require_once dirname(__DIR__) . '/config/database.php';

function api_fail(string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function api_ok(array $data): never
{
    echo json_encode(['success' => true, 'data' => $data], JSON_THROW_ON_ERROR);
    exit;
}

function api_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        api_fail('This request method is not supported.', 405);
    }
}

function api_body(): array
{
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        api_fail('Send a JSON request.', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    if ($raw === false || strlen($raw) > 4096) {
        api_fail('The request is too large.', 413);
    }
    try {
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        api_fail('The request could not be read.', 400);
    }
    if (!is_array($data) || array_is_list($data)) {
        api_fail('Send a JSON object.', 400);
    }
    return $data;
}

function mobile_token_hash(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/i', $header, $match)) {
        api_fail('Please sign in to continue.', 401);
    }
    return hash('sha256', $match[1]);
}

function mobile_owner(): array
{
    $query = db()->prepare('SELECT u.id, u.full_name, u.email, u.password_hash,
        t.password_fingerprint FROM mobile_tokens t
        JOIN users u ON u.id = t.user_id JOIN roles r ON r.id = u.role_id
        WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP()
        AND u.is_active = 1 AND r.slug = ?');
    $query->execute([mobile_token_hash(), 'owner']);
    $user = $query->fetch();
    if (!$user || !hash_equals($user['password_fingerprint'], hash('sha256', $user['password_hash']))) {
        api_fail('Your session has expired. Please sign in again.', 401);
    }
    return ['id' => (int) $user['id'], 'full_name' => $user['full_name'], 'email' => $user['email']];
}
