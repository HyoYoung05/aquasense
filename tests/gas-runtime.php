<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/includes/telemetry.php';
$checks = 0;
function gas_check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
}
$bootA = '11111111-1111-4111-8111-111111111111';
$bootB = '22222222-2222-4222-8222-222222222222';
$warming = ['boot_id' => $bootA, 'state' => 'WARMING_UP', 'warmup_remaining_ms' => 60000];
$start = new DateTimeImmutable('2026-11-09T00:00:00Z');
$validated = phase3_validate_gas_runtime($warming);
$snapshot = phase3_gas_runtime_snapshot($validated, $start, null);
gas_check($snapshot['warmup_until'] === '2026-11-09T00:01:00Z' && $snapshot['gas_raw'] === null, 'One-minute warm-up has no cached Gas measurement');
$repeated = phase3_gas_runtime_snapshot($validated, $start->modify('+30 seconds'), $snapshot);
gas_check($repeated['warmup_until'] === $snapshot['warmup_until'], 'Repeated heartbeat does not extend same-boot warm-up');
$restart = phase3_gas_runtime_snapshot(phase3_validate_gas_runtime(array_replace($warming, ['boot_id' => $bootB])), $start->modify('+45 seconds'), $snapshot);
gas_check($restart['warmup_until'] === '2026-11-09T00:01:45Z', 'New boot starts a fresh one-minute interval');
$ready = phase3_validate_gas_runtime(['boot_id' => $bootA, 'state' => 'READY', 'warmup_remaining_ms' => 0, 'gas_raw' => 0]);
gas_check(phase3_gas_runtime_snapshot($ready, $start, $snapshot)['gas_raw'] === 0, 'Valid zero is preserved');
foreach ([['warmup_remaining_ms' => 60001], ['warmup_remaining_ms' => -1], ['warmup_remaining_ms' => 0],
    ['gas_raw' => 100], ['boot_id' => 'invalid'], ['state' => 'UNKNOWN'], ['extra' => true]] as $invalid) {
    try { phase3_validate_gas_runtime(array_replace($warming, $invalid)); throw new RuntimeException('Invalid runtime accepted'); }
    catch (TelemetryException $error) { gas_check($error->httpStatus === 422, 'Invalid runtime rejected: ' . implode(',', array_keys($invalid))); }
}
foreach (['ERROR', 'DISABLED'] as $state) {
    gas_check(phase3_validate_gas_runtime(['boot_id' => $bootA, 'state' => $state, 'warmup_remaining_ms' => 0])['gas_raw'] === null, $state . ' cannot reuse a Gas reading');
}
$legacy = phase3_heartbeat_payload(['device_id' => 'AQS-TEST', 'firmware_version' => '0.8.19']);
gas_check($legacy['gas_runtime'] === null, 'Legacy heartbeat remains valid');
$new = phase3_heartbeat_payload(['device_id' => 'AQS-TEST', 'firmware_version' => '0.8.20', 'gas_runtime' => $warming]);
gas_check($new['gas_runtime']['boot_id'] === $bootA, 'Heartbeat accepts explicit boot metadata');
echo "$checks Gas runtime checks passed.\n";
