<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require dirname(__DIR__, 2) . '/includes/mobile-monitoring.php';
api_method('GET');
$owner = mobile_owner();
$trapRaw = $_GET['grease_trap_id'] ?? null;
$pageRaw = $_GET['page'] ?? '1';
if (!is_string($trapRaw) || !ctype_digit($trapRaw) || (int) $trapRaw < 1) {
    api_fail('Select a valid grease trap.', 422);
}
if (!is_string($pageRaw) || !ctype_digit($pageRaw) || (int) $pageRaw < 1) {
    api_fail('Select a valid history page.', 422);
}
$range = isset($_GET['range']) && is_string($_GET['range']) ? $_GET['range'] : 'today';
$from = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) && is_string($_GET['to']) ? $_GET['to'] : null;
try {
    $data = mobile_monitoring_history($owner['id'], (int) $trapRaw, $range, $from, $to, (int) $pageRaw);
    if ($data === null) {
        api_fail('Monitoring history was not found.', 404);
    }
    api_ok($data);
} catch (InvalidArgumentException $error) {
    api_fail($error->getMessage(), 422);
}
