<?php
declare(strict_types=1);
require_once __DIR__.'/alert-engine.php';

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
