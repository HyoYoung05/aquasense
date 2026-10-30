<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    exit("CLI only: php tests/device-support.php --allow-local-fixtures\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/device-support.php';

$checks = 0;
$userIds = [];
$requestIds = [];
$deviceId = null;

function support_check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}

try {
    $pdo = db();
    $roles = $pdo->query('SELECT slug,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['system_admin', 'administrator', 'administrator', 'environmental_staff'] as $index => $role) {
        $email = 'support-test-' . $index . '-' . bin2hex(random_bytes(4)) . '@example.test';
        $pdo->prepare('INSERT INTO users (role_id,full_name,email,password_hash) VALUES (?,?,?,?)')
            ->execute([$roles[$role], 'Support fixture ' . $index, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $userIds[] = (int) $pdo->lastInsertId();
    }
    $actors = [
        'system' => ['id' => $userIds[0], 'role_slug' => 'system_admin'],
        'admin' => ['id' => $userIds[1], 'role_slug' => 'administrator'],
        'other_admin' => ['id' => $userIds[2], 'role_slug' => 'administrator'],
        'environmental' => ['id' => $userIds[3], 'role_slug' => 'environmental_staff'],
    ];
    support_check(workspace_role_label($actors['system']) === 'System Admin'
        && workspace_role_label($actors['admin']) === 'Admin'
        && workspace_role_label($actors['environmental']) === 'Environmental Management',
        'Workspace header labels distinguish the three website roles');
    support_check(can_use_device_support($actors['admin'])
        && can_use_device_support($actors['system'])
        && !can_use_device_support($actors['environmental']),
        'Device Support authorization excludes Environmental Management');
    $deviceCode = 'SUPPORT-' . strtoupper(bin2hex(random_bytes(5)));
    $pdo->prepare("INSERT INTO devices (device_code,name,device_type) VALUES (?,'Support fixture device','ESP32 DevKit')")
        ->execute([$deviceCode]);
    $deviceId = (int) $pdo->lastInsertId();

    $requestId = device_support_create($pdo, [
        'device_id' => $deviceId,
        'category' => 'CONNECTION',
        'priority' => 'HIGH',
        'subject' => 'Fixture device is offline',
        'message' => 'The fixture stopped sending telemetry during the test.',
    ], $actors['admin']);
    $requestIds[] = $requestId;
    support_check($requestId > 0, 'Barangay Admin can create a device support request');
    support_check(count(device_support_list($pdo, $actors['admin'])) === 1, 'Barangay Admin sees their own support request');
    support_check(device_support_get($pdo, $requestId, $actors['other_admin']) === null, 'Another Barangay Admin cannot open the request');
    support_check(device_support_get($pdo, $requestId, $actors['environmental']) === null, 'Environmental Management cannot open support requests');
    support_check(count(device_support_list($pdo, $actors['system'])) === 1, 'System Admin receives the support request');

    device_support_reply($pdo, $requestId, 'Check the router and restart the device power supply.', 'IN_PROGRESS', $actors['system']);
    $request = device_support_get($pdo, $requestId, $actors['admin']);
    support_check($request['status'] === 'IN_PROGRESS' && count($request['messages']) === 2,
        'System Admin can reply and update support status');
    device_support_reply($pdo, $requestId, 'The device is online again after the router check.', null, $actors['admin']);
    $request = device_support_get($pdo, $requestId, $actors['system']);
    support_check(count($request['messages']) === 3, 'Barangay Admin can continue the support conversation');
    device_support_reply($pdo, $requestId, 'Confirmed. Closing the resolved device issue.', 'RESOLVED', $actors['system']);
    $request = device_support_get($pdo, $requestId, $actors['system']);
    support_check($request['status'] === 'RESOLVED' && $request['resolved_at'] !== null,
        'System Admin can resolve a support request');

    $blocked = false;
    try {
        device_support_create($pdo, [
            'device_id' => $deviceId,
            'category' => 'OTHER',
            'priority' => 'NORMAL',
            'subject' => 'Environmental fixture request',
            'message' => 'This request must be rejected by role authorization.',
        ], $actors['environmental']);
    } catch (DeviceSupportValidationException) {
        $blocked = true;
    }
    support_check($blocked, 'Environmental Management cannot create support requests');
    echo $checks . " checks passed.\n";
} finally {
    $pdo = $pdo ?? null;
    if ($pdo instanceof PDO) {
        foreach ($requestIds as $id) {
            $pdo->prepare('DELETE FROM device_support_messages WHERE request_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM device_support_requests WHERE id=?')->execute([$id]);
        }
        if ($userIds) {
            $marks = implode(',', array_fill(0, count($userIds), '?'));
            $pdo->prepare("DELETE FROM audit_logs WHERE user_id IN ($marks)")->execute($userIds);
        }
        if ($deviceId) $pdo->prepare('DELETE FROM devices WHERE id=?')->execute([$deviceId]);
        foreach ($userIds as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
}
