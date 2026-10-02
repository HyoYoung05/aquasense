<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    fwrite(STDERR, "Usage: php tests/performance.php --allow-local-fixtures\nCreates and removes 12,000 temporary history rows.\n");
    exit(1);
}
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/reports.php';
if ($config['environment'] !== 'development') throw new RuntimeException('Performance fixtures require development mode.');
$pdo=db();$checks=0;$ids=[];$timings=[];
function perf_check(bool $ok,string $label):void{global$checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo 'PASS: '.$label.PHP_EOL;}
function measured(string $name,callable $work):mixed{global$timings;$start=microtime(true);$result=$work();$timings[$name]=microtime(true)-$start;return$result;}
try{
    $adminRole=(int)$pdo->query("SELECT id FROM roles WHERE slug='administrator'")->fetchColumn();
    $pdo->prepare('INSERT INTO users(role_id,full_name,email,password_hash)VALUES(?,?,?,?)')->execute([$adminRole,'Performance Test','performance-'.bin2hex(random_bytes(5)).'@example.test',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$ids['user']=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO establishments(registration_code,business_name,owner_name,address,registration_date,created_by)VALUES(?,?,?,?,CURRENT_DATE(),?)')->execute(['PERF-'.strtoupper(bin2hex(random_bytes(4))),'Phase 8 Performance TEST DATA','Performance Test','Test only',$ids['user']]);$ids['site']=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO grease_traps(establishment_id,trap_code,name,capacity_liters,low_threshold,medium_threshold,high_threshold,critical_threshold,empty_distance_cm,full_distance_cm)VALUES(?,?,?,50,20,50,75,90,30,5)')->execute([$ids['site'],'PERF-TRAP-'.strtoupper(bin2hex(random_bytes(3))),'Performance trap']);$ids['trap']=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO devices(device_code,name,last_seen_at)VALUES(?,?,UTC_TIMESTAMP())')->execute(['PERF-DEV-'.strtoupper(bin2hex(random_bytes(3))),'Performance device']);$ids['device']=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO device_assignments(device_id,grease_trap_id,started_at)VALUES(?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY))')->execute([$ids['device'],$ids['trap']]);$ids['assignment']=(int)$pdo->lastInsertId();
    $insertReading=$pdo->prepare('INSERT INTO sensor_readings(device_assignment_id,ultrasonic_distance_cm,waste_level_percent,temperature_c,level_status,recorded_at,received_at)VALUES(?,?,?,?,?,?,?)');
    $pdo->beginTransaction();for($i=0;$i<10000;$i++){$time=gmdate('Y-m-d H:i:s',time()-$i*60);$level=(float)($i%101);$status=$level>=90?'CRITICAL':($level>=75?'HIGH':($level>=50?'MEDIUM':'NORMAL'));$insertReading->execute([$ids['assignment'],30-($level*.25),$level,24+($i%20)/10,$status,$time,$time]);} $pdo->commit();
    $insertAlert=$pdo->prepare("INSERT INTO alerts(device_assignment_id,alert_type,severity,message,status,first_triggered_at,last_triggered_at,resolved_at)VALUES(?,?,'WARNING','Phase 8 performance test','RESOLVED',?,?,?)");
    $pdo->beginTransaction();for($i=0;$i<2000;$i++){$time=gmdate('Y-m-d H:i:s',time()-$i*240);$type=['HIGH_LEVEL','CRITICAL_LEVEL','EMULSION_WARNING','DEVICE_OFFLINE'][$i%4];$insertAlert->execute([$ids['assignment'],$type,$time,$time,$time]);}$pdo->commit();
    perf_check((int)$pdo->query('SELECT COUNT(*) FROM sensor_readings WHERE device_assignment_id='.(int)$ids['assignment'])->fetchColumn()===10000,'Created 10,000-reading telemetry history');
    perf_check((int)$pdo->query('SELECT COUNT(*) FROM alerts WHERE device_assignment_id='.(int)$ids['assignment'])->fetchColumn()===2000,'Created 2,000-alert history');
    $poll=$pdo->prepare('SELECT id,waste_level_percent,recorded_at FROM sensor_readings WHERE device_assignment_id=? ORDER BY recorded_at DESC,id DESC LIMIT 1');
    measured('100 latest-reading polls',function()use($poll,$ids){for($i=0;$i<100;$i++){$poll->execute([$ids['assignment']]);$poll->fetch();}});perf_check($timings['100 latest-reading polls']<3,'One hundred repeated latest-reading polls finish within three seconds');
    $from=gmdate('Y-m-d',time()-8*86400);$to=gmdate('Y-m-d');$base=['period'=>'custom','from'=>$from,'to'=>$to,'establishment_id'=>$ids['site'],'page_size'=>100];
    $telemetry=measured('10,000-row telemetry report page',fn()=>phase7_report_data($pdo,phase7_report_filters($base+['report'=>'telemetry'])));perf_check($telemetry['total']===10000&&count($telemetry['rows'])===100&&$telemetry['pages']===100,'Telemetry report counts and paginates 10,000 rows');perf_check($timings['10,000-row telemetry report page']<5,'Telemetry report page finishes within five seconds');
    $deep=measured('deep telemetry page',fn()=>phase7_report_data($pdo,phase7_report_filters($base+['report'=>'telemetry','page'=>100])));perf_check(count($deep['rows'])===100&&$timings['deep telemetry page']<5,'Deep telemetry pagination remains bounded');
    $alerts=measured('2,000-row alert report page',fn()=>phase7_report_data($pdo,phase7_report_filters($base+['report'=>'alerts'])));perf_check($alerts['total']===2000&&count($alerts['rows'])===100&&$timings['2,000-row alert report page']<5,'Alert history report remains bounded and responsive');
    $plan=$pdo->prepare('EXPLAIN SELECT id FROM sensor_readings WHERE device_assignment_id=? ORDER BY recorded_at DESC LIMIT 1');$plan->execute([$ids['assignment']]);perf_check(str_contains((string)($plan->fetch()['key']??''),'reading_assignment'),'High-frequency latest query uses the assignment/time index');
    foreach($timings as$name=>$seconds)echo sprintf("TIME: %s %.4f s\n",$name,$seconds);echo PHP_EOL.$checks." performance checks passed.\n";
}finally{
    if($pdo->inTransaction())$pdo->rollBack();if(isset($ids['assignment'])){$pdo->prepare('DELETE FROM alerts WHERE device_assignment_id=?')->execute([$ids['assignment']]);$pdo->prepare('DELETE FROM sensor_readings WHERE device_assignment_id=?')->execute([$ids['assignment']]);$pdo->prepare('DELETE FROM device_assignments WHERE id=?')->execute([$ids['assignment']]);}if(isset($ids['device']))$pdo->prepare('DELETE FROM devices WHERE id=?')->execute([$ids['device']]);if(isset($ids['trap']))$pdo->prepare('DELETE FROM grease_traps WHERE id=?')->execute([$ids['trap']]);if(isset($ids['site']))$pdo->prepare('DELETE FROM establishments WHERE id=?')->execute([$ids['site']]);if(isset($ids['user'])){$pdo->prepare('DELETE FROM audit_logs WHERE user_id=?')->execute([$ids['user']]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$ids['user']]);}
}
