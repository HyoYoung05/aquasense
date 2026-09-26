<?php
declare(strict_types=1);

final class Phase2ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Phase 2 validation failed.');
    }
}

function phase2_text(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

function phase2_optional(array $input, string $key): ?string
{
    $value = phase2_text($input, $key);
    return $value === '' ? null : $value;
}

function phase2_date(?string $value, string $label, array &$errors): ?string
{
    if ($value === null || $value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) $errors[] = $label . ' must be a valid date.';
    return $value;
}

function phase2_required(string $value, string $label, int $max, array &$errors): void
{
    if ($value === '') $errors[] = $label . ' is required.';
    elseif (mb_strlen($value) > $max) $errors[] = $label . ' must be ' . $max . ' characters or fewer.';
}

function phase2_validate_contact(?string $contact, array &$errors): void
{
    if ($contact !== null && (!preg_match('/^[0-9+().\-\s]{7,30}$/D', $contact))) {
        $errors[] = 'Contact number may contain digits, spaces, +, parentheses, periods, and hyphens.';
    }
}

function phase2_validate_establishment(array $input): array
{
    $data = [
        'registration_code' => strtoupper(phase2_text($input, 'registration_code')),
        'business_name' => phase2_text($input, 'business_name'),
        'owner_name' => phase2_text($input, 'owner_name'),
        'address' => phase2_text($input, 'address'),
        'contact_number' => phase2_optional($input, 'contact_number'),
        'email' => phase2_optional($input, 'email'),
        'notes' => phase2_optional($input, 'notes'),
        'registration_date' => phase2_text($input, 'registration_date'),
    ];
    $errors = [];
    phase2_required($data['registration_code'], 'Registration code', 40, $errors);
    if ($data['registration_code'] !== '' && !preg_match('/^[A-Z0-9_-]+$/D', $data['registration_code'])) $errors[] = 'Registration code may contain letters, numbers, hyphens, and underscores only.';
    phase2_required($data['business_name'], 'Business name', 190, $errors);
    phase2_required($data['owner_name'], 'Owner name', 150, $errors);
    phase2_required($data['address'], 'Address', 500, $errors);
    if ($data['email'] !== null && (mb_strlen($data['email']) > 190 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) $errors[] = 'Enter a valid email address.';
    if ($data['notes'] !== null && mb_strlen($data['notes']) > 4000) $errors[] = 'Notes must be 4,000 characters or fewer.';
    phase2_validate_contact($data['contact_number'], $errors);
    phase2_date($data['registration_date'], 'Registration date', $errors);
    if ($data['registration_date'] === '') $errors[] = 'Registration date is required.';
    if ($errors) throw new Phase2ValidationException($errors);
    return $data;
}

function phase2_create_establishment(array $input, int $actorId): int
{
    $data = phase2_validate_establishment($input);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('INSERT INTO establishments
            (registration_code,business_name,owner_name,address,contact_number,email,notes,registration_date,created_by)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $q->execute([$data['registration_code'],$data['business_name'],$data['owner_name'],$data['address'],
            $data['contact_number'],$data['email'],$data['notes'],$data['registration_date'],$actorId]);
        $id = (int) $pdo->lastInsertId();
        audit('ESTABLISHMENT_CREATED', $actorId, 'establishments', $id);
        $pdo->commit();
        return $id;
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error->getCode() === '23000') throw new Phase2ValidationException(['Registration code is already in use.']);
        throw $error;
    }
}

function phase2_update_establishment(int $id, array $input, int $actorId): void
{
    $data = phase2_validate_establishment($input);
    $q = db()->prepare('UPDATE establishments SET registration_code=?,business_name=?,owner_name=?,address=?,contact_number=?,email=?,notes=?,registration_date=? WHERE id=?');
    try {
        $q->execute([$data['registration_code'],$data['business_name'],$data['owner_name'],$data['address'],
            $data['contact_number'],$data['email'],$data['notes'],$data['registration_date'],$id]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') throw new Phase2ValidationException(['Registration code is already in use.']);
        throw $error;
    }
    if ($q->rowCount() === 0 && !(bool) db()->query('SELECT EXISTS(SELECT 1 FROM establishments WHERE id=' . (int)$id . ')')->fetchColumn()) {
        throw new Phase2ValidationException(['Establishment was not found.']);
    }
    audit('ESTABLISHMENT_UPDATED', $actorId, 'establishments', $id);
}

function phase2_set_establishment_active(int $id, bool $active, int $actorId): void
{
    $q = db()->prepare('UPDATE establishments SET is_active=? WHERE id=?');
    $q->execute([$active ? 1 : 0, $id]);
    if ($q->rowCount() === 0) throw new Phase2ValidationException(['Establishment was not found or already has that status.']);
    audit($active ? 'ESTABLISHMENT_ACTIVATED' : 'ESTABLISHMENT_DEACTIVATED', $actorId, 'establishments', $id);
}

function phase2_validate_trap(array $input): array
{
    $data = [
        'establishment_id' => filter_var($input['establishment_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null,
        'trap_code' => strtoupper(phase2_text($input, 'trap_code')),
        'name' => phase2_text($input, 'name'),
        'capacity_liters' => filter_var($input['capacity_liters'] ?? null, FILTER_VALIDATE_FLOAT),
        'low_threshold' => filter_var($input['low_threshold'] ?? null, FILTER_VALIDATE_FLOAT),
        'medium_threshold' => filter_var($input['medium_threshold'] ?? null, FILTER_VALIDATE_FLOAT),
        'high_threshold' => filter_var($input['high_threshold'] ?? null, FILTER_VALIDATE_FLOAT),
        'critical_threshold' => filter_var($input['critical_threshold'] ?? null, FILTER_VALIDATE_FLOAT),
        'installation_date' => phase2_optional($input, 'installation_date'),
        'last_service_date' => phase2_optional($input, 'last_service_date'),
    ];
    $errors = [];
    if (!$data['establishment_id']) $errors[] = 'Select an establishment.';
    phase2_required($data['trap_code'], 'Trap code', 40, $errors);
    if ($data['trap_code'] !== '' && !preg_match('/^[A-Z0-9_-]+$/D', $data['trap_code'])) $errors[] = 'Trap code may contain letters, numbers, hyphens, and underscores only.';
    phase2_required($data['name'], 'Trap name', 100, $errors);
    if ($data['capacity_liters'] === false || $data['capacity_liters'] <= 0 || $data['capacity_liters'] > 1000000) $errors[] = 'Capacity must be greater than zero and shown in liters.';
    $thresholds = [$data['low_threshold'],$data['medium_threshold'],$data['high_threshold'],$data['critical_threshold']];
    if (in_array(false, $thresholds, true) || !($thresholds[0] >= 0 && $thresholds[0] < $thresholds[1] && $thresholds[1] < $thresholds[2] && $thresholds[2] < $thresholds[3] && $thresholds[3] <= 100)) {
        $errors[] = 'Thresholds must follow low < medium < high < critical, between 0 and 100.';
    }
    phase2_date($data['installation_date'], 'Installation date', $errors);
    phase2_date($data['last_service_date'], 'Last service date', $errors);
    if ($data['installation_date'] && $data['last_service_date'] && $data['last_service_date'] < $data['installation_date']) $errors[] = 'Last service date cannot be before installation date.';
    if ($data['establishment_id']) {
        $q = db()->prepare('SELECT COUNT(*) FROM establishments WHERE id=?'); $q->execute([$data['establishment_id']]);
        if (!(int)$q->fetchColumn()) $errors[] = 'Selected establishment was not found.';
    }
    if ($errors) throw new Phase2ValidationException($errors);
    return $data;
}

function phase2_create_trap(array $input, int $actorId): int
{
    $d = phase2_validate_trap($input);
    try {
        $q=db()->prepare('INSERT INTO grease_traps (establishment_id,trap_code,name,capacity_liters,low_threshold,medium_threshold,high_threshold,critical_threshold,installation_date,last_service_date) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$d['establishment_id'],$d['trap_code'],$d['name'],$d['capacity_liters'],$d['low_threshold'],$d['medium_threshold'],$d['high_threshold'],$d['critical_threshold'],$d['installation_date'],$d['last_service_date']]);
    } catch (PDOException $error) {
        if ($error->getCode()==='23000') throw new Phase2ValidationException(['Trap code is already in use.']);
        throw $error;
    }
    $id=(int)db()->lastInsertId(); audit('GREASE_TRAP_CREATED',$actorId,'grease_traps',$id); return $id;
}

function phase2_update_trap(int $id, array $input, int $actorId): void
{
    $d=phase2_validate_trap($input);
    $pdo=db();
    $current=$pdo->prepare('SELECT establishment_id,
        (SELECT COUNT(*) FROM device_assignments WHERE grease_trap_id=grease_traps.id) AS assignment_count
        FROM grease_traps WHERE id=?');
    $current->execute([$id]);
    $existing=$current->fetch();
    if(!$existing) throw new Phase2ValidationException(['Grease trap was not found.']);
    if((int)$existing['establishment_id']!==(int)$d['establishment_id'] && (int)$existing['assignment_count']>0) {
        throw new Phase2ValidationException(['A grease trap with device assignment history cannot be moved to another establishment.']);
    }
    try {
        $q=$pdo->prepare('UPDATE grease_traps SET establishment_id=?,trap_code=?,name=?,capacity_liters=?,low_threshold=?,medium_threshold=?,high_threshold=?,critical_threshold=?,installation_date=?,last_service_date=? WHERE id=?');
        $q->execute([$d['establishment_id'],$d['trap_code'],$d['name'],$d['capacity_liters'],$d['low_threshold'],$d['medium_threshold'],$d['high_threshold'],$d['critical_threshold'],$d['installation_date'],$d['last_service_date'],$id]);
    } catch (PDOException $error) {
        if ($error->getCode()==='23000') throw new Phase2ValidationException(['Trap code is already in use.']);
        throw $error;
    }
    audit('GREASE_TRAP_UPDATED',$actorId,'grease_traps',$id);
}

function phase2_set_trap_active(int $id, bool $active, int $actorId): void
{
    $q=db()->prepare('UPDATE grease_traps SET is_active=? WHERE id=?'); $q->execute([$active?1:0,$id]);
    if(!$q->rowCount()) throw new Phase2ValidationException(['Grease trap was not found or already has that status.']);
    audit($active?'GREASE_TRAP_ACTIVATED':'GREASE_TRAP_DEACTIVATED',$actorId,'grease_traps',$id);
}

function phase2_validate_device(array $input): array
{
    $data=[
        'device_code'=>strtoupper(phase2_text($input,'device_code')),
        'name'=>phase2_text($input,'name'),
        'device_type'=>phase2_text($input,'device_type'),
        'firmware_version'=>phase2_optional($input,'firmware_version'),
        'installation_date'=>phase2_optional($input,'installation_date'),
        'establishment_id'=>filter_var($input['establishment_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null,
        'grease_trap_id'=>filter_var($input['grease_trap_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null,
    ];
    $errors=[];
    phase2_required($data['device_code'],'Device code',60,$errors);
    if($data['device_code']!==''&&!preg_match('/^[A-Z0-9_-]+$/D',$data['device_code'])) $errors[]='Device code may contain letters, numbers, hyphens, and underscores only.';
    phase2_required($data['name'],'Device name',100,$errors);
    phase2_required($data['device_type'],'Device type',60,$errors);
    if($data['firmware_version']!==null&&mb_strlen($data['firmware_version'])>40) $errors[]='Firmware version must be 40 characters or fewer.';
    phase2_date($data['installation_date'],'Installation date',$errors);
    if(($data['establishment_id']===null)!==($data['grease_trap_id']===null)) $errors[]='Select both an establishment and one of its grease traps, or leave both unassigned.';
    if($data['grease_trap_id']) {
        $q=db()->prepare('SELECT COUNT(*) FROM grease_traps WHERE id=? AND establishment_id=?');
        $q->execute([$data['grease_trap_id'],$data['establishment_id']]);
        if(!(int)$q->fetchColumn()) $errors[]='The selected grease trap does not belong to the selected establishment.';
    }
    if($errors) throw new Phase2ValidationException($errors);
    return $data;
}

function phase2_change_assignment(PDO $pdo, int $deviceId, ?int $trapId, int $actorId): bool
{
    $q=$pdo->prepare('SELECT id,grease_trap_id FROM device_assignments WHERE device_id=? AND ended_at IS NULL FOR UPDATE');
    $q->execute([$deviceId]); $current=$q->fetch();
    if($current && (int)$current['grease_trap_id']===$trapId) return false;
    if($trapId!==null) {
        $q=$pdo->prepare('SELECT a.device_id FROM device_assignments a WHERE a.grease_trap_id=? AND a.ended_at IS NULL FOR UPDATE');
        $q->execute([$trapId]); $occupied=$q->fetchColumn();
        if($occupied!==false && (int)$occupied!==$deviceId) throw new Phase2ValidationException(['That grease trap already has an assigned device.']);
    }
    if($current) $pdo->prepare('UPDATE device_assignments SET ended_at=UTC_TIMESTAMP() WHERE id=?')->execute([$current['id']]);
    if($trapId!==null) $pdo->prepare('INSERT INTO device_assignments (device_id,grease_trap_id,started_at) VALUES (?,?,UTC_TIMESTAMP())')->execute([$deviceId,$trapId]);
    audit('DEVICE_ASSIGNMENT_CHANGED',$actorId,'devices',$deviceId);
    return true;
}

function phase2_create_device(array $input,int $actorId): int
{
    $d=phase2_validate_device($input); $pdo=db(); $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('INSERT INTO devices (device_code,name,device_type,firmware_version,installation_date) VALUES (?,?,?,?,?)');
        $q->execute([$d['device_code'],$d['name'],$d['device_type'],$d['firmware_version'],$d['installation_date']]);
        $id=(int)$pdo->lastInsertId();
        if($d['grease_trap_id']) phase2_change_assignment($pdo,$id,$d['grease_trap_id'],$actorId);
        audit('DEVICE_REGISTERED',$actorId,'devices',$id); $pdo->commit(); return $id;
    } catch(PDOException $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        if($error->getCode()==='23000')throw new Phase2ValidationException(['Device code is already in use.']); throw $error;
    } catch(Throwable $error) { if($pdo->inTransaction())$pdo->rollBack(); throw $error; }
}

function phase2_update_device(int $id,array $input,int $actorId): void
{
    $d=phase2_validate_device($input); $pdo=db(); $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('UPDATE devices SET device_code=?,name=?,device_type=?,firmware_version=?,installation_date=? WHERE id=?');
        $q->execute([$d['device_code'],$d['name'],$d['device_type'],$d['firmware_version'],$d['installation_date'],$id]);
        phase2_change_assignment($pdo,$id,$d['grease_trap_id'],$actorId);
        audit('DEVICE_UPDATED',$actorId,'devices',$id); $pdo->commit();
    } catch(PDOException $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        if($error->getCode()==='23000')throw new Phase2ValidationException(['Device code is already in use.']); throw $error;
    } catch(Throwable $error) { if($pdo->inTransaction())$pdo->rollBack(); throw $error; }
}

function phase2_set_device_active(int $id,bool $active,int $actorId): void
{
    $q=db()->prepare('UPDATE devices SET is_active=? WHERE id=?');$q->execute([$active?1:0,$id]);
    if(!$q->rowCount())throw new Phase2ValidationException(['Device was not found or already has that status.']);
    audit($active?'DEVICE_ACTIVATED':'DEVICE_DEACTIVATED',$actorId,'devices',$id);
}

function phase2_device_status(array $device, int $freshnessSeconds): string
{
    if(!(bool)$device['is_active'])return 'INACTIVE';
    if(empty($device['last_seen_at']))return 'NOT CONNECTED';
    return time()-strtotime($device['last_seen_at'].' UTC')<$freshnessSeconds?'ONLINE':'OFFLINE';
}
