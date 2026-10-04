<?php
declare(strict_types=1);
require_once __DIR__.'/alert-engine.php';
require_once __DIR__.'/monitoring.php';

function mobile_reading_status(array $trap, array $reading, array $settings): string
{
    // Test status was computed server-side at ingestion, not accepted from the device.
    if (!empty($reading['is_test']) && isset($reading['level_status'])
        && in_array($reading['level_status'], ['NORMAL', 'WARNING', 'CRITICAL'], true)) {
        return $reading['level_status'];
    }
    $level = (float) $reading['waste_level_percent'];
    if ($level >= (float) $settings['overflow_threshold']) {
        return 'OVERFLOW';
    }
    foreach (['critical' => 'CRITICAL', 'high' => 'HIGH'] as $key => $status) {
        if ($level >= (float) $trap[$key . '_threshold']) {
            return $status;
        }
    }
    if ($reading['temperature_c'] !== null && (float) $reading['temperature_c'] >= (float) $settings['emulsion_temperature_threshold']) {
        return 'EMULSION WARNING';
    }
    foreach (['medium' => 'MEDIUM', 'low' => 'LOW'] as $key => $status) {
        if ($level >= (float) $trap[$key . '_threshold']) {
            return $status;
        }
    }
    return 'NORMAL';
}

function mobile_dashboard(int $ownerId): array
{
    $settings = db()->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['overflow_threshold', 'emulsion_temperature_threshold', 'device_offline_timeout_minutes'] as $key) {
        if (!isset($settings[$key]) || !is_numeric($settings[$key])) {
            throw new RuntimeException('Missing monitoring configuration: ' . $key);
        }
    }
    $sitesQuery = db()->prepare('SELECT id, business_name FROM establishments WHERE owner_user_id = ? AND is_active = 1 ORDER BY id');
    $sitesQuery->execute([$ownerId]);
    $sites = [];
    foreach ($sitesQuery->fetchAll() as $site) {
        $query = db()->prepare('SELECT g.*, a.id AS assignment_id, d.device_code, d.is_active AS device_active
            FROM grease_traps g LEFT JOIN device_assignments a ON a.grease_trap_id = g.id AND a.ended_at IS NULL
            LEFT JOIN devices d ON d.id = a.device_id
            WHERE g.establishment_id = ? AND g.is_active = 1 ORDER BY g.id');
        $query->execute([$site['id']]);
        $traps = [];
        foreach ($query->fetchAll() as $trap) {
            $readQuery = db()->prepare('SELECT waste_level_percent, ultrasonic_distance_cm, temperature_c, level_status, is_simulated, is_test, recorded_at
                FROM sensor_readings WHERE device_assignment_id = ? AND recorded_at <= UTC_TIMESTAMP()
                ORDER BY recorded_at DESC, id DESC LIMIT 1');
            $readQuery->execute([$trap['assignment_id']]);
            $reading = $readQuery->fetch();
            $age = $reading ? time() - strtotime($reading['recorded_at'] . ' UTC') : null;
            $stale = !$reading || !$trap['device_active'] || $age >= (float) $settings['device_offline_timeout_minutes'] * 60;
            $status = !$trap['assignment_id'] ? 'NOT ASSIGNED' : (!$reading ? 'NO DATA' : ($stale ? 'OFFLINE' : mobile_reading_status($trap, $reading, $settings)));
            $traps[] = ['id' => (int) $trap['id'], 'name' => $trap['name'], 'status' => $status,
                'device_code' => $trap['device_code'],
                'device_status' => !$trap['assignment_id'] ? 'NOT ASSIGNED' : ($stale ? 'OFFLINE' : 'ONLINE'),
                'is_stale' => $stale, 'reading' => !$reading ? null : [
                    'waste_level_percent' => (float) $reading['waste_level_percent'],
                    'temperature_c' => $reading['temperature_c'] === null ? null : (float) $reading['temperature_c'],
                    'ultrasonic_distance_cm' => $reading['ultrasonic_distance_cm'] === null ? null : (float) $reading['ultrasonic_distance_cm'],
                    'is_test' => (bool) $reading['is_test'],
                    'is_simulated' => (bool) $reading['is_simulated'],
                    'recorded_at' => str_replace(' ', 'T', $reading['recorded_at']) . 'Z',
                ]];
        }
        $sites[] = ['id' => (int) $site['id'], 'business_name' => $site['business_name'], 'traps' => $traps];
    }
    $alertQuery=db()->prepare("SELECT al.id,al.alert_type,al.severity,al.message,al.status,al.last_triggered_at,g.id grease_trap_id,g.name grease_trap_name,e.id establishment_id,e.business_name FROM alerts al JOIN device_assignments a ON a.id=al.device_assignment_id JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id WHERE e.owner_user_id=? AND al.status IN ('ACTIVE','ACKNOWLEDGED') ORDER BY al.last_triggered_at DESC");$alertQuery->execute([$ownerId]);$ownerAlerts=[];foreach($alertQuery->fetchAll() as $alert){$alert['id']=(int)$alert['id'];$alert['grease_trap_id']=(int)$alert['grease_trap_id'];$alert['establishment_id']=(int)$alert['establishment_id'];$alert['label']=phase4_alert_label($alert['alert_type']);$ownerAlerts[]=$alert;}
    return ['establishments' => $sites, 'alerts'=>$ownerAlerts, 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'freshness_seconds' => (float) $settings['device_offline_timeout_minutes'] * 60];
}

function mobile_monitoring(int $ownerId): array
{
    $pdo = db();
    $settings = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['overflow_threshold', 'emulsion_temperature_threshold', 'device_offline_timeout_minutes'] as $key) {
        if (!isset($settings[$key]) || !is_numeric($settings[$key])) {
            throw new RuntimeException('Missing monitoring configuration: ' . $key);
        }
    }
    $freshness = (int) $settings['device_offline_timeout_minutes'] * 60;
    $query = $pdo->prepare("SELECT e.id establishment_id,e.business_name,g.id grease_trap_id,g.name grease_trap_name,g.is_active trap_active,
        g.low_threshold,g.medium_threshold,g.high_threshold,g.critical_threshold,a.id assignment_id,
        d.id device_id,d.device_code,d.name device_name,d.firmware_version,d.is_active device_active,d.last_seen_at,
        r.ultrasonic_distance_cm,r.waste_level_percent,r.temperature_c,r.turbidity_ntu,r.flow_rate_lpm,r.gas_value,
        r.level_status,r.is_simulated,r.is_test,r.recorded_at
        FROM establishments e JOIN grease_traps g ON g.establishment_id=e.id AND g.is_active=1
        LEFT JOIN device_assignments a ON a.grease_trap_id=g.id AND a.ended_at IS NULL
        LEFT JOIN devices d ON d.id=a.device_id
        LEFT JOIN sensor_readings r ON r.id=(SELECT sr.id FROM sensor_readings sr WHERE sr.device_assignment_id=a.id AND sr.recorded_at<=UTC_TIMESTAMP() ORDER BY sr.recorded_at DESC,sr.id DESC LIMIT 1)
        WHERE e.owner_user_id=? AND e.is_active=1 ORDER BY e.business_name,g.name");
    $query->execute([$ownerId]);
    $traps = [];
    foreach ($query->fetchAll() as $row) {
        $deviceStatus = phase3_device_state($row, $freshness);
        $hasReading = $row['recorded_at'] !== null;
        $readingStale = $hasReading && time() - strtotime($row['recorded_at'] . ' UTC') >= $freshness;
        $sensorState = !(bool) $row['trap_active'] ? 'INACTIVE'
            : (!$row['assignment_id'] ? 'AWAITING DEVICE'
            : (!$hasReading ? 'AWAITING SENSOR DATA'
            : ($deviceStatus !== 'ONLINE' || $readingStale ? 'OFFLINE' : mobile_reading_status($row, $row, $settings))));
        $reading = null;
        if ($hasReading) {
            $reading = [
                'waste_level_percent' => $row['waste_level_percent'] === null ? null : (float) $row['waste_level_percent'],
                'ultrasonic_distance_cm' => $row['ultrasonic_distance_cm'] === null ? null : (float) $row['ultrasonic_distance_cm'],
                'temperature_c' => $row['temperature_c'] === null ? null : (float) $row['temperature_c'],
                'turbidity_ntu' => $row['turbidity_ntu'] === null ? null : (float) $row['turbidity_ntu'],
                'flow_rate_lpm' => $row['flow_rate_lpm'] === null ? null : (float) $row['flow_rate_lpm'],
                'gas_value' => $row['gas_value'] === null ? null : (float) $row['gas_value'],
                'condition' => $sensorState,
                'is_simulated' => (bool) $row['is_simulated'],
                'is_test' => (bool) $row['is_test'],
                'recorded_at' => str_replace(' ', 'T', (string) $row['recorded_at']) . 'Z',
            ];
        }
        $activeAlert = null;
        if ($row['assignment_id']) {
            $alertQuery = $pdo->prepare("SELECT id,alert_type,severity,status,message,last_triggered_at FROM alerts WHERE device_assignment_id=? AND status IN ('ACTIVE','ACKNOWLEDGED') ORDER BY FIELD(severity,'CRITICAL','WARNING','INFO'),last_triggered_at DESC,id DESC LIMIT 1");
            $alertQuery->execute([(int) $row['assignment_id']]);
            if ($alertRow = $alertQuery->fetch()) {
                $activeAlert = [
                    'id' => (int) $alertRow['id'],
                    'alert_type' => $alertRow['alert_type'],
                    'label' => phase4_alert_label($alertRow['alert_type']),
                    'severity' => $alertRow['severity'],
                    'status' => $alertRow['status'],
                    'message' => $alertRow['message'],
                    'last_triggered_at' => str_replace(' ', 'T', $alertRow['last_triggered_at']) . 'Z',
                ];
            }
        }
        $traps[] = [
            'establishment_id' => (int) $row['establishment_id'],
            'business_name' => $row['business_name'],
            'grease_trap_id' => (int) $row['grease_trap_id'],
            'grease_trap_name' => $row['grease_trap_name'],
            'device_code' => $row['device_code'],
            'device_name' => $row['device_name'],
            'firmware_version' => $row['firmware_version'],
            'device_status' => $deviceStatus,
            'last_seen_at' => $row['last_seen_at'] === null ? null : str_replace(' ', 'T', $row['last_seen_at']) . 'Z',
            'sensor_state' => $sensorState,
            'is_stale' => $hasReading && ($deviceStatus !== 'ONLINE' || $readingStale),
            'active_alert' => $activeAlert,
            'reading' => $reading,
        ];
    }
    return ['generated_at' => gmdate('c'), 'freshness_seconds' => $freshness, 'traps' => $traps];
}

function mobile_monitoring_history(int $ownerId, int $trapId, string $range, ?string $from, ?string $to, int $page): ?array
{
    $ownerTrap = db()->prepare('SELECT g.id FROM grease_traps g JOIN establishments e ON e.id=g.establishment_id WHERE g.id=? AND e.owner_user_id=? AND e.is_active=1 AND g.is_active=1');
    $ownerTrap->execute([$trapId, $ownerId]);
    if (!$ownerTrap->fetchColumn()) {
        return null;
    }
    $history = phase3_monitoring_history($trapId, $range, $from, $to, $page, 50);
    $trap = $history['trap'];
    $history['trap'] = [
        'id' => (int) $trap['id'],
        'name' => $trap['name'],
        'business_name' => $trap['business_name'],
    ];
    foreach ($history['readings'] as &$reading) {
        unset($reading['id'], $reading['device_reported_percent'], $reading['received_at']);
        $reading['recorded_at'] = str_replace(' ', 'T', $reading['recorded_at']) . 'Z';
    }
    unset($reading);
    return $history;
}
