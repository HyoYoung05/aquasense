<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';require_once dirname(__DIR__).'/includes/compliance.php';
$user=require_staff();$id=query_id();if(!$id)redirect('admin/compliance-ledger.php');
$q=db()->prepare("SELECT cl.*,e.business_name,u.full_name creator_name,d.device_code,d.name device_name,g.trap_code,g.name trap_name,
 os.transaction_code surrender_code,it.transaction_code incentive_code
 FROM compliance_ledger cl LEFT JOIN establishments e ON e.id=cl.establishment_id LEFT JOIN users u ON u.id=cl.created_by
 LEFT JOIN devices d ON d.id=cl.device_id LEFT JOIN grease_traps g ON g.id=cl.grease_trap_id
 LEFT JOIN oil_surrenders os ON cl.related_record_type='oil_surrenders' AND os.id=cl.related_record_id
 LEFT JOIN incentive_transactions it ON cl.related_record_type='incentive_transactions' AND it.id=cl.related_record_id WHERE cl.id=?");$q->execute([$id]);$event=$q->fetch();if(!$event)redirect('admin/compliance-ledger.php');
$pageTitle='Ledger event';$activeNav='compliance-ledger';require dirname(__DIR__).'/includes/header.php';
$relatedUrl=null;$relatedLabel=$event['related_record_type']&&$event['related_record_id']?$event['related_record_type'].' #'.$event['related_record_id']:'Not linked';
if($event['surrender_code']){$relatedLabel=$event['surrender_code'];$relatedUrl=url('admin/oil-surrender-view.php?id='.(int)$event['related_record_id']);}
if($event['incentive_code']){$relatedLabel=$event['incentive_code'];$relatedUrl=url('admin/incentive-view.php?id='.(int)$event['related_record_id']);}
?>
<div class="page-heading"><div><div class="breadcrumb"><a href="<?= e(url('admin/compliance-ledger.php')) ?>">Compliance Ledger</a> <span>/</span> Event</div><h1><?= e($event['event_code']??('Ledger #'.$event['id'])) ?></h1><p>Immutable compliance event details.</p></div><span class="status-badge status-info"><?= e(compliance_event_label($event['event_type'])) ?></span></div>
<section class="panel detail-card ledger-detail"><div class="panel-heading"><div><h2>Event information</h2><p>This view has no edit or delete controls. Corrections require a new ledger event.</p></div></div><dl class="detail-list"><div><dt>Timestamp</dt><dd><?= e(display_date($event['event_timestamp']??$event['created_at'])) ?></dd></div><div><dt>Event type</dt><dd><?= e($event['event_type']) ?></dd></div><div><dt>Establishment</dt><dd><?= $event['establishment_id']?'<a href="'.e(url('admin/establishment-view.php?id='.(int)$event['establishment_id'])).'">'.e($event['business_name']).'</a>':'System-wide' ?></dd></div><div><dt>Related record</dt><dd><?= $relatedUrl?'<a href="'.e($relatedUrl).'">'.e($relatedLabel).'</a>':e($relatedLabel) ?></dd></div><div><dt>Grease trap</dt><dd><?= e($event['trap_code']?($event['trap_code'].' · '.$event['trap_name']):'Not linked') ?></dd></div><div><dt>Device</dt><dd><?= e($event['device_code']?($event['device_code'].' · '.$event['device_name']):'Not linked') ?></dd></div><div><dt>Created by</dt><dd><?= e($event['creator_name']??'System') ?></dd></div><div><dt>Description</dt><dd><?= e($event['description']) ?></dd></div></dl></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
