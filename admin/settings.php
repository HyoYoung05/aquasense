<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/alert-engine.php';
require_once dirname(__DIR__).'/includes/oil-surrender.php';
$user=require_administrator();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!valid_csrf()){$errors[]='The form expired. Reload and try again.';http_response_code(403);}
    else try{
        if(post_string('section')==='oil-surrender'){
            phase5_update_settings(db(),$_POST,(int)$user['id']);flash('success','Oil surrender settings were updated.');
        }else{
            phase4_update_settings($_POST,(int)$user['id']);flash('success','Alert thresholds were updated.');
        }
        redirect('admin/settings.php');
    }catch(InvalidArgumentException|Phase5ValidationException $error){$errors[]=$error->getMessage();}
}
$settings=phase4_numeric_settings(db());$surrenderSettings=phase5_settings(db());
$pageTitle='System Settings';$activeNav='settings';$flash=consume_flash();require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Administration <span>/</span> System settings</div><h1>Operational settings</h1><p>Global alert, evidence upload, and Hybrid Verification policy.</p></div></div>
<?php if($flash): ?><div class="notice notice-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php foreach($errors as $error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endforeach; ?>
<form method="post" class="panel record-form"><?= csrf_field() ?><input type="hidden" name="section" value="alerts"><div class="panel-heading"><div><h2>Global alert policy</h2><p>Changes affect subsequent telemetry and offline checks.</p></div></div><div class="form-grid">
<?php foreach([['emulsion_temperature_threshold','Emulsion warning (°C)','40'],['high_temperature_threshold','High temperature (°C)','45'],['high_turbidity_threshold','High turbidity (NTU)','500'],['flow_rate_min','Minimum flow (L/min)','0'],['flow_rate_max','Maximum flow (L/min)','10'],['overflow_threshold','Overflow level (%)','100'],['device_offline_timeout_minutes','Offline timeout (minutes)','10']] as [$key,$label,$placeholder]): ?><label class="form-field"><span><?= e($label) ?></span><input type="number" step="0.01" name="<?= e($key) ?>" required value="<?= e((string)($_POST['section']??'')==='alerts'?($_POST[$key]??$settings[$key]):$settings[$key]) ?>" placeholder="<?= e($placeholder) ?>"></label><?php endforeach; ?>
</div><div class="form-actions"><button class="button button-primary" type="submit">Save thresholds</button></div></form>
<form method="post" class="panel record-form"><?= csrf_field() ?><input type="hidden" name="section" value="oil-surrender"><div class="panel-heading"><div><h2>Oil surrender evidence policy</h2><p>Controls protected photo uploads and the Hybrid Verification telemetry window.</p></div></div><div class="form-grid"><label class="form-field"><span>Maximum photo size (bytes)</span><input type="number" min="1024" max="20971520" step="1024" name="oil_surrender_max_upload_bytes" required value="<?= e((string)(($_POST['section']??'')==='oil-surrender'?($_POST['oil_surrender_max_upload_bytes']??$surrenderSettings['oil_surrender_max_upload_bytes']):$surrenderSettings['oil_surrender_max_upload_bytes'])) ?>"></label><label class="form-field"><span>Telemetry window before/after submission (hours)</span><input type="number" min="1" max="168" name="oil_surrender_telemetry_window_hours" required value="<?= e((string)(($_POST['section']??'')==='oil-surrender'?($_POST['oil_surrender_telemetry_window_hours']??$surrenderSettings['oil_surrender_telemetry_window_hours']):$surrenderSettings['oil_surrender_telemetry_window_hours'])) ?>"></label></div><div class="form-actions"><button class="button button-primary" type="submit">Save surrender settings</button></div></form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Per-trap level thresholds</h2><p>Low &lt; Medium &lt; High &lt; Critical, all between 0 and 100.</p></div><a class="button button-secondary" href="<?= e(url('admin/grease-traps.php')) ?>">Manage grease traps</a></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
