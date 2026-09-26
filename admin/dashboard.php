<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-core.php';
$user = require_staff();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageScript = 'assets/js/admin-dashboard.js';

$pdo = db();
$freshness = (int) $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='device_offline_timeout_minutes'")->fetchColumn() * 60;
$establishmentCounts = $pdo->query('SELECT COUNT(*) total, SUM(is_active=1) active FROM establishments')->fetch();
$trapRows = $pdo->query("SELECT g.id,g.name,g.is_active,g.low_threshold,g.medium_threshold,g.high_threshold,g.critical_threshold,
    e.business_name,d.device_code,d.is_active device_active,d.last_seen_at,
    r.waste_level_percent,r.temperature_c,r.level_status,r.recorded_at
    FROM grease_traps g JOIN establishments e ON e.id=g.establishment_id
    LEFT JOIN device_assignments a ON a.grease_trap_id=g.id AND a.ended_at IS NULL
    LEFT JOIN devices d ON d.id=a.device_id
    LEFT JOIN sensor_readings r ON r.id=(SELECT sr.id FROM sensor_readings sr WHERE sr.device_assignment_id=a.id ORDER BY sr.recorded_at DESC,sr.id DESC LIMIT 1)
    ORDER BY e.business_name,g.name")->fetchAll();
$devices = $pdo->query('SELECT is_active,last_seen_at FROM devices')->fetchAll();

$deviceCounts=['online'=>0,'offline'=>0,'not_connected'=>0];
foreach($devices as $device){
    $state=phase2_device_status($device,$freshness);
    if($state==='ONLINE')$deviceCounts['online']++;
    elseif($state==='NOT CONNECTED')$deviceCounts['not_connected']++;
    else $deviceCounts['offline']++;
}
$trapCounts=['normal'=>0,'warning'=>0,'critical'=>0,'offline'=>0];
foreach($trapRows as &$trap){
    $deviceState=$trap['device_code']===null?'NOT CONNECTED':phase2_device_status(['is_active'=>$trap['device_active'],'last_seen_at'=>$trap['last_seen_at']],$freshness);
    if(!(bool)$trap['is_active'])$status='INACTIVE';
    elseif($trap['device_code']===null)$status='AWAITING DEVICE';
    elseif($trap['recorded_at']===null)$status='AWAITING TELEMETRY';
    elseif($deviceState!=='ONLINE')$status='OFFLINE';
    else $status=(string)$trap['level_status'];
    $trap['device_status']=$deviceState;$trap['current_status']=$status;
    if(in_array($status,['CRITICAL','OVERFLOW'],true))$trapCounts['critical']++;
    elseif(in_array($status,['WARNING','HIGH','MEDIUM'],true))$trapCounts['warning']++;
    elseif(in_array($status,['NORMAL','LOW'],true))$trapCounts['normal']++;
    else $trapCounts['offline']++;
}
unset($trap);
$surrenders=$pdo->query("SELECT SUM(status='PENDING') pending,SUM(status='APPROVED') approved,
    COALESCE(SUM(CASE WHEN status='APPROVED' AND oil_unit='L' THEN oil_quantity ELSE 0 END),0) oil_liters
    FROM oil_surrenders")->fetch();
$rice=(float)$pdo->query("SELECT COALESCE(SUM(rice_quantity),0) FROM incentive_transactions WHERE status='DISTRIBUTED' AND rice_unit='kg'")->fetchColumn();
$alerts=$pdo->query("SELECT al.severity,al.message,al.status,al.created_at,e.business_name,g.name trap_name
    FROM alerts al JOIN device_assignments a ON a.id=al.device_assignment_id
    JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id
    ORDER BY al.created_at DESC,al.id DESC LIMIT 5")->fetchAll();
$activityQuery=$pdo->prepare("SELECT action,created_at FROM audit_logs WHERE user_id=? AND action IN ('LOGIN','LOGOUT') ORDER BY id DESC LIMIT 5");
$activityQuery->execute([$user['id']]);$activity=$activityQuery->fetchAll();
$stats=[
 ['Registered establishments',(int)$establishmentCounts['total'],'store','blue'],['Active establishments',(int)$establishmentCounts['active'],'check','mint'],
 ['Active grease traps',count(array_filter($trapRows,fn($r)=>(bool)$r['is_active'])),'activity','mint'],['Registered devices',count($devices),'device','blue'],
 ['Online devices',$deviceCounts['online'],'check','mint'],['Offline devices',$deviceCounts['offline'],'device','sand'],
 ['Normal grease traps',$trapCounts['normal'],'check','mint'],['Warning grease traps',$trapCounts['warning'],'bell','sand'],
 ['Critical / overflow',$trapCounts['critical'],'bell','rose'],['Pending oil surrenders',(int)$surrenders['pending'],'oil','sand'],
 ['Approved oil surrenders',(int)$surrenders['approved'],'oil','mint'],['Total oil collected',number_format((float)$surrenders['oil_liters'],1).' L','oil','blue'],
 ['Rice incentives distributed',number_format($rice,1).' kg','gift','lavender'],
];
$chartData=json_encode([
 'establishments'=>['labels'=>['Active','Inactive'],'values'=>[(int)$establishmentCounts['active'],(int)$establishmentCounts['total']-(int)$establishmentCounts['active']]],
 'traps'=>['labels'=>['Normal','Warning','Critical','Offline / no data'],'values'=>array_values($trapCounts)],
 'devices'=>['labels'=>['Online','Offline','Not connected'],'values'=>[$deviceCounts['online'],$deviceCounts['offline'],$deviceCounts['not_connected']]],
],JSON_THROW_ON_ERROR);
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Overview</div><h1>Administrative dashboard</h1><p>Current registration, assignment, and available monitoring records.</p></div><div class="date-label"><?= icon('clock') ?> <?= e(date('l, F j, Y')) ?></div></div>
<div class="phase-notice operational-notice"><?= icon('info') ?><p><strong>Phase 2 administrative core.</strong> Counts use current database records. Missing sensor measurements are shown as awaiting telemetry; no values are invented.</p><span class="tag">Phase 2</span></div>
<section class="stat-grid" aria-label="Operational summary">
<?php foreach($stats as [$label,$value,$symbol,$color]): ?><article class="stat-card"><span class="feature-icon <?= e($color) ?>"><?= icon($symbol) ?></span><div><strong><?= e((string)$value) ?></strong><span><?= e($label) ?></span></div></article><?php endforeach; ?>
</section>
<section class="panel section-panel" aria-labelledby="trap-status-title">
<div class="panel-heading"><div><h2 id="trap-status-title">Grease trap status</h2><p>Latest available record for every registered trap.</p></div><a class="button button-secondary button-small" href="<?= e(url('admin/grease-traps.php')) ?>">Manage grease traps</a></div>
<div class="table-scroll"><table><thead><tr><th>Establishment</th><th>Grease trap</th><th>Assigned device</th><th>Waste level</th><th>Temperature</th><th>Current status</th><th>Device status</th><th>Last update</th><th>Action</th></tr></thead><tbody>
<?php foreach($trapRows as $trap): ?><tr><td><?= e($trap['business_name']) ?></td><td><?= e($trap['name']) ?></td><td><?= e($trap['device_code']??'Not assigned') ?></td><td><?= $trap['waste_level_percent']===null?'No data yet':e(number_format((float)$trap['waste_level_percent'],1).'%') ?></td><td><?= $trap['temperature_c']===null?'No data yet':e(number_format((float)$trap['temperature_c'],1).' °C') ?></td><td><span class="status-badge status-<?= e(strtolower(str_replace([' ','/'],'-',$trap['current_status']))) ?>"><?= e($trap['current_status']) ?></span></td><td><span class="status-badge status-<?= e(strtolower(str_replace(' ','-',$trap['device_status']))) ?>"><?= e($trap['device_status']) ?></span></td><td><?= $trap['recorded_at']?e(display_date($trap['recorded_at'])):'Awaiting telemetry' ?></td><td><a class="table-action" href="<?= e(url('admin/grease-trap-view.php?id='.(int)$trap['id'])) ?>">View</a></td></tr><?php endforeach; ?>
<?php if(!$trapRows): ?><tr><td colspan="9" class="empty-state">No grease traps are registered yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="chart-grid" data-dashboard-charts='<?= e($chartData) ?>' aria-label="Database overview charts">
<?php foreach([['establishments-chart','Establishment status'],['traps-chart','Grease trap status'],['devices-chart','Device status']] as [$id,$title]): ?><article class="panel chart-panel"><div class="panel-heading"><div><h2><?= e($title) ?></h2><p>Current database totals</p></div></div><canvas id="<?= e($id) ?>" width="440" height="230" aria-label="<?= e($title) ?> chart"></canvas><div class="chart-legend" data-legend="<?= e($id) ?>"></div></article><?php endforeach; ?>
</section>
<section class="panel section-panel" aria-labelledby="alerts-title"><div class="panel-heading"><div><h2 id="alerts-title">Recent alerts</h2><p>Latest existing alert records; automated generation remains a later phase.</p></div></div><div class="table-scroll"><table><thead><tr><th>Establishment</th><th>Grease trap</th><th>Severity</th><th>Message</th><th>Status</th><th>Recorded</th></tr></thead><tbody>
<?php foreach($alerts as $alert): ?><tr><td><?= e($alert['business_name']) ?></td><td><?= e($alert['trap_name']) ?></td><td><span class="status-badge status-<?= e(strtolower($alert['severity'])) ?>"><?= e($alert['severity']) ?></span></td><td class="wrap-cell"><?= e($alert['message']) ?></td><td><?= e($alert['status']) ?></td><td><?= e(display_date($alert['created_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$alerts): ?><tr><td colspan="6" class="empty-state">No alerts have been recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel section-panel" aria-labelledby="activity-title"><div class="panel-heading"><div><h2 id="activity-title">Your recent activity</h2><p>Sign-ins and sign-outs for your account.</p></div><span class="feature-icon compact mint"><?= icon('clock') ?></span></div><div class="table-scroll"><table><thead><tr><th>Activity</th><th>Date and time (Manila)</th><th>Status</th></tr></thead><tbody><?php foreach($activity as $event): ?><tr><td><span class="table-event"><?= icon($event['action']==='LOGIN'?'shield':'logout') ?> <?= $event['action']==='LOGIN'?'Signed in to workspace':'Signed out of workspace' ?></span></td><td><?= e(display_date($event['created_at'])) ?></td><td><span class="status-badge status-active">Successful</span></td></tr><?php endforeach; ?><?php if(!$activity): ?><tr><td colspan="3" class="empty-state">Your account activity will appear here.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
