<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    fwrite(STDERR, "Usage: php tests/phase8.php --allow-local-fixtures\nCreates and removes temporary users/audit rows.\n");
    exit(1);
}
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/telemetry.php';
require_once dirname(__DIR__) . '/includes/reports.php';
if ($config['environment'] !== 'development') throw new RuntimeException('Phase 8 fixtures require development mode.');
$pdo = db();
$base = rtrim((string) $config['test_base_url'], '/');
$checks = 0;
$ids = [];
$emails = [];
$password = 'Phase8!' . bin2hex(random_bytes(8));
$testStartedAt = gmdate('Y-m-d H:i:s');
function p8_check(bool $condition, string $label): void { global $checks; if (!$condition) throw new RuntimeException('FAIL: ' . $label); $checks++; echo 'PASS: ' . $label . PHP_EOL; }
function p8_http(?CurlHandle $client, string $url, string $method='GET'): array {
    $own=$client===null;if($own){$client=curl_init();curl_setopt($client,CURLOPT_COOKIEFILE,'');}
    curl_setopt_array($client,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CUSTOMREQUEST=>$method]);
    $raw=curl_exec($client);if($raw===false)throw new RuntimeException('HTTP failure: '.curl_error($client));$size=curl_getinfo($client,CURLINFO_HEADER_SIZE);
    $result=['status'=>curl_getinfo($client,CURLINFO_RESPONSE_CODE),'headers'=>substr((string)$raw,0,$size),'body'=>substr((string)$raw,$size)];if($own)curl_close($client);return$result;
}
function p8_login(string $base,string $email,string $password):CurlHandle{
    $client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15]);
    $form=p8_http($client,$base.'/public/login.php');preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$form['body'],$match);
    curl_setopt_array($client,[CURLOPT_URL=>$base.'/public/login.php',CURLOPT_POST=>true,CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_POSTFIELDS=>http_build_query(['csrf_token'=>$match[1]??'','email'=>$email,'password'=>$password])]);curl_exec($client);return$client;
}
try {
    p8_check(APP_VERSION==='1.0.0','Release version is 1.0.0');
    p8_check($config['require_https']===false,'Development mode does not force HTTPS');
    p8_check(phase3_simulator_available('development')&&!phase3_simulator_available('test')&&!phase3_simulator_available('production'),'Simulator is limited to development mode');
    p8_check(in_array($config['log_level'],['error','warning','info'],true),'Configured log level is valid');
    p8_check($config['report_export_max_per_minute']>=1&&$config['upload_max_submissions_per_hour']>=1,'Expensive export and upload limits are enabled');

    $health=p8_http(null,$base.'/api/health.php');
    p8_check($health['status']===200&&$health['body']==='{"status":"ok"}','Health endpoint confirms web and database availability without details');
    p8_check(str_contains($health['headers'],'Permissions-Policy:')&&str_contains($health['headers'],'X-Frame-Options: DENY'),'Health endpoint sends hardened headers');
    p8_check(p8_http(null,$base.'/api/health.php','POST')['status']===405,'Health endpoint rejects unsupported methods');
    $login=p8_http(null,$base.'/public/login.php');
    p8_check(str_contains($login['headers'],'Content-Security-Policy:')&&str_contains($login['headers'],'Permissions-Policy:'),'Browser pages send CSP and Permissions-Policy');
    p8_check(str_contains($login['body'],'Skip to content')||str_contains($login['body'],'Welcome'),'Login page renders without debug output');

    $roles=$pdo->query('SELECT slug,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);$hash=password_hash($password,PASSWORD_DEFAULT);
    foreach(['administrator','environmental_staff','owner'] as $role){$email='phase8-'.$role.'-'.bin2hex(random_bytes(4)).'@example.test';$emails[$role]=$email;$pdo->prepare('INSERT INTO users(role_id,full_name,email,password_hash)VALUES(?,?,?,?)')->execute([(int)$roles[$role],'Phase 8 '.str_replace('_',' ',$role),$email,$hash]);$ids[$role]=(int)$pdo->lastInsertId();}
    $anonymous=p8_http(null,$base.'/admin/users.php');p8_check($anonymous['status']===303,'User directory requires authentication');
    $admin=p8_login($base,$emails['administrator'],$password);$users=p8_http($admin,$base.'/admin/users.php');
    p8_check($users['status']===200&&str_contains($users['body'],$emails['owner']),'Administrator can review account roles and ownership links');
    $staff=p8_login($base,$emails['environmental_staff'],$password);p8_check(p8_http($staff,$base.'/admin/users.php')['status']===303,'Environmental staff cannot open administrator user directory');curl_close($staff);
    $owner=p8_login($base,$emails['owner'],$password);p8_check(curl_getinfo($owner,CURLINFO_RESPONSE_CODE)===200,'Owner remains denied from staff website login');curl_close($owner);
    foreach(['dashboard.php','establishments.php','grease-traps.php','devices.php','monitoring.php','alerts.php','oil-surrenders.php','incentives.php','incentive-rules.php','compliance-ledger.php','reports.php','settings.php','users.php'] as $route){$response=p8_http($admin,$base.'/admin/'.$route);p8_check($response['status']===200,'Authenticated release route works: '.$route);}
    p8_check(p8_http(null,$base.'/assets/css/style.css')['status']===200&&p8_http(null,$base.'/assets/images/water-drop-splash.png')['status']===200,'Release CSS and water artwork assets load');

    for($i=0;$i<(int)$config['report_export_max_per_minute'];$i++){$pdo->prepare("INSERT INTO audit_logs(user_id,action,record_type,ip_address)VALUES(?,'REPORT_CSV_EXPORTED','phase8-test','127.0.0.1')")->execute([$ids['administrator']]);}
    p8_check(audit_rate_limit_reached($pdo,$ids['administrator'],['REPORT_CSV_EXPORTED','REPORT_PDF_EXPORTED'],60,(int)$config['report_export_max_per_minute']),'Report export throttling uses server-side audit history');
    p8_check(!audit_rate_limit_reached($pdo,$ids['owner'],['OIL_SURRENDER_UPLOAD_ATTEMPTED'],3600,(int)$config['upload_max_submissions_per_hour']),'Upload throttling keeps independent owner budgets');

    $requiredIndexes=['sensor_readings.idx_reading_assignment_time','alerts.idx_alert_status_time','oil_surrenders.idx_surrender_review_queue','incentive_transactions.idx_incentive_status_processed','compliance_ledger.idx_ledger_event_time','audit_logs.idx_audit_created_at'];
    foreach($requiredIndexes as $item){[$table,$index]=explode('.',$item);$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');$q->execute([$table,$index]);p8_check((int)$q->fetchColumn()>=1,'Reviewed query index exists: '.$item);}
    $assignment=(int)$pdo->query('SELECT id FROM device_assignments ORDER BY id LIMIT 1')->fetchColumn();
    $q=$pdo->prepare('EXPLAIN SELECT id,waste_level_percent,recorded_at FROM sensor_readings WHERE device_assignment_id=? ORDER BY recorded_at DESC LIMIT 1');$q->execute([$assignment]);$plan=$q->fetch();
    p8_check(str_contains((string)($plan['key']??''),'reading_assignment'),'Latest telemetry query uses the assignment/time index');
    $started=microtime(true);phase7_report_data($pdo,phase7_report_filters(['report'=>'overview','period'=>'last30']));p8_check(microtime(true)-$started<5,'Representative 30-day report completes within five seconds locally');
    $constraints=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE()')->fetchColumn();p8_check($constraints>=25,'Foreign-key coverage is present across operational records');
    $before=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();$pdo->exec((string)file_get_contents(dirname(__DIR__).'/database/migrations/008-phase7-reporting.sql'));p8_check((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()===$before,'Latest migration is safe to reapply without changing business rows');

    $reference=(string)file_get_contents(dirname(__DIR__).'/database/reference-data.sql');$sample=(string)file_get_contents(dirname(__DIR__).'/database/sample-data.sql');
    p8_check(!str_contains($reference,'password_hash')&&!str_contains($reference,'@aquasense.test'),'Production reference data contains no accounts or passwords');
    p8_check(str_starts_with($sample,'-- DEVELOPMENT DATA ONLY'),'Sample data is explicitly development-only');
    $ignore=(string)file_get_contents(dirname(__DIR__).'/.gitignore');p8_check(str_contains($ignore,'/.env')&&str_contains($ignore,'/config/local.php')&&str_contains($ignore,'*.pem'),'Secret and certificate file patterns are ignored');
    foreach(['BACKUP_RESTORE.md','DEPLOYMENT.md','PRODUCTION_CHECKLIST.md','SECURITY.md','ARCHITECTURE.md','RELEASE_NOTES_1.0.0.md','CAPSTONE_DEMO.md'] as $document){p8_check(is_file(dirname(__DIR__).'/docs/'.$document),'Release document exists: '.$document);}
    p8_check(is_file(dirname(__DIR__).'/vendor/autoload.php'),'Locked Composer dependencies are installed');
    curl_close($admin);
    echo PHP_EOL.$checks." Phase 8 checks passed.\n";
} finally {
    if($pdo->inTransaction())$pdo->rollBack();
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$pdo->prepare("DELETE FROM audit_logs WHERE user_id IN ($marks)")->execute(array_values($ids));$pdo->prepare("DELETE FROM login_attempts WHERE email_hash IN ($marks)")->execute(array_map(fn($email)=>hash('sha256',strtolower($email)),array_values($emails)));foreach(array_reverse($ids)as$id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}$pdo->prepare("DELETE FROM audit_logs WHERE action='LOGIN_FAILED' AND created_at>=?")->execute([$testStartedAt]);
}
