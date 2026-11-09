<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) exit("Use --allow-local-fixtures\n");
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/telemetry.php';
require_once dirname(__DIR__) . '/includes/mobile-monitoring.php';
if ($config['environment'] !== 'development') throw new RuntimeException('Local fixtures only.');
$pdo = db();
$config['telemetry_min_interval_seconds'] = 0;
$prefix = 'PARTIAL' . strtoupper(bin2hex(random_bytes(4)));
$key = bin2hex(random_bytes(32));
$owner = $site = $trap = $device = $assignment = null;
$checks = 0;
function partial_check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
    echo 'PASS: ' . $message . PHP_EOL;
}
try {
    $role = $pdo->query("SELECT id FROM roles WHERE slug='owner'")->fetchColumn();
    $pdo->prepare('INSERT INTO users(role_id,full_name,email,password_hash) VALUES(?,?,?,?)')->execute([$role,$prefix,$prefix.'@example.test',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $owner = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO establishments(registration_code,business_name,owner_user_id,owner_name,address,registration_date) VALUES(?,?,?,?,?,CURRENT_DATE())')->execute([$prefix,$prefix,$owner,'Fixture','Fixture']);
    $site = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO grease_traps(establishment_id,trap_code,name,capacity_liters,low_threshold,medium_threshold,high_threshold,critical_threshold,empty_distance_cm,full_distance_cm) VALUES(?,?,?,25,20,50,75,90,30,5)')->execute([$site,$prefix,$prefix]);
    $trap = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO devices(device_code,name,device_type,api_key_hash) VALUES(?,?,'ESP32',?)")->execute([$prefix,$prefix,hash('sha256',$key)]);
    $device = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO device_assignments(device_id,grease_trap_id,started_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$device,$trap]);
    $assignment = (int)$pdo->lastInsertId();
    $base = ['device_id'=>$prefix,'grease_trap_id'=>$trap];
    $critical = phase3_ingest_telemetry($pdo,$base+['ultrasonic_distance'=>6.0],$key);
    partial_check($critical['status']==='CRITICAL', 'Valid distance still creates critical level alerts');
    $payload = $base+['temperature'=>30.25,'flow_rate'=>0.0,'flow_pulse_count'=>0,'turbidity_raw'=>1023,'gas_raw'=>1300,'ambient_temperature'=>29.0,'humidity'=>68.0,'aqi'=>2,'tvoc_ppb'=>120,'eco2_ppm'=>510,'waste_level_percent'=>0,'reading_uuid'=>'123e4567-e89b-42d3-a456-426614174018'];
    $result = phase3_ingest_telemetry($pdo,$payload,$key);
    partial_check($result['http_status']===201 && $result['waste_level_percent']===null && $result['ultrasonic_distance']===null && $result['status']==='UNKNOWN', 'Partial telemetry accepted without fabricating level or distance');
    $row = $pdo->query('SELECT * FROM sensor_readings WHERE id='.(int)$result['reading_id'])->fetch();
    partial_check($row['waste_level_percent']===null && $row['device_reported_percent']===null && $row['ultrasonic_distance_cm']===null, 'Missing level stays SQL NULL, including untrusted device percentage');
    partial_check((float)$row['temperature_c']===30.25 && (float)$row['flow_rate_lpm']===0.0 && (int)$row['flow_pulse_count']===0 && (int)$row['turbidity_raw_adc']===1023 && (int)$row['gas_raw_adc']===1300 && (int)$row['ens160_aqi']===2 && (int)$row['tvoc_ppb']===120 && (int)$row['eco2_ppm']===510 && (float)$row['ambient_temperature_c']===29.0 && (float)$row['humidity_percent']===68.0, 'All six sensor modules retain independent available values, including zero flow');
    partial_check((int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE device_assignment_id=$assignment AND alert_type='CRITICAL_LEVEL' AND status='ACTIVE'")->fetchColumn()===1, 'Missing level does not falsely resolve an existing critical alert');
    $monitor = mobile_monitoring($owner)['traps'][0];
    partial_check($monitor['sensor_state']==='LEVEL UNAVAILABLE' && $monitor['reading']['temperature_c']===30.25 && $monitor['reading']['waste_level_percent']===null && $monitor['active_alert']!==null, 'Owner monitoring returns partial data and preserves level alerts');
    partial_check(mobile_dashboard($owner)['establishments'][0]['traps'][0]['reading']['waste_level_percent']===null, 'Dashboard preserves missing level as null');
    $gasRuntime = ['boot_id'=>'11111111-1111-4111-8111-111111111111','state'=>'WARMING_UP','warmup_remaining_ms'=>60000];
    $heartbeat = ['device_id'=>$prefix,'firmware_version'=>'0.8.20','gas_runtime'=>$gasRuntime];
    partial_check(phase3_record_heartbeat($pdo,$heartbeat,$key)['http_status']===200, 'Authenticated Gas runtime heartbeat accepted');
    $gasMonitor = mobile_monitoring($owner)['traps'][0];
    partial_check($gasMonitor['gas_runtime']['state']==='WARMING_UP' && $gasMonitor['gas_runtime']['gas_raw']===null && $gasMonitor['reading']['gas_raw_adc']===1300, 'Warm-up metadata overrides cached Gas without deleting historical telemetry');
    phase3_record_heartbeat($pdo,['device_id'=>$prefix],$key);
    partial_check(mobile_monitoring($owner)['traps'][0]['gas_runtime']['boot_id']===$gasRuntime['boot_id'], 'Legacy heartbeat does not erase runtime');
    $ready = array_replace($gasRuntime,['state'=>'READY','warmup_remaining_ms'=>0,'gas_raw'=>377]);
    phase3_record_heartbeat($pdo,array_replace($heartbeat,['gas_runtime'=>$ready]),$key);
    partial_check(mobile_monitoring($owner)['traps'][0]['gas_runtime']['gas_raw']===377, 'Current Gas value can arrive independently through heartbeat');
    $newBoot = array_replace($gasRuntime,['boot_id'=>'22222222-2222-4222-8222-222222222222']);
    phase3_record_heartbeat($pdo,array_replace($heartbeat,['gas_runtime'=>$newBoot]),$key);
    partial_check(mobile_monitoring($owner)['traps'][0]['gas_runtime']['gas_raw']===null, 'Reset clears current Gas measurement even with old telemetry retained');
    try { phase3_record_heartbeat($pdo,$heartbeat,str_repeat('0',64)); throw new RuntimeException('Invalid key accepted'); }
    catch (TelemetryException $e) { partial_check($e->httpStatus===401, 'Invalid device key cannot replace Gas runtime'); }
    $duplicate = phase3_ingest_telemetry($pdo,$payload,$key);
    partial_check($duplicate['duplicate']===true, 'Partial reading retry remains idempotent');
    $zero = phase3_ingest_telemetry($pdo,$base+['flow_rate'=>0.0],$key);
    partial_check($zero['http_status']===201, 'Flow-only zero reading is valid telemetry');
    foreach (['temperature'=>30.25,'turbidity_raw'=>0,'flow_pulse_count'=>0,'gas_raw'=>0,'ambient_temperature'=>29.0,'humidity'=>68.0,'aqi'=>2,'tvoc_ppb'=>0,'eco2_ppm'=>510] as $field=>$value) {
        $single = phase3_ingest_telemetry($pdo,$base+[$field=>$value],$key);
        partial_check($single['http_status']===201 && $single['waste_level_percent']===null, $field.' uploads independently without a level reading');
    }
    try { phase3_ingest_telemetry($pdo,$base,$key); throw new RuntimeException('Empty payload accepted'); }
    catch (TelemetryException $e) { partial_check($e->httpStatus===422, 'Empty measurement payload rejected'); }
    foreach (['ultrasonic_distance'=>-1,'temperature'=>130,'humidity'=>101,'gas_raw'=>4096] as $field=>$value) {
        try { phase3_ingest_telemetry($pdo,$base+[$field=>$value],$key); throw new RuntimeException('Invalid measurement accepted'); }
        catch (TelemetryException $e) { partial_check($e->httpStatus===422, 'Invalid '.$field.' rejected'); }
    }
    $high=phase3_ingest_telemetry($pdo,$base+['ultrasonic_distance'=>10],$key);
    partial_check($high['status']==='HIGH', 'Recovered ultrasonic sensor still triggers HIGH threshold');
    $normal=phase3_ingest_telemetry($pdo,$base+['ultrasonic_distance'=>29],$key);
    partial_check($normal['status']==='NORMAL' && (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE device_assignment_id=$assignment AND alert_type IN ('HIGH_LEVEL','CRITICAL_LEVEL','OVERFLOW_WARNING','OVERFLOW') AND status<>'RESOLVED'")->fetchColumn()===0, 'Valid normal level resolves level alerts');
    echo "$checks partial telemetry checks passed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($device) $pdo->prepare('DELETE FROM compliance_ledger WHERE device_id=?')->execute([$device]);
    if ($assignment) {
        $pdo->prepare('DELETE FROM alerts WHERE device_assignment_id=?')->execute([$assignment]);
        $pdo->prepare('DELETE FROM sensor_readings WHERE device_assignment_id=?')->execute([$assignment]);
        $pdo->prepare('DELETE FROM device_assignments WHERE id=?')->execute([$assignment]);
    }
    if ($device) $pdo->prepare('DELETE FROM devices WHERE id=?')->execute([$device]);
    if ($trap) $pdo->prepare('DELETE FROM grease_traps WHERE id=?')->execute([$trap]);
    if ($site) $pdo->prepare('DELETE FROM establishments WHERE id=?')->execute([$site]);
    if ($owner) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$owner]);
}
