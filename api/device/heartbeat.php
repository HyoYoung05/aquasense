<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/includes/functions.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/includes/telemetry.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
send_security_headers();

function heartbeat_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}
function heartbeat_fail(int $status, string $message): never
{
    heartbeat_reply($status, ['success' => false, 'message' => $message]);
}
set_exception_handler(function (Throwable $error): void {
    error_log('Device heartbeat endpoint error: '.$error->getMessage());
    heartbeat_fail(500, 'Unable to record the device heartbeat. Please try again.');
});
if ($config['require_https'] && !request_is_https()) {
    header('Upgrade: TLS/1.2');
    heartbeat_fail(426, 'HTTPS is required.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    heartbeat_fail(405, 'Use POST.');
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    heartbeat_fail(415, 'Send application/json.');
}
$key = trim((string) ($_SERVER['HTTP_X_DEVICE_KEY'] ?? ''));
if ($key === '') {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $match)) $key = $match[1];
}
if ($key === '') heartbeat_fail(401, 'A valid device credential is required.');
$raw = file_get_contents('php://input', false, null, 0, $config['telemetry_max_body_bytes'] + 1);
if ($raw === false || strlen($raw) > $config['telemetry_max_body_bytes']) {
    heartbeat_fail(413, 'Heartbeat body is too large.');
}
try {
    $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    heartbeat_fail(400, 'Invalid JSON.');
}
if (!is_array($body)) heartbeat_fail(400, 'Invalid JSON object.');
try {
    $result = phase3_record_heartbeat(db(), $body, $key);
    $status = $result['http_status'];
    unset($result['http_status']);
    heartbeat_reply($status, ['success' => true, 'data' => $result]);
} catch (TelemetryException $error) {
    error_log('Heartbeat rejected: device='.(is_string($body['device_id'] ?? null)
        ? $body['device_id'] : 'unknown').' category='.$error->category);
    heartbeat_fail($error->httpStatus, $error->getMessage());
}
