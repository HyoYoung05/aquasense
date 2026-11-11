<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/monitoring.php';
require_once dirname(__DIR__) . '/includes/admin-core.php';

$checks = 0;
$check = static function (bool $ok, string $name) use (&$checks): void {
    if (!$ok) throw new RuntimeException($name);
    $checks++;
};
$now = 1800000000;
foreach ([null, '', 'not-a-date', '2026-02-30 00:00:00'] as $invalid) {
    $check(!device_contact_is_fresh($invalid, 300, $now), 'Invalid or missing contact must be offline');
}
$check(device_contact_is_fresh(gmdate('Y-m-d H:i:s', $now - 299), 300, $now), 'Recent contact online');
$check(!device_contact_is_fresh(gmdate('Y-m-d H:i:s', $now - 300), 300, $now), 'Exact timeout offline');
$check(!device_contact_is_fresh(gmdate('Y-m-d H:i:s', $now + 1), 300, $now), 'Future contact cannot prove online');
$check(!device_contact_is_fresh(gmdate('Y-m-d H:i:s', $now), 0, $now), 'Invalid timeout fails closed');
$device = ['device_id'=>1, 'device_active'=>1, 'is_active'=>1, 'assignment_id'=>1, 'last_seen_at'=>gmdate('Y-m-d H:i:s', time()-3600)];
$check(phase3_device_state($device, 300)==='OFFLINE', 'Mobile API stale assigned device offline');
$check(phase2_device_status($device, 300)==='OFFLINE', 'Website stale assigned device offline');
$device['last_seen_at'] = gmdate('Y-m-d H:i:s');
$check(phase3_device_state($device, 300)==='ONLINE', 'Mobile API recent device online');
$check(phase2_device_status($device, 300)==='ONLINE', 'Website recent device online');
$device['device_active'] = $device['is_active'] = 0;
$check(phase3_device_state($device, 300)==='INACTIVE', 'Inactive remains inactive');
$check(phase2_device_status($device, 300)==='INACTIVE', 'Website inactive remains inactive');
$check(phase3_device_state(null, 300)==='NOT CONNECTED', 'Missing assignment stays disconnected');
$device['is_active']=1; $device['assignment_id']=null;
$check(phase2_device_status($device, 300)==='NOT CONNECTED', 'Unassigned device stays disconnected');
echo "PASS: $checks device connectivity checks\n";
