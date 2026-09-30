<?php
declare(strict_types=1);
require_once __DIR__.'/alert-engine.php';

final class TelemetryException extends RuntimeException
{
    public function __construct(public readonly int $httpStatus, public readonly string $category, string $message)
    {
        parent::__construct($message);
    }
}

function phase3_json_number(mixed $value, string $label, float $minimum, float $maximum, bool $required = false): ?float
{
    if ($value === null && !$required) return null;
    if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
        throw new TelemetryException(422, 'invalid_sensor_value', $label . ' must be a JSON number.');
    }
    $number = (float)$value;
    if ($number < $minimum || $number > $maximum) {
        throw new TelemetryException(422, 'sensor_out_of_range', $label . ' is outside the accepted range.');
    }
    return $number;
}

function phase3_calculate_level(float $distance, array $trap, float $overflowThreshold): array
{
    $empty = isset($trap['empty_distance_cm']) ? (float)$trap['empty_distance_cm'] : 0.0;
    $full = isset($trap['full_distance_cm']) ? (float)$trap['full_distance_cm'] : 0.0;
    if ($full < 2 || $empty <= $full || $empty > 400) {
        throw new TelemetryException(409, 'invalid_calibration', 'Grease trap ultrasonic calibration is incomplete or invalid.');
    }
    $percent = round(max(0, min(100, (($empty - $distance) / ($empty - $full)) * 100)), 2);
    if ($percent >= $overflowThreshold) $status = 'OVERFLOW';
    elseif ($percent >= (float)$trap['critical_threshold']) $status = 'CRITICAL';
    elseif ($percent >= (float)$trap['high_threshold']) $status = 'HIGH';
    elseif ($percent >= (float)$trap['medium_threshold']) $status = 'MEDIUM';
    elseif ($percent >= (float)$trap['low_threshold']) $status = 'LOW';
    else $status = 'NORMAL';
    return ['waste_level_percent'=>$percent,'status'=>$status];
}

function phase3_recorded_at(mixed $value, DateTimeImmutable $received, array $config): string
{
    if ($value === null) return $received->format('Y-m-d H:i:s');
    if (!is_string($value) || strlen($value) > 40 || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D',$value)) {
        throw new TelemetryException(422, 'invalid_timestamp', 'recorded_at must be an ISO 8601 timestamp.');
    }
    try { $recorded = new DateTimeImmutable($value); }
    catch (Throwable) { throw new TelemetryException(422, 'invalid_timestamp', 'recorded_at must be an ISO 8601 timestamp.'); }
    $recorded = $recorded->setTimezone(new DateTimeZone('UTC'));
    $delta = $recorded->getTimestamp() - $received->getTimestamp();
    if ($delta > $config['telemetry_future_skew_seconds'] || $delta < -$config['telemetry_max_past_seconds']) {
        throw new TelemetryException(422, 'untrusted_timestamp', 'recorded_at is outside the accepted server time window.');
    }
    return $recorded->format('Y-m-d H:i:s');
}

function phase3_telemetry_payload(array $body, array $config, DateTimeImmutable $received): array
{
    $allowed=['device_id','grease_trap_id','ultrasonic_distance','waste_level_percent','status','temperature','temperature_c','turbidity','flow_rate','gas_value','recorded_at','reading_uuid','sequence_number'];
    if(array_is_list($body) || array_diff(array_keys($body),$allowed)) throw new TelemetryException(422,'unknown_field','The telemetry payload contains an unsupported field.');
    if(!isset($body['device_id']) || !is_string($body['device_id']) || !preg_match('/^[A-Za-z0-9_-]{1,60}$/D',$body['device_id'])) throw new TelemetryException(422,'invalid_device_id','device_id is required and must be a registered device code.');
    if(!isset($body['grease_trap_id']) || !is_int($body['grease_trap_id']) || $body['grease_trap_id']<1) throw new TelemetryException(422,'invalid_trap_id','grease_trap_id must be a positive integer.');
    if(array_key_exists('temperature',$body) && array_key_exists('temperature_c',$body)) throw new TelemetryException(422,'duplicate_temperature','Send temperature or temperature_c, not both.');
    $uuid=$body['reading_uuid']??null;
    if($uuid!==null && (!is_string($uuid)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$uuid))) throw new TelemetryException(422,'invalid_reading_uuid','reading_uuid must be a standard UUID.');
    $sequence=$body['sequence_number']??null;
    if($sequence!==null && (!is_int($sequence)||$sequence<0)) throw new TelemetryException(422,'invalid_sequence','sequence_number must be a non-negative integer.');
    if(isset($body['status']) && (!is_string($body['status']) || !in_array($body['status'],['NORMAL','LOW','MEDIUM','HIGH','WARNING','CRITICAL','OVERFLOW'],true))) throw new TelemetryException(422,'invalid_device_status','status is not recognized.');
    return [
        'device_id'=>$body['device_id'],'grease_trap_id'=>$body['grease_trap_id'],
        'ultrasonic_distance'=>phase3_json_number($body['ultrasonic_distance']??null,'ultrasonic_distance',$config['telemetry_distance_min_cm'],$config['telemetry_distance_max_cm'],true),
        'device_reported_percent'=>phase3_json_number($body['waste_level_percent']??null,'waste_level_percent',0,100),
        'temperature_c'=>phase3_json_number($body['temperature']??$body['temperature_c']??null,'temperature',-40,125),
        'turbidity_ntu'=>phase3_json_number($body['turbidity']??null,'turbidity',0,100000),
        'flow_rate_lpm'=>phase3_json_number($body['flow_rate']??null,'flow_rate',0,10000),
        'gas_value'=>phase3_json_number($body['gas_value']??null,'gas_value',0,1000000000),
        'recorded_at'=>phase3_recorded_at($body['recorded_at']??null,$received,$config),
        'reading_uuid'=>$uuid===null?null:strtolower($uuid),'sequence_number'=>$sequence,
    ];
}

function phase3_ingest_telemetry(PDO $pdo, array $body, string $deviceKey, bool $simulated = false): array
{
    global $config;
    if(!preg_match('/^[a-f0-9]{64}$/iD',$deviceKey)) throw new TelemetryException(401,'missing_or_invalid_key','A valid device credential is required.');
    $received=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $data=phase3_telemetry_payload($body,$config,$received);
    $canonical=$body;ksort($canonical);$payloadHash=hash('sha256',json_encode($canonical,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT id,device_code,device_type,api_key_hash,is_active,last_seen_at FROM devices WHERE device_code=? FOR UPDATE');$q->execute([$data['device_id']]);$device=$q->fetch();
        if(!$device) throw new TelemetryException(404,'unknown_device','Unknown device.');
        if(!(bool)$device['is_active']) throw new TelemetryException(403,'inactive_device','Device access is inactive.');
        if(!$device['api_key_hash']||!hash_equals((string)$device['api_key_hash'],hash('sha256',$deviceKey))) throw new TelemetryException(401,'invalid_key','A valid device credential is required.');
        $q=$pdo->prepare('SELECT a.id assignment_id,a.grease_trap_id assigned_trap_id,g.*,e.is_active establishment_active FROM device_assignments a JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id WHERE a.device_id=? AND a.ended_at IS NULL FOR UPDATE');$q->execute([$device['id']]);$assignment=$q->fetch();
        $trapExists=$pdo->prepare('SELECT COUNT(*) FROM grease_traps WHERE id=?');$trapExists->execute([$data['grease_trap_id']]);
        if(!(int)$trapExists->fetchColumn()) throw new TelemetryException(404,'unknown_trap','Unknown grease trap.');
        if(!$assignment || (int)$assignment['assigned_trap_id']!==$data['grease_trap_id']) throw new TelemetryException(403,'assignment_mismatch','Invalid grease trap assignment.');
        if(!(bool)$assignment['is_active']||!(bool)$assignment['establishment_active']) throw new TelemetryException(403,'inactive_assignment','The assigned establishment or grease trap is inactive.');
        if($data['reading_uuid']!==null||$data['sequence_number']!==null){
            $conditions=[];$parameters=[$assignment['assignment_id']];
            if($data['reading_uuid']!==null){$conditions[]='reading_uuid=?';$parameters[]=$data['reading_uuid'];}
            if($data['sequence_number']!==null){$conditions[]='sequence_number=?';$parameters[]=$data['sequence_number'];}
            $q=$pdo->prepare('SELECT id,payload_hash,received_at FROM sensor_readings WHERE device_assignment_id=? AND ('.implode(' OR ',$conditions).') LIMIT 1');$q->execute($parameters);$duplicate=$q->fetch();
            if($duplicate){if($duplicate['payload_hash']!==null&&!hash_equals($duplicate['payload_hash'],$payloadHash)) throw new TelemetryException(409,'idempotency_conflict','The reading identifier was already used for different telemetry.');$pdo->commit();$duplicateTime=new DateTimeImmutable($duplicate['received_at'],new DateTimeZone('UTC'));return ['http_status'=>200,'duplicate'=>true,'reading_id'=>(int)$duplicate['id'],'device_id'=>$device['device_code'],'grease_trap_id'=>$data['grease_trap_id'],'received_at'=>$duplicateTime->format(DATE_ATOM)];}
        }
        if($device['last_seen_at']!==null && $received->getTimestamp()-strtotime($device['last_seen_at'].' UTC')<$config['telemetry_min_interval_seconds']) throw new TelemetryException(429,'rate_limited','Wait before the next reading.');
        $overflow=$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='overflow_threshold'")->fetchColumn();if($overflow===false||!is_numeric($overflow))throw new RuntimeException('Missing overflow threshold.');
        $level=phase3_calculate_level($data['ultrasonic_distance'],$assignment,(float)$overflow);
        $q=$pdo->prepare('INSERT INTO sensor_readings (device_assignment_id,ultrasonic_distance_cm,waste_level_percent,device_reported_percent,temperature_c,turbidity_ntu,flow_rate_lpm,gas_value,level_status,is_simulated,is_test,reading_uuid,sequence_number,payload_hash,recorded_at,received_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$assignment['assignment_id'],round($data['ultrasonic_distance'],2),$level['waste_level_percent'],$data['device_reported_percent'],$data['temperature_c'],$data['turbidity_ntu'],$data['flow_rate_lpm'],$data['gas_value'],$level['status'],$simulated?1:0,str_contains((string)$device['device_type'],'TEST')?1:0,$data['reading_uuid'],$data['sequence_number'],$payloadHash,$data['recorded_at'],$received->format('Y-m-d H:i:s')]);
        $id=(int)$pdo->lastInsertId();$at=$received->format('Y-m-d H:i:s');$pdo->prepare('UPDATE devices SET last_seen_at=? WHERE id=?')->execute([$at,$device['id']]);
        $alertTypes=phase4_evaluate_telemetry($pdo,$assignment,$id,['waste_level_percent'=>$level['waste_level_percent'],'temperature_c'=>$data['temperature_c'],'turbidity_ntu'=>$data['turbidity_ntu'],'flow_rate_lpm'=>$data['flow_rate_lpm']],$at);$pdo->commit();
        return ['http_status'=>201,'duplicate'=>false,'reading_id'=>$id,'device_id'=>$device['device_code'],'grease_trap_id'=>$data['grease_trap_id'],'ultrasonic_distance'=>round($data['ultrasonic_distance'],2),'waste_level_percent'=>$level['waste_level_percent'],'status'=>$level['status'],'active_alert_types'=>$alertTypes,'received_at'=>$received->format(DATE_ATOM)];
    } catch(Throwable $error) {if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function phase3_rotate_device_key(int $deviceId,int $actorId): string
{
    $key=bin2hex(random_bytes(32));$q=db()->prepare('UPDATE devices SET api_key_hash=? WHERE id=?');$q->execute([hash('sha256',$key),$deviceId]);if(!$q->rowCount())throw new Phase2ValidationException(['Device was not found.']);audit('DEVICE_CREDENTIAL_ROTATED',$actorId,'devices',$deviceId);return $key;
}

function phase3_simulator_available(string $environment): bool{return $environment==='development';}
