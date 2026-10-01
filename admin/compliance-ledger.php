<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/compliance.php';
$user=require_staff();$pdo=db();$pageTitle='Compliance Ledger';$activeNav='compliance-ledger';

$event=trim((string)($_GET['event_type']??''));$establishment=(int)($_GET['establishment_id']??0);
$device=(int)($_GET['device_id']??0);$trap=(int)($_GET['grease_trap_id']??0);
$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));$search=trim((string)($_GET['search']??''));
$where=[];$params=[];
if($event!==''){$where[]='cl.event_type=?';$params[]=$event;}
if($establishment>0){$where[]='cl.establishment_id=?';$params[]=$establishment;}
if($device>0){$where[]='cl.device_id=?';$params[]=$device;}
if($trap>0){$where[]='cl.grease_trap_id=?';$params[]=$trap;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$from)){$where[]='cl.event_timestamp>=?';$params[]=$from.' 00:00:00';}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$to)){$where[]='cl.event_timestamp<?';$params[]=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
if($search!==''){$where[]='(cl.event_code LIKE ? OR e.business_name LIKE ? OR os.transaction_code LIKE ? OR it.transaction_code LIKE ?)';$needle='%'.$search.'%';array_push($params,$needle,$needle,$needle,$needle);}
$sql="SELECT cl.*,e.business_name,u.full_name creator_name,d.device_code,g.trap_code,
 CASE WHEN cl.related_record_type='oil_surrenders' THEN os.transaction_code WHEN cl.related_record_type='incentive_transactions' THEN it.transaction_code ELSE CONCAT(cl.related_record_type,' #',cl.related_record_id) END related_code
 FROM compliance_ledger cl LEFT JOIN establishments e ON e.id=cl.establishment_id LEFT JOIN users u ON u.id=cl.created_by
 LEFT JOIN devices d ON d.id=cl.device_id LEFT JOIN grease_traps g ON g.id=cl.grease_trap_id
 LEFT JOIN oil_surrenders os ON cl.related_record_type='oil_surrenders' AND os.id=cl.related_record_id
 LEFT JOIN incentive_transactions it ON cl.related_record_type='incentive_transactions' AND it.id=cl.related_record_id";
if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY cl.event_timestamp DESC,cl.id DESC LIMIT 500';
$q=$pdo->prepare($sql);$q->execute($params);$events=$q->fetchAll();
$types=$pdo->query('SELECT DISTINCT event_type FROM compliance_ledger ORDER BY event_type')->fetchAll(PDO::FETCH_COLUMN);
$establishments=$pdo->query('SELECT id,business_name FROM establishments ORDER BY business_name')->fetchAll();
$devices=$pdo->query('SELECT id,device_code FROM devices ORDER BY device_code')->fetchAll();
$traps=$pdo->query('SELECT id,trap_code FROM grease_traps ORDER BY trap_code')->fetchAll();
require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Compliance Ledger</div><h1>Digital Compliance Ledger</h1><p>Append-only environmental and program events. The technical audit log remains separate.</p></div></div>
<form class="filter-bar ledger-filter" method="get"><input type="search" name="search" value="<?= e($search) ?>" placeholder="Event code, transaction, or business"><select name="event_type"><option value="">All event types</option><?php foreach($types as $type): ?><option value="<?= e($type) ?>"<?= selected($event,$type) ?>><?= e(compliance_event_label($type)) ?></option><?php endforeach; ?></select><select name="establishment_id"><option value="0">All establishments</option><?php foreach($establishments as $row): ?><option value="<?= (int)$row['id'] ?>"<?= selected((string)$establishment,(string)$row['id']) ?>><?= e($row['business_name']) ?></option><?php endforeach; ?></select><select name="device_id"><option value="0">All devices</option><?php foreach($devices as $row): ?><option value="<?= (int)$row['id'] ?>"<?= selected((string)$device,(string)$row['id']) ?>><?= e($row['device_code']) ?></option><?php endforeach; ?></select><select name="grease_trap_id"><option value="0">All grease traps</option><?php foreach($traps as $row): ?><option value="<?= (int)$row['id'] ?>"<?= selected((string)$trap,(string)$row['id']) ?>><?= e($row['trap_code']) ?></option><?php endforeach; ?></select><input type="date" name="from" value="<?= e($from) ?>" aria-label="From date"><input type="date" name="to" value="<?= e($to) ?>" aria-label="To date"><button class="button button-secondary" type="submit"><?= icon('search') ?> Filter</button></form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Ledger events</h2><p><?= count($events) ?> matching events, newest first. Existing events cannot be edited or deleted here.</p></div></div><div class="table-scroll"><table><thead><tr><th>Event code</th><th>Date and time</th><th>Event type</th><th>Establishment</th><th>Related record</th><th>Description</th><th>Created by</th><th>Action</th></tr></thead><tbody><?php foreach($events as $row): ?><tr><td><strong><?= e($row['event_code']??('Ledger #'.$row['id'])) ?></strong></td><td><?= e(display_date($row['event_timestamp']??$row['created_at'])) ?></td><td><span class="status-badge status-info"><?= e(compliance_event_label($row['event_type'])) ?></span></td><td><?= e($row['business_name']??'System-wide') ?></td><td><?= e($row['related_code']??'Not linked') ?></td><td class="wrap-cell"><?= e($row['description']) ?></td><td><?= e($row['creator_name']??'System') ?></td><td><a class="table-action" href="<?= e(url('admin/compliance-ledger-view.php?id='.(int)$row['id'])) ?>">View</a></td></tr><?php endforeach; ?><?php if(!$events): ?><tr><td colspan="8" class="empty-state">No compliance events match the current filters.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
