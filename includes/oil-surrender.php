<?php
declare(strict_types=1);
require_once __DIR__ . '/compliance.php';

final class Phase5ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}

final class Phase5ConflictException extends RuntimeException {}

function phase5_settings(PDO $pdo): array
{
    $keys = ['oil_surrender_max_upload_bytes', 'oil_surrender_telemetry_window_hours'];
    $marks = implode(',', array_fill(0, count($keys), '?'));
    $query = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($marks)");
    $query->execute($keys);
    $values = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($keys as $key) {
        if (!isset($values[$key]) || !is_numeric($values[$key])) {
            throw new RuntimeException('Missing oil-surrender setting: ' . $key);
        }
    }
    $settings = array_map('floatval', $values);
    if ($settings['oil_surrender_max_upload_bytes'] < 1024
        || $settings['oil_surrender_max_upload_bytes'] > 20 * 1024 * 1024) {
        throw new RuntimeException('The oil-surrender upload limit is invalid.');
    }
    if ($settings['oil_surrender_telemetry_window_hours'] < 1
        || $settings['oil_surrender_telemetry_window_hours'] > 168) {
        throw new RuntimeException('The Hybrid Verification telemetry window is invalid.');
    }
    return $settings;
}

function phase5_allowed_image_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

function phase5_update_settings(PDO $pdo, array $input, int $userId): void
{
    $maximum = filter_var($input['oil_surrender_max_upload_bytes'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1024, 'max_range' => 20 * 1024 * 1024]]);
    $window = filter_var($input['oil_surrender_telemetry_window_hours'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 168]]);
    $errors = [];
    if ($maximum === false) $errors[] = 'Photo limit must be between 1 KB and 20 MB.';
    if ($window === false) $errors[] = 'Telemetry window must be between 1 and 168 hours.';
    if ($errors) throw new Phase5ValidationException($errors);
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('UPDATE system_settings SET setting_value=?,updated_by=? WHERE setting_key=?');
        $query->execute([(string) $maximum, $userId, 'oil_surrender_max_upload_bytes']);
        $query->execute([(string) $window, $userId, 'oil_surrender_telemetry_window_hours']);
        $audit = $pdo->prepare("INSERT INTO audit_logs (user_id,action,record_type,record_id,ip_address)
            VALUES (?,'OIL_SURRENDER_SETTINGS_UPDATED','system_settings',NULL,?)");
        $audit->execute([$userId, client_ip()]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function phase5_validate_submission(array $input): array
{
    $errors = [];
    $uuid = strtolower(trim((string) ($input['submission_uuid'] ?? '')));
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $uuid)) {
        $errors[] = 'Provide a valid version 4 submission UUID.';
    }
    $quantity = filter_var($input['oil_quantity'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($quantity === false || !is_finite((float) $quantity) || (float) $quantity <= 0 || (float) $quantity > 9999999.999) {
        $errors[] = 'Oil quantity must be greater than zero.';
    }
    $unit = (string) ($input['oil_unit'] ?? '');
    if (!in_array($unit, ['L', 'kg'], true)) {
        $errors[] = 'Oil quantity unit must be L or kg.';
    }
    $notes = trim((string) ($input['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) {
        $errors[] = 'Submission notes must be 2000 characters or fewer.';
    }
    $trapId = filter_var($input['grease_trap_id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    if (($input['grease_trap_id'] ?? '') !== '' && $trapId === false) {
        $errors[] = 'Select a valid grease trap.';
    }
    if ($errors) {
        throw new Phase5ValidationException($errors);
    }
    return [
        'submission_uuid' => $uuid,
        'oil_quantity' => round((float) $quantity, 3),
        'oil_unit' => $unit,
        'notes' => $notes === '' ? null : $notes,
        'grease_trap_id' => $trapId === false ? null : $trapId,
    ];
}

function phase5_validate_photo(array $file, int $maximumBytes): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new Phase5ValidationException(['Please attach valid photo evidence.']);
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new Phase5ValidationException(['The photo exceeds the configured upload limit.']);
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new Phase5ValidationException(['The photo upload could not be completed.']);
    }
    $temporary = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($temporary === '' || !is_uploaded_file($temporary) || $size < 1) {
        throw new Phase5ValidationException(['Please attach valid photo evidence.']);
    }
    if ($size > $maximumBytes) {
        throw new Phase5ValidationException(['The photo exceeds the configured upload limit.']);
    }
    $original = basename(str_replace(chr(0), '', (string) ($file['name'] ?? 'photo')));
    $original = preg_replace('/[\x00-\x1F\x7F]+/u', '', $original) ?: 'photo';
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = phase5_allowed_image_types();
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
    $expectedExtension = $allowed[$mime] ?? null;
    $extensionMatches = $mime === 'image/jpeg'
        ? in_array($extension, ['jpg', 'jpeg'], true)
        : $extension === $expectedExtension;
    $dimensions = @getimagesize($temporary);
    if ($expectedExtension === null || !$extensionMatches || $dimensions === false
        || ($dimensions['mime'] ?? '') !== $mime) {
        throw new Phase5ValidationException(['Photo evidence must be a valid JPEG, PNG, or WEBP image.']);
    }
    return [
        'temporary_path' => $temporary,
        'mime_type' => $mime,
        'extension' => $expectedExtension,
        'file_size' => $size,
        'original_filename' => mb_substr($original, 0, 255),
    ];
}

function phase5_owner_context(PDO $pdo, int $ownerId, ?int $trapId): array
{
    if ($trapId !== null) {
        $query = $pdo->prepare('SELECT e.id AS establishment_id, e.business_name,
            g.id AS grease_trap_id, g.name AS grease_trap_name,
            a.id AS assignment_id, a.device_id
            FROM establishments e JOIN grease_traps g ON g.establishment_id=e.id
            LEFT JOIN device_assignments a ON a.grease_trap_id=g.id AND a.ended_at IS NULL
            WHERE e.owner_user_id=? AND e.is_active=1 AND g.is_active=1 AND g.id=?');
        $query->execute([$ownerId, $trapId]);
        $context = $query->fetch();
        if (!$context) {
            throw new Phase5ValidationException(['The selected grease trap is not available to this owner.']);
        }
        return $context;
    }
    $query = $pdo->prepare('SELECT id AS establishment_id, business_name
        FROM establishments WHERE owner_user_id=? AND is_active=1 ORDER BY id');
    $query->execute([$ownerId]);
    $sites = $query->fetchAll();
    if (!$sites) {
        throw new Phase5ValidationException(['No active establishment is linked to this owner account.']);
    }
    if (count($sites) > 1) {
        throw new Phase5ValidationException(['Select a grease trap to identify the establishment for this submission.']);
    }
    return $sites[0] + ['grease_trap_id' => null, 'grease_trap_name' => null,
        'assignment_id' => null, 'device_id' => null];
}

function phase5_find_related_reading(PDO $pdo, ?int $trapId, string $submittedAt, int $windowHours): ?int
{
    if ($trapId === null) {
        return null;
    }
    $start = gmdate('Y-m-d H:i:s', strtotime($submittedAt . ' UTC') - $windowHours * 3600);
    $end = gmdate('Y-m-d H:i:s', strtotime($submittedAt . ' UTC') + $windowHours * 3600);
    $query = $pdo->prepare('SELECT r.id FROM sensor_readings r
        JOIN device_assignments a ON a.id=r.device_assignment_id
        WHERE a.grease_trap_id=? AND r.recorded_at BETWEEN ? AND ?
        ORDER BY ABS(TIMESTAMPDIFF(SECOND,r.recorded_at,?)),r.id DESC LIMIT 1');
    $query->execute([$trapId, $start, $end, $submittedAt]);
    $id = $query->fetchColumn();
    return $id === false ? null : (int) $id;
}

function phase5_transaction_code(): string
{
    return 'OS-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function phase5_ledger(PDO $pdo, string $event, int $establishmentId, int $surrenderId,
    string $description, int $userId): void
{
    compliance_append($pdo, $event, $establishmentId, 'oil_surrenders', $surrenderId,
        $description, $userId, null, null, $event . ':oil_surrenders:' . $surrenderId);
}

function phase5_audit(PDO $pdo, string $action, int $userId, int $surrenderId): void
{
    $query = $pdo->prepare('INSERT INTO audit_logs (user_id,action,record_type,record_id,ip_address)
        VALUES (?,?,?,?,?)');
    $query->execute([$userId, $action, 'oil_surrenders', $surrenderId, client_ip()]);
}

function phase5_owner_record(PDO $pdo, int $ownerId, int $id): ?array
{
    $query = $pdo->prepare('SELECT os.id,os.transaction_code,os.submission_uuid,os.oil_quantity,
        os.oil_unit,os.notes,os.surrendered_at,os.status,os.verification_status,os.reviewed_at,
        os.approved_at,os.rejected_at,os.remarks,e.business_name,g.name AS grease_trap_name,
        p.id AS photo_id,p.uploaded_at
        FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id
        LEFT JOIN grease_traps g ON g.id=os.grease_trap_id
        LEFT JOIN oil_surrender_photos p ON p.id=(SELECT p2.id FROM oil_surrender_photos p2
            WHERE p2.oil_surrender_id=os.id ORDER BY p2.id LIMIT 1)
        WHERE os.id=? AND os.submitted_by=? AND e.owner_user_id=?');
    $query->execute([$id, $ownerId, $ownerId]);
    $row = $query->fetch();
    return $row ? phase5_owner_payload($row) : null;
}

function phase5_owner_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'transaction_code' => $row['transaction_code'],
        'submission_uuid' => $row['submission_uuid'],
        'business_name' => $row['business_name'],
        'grease_trap_name' => $row['grease_trap_name'],
        'oil_quantity' => (float) $row['oil_quantity'],
        'oil_unit' => $row['oil_unit'],
        'notes' => $row['notes'],
        'submitted_at' => str_replace(' ', 'T', $row['surrendered_at']) . 'Z',
        'status' => $row['status'],
        'verification_status' => $row['verification_status'],
        'review_remarks' => $row['remarks'],
        'reviewed_at' => $row['reviewed_at'] ? str_replace(' ', 'T', $row['reviewed_at']) . 'Z' : null,
        'approved_at' => $row['approved_at'] ? str_replace(' ', 'T', $row['approved_at']) . 'Z' : null,
        'rejected_at' => $row['rejected_at'] ? str_replace(' ', 'T', $row['rejected_at']) . 'Z' : null,
        'photo' => $row['photo_id'] === null ? null : [
            'id' => (int) $row['photo_id'],
            'url' => url('api/mobile/oil-surrender-photo.php?id=' . (int) $row['photo_id']),
            'uploaded_at' => str_replace(' ', 'T', $row['uploaded_at']) . 'Z',
        ],
    ];
}

function phase5_submit(PDO $pdo, int $ownerId, array $input, array $file): array
{
    $data = phase5_validate_submission($input);
    $existing = $pdo->prepare('SELECT id,submitted_by FROM oil_surrenders WHERE submission_uuid=?');
    $existing->execute([$data['submission_uuid']]);
    $duplicate = $existing->fetch();
    if ($duplicate) {
        if ((int) $duplicate['submitted_by'] !== $ownerId) {
            throw new Phase5ConflictException('That submission identifier is already in use.');
        }
        return ['created' => false, 'record' => phase5_owner_record($pdo, $ownerId, (int) $duplicate['id'])];
    }
    $settings = phase5_settings($pdo);
    $photo = phase5_validate_photo($file, (int) $settings['oil_surrender_max_upload_bytes']);
    $context = phase5_owner_context($pdo, $ownerId, $data['grease_trap_id']);
    $submittedAt = gmdate('Y-m-d H:i:s');
    $relativeDirectory = 'surrender-photos/' . gmdate('Y/m');
    $directory = storage_path($relativeDirectory);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not prepare protected photo storage.');
    }
    $storedFilename = bin2hex(random_bytes(24)) . '.' . $photo['extension'];
    $relativePath = $relativeDirectory . '/' . $storedFilename;
    $absolutePath = storage_path($relativePath);
    $moved = false;
    $pdo->beginTransaction();
    try {
        $readingId = phase5_find_related_reading($pdo,
            $context['grease_trap_id'] === null ? null : (int) $context['grease_trap_id'],
            $submittedAt, (int) $settings['oil_surrender_telemetry_window_hours']);
        $query = $pdo->prepare('INSERT INTO oil_surrenders
            (transaction_code,submission_uuid,establishment_id,grease_trap_id,device_id,submitted_by,
             surrendered_at,oil_quantity,oil_unit,notes,related_sensor_reading_id,status,verification_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,\'PENDING\',\'UNVERIFIED\')');
        $query->execute([phase5_transaction_code(), $data['submission_uuid'], $context['establishment_id'],
            $context['grease_trap_id'], $context['device_id'], $ownerId, $submittedAt,
            $data['oil_quantity'], $data['oil_unit'], $data['notes'], $readingId]);
        $id = (int) $pdo->lastInsertId();
        if (!move_uploaded_file($photo['temporary_path'], $absolutePath)) {
            throw new RuntimeException('The uploaded evidence could not be stored.');
        }
        $moved = true;
        @chmod($absolutePath, 0640);
        $query = $pdo->prepare('INSERT INTO oil_surrender_photos
            (oil_surrender_id,file_path,original_filename,mime_type,file_size,uploaded_by,uploaded_at)
            VALUES (?,?,?,?,?,?,?)');
        $query->execute([$id, $relativePath, $photo['original_filename'], $photo['mime_type'],
            $photo['file_size'], $ownerId, $submittedAt]);
        phase5_audit($pdo, 'OIL_SURRENDER_SUBMITTED', $ownerId, $id);
        phase5_audit($pdo, 'OIL_SURRENDER_PHOTO_UPLOADED', $ownerId, $id);
        phase5_ledger($pdo, 'OIL_SURRENDER_SUBMITTED', (int) $context['establishment_id'], $id,
            'Owner submitted oil surrender evidence for Barangay review.', $ownerId);
        $pdo->commit();
        return ['created' => true, 'record' => phase5_owner_record($pdo, $ownerId, $id)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($moved && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
        if ($error instanceof PDOException && $error->getCode() === '23000') {
            $existing->execute([$data['submission_uuid']]);
            $duplicate = $existing->fetch();
            if ($duplicate && (int) $duplicate['submitted_by'] === $ownerId) {
                return ['created' => false, 'record' => phase5_owner_record($pdo, $ownerId, (int) $duplicate['id'])];
            }
        }
        throw $error;
    }
}

function phase5_owner_history(PDO $pdo, int $ownerId, ?string $status): array
{
    $allowed = ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED'];
    if ($status !== null && !in_array($status, $allowed, true)) {
        throw new Phase5ValidationException(['Unknown surrender status filter.']);
    }
    $sql = 'SELECT os.id,os.transaction_code,os.submission_uuid,os.oil_quantity,os.oil_unit,
        os.notes,os.surrendered_at,os.status,os.verification_status,os.reviewed_at,os.approved_at,
        os.rejected_at,os.remarks,e.business_name,g.name AS grease_trap_name,
        p.id AS photo_id,p.uploaded_at
        FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id
        LEFT JOIN grease_traps g ON g.id=os.grease_trap_id
        LEFT JOIN oil_surrender_photos p ON p.id=(SELECT p2.id FROM oil_surrender_photos p2
            WHERE p2.oil_surrender_id=os.id ORDER BY p2.id LIMIT 1)
        WHERE os.submitted_by=? AND e.owner_user_id=?';
    $parameters = [$ownerId, $ownerId];
    if ($status !== null) {
        $sql .= ' AND os.status=?';
        $parameters[] = $status;
    }
    $sql .= ' ORDER BY os.surrendered_at DESC,os.id DESC LIMIT 200';
    $query = $pdo->prepare($sql);
    $query->execute($parameters);
    return array_map('phase5_owner_payload', $query->fetchAll());
}

function phase5_admin_record(PDO $pdo, int $id): ?array
{
    $query = $pdo->prepare('SELECT os.*,e.business_name,e.owner_name,u.full_name AS submitter_name,
        g.name AS grease_trap_name,g.trap_code,d.device_code,d.name AS device_name,
        reviewer.full_name AS reviewer_name,p.id AS photo_id,p.mime_type,p.file_size,p.uploaded_at
        FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id
        JOIN users u ON u.id=os.submitted_by
        LEFT JOIN grease_traps g ON g.id=os.grease_trap_id
        LEFT JOIN devices d ON d.id=os.device_id
        LEFT JOIN users reviewer ON reviewer.id=os.reviewed_by
        LEFT JOIN oil_surrender_photos p ON p.id=(SELECT p2.id FROM oil_surrender_photos p2
            WHERE p2.oil_surrender_id=os.id ORDER BY p2.id LIMIT 1)
        WHERE os.id=?');
    $query->execute([$id]);
    return $query->fetch() ?: null;
}

function phase5_sensor_evidence(PDO $pdo, array $surrender): array
{
    if ($surrender['grease_trap_id'] === null) {
        return ['window_hours' => (int) phase5_settings($pdo)['oil_surrender_telemetry_window_hours'],
            'readings' => [], 'before' => null, 'after' => null, 'level_change' => null];
    }
    $hours = (int) phase5_settings($pdo)['oil_surrender_telemetry_window_hours'];
    $timestamp = strtotime($surrender['surrendered_at'] . ' UTC');
    $start = gmdate('Y-m-d H:i:s', $timestamp - $hours * 3600);
    $end = gmdate('Y-m-d H:i:s', $timestamp + $hours * 3600);
    $query = $pdo->prepare('SELECT r.id,r.waste_level_percent,r.ultrasonic_distance_cm,
        r.temperature_c,r.level_status,r.recorded_at,d.device_code
        FROM sensor_readings r JOIN device_assignments a ON a.id=r.device_assignment_id
        JOIN devices d ON d.id=a.device_id
        WHERE a.grease_trap_id=? AND r.recorded_at BETWEEN ? AND ?
        ORDER BY r.recorded_at,r.id LIMIT 200');
    $query->execute([$surrender['grease_trap_id'], $start, $end]);
    $readings = $query->fetchAll();
    $before = $after = null;
    foreach ($readings as $reading) {
        if ($reading['recorded_at'] <= $surrender['surrendered_at']) {
            $before = $reading;
        } elseif ($after === null) {
            $after = $reading;
        }
    }
    return [
        'window_hours' => $hours,
        'readings' => $readings,
        'before' => $before,
        'after' => $after,
        'level_change' => $before && $after
            ? round((float) $after['waste_level_percent'] - (float) $before['waste_level_percent'], 2)
            : null,
    ];
}

function phase5_review(PDO $pdo, int $id, string $action, int $actorId,
    int $expectedVersion, string $remarks): array
{
    $remarks = trim($remarks);
    if (mb_strlen($remarks) > 2000) {
        throw new Phase5ValidationException(['Review remarks must be 2000 characters or fewer.']);
    }
    if ($action === 'reject' && $remarks === '') {
        throw new Phase5ValidationException(['A rejection reason is required.']);
    }
    if (!in_array($action, ['start_review', 'approve', 'reject'], true)) {
        throw new Phase5ValidationException(['Unknown review action.']);
    }
    $pdo->beginTransaction();
    try {
        $current = phase5_admin_record($pdo, $id);
        if (!$current) {
            throw new Phase5ValidationException(['Oil surrender was not found.']);
        }
        if ((int) $current['review_version'] !== $expectedVersion) {
            throw new Phase5ConflictException('This surrender changed after the page was opened. Reload before reviewing it.');
        }
        $allowed = $action === 'start_review'
            ? ['PENDING'] : ['PENDING', 'UNDER_REVIEW'];
        if (!in_array($current['status'], $allowed, true)) {
            throw new Phase5ConflictException('This surrender has already reached a final or conflicting state.');
        }
        $newStatus = ['start_review' => 'UNDER_REVIEW', 'approve' => 'APPROVED', 'reject' => 'REJECTED'][$action];
        $verification = $action === 'approve' ? 'VERIFIED' : ($action === 'reject' ? 'DISCREPANCY' : 'UNVERIFIED');
        $query = $pdo->prepare('UPDATE oil_surrenders SET status=?,verification_status=?,
            review_started_at=CASE WHEN ?=\'UNDER_REVIEW\' THEN UTC_TIMESTAMP() ELSE review_started_at END,
            reviewed_by=?,reviewed_at=CASE WHEN ? IN (\'APPROVED\',\'REJECTED\') THEN UTC_TIMESTAMP() ELSE reviewed_at END,
            approved_at=CASE WHEN ?=\'APPROVED\' THEN UTC_TIMESTAMP() ELSE approved_at END,
            rejected_at=CASE WHEN ?=\'REJECTED\' THEN UTC_TIMESTAMP() ELSE rejected_at END,
            remarks=CASE WHEN ? IN (\'APPROVED\',\'REJECTED\') THEN ? ELSE remarks END,
            review_version=review_version+1
            WHERE id=? AND status=? AND review_version=?');
        $query->execute([$newStatus, $verification, $newStatus, $actorId, $newStatus,
            $newStatus, $newStatus, $newStatus, $remarks === '' ? null : $remarks,
            $id, $current['status'], $expectedVersion]);
        if ($query->rowCount() !== 1) {
            throw new Phase5ConflictException('Another reviewer updated this surrender. Reload before continuing.');
        }
        $auditAction = ['start_review' => 'OIL_SURRENDER_REVIEW_STARTED',
            'approve' => 'OIL_SURRENDER_APPROVED', 'reject' => 'OIL_SURRENDER_REJECTED'][$action];
        phase5_audit($pdo, $auditAction, $actorId, $id);
        phase5_ledger($pdo, $auditAction, (int) $current['establishment_id'], $id,
            $action === 'start_review'
                ? 'Barangay review started.'
                : ($action === 'approve' ? 'Barangay review approved the oil surrender.' : 'Barangay review rejected the oil surrender.'),
            $actorId);
        $pdo->commit();
        return phase5_admin_record($pdo, $id);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function phase5_photo(PDO $pdo, int $photoId): ?array
{
    $query = $pdo->prepare('SELECT p.*,os.submitted_by,e.owner_user_id
        FROM oil_surrender_photos p JOIN oil_surrenders os ON os.id=p.oil_surrender_id
        JOIN establishments e ON e.id=os.establishment_id WHERE p.id=?');
    $query->execute([$photoId]);
    return $query->fetch() ?: null;
}

function phase5_stream_photo(array $photo): never
{
    $allowed = phase5_allowed_image_types();
    if (!isset($allowed[$photo['mime_type']])) {
        http_response_code(404);
        exit;
    }
    $root = realpath(storage_path());
    $path = realpath(storage_path((string) $photo['file_path']));
    if ($root === false || $path === false
        || !str_starts_with(strtolower($path), strtolower(rtrim($root, '/\\') . DIRECTORY_SEPARATOR))
        || !is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $photo['mime_type']);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="evidence.' . $allowed[$photo['mime_type']] . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
