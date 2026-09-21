<?php
declare(strict_types=1);

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
    return ['establishments' => $sites, 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'freshness_seconds' => (float) $settings['device_offline_timeout_minutes'] * 60];
}
