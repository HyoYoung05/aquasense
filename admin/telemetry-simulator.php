<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/telemetry.php';require_once dirname(__DIR__).'/includes/admin-core.php';
if(!phase3_simulator_available($config['environment'])){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');exit('Not found.');}
$user=require_administrator();$pageTitle='Telemetry Simulator';$activeNav='monitoring';$errors=[];$result=null;
$devices=db()->query("SELECT d.id,d.device_code,d.name,a.grease_trap_id,g.name trap_name,e.business_name FROM devices d JOIN device_assignments a ON a.device_id=d.id AND a.ended_at IS NULL JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id WHERE d.is_active=1 AND g.is_active=1 AND e.is_active=1 ORDER BY d.device_code")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!valid_csrf()){http_response_code(403);$errors[]='The form expired. Reload and try again.';}
    else{
        $deviceId=post_id('device_id');$trapId=post_id('grease_trap_id');$key=post_string('device_key');$selected=null;foreach($devices as $candidate)if((int)$candidate['id']===$deviceId)$selected=$candidate;
        if(!$selected)$errors[]='Select an active assigned device.';if(!$trapId)$errors[]='Select a grease trap.';if(!preg_match('/^[a-f0-9]{64}$/iD',$key))$errors[]='Enter the 64-character device credential.';
        $distance=filter_var($_POST['ultrasonic_distance']??null,FILTER_VALIDATE_FLOAT);if($distance===false)$errors[]='Enter an ultrasonic distance.';
        if(!$errors){
            $hex=bin2hex(random_bytes(16));$hex[12]='4';$hex[16]=dechex((hexdec($hex[16])&3)|8);$uuid=sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20,12));
            $payload=['device_id'=>$selected['device_code'],'grease_trap_id'=>$trapId,'ultrasonic_distance'=>(float)$distance,'reading_uuid'=>$uuid];
            foreach(['temperature','turbidity','flow_rate','gas_value'] as $field)if(phase2_text($_POST,$field)!=='')$payload[$field]=(float)phase2_text($_POST,$field);
            $endpoint=rtrim((string)$config['test_base_url'],'/').'/api/device/telemetry.php';
            if(!filter_var($endpoint,FILTER_VALIDATE_URL))$errors[]='Configure test_base_url before using the simulator.';
            else{
                $curl=curl_init($endpoint);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-Device-Key: '.$key,'X-Aquasense-Simulator: 1'],CURLOPT_TIMEOUT=>15]);$raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$curlError=curl_error($curl);curl_close($curl);
                if($raw===false)$errors[]='The telemetry API could not be reached: '.$curlError;
                else{$decoded=json_decode($raw,true);$result=['status'=>$status,'body'=>$decoded??['message'=>'The API returned an unreadable response.']];if($status>=200&&$status<300){audit('TELEMETRY_SIMULATED',(int)$user['id'],'devices',$deviceId);}}
            }
        }
    }
}
require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb"><a href="<?= e(url('admin/monitoring.php')) ?>">Monitoring</a> <span>/</span> Simulator</div><h1>Development telemetry simulator</h1><p>Submits through the same authenticated PHP endpoint used by the ESP32.</p></div><span class="status-badge status-warning">DEVELOPMENT ONLY</span></div>
<div class="phase-notice operational-notice"><?= icon('info') ?><p>The simulator never inserts directly into MySQL. Enter a device credential generated from Device Management; it is sent only to the local telemetry endpoint and is not stored.</p></div>
<?php if($errors): ?><div class="notice notice-error"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if($result): ?><div class="notice <?= $result['status']>=200&&$result['status']<300?'notice-success':'notice-error' ?>"><strong>HTTP <?= (int)$result['status'] ?></strong><pre class="simulator-result"><?= e(json_encode($result['body'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre></div><?php endif; ?>
<form class="panel record-form" method="post" autocomplete="off"><?= csrf_field() ?><div class="panel-heading"><div><h2>Simulated sensor reading</h2><p>Distance is authoritative. The backend calculates fill percentage and state from the selected trap calibration.</p></div></div><div class="form-grid">
<label class="form-field"><span>Device *</span><select name="device_id" required><option value="">Select device</option><?php foreach($devices as $device): ?><option value="<?= (int)$device['id'] ?>"<?= selected($_POST['device_id']??'',(string)$device['id']) ?>><?= e($device['device_code'].' · '.$device['name']) ?></option><?php endforeach; ?></select></label>
<label class="form-field"><span>Grease trap *</span><select name="grease_trap_id" required><option value="">Select grease trap</option><?php foreach($devices as $device): ?><option value="<?= (int)$device['grease_trap_id'] ?>"<?= selected($_POST['grease_trap_id']??'',(string)$device['grease_trap_id']) ?>><?= e($device['business_name'].' · '.$device['trap_name']) ?></option><?php endforeach; ?></select></label>
<label class="form-field form-span"><span>Device credential *</span><input type="password" name="device_key" minlength="64" maxlength="64" required autocomplete="new-password"></label>
<?php foreach([['ultrasonic_distance','Ultrasonic distance (cm) *','12.4'],['temperature','Temperature (°C)',''],['turbidity','Turbidity (NTU)',''],['flow_rate','Flow rate (L/min)',''],['gas_value','Gas / odor reading','']] as [$name,$label,$placeholder]): ?><label class="form-field"><span><?= e($label) ?></span><input type="number" step="0.01" name="<?= e($name) ?>" value="<?= e(phase2_text($_POST,$name)) ?>" placeholder="<?= e($placeholder) ?>"<?= $name==='ultrasonic_distance'?' required':'' ?>></label><?php endforeach; ?>
</div><div class="form-actions"><a class="button button-secondary" href="<?= e(url('admin/monitoring.php')) ?>">Return to monitoring</a><button class="button button-primary" type="submit">Send through telemetry API</button></div></form>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
