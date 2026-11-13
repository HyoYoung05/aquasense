<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/owner-warning.php';
foreach (['WARNING'=>'MANUAL_WARNING','CRITICAL'=>'MANUAL_CRITICAL'] as $severity=>$type) {
    if (owner_warning_request(['grease_trap_id'=>3,'severity'=>$severity]) !== [3,$severity,$type]) throw new RuntimeException('Incorrect warning mapping');
    $definition=phase4_alert_definitions()[$type];
    if ($definition['sensor']!=='owner_report' || $definition['severity']!==$severity || stripos($definition['message'],'test')!==false) throw new RuntimeException('Incorrect owner warning definition');
}
foreach ([[],['grease_trap_id'=>0,'severity'=>'WARNING'],['grease_trap_id'=>'3','severity'=>'WARNING'],['grease_trap_id'=>3,'severity'=>'OTHER'],['grease_trap_id'=>3,'severity'=>'WARNING','owner_id'=>2],['grease_trap_id'=>3,'severity'=>'WARNING','sensor_value'=>99]] as $body) {
    try { owner_warning_request($body); throw new RuntimeException('Invalid/forged request accepted'); } catch (InvalidArgumentException $expected) { }
}
echo "PASS: Owner warning severity mapping, non-fabricated definitions, invalid IDs/severities and forged ownership/sensor fields.\n";
