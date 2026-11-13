<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/telemetry.php';
$received = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$base = ['device_id'=>'AQS-003','grease_trap_id'=>3];
$checks = 0;
function contract_check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
}
foreach (['temperature'=>31.2,'gas_raw'=>0,'turbidity_raw'=>400,'flow_rate'=>0.0,'flow_pulse_count'=>0] as $key=>$value) {
    $payload = phase3_telemetry_payload($base+[$key=>$value], $config, $received);
    contract_check($payload['ultrasonic_distance'] === null && $payload['device_reported_percent'] === null, $key . ' is accepted without inventing a distance or level');
}
$payload = phase3_telemetry_payload($base+['ultrasonic_distance'=>6.62], $config, $received);
contract_check($payload['ultrasonic_distance'] === 6.62, 'Valid numeric distance is preserved');
foreach ([$base,$base+['ultrasonic_distance'=>'6.62'],$base+['gas_raw'=>4096],$base+['unexpected'=>1]] as $input) {
    try { phase3_telemetry_payload($input,$config,$received); throw new RuntimeException('Invalid payload accepted'); }
    catch (TelemetryException $e) { contract_check($e->httpStatus === 422, 'Invalid or empty measurement remains rejected'); }
}
$runtime = ['boot_id'=>'11111111-1111-4111-8111-111111111111','state'=>'READY','warmup_remaining_ms'=>0,'gas_raw'=>0];
$heartbeat = phase3_heartbeat_payload(['device_id'=>'AQS-003','firmware_version'=>'0.8.21','gas_runtime'=>$runtime]);
contract_check($heartbeat['gas_runtime']['gas_raw'] === 0, 'Heartbeat accepts actual Gas metadata including valid zero');
contract_check(phase3_heartbeat_payload(['device_id'=>'AQS-003'])['gas_runtime'] === null, 'Older firmware heartbeat remains compatible');
echo $checks . " telemetry contract checks passed.\n";
