<?php
declare(strict_types=1);
$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
send_security_headers();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    if ($method !== 'HEAD') echo json_encode(['status' => 'method_not_allowed']);
    exit;
}
if ($config['require_https'] && !request_is_https()) {
    header('Upgrade: TLS/1.2');
    http_response_code(426);
    if ($method !== 'HEAD') echo json_encode(['status' => 'https_required']);
    exit;
}
try {
    require_once dirname(__DIR__) . '/config/database.php';
    db()->query('SELECT 1')->fetchColumn();
    http_response_code(200);
    if ($method !== 'HEAD') echo json_encode(['status' => 'ok']);
} catch (Throwable $error) {
    app_log('error', 'Health check database connection failed.');
    http_response_code(503);
    if ($method !== 'HEAD') echo json_encode(['status' => 'unavailable']);
}
