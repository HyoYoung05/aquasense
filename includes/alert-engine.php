<?php
declare(strict_types=1);

function phase4_alert_definitions(): array
{
    return [
        'HIGH_LEVEL'=>['label'=>'High level','severity'=>'WARNING','sensor'=>'waste_level_percent','message'=>'Grease trap level is high and should be monitored.'],
        'CRITICAL_LEVEL'=>['label'=>'Critical level','severity'=>'CRITICAL','sensor'=>'waste_level_percent','message'=>'Grease trap is nearing maximum capacity. Service is recommended immediately.'],
        'OVERFLOW_WARNING'=>['label'=>'Overflow warning','severity'=>'CRITICAL','sensor'=>'waste_level_percent','message'=>'Grease trap is at critical capacity and may overflow soon.'],
        'OVERFLOW'=>['label'=>'Overflow','severity'=>'CRITICAL','sensor'=>'waste_level_percent','message'=>'Grease trap has reached the configured overflow condition.'],
        'HIGH_TEMPERATURE'=>['label'=>'High temperature','severity'=>'WARNING','sensor'=>'temperature_c','message'=>'Wastewater temperature is above the configured high-temperature threshold.'],
        'EMULSION_WARNING'=>['label'=>'Emulsion warning','severity'=>'CRITICAL','sensor'=>'temperature_c','message'=>'Wastewater temperature is high enough to affect oil-water separation.'],
        'HIGH_TURBIDITY'=>['label'=>'High turbidity','severity'=>'WARNING','sensor'=>'turbidity_ntu','message'=>'Turbidity exceeds the configured threshold.'],
        'ABNORMAL_FLOW'=>['label'=>'Abnormal flow','severity'=>'WARNING','sensor'=>'flow_rate_lpm','message'=>'Flow rate is outside the configured operating range.'],
        'DEVICE_OFFLINE'=>['label'=>'Device offline','severity'=>'WARNING','sensor'=>'last_seen_at','message'=>'The monitoring device has stopped reporting data.'],
    ];
}

function phase4_alert_label(string $type): string
{
    return phase4_alert_definitions()[$type]['label'] ?? ucwords(strtolower(str_replace('_',' ',$type)));
}

function phase4_numeric_settings(PDO $pdo): array
{
    $keys=['overflow_threshold','emulsion_temperature_threshold','high_temperature_threshold','high_turbidity_threshold','flow_rate_min','flow_rate_max','device_offline_timeout_minutes'];
    $in=implode(',',array_fill(0,count($keys),'?'));
    $q=$pdo->prepare("SELECT setting_key,setting_value FROM system_settings WHERE setting_key IN ($in)");$q->execute($keys);$values=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($keys as $key)if(!isset($values[$key])||!is_numeric($values[$key]))throw new RuntimeException('Missing alert setting: '.$key);
    return array_map('floatval',$values);
}

function phase4_upsert_alert(PDO $pdo,int $assignmentId,?int $readingId,string $type,?float $value,?float $threshold,string $at): void
{
    $definition=phase4_alert_definitions()[$type]??null;if(!$definition)throw new InvalidArgumentException('Unsupported alert type.');
    $q=$pdo->prepare("INSERT INTO alerts (device_assignment_id,sensor_reading_id,alert_type,severity,sensor_name,sensor_value,threshold_value,message,status,first_triggered_at,last_triggered_at,trigger_count) VALUES (?,?,?,?,?,?,?,?, 'ACTIVE',?,?,1) ON DUPLICATE KEY UPDATE sensor_reading_id=VALUES(sensor_reading_id),severity=VALUES(severity),sensor_name=VALUES(sensor_name),sensor_value=VALUES(sensor_value),threshold_value=VALUES(threshold_value),message=VALUES(message),last_triggered_at=VALUES(last_triggered_at),trigger_count=trigger_count+1");
    $q->execute([$assignmentId,$readingId,$type,$definition['severity'],$definition['sensor'],$value,$threshold,$definition['message'],$at,$at]);
}

function phase4_resolve_recovered(PDO $pdo,int $assignmentId,array $types,string $at,string $note='Condition recovered automatically.'): int
{
    if(!$types)return 0;$marks=implode(',',array_fill(0,count($types),'?'));
    $q=$pdo->prepare("UPDATE alerts SET status='RESOLVED',resolved_by=NULL,resolved_at=?,resolution_note=? WHERE device_assignment_id=? AND alert_type IN ($marks) AND status IN ('ACTIVE','ACKNOWLEDGED')");
    $q->execute(array_merge([$at,$note,$assignmentId],$types));return $q->rowCount();
}

function phase4_evaluate_telemetry(PDO $pdo,array $assignment,int $readingId,array $reading,string $at): array
{
    $settings=phase4_numeric_settings($pdo);$assignmentId=(int)$assignment['assignment_id'];$active=[];
    $level=(float)$reading['waste_level_percent'];$levelTypes=['HIGH_LEVEL','CRITICAL_LEVEL','OVERFLOW_WARNING','OVERFLOW'];
    if($level >= $settings['overflow_threshold'])$active['OVERFLOW']=$settings['overflow_threshold'];
    elseif($level >= (float)$assignment['critical_threshold']){$active['CRITICAL_LEVEL']=(float)$assignment['critical_threshold'];$active['OVERFLOW_WARNING']=(float)$assignment['critical_threshold'];}
    elseif($level >= (float)$assignment['high_threshold'])$active['HIGH_LEVEL']=(float)$assignment['high_threshold'];
    phase4_resolve_recovered($pdo,$assignmentId,array_values(array_diff($levelTypes,array_keys($active))),$at);
    foreach($active as $type=>$threshold)phase4_upsert_alert($pdo,$assignmentId,$readingId,$type,$level,$threshold,$at);

    $temperature=$reading['temperature_c'];$temperatureTypes=[];
    if($temperature!==null){$temperature=(float)$temperature;if($temperature >= $settings['emulsion_temperature_threshold'])$temperatureTypes['EMULSION_WARNING']=$settings['emulsion_temperature_threshold'];if($temperature >= $settings['high_temperature_threshold'])$temperatureTypes['HIGH_TEMPERATURE']=$settings['high_temperature_threshold'];phase4_resolve_recovered($pdo,$assignmentId,array_values(array_diff(['HIGH_TEMPERATURE','EMULSION_WARNING'],array_keys($temperatureTypes))),$at);foreach($temperatureTypes as $type=>$threshold)phase4_upsert_alert($pdo,$assignmentId,$readingId,$type,$temperature,$threshold,$at);}

    $turbidity=$reading['turbidity_ntu'];$turbidityActive=$turbidity!==null&&(float)$turbidity >= $settings['high_turbidity_threshold'];
    if($turbidity!==null){if($turbidityActive)phase4_upsert_alert($pdo,$assignmentId,$readingId,'HIGH_TURBIDITY',(float)$turbidity,$settings['high_turbidity_threshold'],$at);else phase4_resolve_recovered($pdo,$assignmentId,['HIGH_TURBIDITY'],$at);}
    $flow=$reading['flow_rate_lpm'];$flowActive=$flow!==null&&((float)$flow<$settings['flow_rate_min']||(float)$flow>$settings['flow_rate_max']);
    if($flow!==null){if($flowActive){$threshold=(float)$flow<$settings['flow_rate_min']?$settings['flow_rate_min']:$settings['flow_rate_max'];phase4_upsert_alert($pdo,$assignmentId,$readingId,'ABNORMAL_FLOW',(float)$flow,$threshold,$at);}else phase4_resolve_recovered($pdo,$assignmentId,['ABNORMAL_FLOW'],$at);}
    phase4_resolve_recovered($pdo,$assignmentId,['DEVICE_OFFLINE'],$at,'Telemetry resumed; device recovered automatically.');
    return array_keys($active+$temperatureTypes+($turbidityActive?['HIGH_TURBIDITY'=>0]:[])+($flowActive?['ABNORMAL_FLOW'=>0]:[]));
}

function phase4_check_offline_devices(PDO $pdo): array
{
    $settings=phase4_numeric_settings($pdo);$cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-'.(int)$settings['device_offline_timeout_minutes'].' minutes');$now=gmdate('Y-m-d H:i:s');$created=0;$resolved=0;
    $rows=$pdo->query("SELECT a.id assignment_id,d.last_seen_at FROM device_assignments a JOIN devices d ON d.id=a.device_id JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id WHERE a.ended_at IS NULL AND d.is_active=1 AND g.is_active=1 AND e.is_active=1 AND d.last_seen_at IS NOT NULL")->fetchAll();
    foreach($rows as $row){$offline=strtotime($row['last_seen_at'].' UTC')<$cutoff->getTimestamp();if($offline){phase4_upsert_alert($pdo,(int)$row['assignment_id'],null,'DEVICE_OFFLINE',null,$settings['device_offline_timeout_minutes'],$now);$created++;}else{$resolved+=phase4_resolve_recovered($pdo,(int)$row['assignment_id'],['DEVICE_OFFLINE'],$now,'Device reported within the configured timeout.');}}
    return ['checked'=>count($rows),'offline'=>$created,'resolved'=>$resolved,'cutoff'=>$cutoff->format(DATE_ATOM)];
}

function phase4_acknowledge_alert(int $id,int $userId): void
{
    $q=db()->prepare("UPDATE alerts SET status='ACKNOWLEDGED',acknowledged_by=?,acknowledged_at=UTC_TIMESTAMP() WHERE id=? AND status='ACTIVE'");$q->execute([$userId,$id]);if(!$q->rowCount())throw new InvalidArgumentException('Only an active alert can be acknowledged.');audit('ALERT_ACKNOWLEDGED',$userId,'alerts',$id);
}

function phase4_resolve_alert(int $id,int $userId,string $note): void
{
    $note=trim($note);if(strlen($note)>1000)throw new InvalidArgumentException('Resolution note must be 1000 characters or fewer.');
    $q=db()->prepare("UPDATE alerts SET status='RESOLVED',resolved_by=?,resolved_at=UTC_TIMESTAMP(),resolution_note=? WHERE id=? AND status IN ('ACTIVE','ACKNOWLEDGED')");$q->execute([$userId,$note===''?null:$note,$id]);if(!$q->rowCount())throw new InvalidArgumentException('This alert is already resolved or was not found.');audit('ALERT_RESOLVED',$userId,'alerts',$id);
}

function phase4_validate_settings(array $input): array
{
    $fields=['emulsion_temperature_threshold','high_temperature_threshold','high_turbidity_threshold','flow_rate_min','flow_rate_max','overflow_threshold','device_offline_timeout_minutes'];$data=[];$errors=[];
    foreach($fields as $field){$value=filter_var($input[$field]??null,FILTER_VALIDATE_FLOAT);if($value===false)$errors[]='Enter a numeric value for '.str_replace('_',' ',$field).'.';else$data[$field]=(float)$value;}
    if(!$errors){if($data['emulsion_temperature_threshold']<-20||$data['high_temperature_threshold']>125||$data['emulsion_temperature_threshold']>$data['high_temperature_threshold'])$errors[]='Temperature thresholds must increase from Emulsion to High and remain between -20 and 125 °C.';if($data['high_turbidity_threshold']<=0)$errors[]='Turbidity threshold must be greater than zero.';if($data['flow_rate_min']<0||$data['flow_rate_min']>=$data['flow_rate_max'])$errors[]='Flow thresholds require a non-negative minimum below the maximum.';if($data['overflow_threshold']<=0||$data['overflow_threshold']>100)$errors[]='Overflow threshold must be between 0 and 100.';if($data['device_offline_timeout_minutes']<1||$data['device_offline_timeout_minutes']>1440)$errors[]='Offline timeout must be between 1 and 1440 minutes.';}
    if($errors)throw new InvalidArgumentException(implode(' ',$errors));return $data;
}

function phase4_update_settings(array $input,int $userId): void
{
    $data=phase4_validate_settings($input);$pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('UPDATE system_settings SET setting_value=?,updated_by=? WHERE setting_key=?');foreach($data as $key=>$value)$q->execute([(string)$value,$userId,$key]);audit('THRESHOLDS_UPDATED',$userId,'system_settings',null);audit('DEVICE_OFFLINE_THRESHOLD_CHANGED',$userId,'system_settings',null);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
