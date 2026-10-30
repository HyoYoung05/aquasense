<?php
declare(strict_types=1);

final class DeviceSupportValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'The support request could not be saved.');
    }
}

function device_support_categories(): array
{
    return [
        'CONNECTION' => 'Connection / offline',
        'SENSOR' => 'Sensor reading',
        'POWER' => 'Power supply',
        'FIRMWARE' => 'Firmware / API',
        'OTHER' => 'Other device problem',
    ];
}

function device_support_priorities(): array
{
    return ['NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'];
}

function device_support_statuses(): array
{
    return [
        'OPEN' => 'Open',
        'IN_PROGRESS' => 'In progress',
        'RESOLVED' => 'Resolved',
        'CLOSED' => 'Closed',
    ];
}

function device_support_role_allowed(string $roleSlug): bool
{
    return in_array($roleSlug, ['system_admin', 'administrator'], true);
}

function device_support_create(PDO $pdo, array $input, array $actor): int
{
    if (($actor['role_slug'] ?? '') !== 'administrator') {
        throw new DeviceSupportValidationException(['Only a Barangay Admin can create a device support request.']);
    }
    if (audit_rate_limit_reached($pdo, (int) $actor['id'],
        ['DEVICE_SUPPORT_CREATED', 'DEVICE_SUPPORT_REPLIED'], 60, 10)) {
        throw new DeviceSupportValidationException(['Too many support messages were sent. Wait one minute and try again.']);
    }

    $deviceId = filter_var($input['device_id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
    $priority = is_string($input['priority'] ?? null) ? trim($input['priority']) : '';
    $subject = is_string($input['subject'] ?? null) ? trim($input['subject']) : '';
    $message = is_string($input['message'] ?? null) ? trim($input['message']) : '';
    $errors = [];

    if ($deviceId === false) $errors[] = 'Select the device with the problem.';
    if (!isset(device_support_categories()[$category])) $errors[] = 'Select a valid problem category.';
    if (!isset(device_support_priorities()[$priority])) $errors[] = 'Select a valid priority.';
    if (mb_strlen($subject) < 5 || mb_strlen($subject) > 150) {
        $errors[] = 'Subject must contain 5 to 150 characters.';
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
        $errors[] = 'Problem description must contain 10 to 3,000 characters.';
    }
    if ($deviceId !== false) {
        $query = $pdo->prepare('SELECT id FROM devices WHERE id=? LIMIT 1');
        $query->execute([(int) $deviceId]);
        if ($query->fetchColumn() === false) $errors[] = 'The selected device was not found.';
    }
    if ($errors) throw new DeviceSupportValidationException($errors);

    $pdo->beginTransaction();
    try {
        $code = 'SUP-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $query = $pdo->prepare("INSERT INTO device_support_requests
            (request_code,device_id,requested_by,subject,category,priority,status)
            VALUES (?,?,?,?,?,?,'OPEN')");
        $query->execute([$code, (int) $deviceId, (int) $actor['id'], $subject, $category, $priority]);
        $requestId = (int) $pdo->lastInsertId();
        $query = $pdo->prepare('INSERT INTO device_support_messages (request_id,sender_id,message) VALUES (?,?,?)');
        $query->execute([$requestId, (int) $actor['id'], $message]);
        audit('DEVICE_SUPPORT_CREATED', (int) $actor['id'], 'device_support_requests', $requestId);
        $pdo->commit();
        return $requestId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function device_support_list(PDO $pdo, array $actor): array
{
    if (!device_support_role_allowed((string) ($actor['role_slug'] ?? ''))) return [];
    $where = ($actor['role_slug'] ?? '') === 'system_admin' ? '' : ' WHERE sr.requested_by=?';
    $query = $pdo->prepare("SELECT sr.id,sr.request_code,sr.subject,sr.category,sr.priority,sr.status,
        sr.created_at,sr.updated_at,d.device_code,d.name device_name,u.full_name requester_name,
        (SELECT COUNT(*) FROM device_support_messages sm WHERE sm.request_id=sr.id) message_count
        FROM device_support_requests sr JOIN devices d ON d.id=sr.device_id
        JOIN users u ON u.id=sr.requested_by{$where}
        ORDER BY FIELD(sr.status,'OPEN','IN_PROGRESS','RESOLVED','CLOSED'),sr.updated_at DESC,sr.id DESC");
    $query->execute($where === '' ? [] : [(int) $actor['id']]);
    return $query->fetchAll();
}

function device_support_get(PDO $pdo, int $requestId, array $actor): ?array
{
    if (!device_support_role_allowed((string) ($actor['role_slug'] ?? ''))) return null;
    $where = ($actor['role_slug'] ?? '') === 'system_admin' ? '' : ' AND sr.requested_by=?';
    $query = $pdo->prepare("SELECT sr.*,d.device_code,d.name device_name,d.last_seen_at,d.is_active device_active,
        u.full_name requester_name,u.email requester_email,assignee.full_name assigned_name
        FROM device_support_requests sr JOIN devices d ON d.id=sr.device_id
        JOIN users u ON u.id=sr.requested_by LEFT JOIN users assignee ON assignee.id=sr.assigned_to
        WHERE sr.id=?{$where} LIMIT 1");
    $parameters = [$requestId];
    if ($where !== '') $parameters[] = (int) $actor['id'];
    $query->execute($parameters);
    $request = $query->fetch();
    if (!$request) return null;

    $query = $pdo->prepare('SELECT sm.id,sm.message,sm.created_at,sm.sender_id,u.full_name,r.slug role_slug,r.name role_name
        FROM device_support_messages sm JOIN users u ON u.id=sm.sender_id JOIN roles r ON r.id=u.role_id
        WHERE sm.request_id=? ORDER BY sm.id');
    $query->execute([$requestId]);
    $request['messages'] = $query->fetchAll();
    return $request;
}

function device_support_reply(PDO $pdo, int $requestId, string $message, ?string $status, array $actor): void
{
    $role = (string) ($actor['role_slug'] ?? '');
    if (!device_support_role_allowed($role)) {
        throw new DeviceSupportValidationException(['This account cannot use Device Support.']);
    }
    if (audit_rate_limit_reached($pdo, (int) $actor['id'],
        ['DEVICE_SUPPORT_CREATED', 'DEVICE_SUPPORT_REPLIED'], 60, 10)) {
        throw new DeviceSupportValidationException(['Too many support messages were sent. Wait one minute and try again.']);
    }
    $message = trim($message);
    if (mb_strlen($message) < 2 || mb_strlen($message) > 3000) {
        throw new DeviceSupportValidationException(['Reply must contain 2 to 3,000 characters.']);
    }
    $request = device_support_get($pdo, $requestId, $actor);
    if (!$request) throw new DeviceSupportValidationException(['The support request was not found.']);
    if ($request['status'] === 'CLOSED') {
        throw new DeviceSupportValidationException(['A closed support request cannot receive more replies.']);
    }

    $nextStatus = (string) $request['status'];
    if ($role === 'system_admin') {
        $status = $status ?? '';
        if (!isset(device_support_statuses()[$status])) {
            throw new DeviceSupportValidationException(['Select a valid support status.']);
        }
        $nextStatus = $status;
    } elseif ($nextStatus === 'RESOLVED') {
        $nextStatus = 'OPEN';
    }

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('INSERT INTO device_support_messages (request_id,sender_id,message) VALUES (?,?,?)');
        $query->execute([$requestId, (int) $actor['id'], $message]);
        $resolvedAt = $nextStatus === 'RESOLVED' || $nextStatus === 'CLOSED' ? gmdate('Y-m-d H:i:s') : null;
        $assignedTo = $role === 'system_admin' ? (int) $actor['id'] : ($request['assigned_to'] ?? null);
        $query = $pdo->prepare('UPDATE device_support_requests SET status=?,assigned_to=?,resolved_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $query->execute([$nextStatus, $assignedTo, $resolvedAt, $requestId]);
        audit('DEVICE_SUPPORT_REPLIED', (int) $actor['id'], 'device_support_requests', $requestId);
        if ($nextStatus !== $request['status']) {
            audit('DEVICE_SUPPORT_STATUS_CHANGED', (int) $actor['id'], 'device_support_requests', $requestId);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
