<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/ultrasonic-test.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function telemetry_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}
function telemetry_fail(int $status, string $message): never
{
    telemetry_reply($status, ['success' => false, 'message' => $message]);
}
set_exception_handler(function (Throwable $error): void {
    error_log('Ultrasonic test API: ' . $error->getMessage());
    telemetry_fail(503, 'Telemetry service unavailable. Try again later.');
});

// This hardware experiment is deliberately not a production ingestion endpoint.
if ($config['environment'] !== 'development') {
    telemetry_fail(404, 'This test endpoint is disabled.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    telemetry_fail(405, 'Use POST.');
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer ([a-f0-9]{64})$/i', $authorization, $match)) {
    telemetry_fail(401, 'A valid device credential is required.');
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    telemetry_fail(415, 'Send application/json.');
}
$raw = file_get_contents('php://input', false, null, 0, 2049);
if ($raw === false || strlen($raw) > 2048) telemetry_fail(413, 'Telemetry body is too large.');
try {
    $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    telemetry_fail(400, 'Invalid JSON.');
}
$fields = ['device_id', 'grease_trap_id', 'ultrasonic_distance', 'waste_level_percent', 'status'];
if (!is_array($body) || array_is_list($body) || count($body) !== count($fields)
    || array_diff($fields, array_keys($body))) telemetry_fail(422, 'Send the five documented telemetry fields.');
if (!is_string($body['device_id']) || !preg_match('/^[A-Za-z0-9_-]{1,60}$/D', $body['device_id'])
    || !is_int($body['grease_trap_id']) || $body['grease_trap_id'] < 1
    || !is_string($body['status']) || !in_array($body['status'], ['NORMAL','WARNING','CRITICAL'], true)) {
    telemetry_fail(422, 'Invalid device, trap, or status.');
}
foreach (['ultrasonic_distance', 'waste_level_percent'] as $key) {
    if ((!is_int($body[$key]) && !is_float($body[$key])) || !is_finite((float) $body[$key])) {
        telemetry_fail(422, 'Distance and percentage must be finite JSON numbers.');
    }
}
if ($body['ultrasonic_distance'] < 2 || $body['ultrasonic_distance'] > 400
    || $body['waste_level_percent'] < 0 || $body['waste_level_percent'] > 100) {
    telemetry_fail(422, 'Reading is outside the test range.');
}
$pdo = db();
$pdo->beginTransaction();
try {
    // Lock the credential/device row, then its current assignment, to serialize ingestion.
    $q = $pdo->prepare('SELECT d.id, d.device_code, d.last_seen_at, c.*
        FROM devices d JOIN device_ultrasonic_test_config c ON c.device_id = d.id
        WHERE d.api_key_hash = ? AND d.is_active = 1 FOR UPDATE');
    $q->execute([hash('sha256', $match[1])]);
    $device = $q->fetch();
    if (!$device) { $pdo->rollBack(); telemetry_fail(401, 'A valid device credential is required.'); }
    $q = $pdo->prepare('SELECT a.id, a.grease_trap_id FROM device_assignments a
        JOIN grease_traps g ON g.id = a.grease_trap_id
        JOIN establishments e ON e.id = g.establishment_id
        WHERE a.device_id = ? AND a.ended_at IS NULL AND g.is_active = 1 AND e.is_active = 1 FOR UPDATE');
    $q->execute([$device['id']]);
    $assignment = $q->fetch();
    if ($device['device_code'] !== $body['device_id'] || !$assignment
        || (int) $assignment['grease_trap_id'] !== $body['grease_trap_id']) {
        $pdo->rollBack(); telemetry_fail(403, 'Device is not assigned to this active trap.');
    }
    $level = ultrasonic_test_level((float) $body['ultrasonic_distance'], $device);
    if (abs($level['waste_level_percent'] - $body['waste_level_percent']) > 0.6
        || $level['status'] !== $body['status']) {
        $pdo->rollBack(); telemetry_fail(422, 'Reading does not match saved calibration. Synchronize firmware and server settings.');
    }
    if ($device['last_seen_at'] !== null && time() - strtotime($device['last_seen_at'] . ' UTC') < 2) {
        $pdo->rollBack(); header('Retry-After: 2'); telemetry_fail(429, 'Wait before the next reading.');
    }
    $q = $pdo->prepare('INSERT INTO sensor_readings
        (device_assignment_id, ultrasonic_distance_cm, waste_level_percent, temperature_c,
         level_status, is_simulated, is_test, recorded_at)
        VALUES (?, ?, ?, NULL, ?, 0, 1, UTC_TIMESTAMP())');
    $q->execute([$assignment['id'], round((float) $body['ultrasonic_distance'], 2),
        $level['waste_level_percent'], $level['status']]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE devices SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$device['id']]);
    $pdo->commit();
    telemetry_reply(201, ['success' => true, 'data' => [
        'reading_id' => $id, 'device_id' => $device['device_code'],
        'grease_trap_id' => (int) $assignment['grease_trap_id'],
        'ultrasonic_distance' => round((float) $body['ultrasonic_distance'], 2),
        'waste_level_percent' => $level['waste_level_percent'], 'status' => $level['status'],
        'temperature_c' => null, 'is_test' => true, 'is_simulated' => false,
    ]]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
