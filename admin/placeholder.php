<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
$user=require_staff();
$modules=[];
$module=(string)($_GET['module']??'');
if(!isset($modules[$module])){http_response_code(404);redirect('admin/dashboard.php');}
if($module==='monitoring')redirect('admin/monitoring.php');
if($module==='alerts')redirect('admin/alerts.php');
if($module==='oil-surrenders')redirect('admin/oil-surrenders.php');
if($module==='reports')redirect('admin/reports.php');
if($module==='settings')redirect('admin/settings.php');
if(in_array($module,['users','settings'],true)&&$user['role_slug']!=='administrator'){http_response_code(403);redirect('admin/dashboard.php');}
[$pageTitle,$phase]=$modules[$module];$activeNav=$module;require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> <?= e($pageTitle) ?></div><h1><?= e($pageTitle) ?></h1><p>This navigation destination is reserved and does not expose unfinished actions.</p></div></div>
<section class="panel placeholder-panel"><span class="feature-icon mint"><?= icon('info') ?></span><h2><?= e($phase) ?></h2><p>This route is not part of the AQUASENSE+ 1.0 release. Use the available navigation modules or contact the administrator.</p><a class="button button-primary" href="<?= e(url('admin/dashboard.php')) ?>">Return to dashboard</a></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
