<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    exit("Usage: php tests/ultrasonic-api.php --allow-local-fixtures\n");
}
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/ultrasonic-test.php';
require dirname(__DIR__) . '/includes/mobile-monitoring.php';
if ($config['environment'] !== 'development') throw new RuntimeException('Development fixtures only.');
$base = rtrim((string) $config['test_base_url'], '/');
if (!filter_var($base, FILTER_VALIDATE_URL)) throw new RuntimeException('Configure test_base_url.');
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
}
function request_test(string $path, string $method = 'GET', ?string $body = null, ?string $token = null, string $type = 'application/json'): array {
    global $base;
    $c = curl_init($base . $path);
    $headers = ['Content-Type: ' . $type];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($c);
    if ($raw === false) throw new RuntimeException('API unavailable: ' . curl_error($c));
    $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    curl_close($c);
    return ['status' => $status, 'body' => $raw, 'json' => json_decode($raw, true)];
}
$cal = ['empty_distance_cm' => 30, 'full_distance_cm' => 5, 'warning_percent' => 75, 'critical_percent' => 90];
foreach ([[30,0,'NORMAL'], [12.4,70.4,'NORMAL'], [11.25,75,'WARNING'], [7.5,90,'CRITICAL'], [5,100,'CRITICAL'], [3,100,'CRITICAL'], [35,0,'NORMAL']] as [$cm,$pct,$status]) {
    $level = ultrasonic_test_level((float) $cm, $cal);
    check(abs($level['waste_level_percent']-$pct) < 0.01 && $level['status'] === $status, 'Calibration boundary at ' . $cm . ' cm');
}
foreach ([0,1.99,401,INF,NAN] as $invalid) {
    try { ultrasonic_test_level($invalid, $cal); $rejected = false; } catch (InvalidArgumentException) { $rejected = true; }
    check($rejected, 'Invalid distance rejected by calculation');
}
$pdo = db();
$uid = $site = $trap = $device = $assignment = null;
$email = 'ultrasonic-' . bin2hex(random_bytes(6)) . '@example.test';
$key = bin2hex(random_bytes(32));
$password = bin2hex(random_bytes(24));
$path = '/api/device/telemetry.php';
$code = 'UT-' . bin2hex(random_bytes(6));
try {
    $role = $pdo->query("SELECT id FROM roles WHERE slug = 'owner'")->fetchColumn();
    $pdo->prepare('INSERT INTO users (role_id,full_name,email,password_hash) VALUES (?,?,?,?)')
        ->execute([$role,'Ultrasonic fixture',$email,password_hash($password,PASSWORD_DEFAULT)]);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO establishments (registration_code,business_name,owner_user_id,owner_name,address,registration_date)
        VALUES (?,?,?,?,?,CURRENT_DATE())')->execute([$code,'Ultrasonic fixture',$uid,'Test','Test']);
    $site = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO grease_traps (establishment_id,trap_code,name,capacity_liters,low_threshold,medium_threshold,high_threshold,critical_threshold)
        VALUES (?,?,?,1,20,50,75,90)')->execute([$site,$code,'Test']);
    $trap = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO devices (device_code,name,device_type,api_key_hash) VALUES (?,?,?,?)')
        ->execute([$code,'Fixture','ESP32_ULTRASONIC_TEST',hash('sha256',$key)]);
    $device = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO device_assignments (device_id,grease_trap_id,started_at) VALUES (?,?,UTC_TIMESTAMP())')->execute([$device,$trap]);
    $assignment = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO device_ultrasonic_test_config VALUES (?,30,5,75,90)')->execute([$device]);
    $body = ['device_id'=>$code,'grease_trap_id'=>$trap,'ultrasonic_distance'=>12.4,'waste_level_percent'=>70.4,'status'=>'NORMAL'];
    $json = json_encode($body);
    check(request_test($path)['status'] === 405, 'GET does not insert telemetry');
    check(request_test($path,'POST',$json)['status'] === 401, 'Anonymous device rejected');
    check(request_test($path,'POST',$json,str_repeat('a',64))['status'] === 401, 'Unknown device key rejected');
    check(request_test($path,'POST',$json,$key,'text/plain')['status'] === 415, 'JSON content type required');
    check(request_test($path,'POST','{',$key)['status'] === 400, 'Malformed JSON rejected');
    check(request_test($path,'POST',str_repeat('x',2049),$key)['status'] === 413, 'Oversized body rejected');
    foreach ([
        ['grease_trap_id'=>(string)$trap], ['ultrasonic_distance'=>0], ['ultrasonic_distance'=>401],
        ['ultrasonic_distance'=>'12.4'], ['waste_level_percent'=>101], ['status'=>'OVERFLOW'],
        ['waste_level_percent'=>12], ['status'=>'CRITICAL'], ['temperature_c'=>25],
        ['ultrasonic_distance'=>[]], ['status'=>[]],
    ] as $change) {
        check(request_test($path,'POST',json_encode(array_replace($body,$change)),$key)['status'] === 422,
            'Invalid/mismatched payload rejected: ' . implode(',',array_keys($change)));
    }
    check(request_test($path,'POST',json_encode(array_replace($body,['grease_trap_id'=>$trap+999999])),$key)['status'] === 403, 'Other trap ID rejected');
    check(request_test($path,'POST',json_encode(array_replace($body,['device_id'=>'OTHER'])),$key)['status'] === 403, 'Device identity cannot be spoofed');
    $q = $pdo->prepare('SELECT COUNT(*) FROM sensor_readings WHERE device_assignment_id=?');
    $q->execute([$assignment]);
    check((int)$q->fetchColumn() === 0, 'Rejected requests stored no telemetry');
    $reply = request_test($path,'POST',$json,$key);
    check($reply['status'] === 201 && $reply['json']['success'], 'Valid telemetry returns 201');
    $q = $pdo->prepare('SELECT * FROM sensor_readings WHERE device_assignment_id=?');
    $q->execute([$assignment]); $row=$q->fetch();
    check((float)$row['ultrasonic_distance_cm'] === 12.4 && (float)$row['waste_level_percent'] === 70.4, 'Distance and derived percentage stored');
    check($row['temperature_c'] === null && $row['turbidity_ntu'] === null && $row['gas_value'] === null, 'Absent sensors remain NULL');
    check((int)$row['is_test'] === 1 && (int)$row['is_simulated'] === 0, 'Hardware contract marks bench test separately from simulation');
    $q=$pdo->prepare('SELECT api_key_hash,last_seen_at FROM devices WHERE id=?'); $q->execute([$device]); $d=$q->fetch();
    check($d['api_key_hash']===hash('sha256',$key) && $d['last_seen_at']!==null,'Only key hash stored; heartbeat updated on acceptance');
    check(request_test($path,'POST',$json,$key)['status'] === 429,'Rapid repeated telemetry is limited');
    foreach ([[11.25,75,'WARNING'],[7.5,90,'CRITICAL']] as [$cm,$pct,$state]) {
        $pdo->prepare('UPDATE devices SET last_seen_at=NULL WHERE id=?')->execute([$device]);
        $reply=request_test($path,'POST',json_encode(array_replace($body,['ultrasonic_distance'=>$cm,'waste_level_percent'=>$pct,'status'=>$state])),$key);
        check($reply['status']===201 && $reply['json']['data']['status']===$state,'Server computes '.$state.' before full');
    }
    $login=request_test('/api/mobile/login.php','POST',json_encode(['email'=>$email,'password'=>$password]));
    check($login['status']===200,'Owner login unaffected');
    $ownerToken=$login['json']['data']['token'];
    check(request_test($path,'POST',$json,$ownerToken)['status']===401,'Owner token cannot act as device key');
    check(request_test('/api/mobile/profile.php','GET',null,$key)['status']===401,'Device key cannot act as owner token');
    $reply=request_test('/api/mobile/dashboard.php','GET',null,$ownerToken);
    $r=$reply['json']['data']['establishments'][0]['traps'][0];
    check($reply['status']===200 && $r['status']==='CRITICAL' && $r['reading']['temperature_c']===null
        && $r['reading']['is_test']===true && $r['reading']['ultrasonic_distance_cm']===7.5,'Owner API reads stored ultrasonic test without fake temperature');
    check(mobile_dashboard($uid)['establishments'][0]['traps'][0]['status']==='CRITICAL','Shared PHP reader can serve website telemetry later');
    $pdo->prepare('UPDATE sensor_readings SET recorded_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE device_assignment_id=?')->execute([$assignment]);
    $r=mobile_dashboard($uid)['establishments'][0]['traps'][0];
    check($r['is_stale'] && $r['status']==='OFFLINE','Missing fresh sensor data becomes offline');
    $pdo->prepare('UPDATE devices SET is_active=0 WHERE id=?')->execute([$device]);
    check(request_test($path,'POST',$json,$key)['status']===401,'Disabled device blocked');
    $pdo->prepare('UPDATE devices SET is_active=1 WHERE id=?')->execute([$device]);
    $pdo->prepare('UPDATE grease_traps SET is_active=0 WHERE id=?')->execute([$trap]);
    check(request_test($path,'POST',$json,$key)['status']===403,'Inactive trap blocked');
    $pdo->prepare('UPDATE grease_traps SET is_active=1 WHERE id=?')->execute([$trap]);
    $pdo->prepare('UPDATE device_assignments SET ended_at=UTC_TIMESTAMP() WHERE id=?')->execute([$assignment]);
    check(request_test($path,'POST',$json,$key)['status']===403,'Ended device assignment blocked');
    $pdo->prepare('UPDATE devices SET api_key_hash=? WHERE id=?')->execute([hash('sha256',bin2hex(random_bytes(32))),$device]);
    check(request_test($path,'POST',$json,$key)['status']===401,'Revoked key blocked');
    check(request_test('/api/device/')['status']===403,'API directory stays private');
    echo "$checks checks passed. Synthetic integration fixtures will be removed.\n";
} finally {
    if($assignment) {
        $pdo->prepare('DELETE FROM sensor_readings WHERE device_assignment_id=?')->execute([$assignment]);
        $pdo->prepare('DELETE FROM device_assignments WHERE id=?')->execute([$assignment]);
    }
    if($device) {
        $pdo->prepare('DELETE FROM device_ultrasonic_test_config WHERE device_id=?')->execute([$device]);
        $pdo->prepare('DELETE FROM devices WHERE id=?')->execute([$device]);
    }
    if($trap) $pdo->prepare('DELETE FROM grease_traps WHERE id=?')->execute([$trap]);
    if($site) $pdo->prepare('DELETE FROM establishments WHERE id=?')->execute([$site]);
    if($uid) {
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id=?')->execute([$uid]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
    }
    $pdo->prepare('DELETE FROM login_attempts WHERE email_hash=?')->execute([hash('sha256',$email)]);
}
