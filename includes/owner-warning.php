<?php
declare(strict_types=1);
require_once __DIR__.'/alert-engine.php';

function owner_warning_request(array $body): array
{
    if (array_diff(array_keys($body), ['grease_trap_id','severity'])) throw new InvalidArgumentException('Unsupported warning fields.');
    $id = $body['grease_trap_id'] ?? null;
    $severity = $body['severity'] ?? null;
    if (!is_int($id) || $id < 1 || !in_array($severity, ['WARNING','CRITICAL'], true)) {
        throw new InvalidArgumentException('Select your assigned grease trap and a warning level.');
    }
    return [$id, $severity, $severity === 'CRITICAL' ? 'MANUAL_CRITICAL' : 'MANUAL_WARNING'];
}

function owner_warning_create(PDO $pdo, int $ownerId, array $body): array
{
    [$trapId, $severity, $type] = owner_warning_request($body);
    $pdo->beginTransaction();
    try {
        // Serialize button presses per assignment and authorize ownership inside
        // the same transaction that creates the alert.
        $q = $pdo->prepare("SELECT a.id,a.device_id,g.establishment_id FROM device_assignments a
            JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id
            JOIN devices d ON d.id=a.device_id
            WHERE g.id=? AND e.owner_user_id=? AND a.ended_at IS NULL
            AND e.is_active=1 AND g.is_active=1 AND d.is_active=1 AND d.device_type<>'SIMULATED'
            FOR UPDATE");
        $q->execute([$trapId,$ownerId]); $assignment=$q->fetch();
        if (!$assignment) throw new OutOfBoundsException('No active device is assigned to this grease trap for your account.');
        $q=$pdo->prepare("SELECT id FROM alerts WHERE device_assignment_id=? AND alert_type=? AND status IN ('ACTIVE','ACKNOWLEDGED') LIMIT 1");
        $q->execute([$assignment['id'],$type]); $existing=$q->fetchColumn();
        if ($existing) {
            $pdo->commit();
            return ['id'=>(int)$existing,'already_active'=>true,'severity'=>$severity,'grease_trap_id'=>$trapId];
        }
        $q=$pdo->prepare("SELECT id FROM alerts WHERE device_assignment_id=? AND alert_type IN ('MANUAL_WARNING','MANUAL_CRITICAL') AND created_at>UTC_TIMESTAMP()-INTERVAL 10 SECOND LIMIT 1");
        $q->execute([$assignment['id']]);
        if ($q->fetchColumn()) throw new LengthException('Please wait 10 seconds before sending another warning.');
        $definition=phase4_alert_definitions()[$type];
        $q=$pdo->prepare("INSERT INTO alerts (device_assignment_id,sensor_reading_id,alert_type,severity,sensor_name,sensor_value,threshold_value,message,status,first_triggered_at,last_triggered_at,trigger_count)
            VALUES (?,NULL,?,?, 'owner_report',NULL,NULL,?,'ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)");
        $q->execute([$assignment['id'],$type,$severity,$definition['message']]);
        $id=(int)$pdo->lastInsertId();
        audit('OWNER_WARNING_TRIGGERED',$ownerId,'alerts',$id);
        compliance_append($pdo,'ALERT_TRIGGERED',(int)$assignment['establishment_id'],'alerts',$id,$definition['message'],$ownerId,$trapId,(int)$assignment['device_id'],'ALERT_TRIGGERED:'.$id);
        $pdo->commit();
        return ['id'=>$id,'already_active'=>false,'severity'=>$severity,'grease_trap_id'=>$trapId];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
