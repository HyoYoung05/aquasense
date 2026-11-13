<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$source = file_get_contents($argv[1] ?? dirname(__DIR__) . '/admin/monitoring.php');
$start = strpos($source, '<div class="table-scroll"><table><thead><tr><th>Recorded</th>');
if ($start === false) throw new RuntimeException('History table not found.');
$end = strpos($source, '</tbody></table></div>', $start);
if ($end === false) throw new RuntimeException('History table closing tags not found.');
$template = substr($source, $start, $end + strlen('</tbody></table></div>') - $start);
$base = ['recorded_at'=>'2026-11-13 00:00:00','device_code'=>'AQS-003','waste_level_percent'=>null,
    'ultrasonic_distance_cm'=>null,'temperature_c'=>null,'turbidity_ntu'=>null,'flow_rate_lpm'=>null,
    'gas_value'=>null,'odor_value'=>null,'turbidity_raw_adc'=>null,'gas_raw_adc'=>null,
    'level_status'=>'UNKNOWN','is_simulated'=>false,'is_test'=>false];
$history = ['readings'=>[$base,array_replace($base,['waste_level_percent'=>0.0]),
    array_replace($base,['waste_level_percent'=>27.1,'ultrasonic_distance_cm'=>6.62,'device_code'=>'<script>bad</script>']),
    array_replace($base,['turbidity_raw_adc'=>3252,'gas_raw_adc'=>38]),
    array_replace($base,['turbidity_raw_adc'=>0,'gas_raw_adc'=>0]),
    array_replace($base,['turbidity_ntu'=>12.3,'turbidity_raw_adc'=>4095,'gas_value'=>4.5,'gas_raw_adc'=>4094,'odor_value'=>7.6])]];
ob_start();
try { eval('declare(strict_types=1); ?>' . $template); $html = ob_get_clean(); }
catch (Throwable $e) { ob_end_clean(); throw $e; }
foreach (['Not available','0.0%','27.1%','6.62 cm','UNKNOWN','&lt;script&gt;bad&lt;/script&gt;',
    '3,252 ADC (raw)','38 ADC (raw)','0 ADC (raw)','12.3 NTU','4.5 calibrated','7.6','Not measured'] as $expected) {
    if (!str_contains($html,$expected)) throw new RuntimeException('Missing expected table content: ' . $expected);
}
if (substr_count($html,'<tr>') !== 7 || str_contains($html,'<script>bad')
    || str_contains($html,'4,095 ADC') || str_contains($html,'4,094 ADC')
    || substr_count($html,'0 ADC (raw)') !== 2) throw new RuntimeException('Malformed/unsafe history table or incorrect calibrated/raw precedence.');
echo "PASS: Actual history template renders missing, measured and raw sensor values; zero and calibrated precedence preserved.\n";
