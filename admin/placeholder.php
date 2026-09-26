<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
$user=require_staff();
$modules=['monitoring'=>['Monitoring','Phase 3 sensor integration'],'alerts'=>['Alerts','Phase 4 alert operations'],'oil-surrenders'=>['Oil Surrenders','Phase 5 surrender workflow'],'incentives'=>['Incentives','Phase 6 reward processing'],'compliance-ledger'=>['Compliance Ledger','Phase 6 ledger interface'],'reports'=>['Reports','Phase 7 reporting'],'users'=>['Users','Phase 8 account management'],'settings'=>['Settings','Later configuration management']];
$module=(string)($_GET['module']??'');
if(!isset($modules[$module])){http_response_code(404);redirect('admin/dashboard.php');}
if(in_array($module,['users','settings'],true)&&$user['role_slug']!=='administrator'){http_response_code(403);redirect('admin/dashboard.php');}
[$pageTitle,$phase]=$modules[$module];$activeNav=$module;require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> <?= e($pageTitle) ?></div><h1><?= e($pageTitle) ?></h1><p>This navigation destination is reserved and does not expose unfinished actions.</p></div></div>
<section class="panel placeholder-panel"><span class="feature-icon mint"><?= icon('info') ?></span><h2><?= e($phase) ?></h2><p><?= e($pageTitle) ?> is outside the Phase 2 administrative-core scope. Existing database records are preserved, and this page will become operational in its scheduled phase.</p><a class="button button-primary" href="<?= e(url('admin/dashboard.php')) ?>">Return to dashboard</a></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
