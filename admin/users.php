<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/user-accounts.php';
$user = require_system_admin();
$activeNav = 'users';
$pageTitle = 'User accounts';
$action = (string) ($_GET['action'] ?? 'list');
if (!in_array($action, ['list', 'add'], true)) $action = 'list';
$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'contact_number' => '',
    'role' => 'administrator',
    'establishment_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = 'add';
    foreach (array_keys($form) as $key) $form[$key] = post_string($key);
    if (!valid_csrf()) {
        http_response_code(403);
        $errors[] = 'The form expired. Reload the page and try again.';
    } else {
        try {
            user_account_create(db(), $_POST, (int) $user['id']);
            flash('success', 'User account created successfully.');
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
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($search, 0, 100)) . '%';
    $parameters[] = $term;
    $parameters[] = $term;
}
if ($role !== '') { $where[] = 'r.slug=?'; $parameters[] = $role; }
if ($status !== '') { $where[] = 'u.is_active=?'; $parameters[] = $status === 'active' ? 1 : 0; }
$sql = 'SELECT u.id,u.full_name,u.email,u.is_active,u.last_login_at,u.created_at,r.slug AS role_slug,r.name AS role_name,
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
<?php if($errors): ?><div class="notice notice-error" role="alert"><div><strong>The account was not created.</strong><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
<?php if($action==='add'): ?>
<form class="panel record-form" method="post" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <div class="panel-heading"><div><h2>Create user account</h2><p>Only full-access Admin users can provision accounts. Every creation is recorded in the audit log.</p></div></div>
    <div class="form-grid">
        <label class="form-field"><span>Full name *</span><input name="full_name" maxlength="150" required autocomplete="name" value="<?= e($form['full_name']) ?>"></label>
        <label class="form-field"><span>Email address *</span><input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($form['email']) ?>"></label>
        <label class="form-field"><span>Contact number</span><input type="tel" name="contact_number" maxlength="30" autocomplete="tel" value="<?= e($form['contact_number']) ?>"></label>
        <label class="form-field"><span>Role *</span><select name="role" required data-user-role><?php foreach(user_account_roles() as $slug=>$name): ?><option value="<?= e($slug) ?>"<?= selected($form['role'],$slug) ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label class="form-field form-span" data-owner-establishment><span>Owner establishment *</span><select name="establishment_id"><option value="">Select an unlinked establishment</option><?php foreach($availableEstablishments as $establishment): ?><option value="<?= (int)$establishment['id'] ?>"<?= selected($form['establishment_id'],$establishment['id']) ?>><?= e($establishment['business_name'].' · '.$establishment['registration_code'].' · '.$establishment['owner_name']) ?></option><?php endforeach; ?></select><small>Required only for a Karinderya Owner account.</small></label>
        <label class="form-field"><span>Temporary password *</span><input type="password" name="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>
        <label class="form-field"><span>Confirm password *</span><input type="password" name="password_confirmation" minlength="12" maxlength="128" required autocomplete="new-password"></label>
    </div>
    <div class="threshold-help">Use 12–128 characters with uppercase, lowercase, a number, and a symbol. Give the temporary password directly to the user through an approved secure channel.</div>
    <div class="form-actions"><a class="button button-secondary" href="<?= e(url('admin/users.php')) ?>">Cancel</a><button class="button button-primary" type="submit">Create account</button></div>
</form>
<?php else: ?>
<div class="notice notice-info" role="status"><strong>Admin-managed provisioning.</strong> New accounts require a strong temporary password and are recorded in the audit log. Owner accounts must be linked to an active establishment.</div>
<form class="filter-bar" method="get">
    <label><span class="sr-only">Search user accounts</span><input type="search" name="search" value="<?= e($search) ?>" placeholder="Search name or email"></label>
    <label><span class="sr-only">Role</span><select name="role"><option value="">All roles</option><option value="system_admin"<?= selected($role,'system_admin') ?>>Admin</option><option value="administrator"<?= selected($role,'administrator') ?>>Barangay Administrator</option><option value="environmental_staff"<?= selected($role,'environmental_staff') ?>>Environmental staff</option><option value="owner"<?= selected($role,'owner') ?>>Owner</option></select></label>
    <label><span class="sr-only">Account status</span><select name="status"><option value="">All statuses</option><option value="active"<?= selected($status,'active') ?>>Active</option><option value="inactive"<?= selected($status,'inactive') ?>>Inactive</option></select></label>
    <button class="button button-secondary" type="submit"><?= icon('search') ?> Filter</button>
</form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Account directory</h2><p><?= count($rows) ?> account<?= count($rows)===1?'':'s' ?> shown</p></div></div><div class="table-scroll"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Linked establishments</th><th>Last sign-in</th><th>Created</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong></td><td><?= e($row['email']) ?></td><td><?= e($row['role_name']) ?></td><td><span class="status-badge status-<?= $row['is_active']?'active':'inactive' ?>"><?= $row['is_active']?'ACTIVE':'INACTIVE' ?></span></td><td><?= (int)$row['establishment_count'] ?></td><td><?= $row['last_login_at']?e(display_date($row['last_login_at'])):'Never' ?></td><td><?= e(display_date($row['created_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" class="empty-state">No user accounts match the current filters.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php';
