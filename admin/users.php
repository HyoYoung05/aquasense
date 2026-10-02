<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_administrator();
$activeNav = 'users';
$pageTitle = 'User accounts';
$search = trim((string) ($_GET['search'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
if (!in_array($role, ['', 'administrator', 'environmental_staff', 'owner'], true)) $role = '';
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
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Administration <span>/</span> User accounts</div><h1>User accounts</h1><p>Read-only account directory for role, activation, and owner-link review.</p></div></div>
<div class="notice notice-info" role="status"><strong>Account provisioning is server-managed.</strong> Use the protected <code>scripts/create-admin.php</code> command for the first administrator. Retain approval and audit records for later account changes.</div>
<form class="filter-bar" method="get">
    <label><span class="sr-only">Search user accounts</span><input type="search" name="search" value="<?= e($search) ?>" placeholder="Search name or email"></label>
    <label><span class="sr-only">Role</span><select name="role"><option value="">All roles</option><option value="administrator"<?= selected($role,'administrator') ?>>Administrator</option><option value="environmental_staff"<?= selected($role,'environmental_staff') ?>>Environmental staff</option><option value="owner"<?= selected($role,'owner') ?>>Owner</option></select></label>
    <label><span class="sr-only">Account status</span><select name="status"><option value="">All statuses</option><option value="active"<?= selected($status,'active') ?>>Active</option><option value="inactive"<?= selected($status,'inactive') ?>>Inactive</option></select></label>
    <button class="button button-secondary" type="submit"><?= icon('search') ?> Filter</button>
</form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Account directory</h2><p><?= count($rows) ?> account<?= count($rows)===1?'':'s' ?> shown</p></div></div><div class="table-scroll"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Linked establishments</th><th>Last sign-in</th><th>Created</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong></td><td><?= e($row['email']) ?></td><td><?= e($row['role_name']) ?></td><td><span class="status-badge status-<?= $row['is_active']?'active':'inactive' ?>"><?= $row['is_active']?'ACTIVE':'INACTIVE' ?></span></td><td><?= (int)$row['establishment_count'] ?></td><td><?= $row['last_login_at']?e(display_date($row['last_login_at'])):'Never' ?></td><td><?= e(display_date($row['created_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" class="empty-state">No user accounts match the current filters.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php require dirname(__DIR__) . '/includes/footer.php';
