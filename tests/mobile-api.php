<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    exit("CLI only: php tests/mobile-api.php --allow-local-fixtures\n");
}
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/mobile-monitoring.php';
$siteBase = $config['test_base_url'] ?? null;
if (!is_string($siteBase) || !filter_var($siteBase, FILTER_VALIDATE_URL)) {
    throw new RuntimeException('Set AQUASENSE_TEST_BASE_URL or test_base_url in ignored local.php.');
}
$base = rtrim($siteBase, '/') . '/api/mobile/';
$checks = 0;
    $users = $sites = $traps = $devices = $assignments = $emails = $cleanupEmails = [];
$password = 'Test!' . bin2hex(random_bytes(12));
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function call_api(string $path, ?array $body = null, ?string $token = null): array {
    global $base;
    $curl = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body)]);
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException(curl_error($curl));
    $result = ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'json' => json_decode($raw, true, 32, JSON_THROW_ON_ERROR)];
    curl_close($curl);
    return $result;
}
try {
    $pdo = db();
    $roles = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['owner', 'owner', 'administrator'] as $i => $role) {
        $email = 'mobile-test-' . bin2hex(random_bytes(6)) . '@example.test';
        $emails[] = $email;
        $cleanupEmails[] = $email;
        $pdo->prepare('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)')
            ->execute([$roles[$role], 'Mobile fixture ' . $i, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $users[] = (int) $pdo->lastInsertId();
    }
    foreach (array_slice($users, 0, 2) as $i => $id) {
        $code = 'TEST-' . bin2hex(random_bytes(6));
        $pdo->prepare('INSERT INTO establishments (registration_code, business_name, owner_user_id, owner_name, address, registration_date) VALUES (?, ?, ?, ?, ?, CURRENT_DATE())')
            ->execute([$code, 'Fixture kitchen ' . $i, $id, 'Test owner', 'Test address']);
        $sites[] = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO grease_traps (establishment_id, trap_code, name, capacity_liters, low_threshold, medium_threshold, high_threshold, critical_threshold) VALUES (?, ?, ?, 50, 20, 50, 75, 90)')
            ->execute([$sites[$i], $code, 'Test grease trap']);
        $traps[] = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO devices (device_code, name, device_type) VALUES (?, 'Test device', 'ESP32_TEST')")->execute([$code]);
        $devices[] = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO device_assignments (device_id, grease_trap_id, started_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$devices[$i], $traps[$i]]);
        $assignments[] = (int) $pdo->lastInsertId();
    }
    $unassignedCode = 'TEST-' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO grease_traps (establishment_id, trap_code, name, capacity_liters, low_threshold, medium_threshold, high_threshold, critical_threshold) VALUES (?, ?, ?, 50, 20, 50, 75, 90)')
        ->execute([$sites[0], $unassignedCode, 'Undeployed owner grease trap']);
    $unassignedTrap = (int) $pdo->lastInsertId();
    $traps[] = $unassignedTrap;
    $simulatedCode = 'SIM-' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO grease_traps (establishment_id, trap_code, name, capacity_liters, low_threshold, medium_threshold, high_threshold, critical_threshold) VALUES (?, ?, ?, 50, 20, 50, 75, 90)')
        ->execute([$sites[0], $simulatedCode, 'Same-owner simulated grease trap']);
    $simulatedTrap = (int) $pdo->lastInsertId();
    $traps[] = $simulatedTrap;
    $pdo->prepare("INSERT INTO devices (device_code, name, device_type) VALUES (?, 'Same-owner simulated device', 'SIMULATED')")->execute([$simulatedCode]);
    $simulatedDevice = (int) $pdo->lastInsertId();
    $devices[] = $simulatedDevice;
    $pdo->prepare('INSERT INTO device_assignments (device_id, grease_trap_id, started_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$simulatedDevice, $simulatedTrap]);
    $simulatedAssignment = (int) $pdo->lastInsertId();
    $assignments[] = $simulatedAssignment;
    $endedCode = 'END-' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO grease_traps (establishment_id, trap_code, name, capacity_liters, low_threshold, medium_threshold, high_threshold, critical_threshold) VALUES (?, ?, ?, 50, 20, 50, 75, 90)')
        ->execute([$sites[0], $endedCode, 'Same-owner ended deployment']);
    $endedTrap = (int) $pdo->lastInsertId();
    $traps[] = $endedTrap;
    $pdo->prepare("INSERT INTO devices (device_code, name, device_type) VALUES (?, 'Ended test device', 'ESP32_TEST')")->execute([$endedCode]);
    $endedDevice = (int) $pdo->lastInsertId();
    $devices[] = $endedDevice;
    $pdo->prepare('INSERT INTO device_assignments (device_id, grease_trap_id, started_at, ended_at) VALUES (?, ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY), DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY))')->execute([$endedDevice, $endedTrap]);
    $endedAssignment = (int) $pdo->lastInsertId();
    $assignments[] = $endedAssignment;
    check(call_api('dashboard.php')['status'] === 401, 'Anonymous dashboard rejected');
    check(call_api('profile.php', null, str_repeat('a', 64))['status'] === 401, 'Forged token rejected');
    check(call_api('login.php')['status'] === 405, 'Login requires POST');
    check(call_api('login.php', ['email' => [], 'password' => 'x'])['status'] === 422, 'Invalid input types rejected');
    check(call_api('login.php', ['email' => $emails[2], 'password' => $password])['status'] === 401, 'Administrator cannot obtain owner token');
    check(call_api('login.php', ['email' => $emails[0], 'password' => 'wrong'])['status'] === 401, 'Wrong password rejected');
    $login = call_api('login.php', ['email' => $emails[0], 'password' => $password]);
    check($login['status'] === 200, 'Owner login succeeds');
    $token = $login['json']['data']['token'];
    $query = $pdo->prepare('SELECT token_hash FROM mobile_tokens WHERE user_id = ?');
    $query->execute([$users[0]]);
    check($query->fetchColumn() === hash('sha256', $token), 'Database stores only token hash');
    check(call_api('profile.php', null, $token)['json']['data']['user']['id'] === $users[0], 'Token restores owner profile');
    check(call_api('account-email.php', [])['status'] === 401, 'Anonymous owner email change rejected');
    check(call_api('account-email.php', null, $token)['status'] === 405, 'Owner email change requires POST');
    check(call_api('account-email.php', ['email' => 'invalid', 'current_password' => $password], $token)['status'] === 422, 'Owner email validation rejects invalid input');
    check(call_api('account-email.php', ['email' => $emails[1], 'current_password' => $password], $token)['status'] === 422, 'Owner email change rejects an existing account address');
    check(call_api('account-email.php', ['email' => 'unused-' . bin2hex(random_bytes(4)) . '@example.test', 'current_password' => 'wrong'], $token)['status'] === 422, 'Owner email change verifies the current password');
    $updatedEmail = 'mobile-updated-' . bin2hex(random_bytes(6)) . '@example.test';
    $emailChange = call_api('account-email.php', ['email' => $updatedEmail, 'current_password' => $password], $token);
    check($emailChange['status'] === 200 && $emailChange['json']['data']['user']['email'] === $updatedEmail, 'Owner can change the mobile sign-in email');
    check(call_api('profile.php', null, $token)['json']['data']['user']['email'] === $updatedEmail, 'Email change updates the active owner profile');
    check(call_api('login.php', ['email' => $emails[0], 'password' => $password])['status'] === 401, 'Old owner email cannot sign in after change');
    $emails[0] = $updatedEmail;
    $cleanupEmails[] = $updatedEmail;
    check(call_api('monitoring.php')['status'] === 401, 'Anonymous mobile monitoring rejected');
    $monitoring = call_api('monitoring.php?user_id=' . $users[1], null, $token)['json']['data'];
    check(count($monitoring['traps']) === 1 && $monitoring['traps'][0]['grease_trap_id'] === $traps[0], 'Mobile monitoring returns only actively deployed traps owned by the bearer');
    check($monitoring['traps'][0]['sensor_state'] === 'AWAITING SENSOR DATA', 'Monitoring distinguishes a missing reading from zero');
    check(call_api('telemetry-history.php', null, $token)['status'] === 422, 'Mobile history requires a valid grease trap');
    check(call_api('telemetry-history.php?grease_trap_id=' . $traps[1], null, $token)['status'] === 404, 'Owner cannot read another owner history');
    check(call_api('telemetry-history.php?grease_trap_id=' . $unassignedTrap, null, $token)['status'] === 404, 'Owner cannot read history for an undeployed trap');
    check(call_api('telemetry-history.php?grease_trap_id=' . $simulatedTrap, null, $token)['status'] === 404, 'Owner app cannot read a same-owner simulated trap');
    check(call_api('telemetry-history.php?grease_trap_id=' . $endedTrap, null, $token)['status'] === 404, 'Owner app cannot read a same-owner ended deployment');
    $emptyHistory = call_api('telemetry-history.php?grease_trap_id=' . $traps[0] . '&range=1h', null, $token)['json']['data'];
    check($emptyHistory['total'] === 0 && $emptyHistory['readings'] === [], 'Empty owner telemetry history is honest');
    $data = call_api('dashboard.php?establishment_id=' . $sites[1] . '&user_id=' . $users[1], null, $token)['json']['data'];
    check(count($data['establishments']) === 1 && $data['establishments'][0]['id'] === $sites[0], 'Other owner IDs cannot change account scope');
    check($data['establishments'][0]['traps'][0]['status'] === 'NO DATA', 'Missing telemetry shown as no data');
    $pdo->prepare('UPDATE devices SET last_seen_at=UTC_TIMESTAMP() WHERE id=?')->execute([$devices[0]]);
    $pdo->prepare("INSERT INTO sensor_readings (device_assignment_id, ultrasonic_distance_cm, waste_level_percent, temperature_c, turbidity_ntu, turbidity_raw_adc, flow_rate_lpm, flow_pulse_count, gas_value, odor_value, gas_raw_adc, ambient_temperature_c, humidity_percent, ens160_aqi, tvoc_ppb, eco2_ppm, level_status, is_simulated, recorded_at) VALUES (?, 7, 92, 30.4, 410, 1820, 1.8, 450, 725, 240, 1450, 29.8, 68.2, 2, 120, 510, 'NORMAL', 1, UTC_TIMESTAMP())")
        ->execute([$assignments[0]]);
    $readingId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO alerts (device_assignment_id,sensor_reading_id,alert_type,severity,sensor_name,sensor_value,threshold_value,message,status,first_triggered_at,last_triggered_at) VALUES (?,?, 'CRITICAL_LEVEL','CRITICAL','waste_level_percent',92,90,'Fixture critical alert','ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([$assignments[0], $readingId]);
    $ownedAlertId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO alerts (device_assignment_id,sensor_reading_id,alert_type,severity,sensor_name,sensor_value,threshold_value,message,status,first_triggered_at,last_triggered_at,resolved_at) VALUES (?,?, 'HIGH_LEVEL','WARNING','waste_level_percent',80,75,'Fixture resolved alert','RESOLVED',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([$assignments[0], $readingId]);
    $resolvedAlertId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO alerts (device_assignment_id,alert_type,severity,message,status,first_triggered_at,last_triggered_at) VALUES (?, 'DEVICE_OFFLINE','WARNING','Other owner offline alert','ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([$assignments[1]]);
    $otherAlertId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO alerts (device_assignment_id,alert_type,severity,message,status,first_triggered_at,last_triggered_at) VALUES (?, 'DEVICE_OFFLINE','WARNING','Same-owner simulated alert','ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([$simulatedAssignment]);
    $simulatedAlertId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO alerts (device_assignment_id,alert_type,severity,message,status,first_triggered_at,last_triggered_at) VALUES (?, 'DEVICE_OFFLINE','WARNING','Same-owner ended-deployment alert','ACTIVE',UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([$endedAssignment]);
    $endedAlertId = (int) $pdo->lastInsertId();
    check(call_api('alerts.php')['status'] === 401, 'Anonymous owner alerts rejected');
    $alerts = call_api('alerts.php?status=ALL&severity=ALL&range=30d', null, $token)['json']['data'];
    check(count($alerts['alerts']) === 2 && $alerts['total'] === 2, 'Owner alert list returns owned active and history only');
    check($alerts['summary']['unresolved_count'] === 1 && $alerts['summary']['resolved_count'] === 1, 'Alert summary uses server-side complete counts');
    check($alerts['page_size'] === 25 && $alerts['pages'] === 1, 'Alert history is bounded and paginated');
    check(call_api('alerts.php?grease_trap_id=' . $traps[1], null, $token)['status'] === 404, 'Owner cannot filter alerts by another owner trap');
    check(call_api('alerts.php?grease_trap_id=' . $simulatedTrap, null, $token)['status'] === 404, 'Owner cannot filter alerts by a same-owner simulated trap');
    check(call_api('alerts.php?grease_trap_id=' . $endedTrap, null, $token)['status'] === 404, 'Owner cannot filter alerts by a same-owner ended deployment');
    $detail = call_api('alert.php?id=' . $ownedAlertId, null, $token);
    check($detail['status'] === 200 && $detail['json']['data']['alert']['device_code'] !== '', 'Owned alert detail returns owner-safe device context');
    check(!array_key_exists('resolution_note', $detail['json']['data']['alert']), 'Owner alert detail omits administrative resolution notes');
    check(call_api('alert.php?id=' . $otherAlertId, null, $token)['status'] === 404, 'Owner cannot read another owner alert detail');
    check(call_api('alert.php?id=' . $simulatedAlertId, null, $token)['status'] === 404, 'Owner cannot read same-owner simulated alert detail');
    check(call_api('alert.php?id=' . $endedAlertId, null, $token)['status'] === 404, 'Owner cannot read same-owner ended-deployment alert detail');
    check(call_api('alert.php?id=' . $ownedAlertId, [], $token)['status'] === 405, 'Owner alert detail is read-only');
    $resolved = call_api('alerts.php?status=RESOLVED&severity=WARNING&range=30d', null, $token)['json']['data'];
    check(count($resolved['alerts']) === 1 && $resolved['alerts'][0]['id'] === $resolvedAlertId, 'Resolved warning filter returns owned alert history');
    $data = call_api('dashboard.php', null, $token)['json']['data'];
    check($data['establishments'][0]['traps'][0]['status'] === 'CRITICAL', 'Backend applies configured trap thresholds');
    check($data['establishments'][0]['traps'][0]['reading']['is_simulated'] === true, 'Simulation is explicitly disclosed');
    $monitoring = call_api('monitoring.php', null, $token)['json']['data']['traps'][0];
    check($monitoring['device_status'] === 'ONLINE' && $monitoring['sensor_state'] === 'CRITICAL', 'Mobile monitoring returns backend-derived device and condition state');
    check((float)$monitoring['reading']['ultrasonic_distance_cm'] === 7.0 && (float)$monitoring['reading']['turbidity_ntu'] === 410.0 && (float)$monitoring['reading']['flow_rate_lpm'] === 1.8 && (float)$monitoring['reading']['gas_value'] === 725.0 && (float)$monitoring['reading']['odor_value'] === 240.0, 'Mobile monitoring returns separate gas and odor readings');
    check((int)$monitoring['reading']['turbidity_raw_adc'] === 1820 && (int)$monitoring['reading']['flow_pulse_count'] === 450 && (int)$monitoring['reading']['gas_raw_adc'] === 1450, 'Mobile monitoring exposes Phase 4 raw ADC and pulse fields');
    check((float)$monitoring['reading']['ambient_temperature_c'] === 29.8 && (float)$monitoring['reading']['humidity_percent'] === 68.2 && (int)$monitoring['reading']['ens160_aqi'] === 2 && (int)$monitoring['reading']['tvoc_ppb'] === 120 && (int)$monitoring['reading']['eco2_ppm'] === 510, 'Mobile monitoring exposes independent ENS160 and AHT2x fields');
    check($monitoring['active_alert']['id'] === $ownedAlertId, 'Monitoring returns the highest active owned alert link');
    $history = call_api('telemetry-history.php?grease_trap_id=' . $traps[0] . '&range=1h&page=1', null, $token)['json']['data'];
    check($history['total'] === 1 && count($history['readings']) === 1 && $history['page'] === 1, 'Owner history returns bounded owned telemetry');
    check((int)$history['readings'][0]['turbidity_raw_adc'] === 1820 && (int)$history['readings'][0]['gas_raw_adc'] === 1450 && (int)$history['readings'][0]['ens160_aqi'] === 2, 'Owner history preserves Phase 4 sensor semantics');
    $pdo->prepare('UPDATE sensor_readings SET recorded_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE device_assignment_id = ?')->execute([$assignments[0]]);
    $data = call_api('dashboard.php', null, $token)['json']['data'];
    check($data['establishments'][0]['traps'][0]['is_stale'] === true && $data['establishments'][0]['traps'][0]['status'] === 'OFFLINE', 'Old readings cannot appear current');
    $pdo->prepare('UPDATE establishments SET owner_user_id = ? WHERE id = ?')->execute([$users[1], $sites[0]]);
    check(call_api('dashboard.php', null, $token)['json']['data']['establishments'] === [], 'Ownership changes apply immediately');
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$users[0]]);
    check(call_api('profile.php', null, $token)['status'] === 401, 'Disabled owner token rejected');
    $pdo->prepare('UPDATE users SET is_active = 1, role_id = ? WHERE id = ?')->execute([$roles['administrator'], $users[0]]);
    check(call_api('profile.php', null, $token)['status'] === 401, 'Role change revokes owner access');
    $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roles['owner'], $users[0]]);
    $pdo->prepare('UPDATE mobile_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE user_id = ?')->execute([$users[0]]);
    check(call_api('profile.php', null, $token)['status'] === 401, 'Expired tokens rejected');
    $login = call_api('login.php', ['email' => $emails[0], 'password' => $password]);
    $token = $login['json']['data']['token'];
    check(call_api('account-password.php', [])['status'] === 401, 'Anonymous owner password change rejected');
    check(call_api('account-password.php', null, $token)['status'] === 405, 'Owner password change requires POST');
    check(call_api('account-password.php', ['current_password' => $password, 'new_password' => 'weak', 'password_confirmation' => 'weak'], $token)['status'] === 422, 'Owner password change enforces the shared password policy');
    check(call_api('account-password.php', ['current_password' => $password, 'new_password' => 'NewPrivate!2026', 'password_confirmation' => 'Different!2026'], $token)['status'] === 422, 'Owner password confirmation must match');
    check(call_api('account-password.php', ['current_password' => 'wrong', 'new_password' => 'NewPrivate!2026', 'password_confirmation' => 'NewPrivate!2026'], $token)['status'] === 422, 'Owner password change verifies the current password');
    check(call_api('account-password.php', ['current_password' => $password, 'new_password' => $password, 'password_confirmation' => $password], $token)['status'] === 422, 'Owner password change rejects the current password as the replacement');
    $newPassword = 'Changed!Password2026';
    check(call_api('account-password.php', ['current_password' => $password, 'new_password' => $newPassword, 'password_confirmation' => $newPassword], $token)['status'] === 200, 'Owner can replace an administrator-issued password');
    check(call_api('profile.php', null, $token)['status'] === 401, 'Password change revokes every previous mobile token');
    check(call_api('login.php', ['email' => $emails[0], 'password' => $password])['status'] === 401, 'Old owner password cannot sign in after change');
    $login = call_api('login.php', ['email' => $emails[0], 'password' => $newPassword]);
    check($login['status'] === 200, 'Owner signs in with the replacement password');
    $token = $login['json']['data']['token'];
    check(call_api('logout.php', [], $token)['status'] === 200, 'Logout revokes the replacement-password token');
    check(call_api('logout.php', [], $token)['status'] === 200, 'Logout is idempotent');
    check(call_api('dashboard.php', null, $token)['status'] === 401, 'Logged-out token cannot access dashboard');
    $thresholds = ['low_threshold'=>20, 'medium_threshold'=>50, 'high_threshold'=>75, 'critical_threshold'=>90];
    $settings = ['overflow_threshold'=>100, 'emulsion_temperature_threshold'=>40];
    foreach ([0=>'NORMAL', 20=>'LOW', 50=>'MEDIUM', 75=>'HIGH', 90=>'CRITICAL', 100=>'OVERFLOW'] as $level=>$status) {
        check(mobile_reading_status($thresholds, ['waste_level_percent'=>$level, 'temperature_c'=>30], $settings) === $status, 'Threshold boundary ' . $status);
    }
    check(mobile_reading_status($thresholds, ['waste_level_percent'=>32, 'temperature_c'=>40], $settings) === 'EMULSION WARNING', 'Backend temperature boundary');
    $query = $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_address) VALUES (?, ?)');
    for ($i = 0; $i < $config['login_max_attempts']; $i++) $query->execute([hash('sha256', $emails[1]), '192.0.2.1']);
    check(call_api('login.php', ['email'=>$emails[1], 'password'=>$password])['status'] === 429, 'API sign-in throttling enforced');
    echo "$checks checks passed.\n";
} finally {
    foreach ($sites as $id) $pdo->prepare('DELETE FROM compliance_ledger WHERE establishment_id = ?')->execute([$id]);
    foreach ($assignments as $id) $pdo->prepare('DELETE FROM alerts WHERE device_assignment_id = ?')->execute([$id]);
    foreach ($assignments as $id) $pdo->prepare('DELETE FROM sensor_readings WHERE device_assignment_id = ?')->execute([$id]);
    foreach ($assignments as $id) $pdo->prepare('DELETE FROM device_assignments WHERE id = ?')->execute([$id]);
    foreach ($devices as $id) $pdo->prepare('DELETE FROM devices WHERE id = ?')->execute([$id]);
    foreach ($traps as $id) $pdo->prepare('DELETE FROM grease_traps WHERE id = ?')->execute([$id]);
    foreach ($sites as $id) $pdo->prepare('DELETE FROM establishments WHERE id = ?')->execute([$id]);
    foreach ($users as $id) {
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    foreach ($cleanupEmails as $email) $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = ?')->execute([hash('sha256', $email)]);
}
