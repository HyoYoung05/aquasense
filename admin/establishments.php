<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-core.php';
$user=require_staff();$pageTitle='Establishments';$activeNav='establishments';$canManage=$user['role_slug']==='administrator';
$errors=[];$action=$_GET['action']??'list';$editId=query_id();
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canManage){http_response_code(403);$errors[]='Only Barangay Administrators can change establishment records.';}
    elseif(!valid_csrf()){http_response_code(403);$errors[]='The form expired. Reload the page and try again.';}
    else try{
        $postAction=post_string('action');$id=post_id('id');
        if($postAction==='create'){$id=phase2_create_establishment($_POST,(int)$user['id']);flash('success','Establishment registered.');redirect('admin/establishment-view.php?id='.$id);}
        if($postAction==='update'&&$id){phase2_update_establishment($id,$_POST,(int)$user['id']);flash('success','Establishment updated.');redirect('admin/establishment-view.php?id='.$id);}
        if($postAction==='status'&&$id){phase2_set_establishment_active($id,post_string('active')==='1',(int)$user['id']);flash('success','Establishment status updated.');redirect('admin/establishment-view.php?id='.$id);}
        throw new Phase2ValidationException(['Invalid establishment action.']);
    }catch(Phase2ValidationException $error){$errors=$error->errors;$action=in_array(post_string('action'),['create','update'],true)?(post_string('action')==='create'?'add':'edit'):'list';$editId=post_id('id');}
}
$form=['registration_code'=>'','business_name'=>'','owner_name'=>'','address'=>'','contact_number'=>'','email'=>'','notes'=>'','registration_date'=>date('Y-m-d')];
if($action==='edit'&&$editId){$q=db()->prepare('SELECT * FROM establishments WHERE id=?');$q->execute([$editId]);$record=$q->fetch();if(!$record){http_response_code(404);$errors[]='Establishment was not found.';$action='list';}else $form=array_replace($form,$record);}
if($errors&&in_array($action,['add','edit'],true))foreach($form as $key=>$value)if(isset($_POST[$key])&&is_string($_POST[$key]))$form[$key]=trim($_POST[$key]);
$search=trim((string)($_GET['search']??''));$status=$_GET['status']??'all';if(!in_array($status,['all','active','inactive'],true))$status='all';
$page=max(1,(int)($_GET['page']??1));$limit=15;$where=[];$params=[];
if($search!==''){$where[]='(e.business_name LIKE ? OR e.owner_name LIKE ? OR e.registration_code LIKE ? OR e.address LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term);}
if($status!=='all'){$where[]='e.is_active=?';$params[]=$status==='active'?1:0;}$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$q=db()->prepare("SELECT COUNT(*) FROM establishments e $whereSql");$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);$offset=($page-1)*$limit;
$q=db()->prepare("SELECT e.*,(SELECT COUNT(*) FROM grease_traps g WHERE g.establishment_id=e.id) trap_count,
 (SELECT COUNT(*) FROM device_assignments a JOIN grease_traps g2 ON g2.id=a.grease_trap_id WHERE g2.establishment_id=e.id AND a.ended_at IS NULL) device_count
 FROM establishments e $whereSql ORDER BY e.business_name LIMIT $limit OFFSET $offset");$q->execute($params);$rows=$q->fetchAll();$flash=consume_flash();
require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Operations <span>/</span> Establishments</div><h1><?= $action==='add'?'Register establishment':($action==='edit'?'Edit establishment':'Establishments') ?></h1><p>Maintain participating business records without deleting their history.</p></div><?php if($canManage&&$action==='list'): ?><a class="button button-primary" href="<?= e(url('admin/establishments.php?action=add')) ?>"><?= icon('plus') ?> Add establishment</a><?php endif; ?></div>
<?php if($flash): ?><div class="notice notice-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if($errors): ?><div class="notice notice-error" role="alert"><strong>Check the form:</strong><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if(in_array($action,['add','edit'],true)&&$canManage): ?>
<form class="panel record-form" method="post" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="<?= $action==='add'?'create':'update' ?>"><?php if($editId): ?><input type="hidden" name="id" value="<?= (int)$editId ?>"><?php endif; ?>
<div class="panel-heading"><div><h2>Establishment information</h2><p>Fields marked required are validated on the server.</p></div></div><div class="form-grid">
<?php foreach([['registration_code','Registration code','text',true],['business_name','Business name','text',true],['owner_name','Owner name','text',true],['contact_number','Contact number','tel',false],['email','Email','email',false],['registration_date','Registration date','date',true]] as [$name,$label,$type,$required]): ?><label class="form-field"><span><?= e($label) ?><?= $required?' *':'' ?></span><input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e((string)$form[$name]) ?>"<?= $required?' required':'' ?>></label><?php endforeach; ?>
<label class="form-field form-span"><span>Address *</span><textarea name="address" rows="3" required><?= e((string)$form['address']) ?></textarea></label><label class="form-field form-span"><span>Notes</span><textarea name="notes" rows="4" maxlength="4000"><?= e((string)($form['notes']??'')) ?></textarea></label></div>
<div class="form-actions"><a class="button button-secondary" href="<?= e(url('admin/establishments.php')) ?>">Cancel</a><button class="button button-primary" type="submit">Save establishment</button></div></form>
<?php else: ?>
<form class="filter-bar" method="get"><label><span class="sr-only">Search establishments</span><input type="search" name="search" value="<?= e($search) ?>" placeholder="Search business, owner, code, or address"></label><label><span class="sr-only">Status</span><select name="status"><option value="all">All statuses</option><option value="active"<?= selected($status,'active') ?>>Active</option><option value="inactive"<?= selected($status,'inactive') ?>>Inactive</option></select></label><button class="button button-secondary" type="submit"><?= icon('search') ?> Search</button></form>
<section class="panel section-panel"><div class="table-scroll"><table><thead><tr><th>Business name</th><th>Owner</th><th>Address</th><th>Contact</th><th>Grease traps</th><th>Assigned devices</th><th>Status</th><th>Registered</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><strong><?= e($row['business_name']) ?></strong><br><span class="muted"><?= e($row['registration_code']) ?></span></td><td><?= e($row['owner_name']) ?></td><td class="wrap-cell"><?= e($row['address']) ?></td><td><?= e($row['contact_number']??'Not provided') ?></td><td><?= (int)$row['trap_count'] ?></td><td><?= (int)$row['device_count'] ?></td><td><span class="status-badge status-<?= $row['is_active']?'active':'inactive' ?>"><?= $row['is_active']?'ACTIVE':'INACTIVE' ?></span></td><td><?= e(date('M j, Y',strtotime($row['registration_date']))) ?></td><td><a class="table-action" href="<?= e(url('admin/establishment-view.php?id='.(int)$row['id'])) ?>">View</a><?php if($canManage): ?> <a class="table-action" href="<?= e(url('admin/establishments.php?action=edit&id='.(int)$row['id'])) ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="9" class="empty-state">No establishments match the current filters.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if($pages>1): ?><nav class="pagination" aria-label="Establishment pages"><?php for($i=1;$i<=$pages;$i++): ?><a class="<?= $i===$page?'current':'' ?>" href="?<?= e(http_build_query(['search'=>$search,'status'=>$status,'page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
