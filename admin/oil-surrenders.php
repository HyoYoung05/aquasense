<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/oil-surrender.php';
$user = require_staff();
$pdo = db();
$statuses = ['PENDING','UNDER_REVIEW','APPROVED','REJECTED'];
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
$unit = (string) ($_GET['unit'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$establishmentId = filter_input(INPUT_GET, 'establishment_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null;
$reviewerId = filter_input(INPUT_GET, 'reviewer_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null;
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$conditions=[];$parameters=[];
if(in_array($status,$statuses,true)){$conditions[]='os.status=?';$parameters[]=$status;}else{$status='';}
if(in_array($unit,['L','kg'],true)){$conditions[]='os.oil_unit=?';$parameters[]=$unit;}else{$unit='';}
if($establishmentId){$conditions[]='os.establishment_id=?';$parameters[]=$establishmentId;}
if($reviewerId){$conditions[]='os.reviewed_by=?';$parameters[]=$reviewerId;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$dateFrom)){$conditions[]='os.surrendered_at>=?';$parameters[]=$dateFrom.' 00:00:00';}else{$dateFrom='';}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$dateTo)){$conditions[]='os.surrendered_at<?';$parameters[]=gmdate('Y-m-d 00:00:00',strtotime($dateTo.' +1 day'));}else{$dateTo='';}
if($search!==''){$conditions[]='(os.transaction_code LIKE ? OR e.business_name LIKE ? OR e.owner_name LIKE ?)';$like='%'.$search.'%';array_push($parameters,$like,$like,$like);}
$where=$conditions?'WHERE '.implode(' AND ',$conditions):'';
$query=$pdo->prepare("SELECT os.id,os.transaction_code,os.oil_quantity,os.oil_unit,os.surrendered_at,
 os.verification_status,os.status,e.business_name,e.owner_name,r.full_name reviewer_name,
 p.id photo_id FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id
 LEFT JOIN users r ON r.id=os.reviewed_by LEFT JOIN oil_surrender_photos p ON p.id=(SELECT p2.id
 FROM oil_surrender_photos p2 WHERE p2.oil_surrender_id=os.id ORDER BY p2.id LIMIT 1)
 $where ORDER BY FIELD(os.status,'PENDING','UNDER_REVIEW','APPROVED','REJECTED'),os.surrendered_at DESC,os.id DESC LIMIT 500");
$query->execute($parameters);$rows=$query->fetchAll();
$countRows=$pdo->query('SELECT status,COUNT(*) total FROM oil_surrenders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts=[];foreach($statuses as $value)$counts[$value]=(int)($countRows[$value]??0);
$establishments=$pdo->query('SELECT id,business_name FROM establishments ORDER BY business_name')->fetchAll();
$reviewers=$pdo->query("SELECT u.id,u.full_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('administrator','environmental_staff') ORDER BY u.full_name")->fetchAll();
$pageTitle='Oil Surrenders';$activeNav='oil-surrenders';require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Operations <span>/</span> Oil surrenders</div><h1>Oil surrender review queue</h1><p>Owner evidence awaiting Barangay Hybrid Verification.</p></div></div>
<section class="stat-grid compact-stats" aria-label="Oil surrender counts"><?php foreach([['Pending review',$counts['PENDING'],'sand'],['Under review',$counts['UNDER_REVIEW'],'blue'],['Approved',$counts['APPROVED'],'mint'],['Rejected',$counts['REJECTED'],'rose']] as [$label,$value,$color]): ?><article class="stat-card"><span class="feature-icon <?= e($color) ?>"><?= icon('oil') ?></span><div><strong><?= $value ?></strong><span><?= e($label) ?></span></div></article><?php endforeach; ?></section>
<form method="get" class="panel filter-panel"><div class="form-grid"><label class="form-field"><span>Search</span><input name="q" value="<?= e($search) ?>" placeholder="Transaction, business, or owner"></label><label class="form-field"><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach($statuses as $value): ?><option value="<?= e($value) ?>"<?= selected($status,$value) ?>><?= e(str_replace('_',' ',$value)) ?></option><?php endforeach; ?></select></label><label class="form-field"><span>Establishment</span><select name="establishment_id"><option value="">All establishments</option><?php foreach($establishments as $site): ?><option value="<?= (int)$site['id'] ?>"<?= selected($establishmentId,$site['id']) ?>><?= e($site['business_name']) ?></option><?php endforeach; ?></select></label><label class="form-field"><span>Reviewer</span><select name="reviewer_id"><option value="">All reviewers</option><?php foreach($reviewers as $reviewer): ?><option value="<?= (int)$reviewer['id'] ?>"<?= selected($reviewerId,$reviewer['id']) ?>><?= e($reviewer['full_name']) ?></option><?php endforeach; ?></select></label><label class="form-field"><span>Unit</span><select name="unit"><option value="">All units</option><option value="L"<?= selected($unit,'L') ?>>L</option><option value="kg"<?= selected($unit,'kg') ?>>kg</option></select></label><label class="form-field"><span>From</span><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label><label class="form-field"><span>To</span><input type="date" name="date_to" value="<?= e($dateTo) ?>"></label></div><div class="form-actions"><button class="button button-primary" type="submit">Apply filters</button><a class="button button-secondary" href="<?= e(url('admin/oil-surrenders.php')) ?>">Clear</a></div></form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Review queue</h2><p><?= count($rows) ?> matching submissions. Pending and under-review records appear first.</p></div></div><div class="table-scroll"><table><thead><tr><th>Transaction</th><th>Establishment</th><th>Owner</th><th>Quantity</th><th>Submitted</th><th>Evidence</th><th>Verification</th><th>Status</th><th>Reviewer</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= e($row['transaction_code']) ?></strong></td><td><?= e($row['business_name']) ?></td><td><?= e($row['owner_name']) ?></td><td><?= e(number_format((float)$row['oil_quantity'],3).' '.$row['oil_unit']) ?></td><td><?= e(display_date($row['surrendered_at'])) ?></td><td><?= $row['photo_id']?'Photo attached':'Missing' ?></td><td><?= e(str_replace('_',' ',$row['verification_status'])) ?></td><td><span class="status-badge status-<?= e(strtolower(str_replace('_','-',$row['status']))) ?>"><?= e(str_replace('_',' ',$row['status'])) ?></span></td><td><?= e($row['reviewer_name']??'Unassigned') ?></td><td><a class="table-action" href="<?= e(url('admin/oil-surrender-view.php?id='.(int)$row['id'])) ?>">Review</a></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="10" class="empty-state">No oil surrenders match these filters.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
