<?php
declare(strict_types=1);
require_once __DIR__.'/alert-engine.php';

function phase3_device_state(?array $device,int $freshnessSeconds):string
{
    if(!$device||empty($device['device_id']))return 'NOT CONNECTED';
    if(!(bool)$device['device_active'])return 'INACTIVE';
    if(empty($device['last_seen_at']))return 'NOT CONNECTED';
    return time()-strtotime($device['last_seen_at'].' UTC')<$freshnessSeconds?'ONLINE':'OFFLINE';
}

function phase3_monitoring_latest():array
{
    $pdo=db();$freshness=(int)$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='device_offline_timeout_minutes'")->fetchColumn()*60;
    $rows=$pdo->query("SELECT e.id establishment_id,e.business_name,g.id grease_trap_id,g.trap_code,g.name grease_trap_name,g.is_active trap_active,g.empty_distance_cm,g.full_distance_cm,g.low_threshold,g.medium_threshold,g.high_threshold,g.critical_threshold,a.id assignment_id,d.id device_id,d.device_code,d.name device_name,d.firmware_version,d.is_active device_active,d.last_seen_at,r.id reading_id,r.ultrasonic_distance_cm,r.waste_level_percent,r.device_reported_percent,r.temperature_c,r.turbidity_ntu,r.flow_rate_lpm,r.gas_value,r.level_status,r.is_simulated,r.is_test,r.recorded_at,r.received_at FROM grease_traps g JOIN establishments e ON e.id=g.establishment_id LEFT JOIN device_assignments a ON a.grease_trap_id=g.id AND a.ended_at IS NULL LEFT JOIN devices d ON d.id=a.device_id LEFT JOIN sensor_readings r ON r.id=(SELECT sr.id FROM sensor_readings sr WHERE sr.device_assignment_id=a.id ORDER BY sr.recorded_at DESC,sr.id DESC LIMIT 1) ORDER BY e.business_name,g.name")->fetchAll();
    $alertQuery=$pdo->prepare("SELECT alert_type,severity,status,message,last_triggered_at FROM alerts WHERE device_assignment_id=? AND status IN ('ACTIVE','ACKNOWLEDGED') ORDER BY FIELD(severity,'CRITICAL','WARNING','INFO'),last_triggered_at DESC");
    foreach($rows as &$row){
        $deviceState=phase3_device_state($row,$freshness);
        $sensorState=!(bool)$row['trap_active']?'INACTIVE':(!$row['device_id']?'AWAITING DEVICE':(!$row['reading_id']?'AWAITING TELEMETRY':($deviceState!=='ONLINE'?'OFFLINE':$row['level_status'])));
        foreach(['establishment_id','grease_trap_id','assignment_id','device_id','reading_id'] as $key)$row[$key]=$row[$key]===null?null:(int)$row[$key];
        foreach(['empty_distance_cm','full_distance_cm','low_threshold','medium_threshold','high_threshold','critical_threshold','ultrasonic_distance_cm','waste_level_percent','device_reported_percent','temperature_c','turbidity_ntu','flow_rate_lpm','gas_value'] as $key)$row[$key]=$row[$key]===null?null:(float)$row[$key];
        $row['device_status']=$deviceState;$row['sensor_state']=$sensorState;$row['is_simulated']=(bool)($row['is_simulated']??false);$row['is_test']=(bool)($row['is_test']??false);$row['active_alerts']=[];
        if($row['assignment_id']){$alertQuery->execute([$row['assignment_id']]);foreach($alertQuery->fetchAll() as $alert){$alert['label']=phase4_alert_label($alert['alert_type']);$row['active_alerts'][]=$alert;}}
    }unset($row);
    return ['generated_at'=>gmdate('c'),'freshness_seconds'=>$freshness,'traps'=>$rows];
}

function phase3_history_bounds(string $range,?string $from,?string $to):array
{
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    if($range==='1h')return[$now->modify('-1 hour'),$now->modify('+1 second')];
    if($range==='24h')return[$now->modify('-24 hours'),$now->modify('+1 second')];
    if($range==='7d')return[$now->modify('-7 days'),$now->modify('+1 second')];
    if($range==='30d')return[$now->modify('-30 days'),$now->modify('+1 second')];
    if($range==='custom'){
        $zone=new DateTimeZone('Asia/Manila');
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$from??'',$zone);$end=DateTimeImmutable::createFromFormat('!Y-m-d',$to??'',$zone);
        if(!$start||!$end||$start->format('Y-m-d')!==$from||$end->format('Y-m-d')!==$to||$end<$start||$end->diff($start)->days>31)throw new InvalidArgumentException('Choose a valid custom range of 31 days or less.');
        return[$start->setTimezone(new DateTimeZone('UTC')),$end->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))];
    }
    $start=(new DateTimeImmutable('today',new DateTimeZone('Asia/Manila')))->setTimezone(new DateTimeZone('UTC'));
    return[$start,$now->modify('+1 second')];
}

function phase3_monitoring_history(int $trapId,string $range='today',?string $from=null,?string $to=null,int $page=1,int $limit=50):array
{
    if(!in_array($range,['1h','today','24h','7d','30d','custom'],true))$range='today';
    [$start,$end]=phase3_history_bounds($range,$from,$to);$page=max(1,$page);$limit=max(1,min(100,$limit));$pdo=db();
    $trapQuery=$pdo->prepare('SELECT g.id,g.name,g.trap_code,g.empty_distance_cm,g.full_distance_cm,g.low_threshold,g.medium_threshold,g.high_threshold,g.critical_threshold,e.business_name FROM grease_traps g JOIN establishments e ON e.id=g.establishment_id WHERE g.id=?');$trapQuery->execute([$trapId]);$trap=$trapQuery->fetch();if(!$trap)throw new InvalidArgumentException('Grease trap was not found.');
    $params=[$trapId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')];
    $count=$pdo->prepare('SELECT COUNT(*) FROM sensor_readings r JOIN device_assignments a ON a.id=r.device_assignment_id WHERE a.grease_trap_id=? AND r.recorded_at>=? AND r.recorded_at<?');$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);$offset=($page-1)*$limit;
    $q=$pdo->prepare("SELECT r.id,d.device_code,r.ultrasonic_distance_cm,r.waste_level_percent,r.device_reported_percent,r.temperature_c,r.turbidity_ntu,r.flow_rate_lpm,r.gas_value,r.level_status,r.is_simulated,r.is_test,r.recorded_at,r.received_at FROM sensor_readings r JOIN device_assignments a ON a.id=r.device_assignment_id JOIN devices d ON d.id=a.device_id WHERE a.grease_trap_id=? AND r.recorded_at>=? AND r.recorded_at<? ORDER BY r.recorded_at DESC,r.id DESC LIMIT $limit OFFSET $offset");$q->execute($params);$readings=$q->fetchAll();
    foreach($readings as &$reading){$reading['id']=(int)$reading['id'];foreach(['ultrasonic_distance_cm','waste_level_percent','device_reported_percent','temperature_c','turbidity_ntu','flow_rate_lpm','gas_value'] as $key)$reading[$key]=$reading[$key]===null?null:(float)$reading[$key];$reading['is_simulated']=(bool)$reading['is_simulated'];$reading['is_test']=(bool)$reading['is_test'];}unset($reading);
    return ['trap'=>$trap,'range'=>$range,'from'=>$start->format('c'),'to'=>$end->format('c'),'page'=>$page,'pages'=>$pages,'total'=>$total,'readings'=>$readings];
}
