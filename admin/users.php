<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/user-accounts.php';
$user = require_administration_manager();
$activeNav = 'users';
$pageTitle = 'User accounts';
$action = (string) ($_GET['action'] ?? 'list');
if (!in_array($action, ['list', 'add', 'edit'], true)) $action = 'list';
$editId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'contact_number' => '',
    'role' => 'administrator',
    'establishment_id' => '',
    'status' => 'active',
];
$assignableRoles = user_account_assignable_roles($user);

if ($action === 'edit') {
    $editQuery = db()->prepare('SELECT u.id,u.full_name,u.email,u.contact_number,u.is_active,r.slug AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $editQuery->execute([$editId]);
    $editAccount = $editQuery->fetch();
    if (!$editAccount) redirect('admin/users.php');
    $form['full_name'] = (string) $editAccount['full_name'];
    $form['email'] = (string) ($editAccount['email'] ?? '');
    $form['contact_number'] = (string) ($editAccount['contact_number'] ?? '');
    $form['role'] = (string) $editAccount['role'];
    $form['status'] = (bool) $editAccount['is_active'] ? 'active' : 'inactive';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operation = post_string('operation');
    $postedUserId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $action = $operation === 'update' ? 'edit' : (in_array($operation, ['delete', 'status'], true) ? 'list' : 'add');
    $editId = $postedUserId;
    foreach (array_keys($form) as $key) if (isset($_POST[$key])) $form[$key] = post_string($key);
    if (!valid_csrf()) {
        http_response_code(403);
        $errors[] = 'The form expired. Reload the page and try again.';
    } else {
        try {
            if ($operation === 'delete') {
                user_account_delete(db(), $postedUserId, (int) $user['id']);
                flash('success', 'User account permanently deleted.');
            } elseif ($operation === 'status') {
                $newStatus = post_string('status');
                if (!in_array($newStatus, ['active', 'inactive'], true)) {
                    throw new UserAccountValidationException(['Choose Active or Inactive status.']);
                }
                user_account_set_status(db(), $postedUserId, $newStatus === 'active', (int) $user['id']);
                flash('success', 'User account status updated.');
            } elseif ($operation === 'update') {
                user_account_update(db(), $postedUserId, $_POST, (int) $user['id']);
                flash('success', 'User account updated successfully.');
            } else {
                user_account_create(db(), $_POST, (int) $user['id']);
                flash('success', 'User account created successfully.');
            }
            redirect('admin/users.php');
        } catch (UserAccountValidationException $error) {
            $errors = $error->errors;
        }
    }
}

$availableEstablishments = db()->query("SELECT id,registration_code,business_name,owner_name FROM establishments WHERE is_active=1 AND owner_user_id IS NULL ORDER BY business_name")->fetchAll();
$search = trim((string) ($_GET['search'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
if (!in_array($role, ['', 'system_admin', 'administrator', 'environmental_staff', 'owner'], true)) $role = '';
if (!in_array($status, ['', 'active', 'inactive'], true)) $status = '';
$where = [];
$parameters = [];
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)';
    $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($search, 0, 100)) . '%';
    $parameters[] = $term;
    $parameters[] = $term;
    $parameters[] = $term;
}
if ($role !== '') { $where[] = 'r.slug=?'; $parameters[] = $role; }
if ($status !== '') { $where[] = 'u.is_active=?'; $parameters[] = $status === 'active' ? 1 : 0; }
$sql = 'SELECT u.id,u.full_name,u.email,u.contact_number,u.is_active,u.last_login_at,u.created_at,r.slug AS role_slug,r.name AS role_name,
    (SELECT COUNT(*) FROM establishments e WHERE e.owner_user_id=u.id) AS establishment_count
    FROM users u JOIN roles r ON r.id=u.role_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY u.is_active DESC,r.name,u.full_name LIMIT 250';
$query = db()->prepare($sql);
$query->execute($parameters);
$rows = $query->fetchAll();
$flash = consume_flash();
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Administration <span>/</span> User accounts</div><h1>User accounts</h1><p>Create and review authorized website and owner-app accounts.</p></div><?php if($action==='list'): ?><a class="button button-primary" href="<?= e(url('admin/users.php?action=add')) ?>"><?= icon('plus') ?> Create account</a><?php endif; ?></div>
<?php if($flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if($errors): ?><div class="notice notice-error" role="alert"><div><strong>The account could not be saved.</strong><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
<?php if($action==='add' || $action==='edit'): ?>
<form class="panel record-form" method="post" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="operation" value="<?= $action==='edit'?'update':'create' ?>">
    <?php if($action==='edit'): ?><input type="hidden" name="user_id" value="<?= (int)$editId ?>"><?php endif; ?>
    <div class="panel-heading"><div><h2><?= $action==='edit'?'Edit user account':'Create user account' ?></h2><p><?= $action==='edit'?'Update the account name, email, or contact number. Status is managed from the account directory.':'System Admin and Barangay Admin account creation is audited. Only a System Admin can create another System Admin.' ?></p></div></div>
    <div class="form-grid">
        <label class="form-field"><span>Full name *</span><input name="full_name" maxlength="150" required autocomplete="name" value="<?= e($form['full_name']) ?>"></label>
        <label class="form-field"><span>Email address</span><input type="email" name="email" maxlength="190" autocomplete="email" value="<?= e($form['email']) ?>"></label>
        <label class="form-field"><span>Contact number *</span><input type="tel" name="contact_number" minlength="7" maxlength="30" required autocomplete="tel" value="<?= e($form['contact_number']) ?>"></label>
        <?php if($action==='edit'): ?>
        <label class="form-field"><span>Role</span><input value="<?= e(user_account_roles()[$form['role']]??$form['role']) ?>" disabled><input type="hidden" name="role" value="<?= e($form['role']) ?>"></label>
        <?php else: ?>
        <label class="form-field"><span>Role *</span><select name="role" required data-user-role><?php foreach($assignableRoles as $slug=>$name): ?><option value="<?= e($slug) ?>"<?= selected($form['role'],$slug) ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label class="form-field form-span" data-owner-establishment><span>Owner establishment *</span><select name="establishment_id"><option value="">Select an unlinked establishment</option><?php foreach($availableEstablishments as $establishment): ?><option value="<?= (int)$establishment['id'] ?>"<?= selected($form['establishment_id'],$establishment['id']) ?>><?= e($establishment['business_name'].' · '.$establishment['registration_code'].' · '.$establishment['owner_name']) ?></option><?php endforeach; ?></select><small>Required only for a Karinderya Owner account.</small></label>
        <div class="form-field"><label for="user-password">Temporary password *</label><div class="password-field"><input id="user-password" type="password" name="password" minlength="8" maxlength="128" required autocomplete="new-password"><button type="button" class="password-toggle" aria-label="Show temporary password" aria-pressed="false" data-password-toggle data-password-target="user-password"><?= icon('eye') ?></button></div></div>
        <div class="form-field"><label for="user-password-confirmation">Confirm password *</label><div class="password-field"><input id="user-password-confirmation" type="password" name="password_confirmation" minlength="8" maxlength="128" required autocomplete="new-password"><button type="button" class="password-toggle" aria-label="Show confirmed password" aria-pressed="false" data-password-toggle data-password-target="user-password-confirmation"><?= icon('eye') ?></button></div></div>
        <?php endif; ?>
    </div>
    <?php if($action==='add'): ?><div class="threshold-help">Use 8–128 characters with uppercase, lowercase, a number, and a symbol. The user can sign in with their email address or required contact number.</div><?php endif; ?>
    <div class="form-actions"><a class="button button-secondary" href="<?= e(url('admin/users.php')) ?>">Cancel</a><button class="button button-primary" type="submit"><?= $action==='edit'?'Save changes':'Create account' ?></button></div>
</form>
<?php else: ?>
<div class="notice notice-info" role="status"><strong>Admin-managed provisioning.</strong> New accounts require a strong temporary password and are recorded in the audit log. Owner accounts must be linked to an active establishment.</div>
<form class="filter-bar user-account-filters" method="get">
    <label><span class="sr-only">Search user accounts</span><input type="search" name="search" value="<?= e($search) ?>" placeholder="Search name, email, or contact"></label>
    <label><span class="sr-only">Role</span><select name="role"><option value="">All roles</option><option value="system_admin"<?= selected($role,'system_admin') ?>>Admin</option><option value="administrator"<?= selected($role,'administrator') ?>>Barangay Administrator</option><option value="environmental_staff"<?= selected($role,'environmental_staff') ?>>Environmental staff</option><option value="owner"<?= selected($role,'owner') ?>>Owner</option></select></label>
    <label><span class="sr-only">Account status</span><select name="status"><option value="">All statuses</option><option value="active"<?= selected($status,'active') ?>>Active</option><option value="inactive"<?= selected($status,'inactive') ?>>Inactive</option></select></label>
    <button class="button button-secondary" type="submit"><?= icon('search') ?> Filter</button>
</form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Account directory</h2><p><?= count($rows) ?> account<?= count($rows)===1?'':'s' ?> shown</p></div></div><div class="table-scroll"><table><thead><tr><th>Name</th><th>Email / contact</th><th>Role</th><th>Status</th><th>Linked establishments</th><th>Last sign-in</th><th>Created</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong></td><td><?= e($row['email'] ?: 'No email') ?><br><span class="muted"><?= e($row['contact_number'] ?: 'No contact') ?></span></td><td><?= e($row['role_name']) ?></td><td class="account-status-cell"><details class="account-status-tree"><summary><span class="status-badge status-<?= $row['is_active']?'active':'inactive' ?>"><?= $row['is_active']?'ACTIVE':'INACTIVE' ?></span></summary><form method="post"><?= csrf_field() ?><input type="hidden" name="operation" value="status"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><ul><li><button type="submit" name="status" value="active"<?= $row['is_active']?' disabled':'' ?>>Active</button></li><li><button type="submit" name="status" value="inactive"<?= !$row['is_active'] || (int)$row['id']===(int)$user['id']?' disabled':'' ?>>Inactive</button></li></ul></form></details></td><td><?= (int)$row['establishment_count'] ?></td><td><?= $row['last_login_at']?e(display_date($row['last_login_at'])):'Never' ?></td><td><?= e(display_date($row['created_at'])) ?></td><td class="account-actions"><div class="account-action-list"><a class="table-action" href="<?= e(url('admin/users.php?action=edit&id='.(int)$row['id'])) ?>">Edit</a><?php if((int)$row['id']!==(int)$user['id']): ?><form method="post" data-confirm="Permanently delete this account? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="operation" value="delete"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><button class="table-action account-delete" type="submit">Delete</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="empty-state">No user accounts match the current filters.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php';
