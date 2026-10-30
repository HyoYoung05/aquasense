<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/device-support.php';

$user = require_staff();
$pageTitle = 'Device Support';
$activeNav = 'device-support';
$allowed = can_use_device_support($user);
$isSystemAdmin = is_system_admin($user);
$action = (string) ($_GET['action'] ?? 'list');
if (!in_array($action, ['list', 'new', 'view'], true)) $action = 'list';
$requestId = query_id();
$errors = [];
$form = [
    'device_id' => '',
    'category' => 'CONNECTION',
    'priority' => 'NORMAL',
    'subject' => '',
    'message' => '',
];

if (!$allowed) {
    http_response_code(403);
    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Device Support</div><h1>Access denied</h1><p>Device Support is available only to Barangay Admin and System Admin accounts.</p></div></div>
    <section class="panel placeholder-panel"><span class="feature-icon mint"><?= icon('shield') ?></span><h2>Environmental Management access is read-only</h2><p>Continue using Monitoring and Alerts for environmental operations. A Barangay Admin must contact the System Admin about device problems.</p><a class="button button-primary" href="<?= e(url('admin/dashboard.php')) ?>">Return to dashboard</a></section>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if ($action === 'new' && $isSystemAdmin) $action = 'list';
if ($action === 'view' && !$requestId && $_SERVER['REQUEST_METHOD'] !== 'POST') $action = 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        http_response_code(403);
        $errors[] = 'The form expired. Reload the page and try again.';
    } else {
        try {
            $postAction = post_string('action');
            if ($postAction === 'create') {
                foreach (array_keys($form) as $key) $form[$key] = post_string($key);
                $requestId = device_support_create(db(), $_POST, $user);
                flash('success', 'Device support request sent to the System Admin.');
                redirect('admin/device-support.php?action=view&id=' . $requestId);
            }
            if ($postAction === 'reply') {
                $requestId = post_id('id');
                if (!$requestId) throw new DeviceSupportValidationException(['Select a valid support request.']);
                device_support_reply(db(), $requestId, post_string('message'),
                    $isSystemAdmin ? post_string('status') : null, $user);
                flash('success', $isSystemAdmin ? 'Reply and support status updated.' : 'Reply sent to the System Admin.');
                redirect('admin/device-support.php?action=view&id=' . $requestId);
            }
            throw new DeviceSupportValidationException(['Unsupported support action.']);
        } catch (DeviceSupportValidationException $error) {
            $errors = $error->errors;
            $action = post_string('action') === 'create' ? 'new' : 'view';
        }
    }
}

$devices = [];
$requests = [];
$request = null;
if ($action === 'new') {
    $devices = db()->query('SELECT id,device_code,name,is_active,last_seen_at FROM devices ORDER BY is_active DESC,device_code')->fetchAll();
} elseif ($action === 'view' && $requestId) {
    $request = device_support_get(db(), $requestId, $user);
    if (!$request) {
        http_response_code(404);
        $errors[] = 'The support request was not found.';
        $action = 'list';
    }
}
if ($action === 'list') $requests = device_support_list(db(), $user);

$flash = consume_flash();
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Device Support</div><h1><?= $action === 'new' ? 'Report a device problem' : ($action === 'view' && $request ? e($request['request_code']) : ($isSystemAdmin ? 'Support Inbox' : 'Device Support')) ?></h1><p><?= $isSystemAdmin ? 'Review and respond to device problems reported by Barangay Admin accounts.' : 'Communicate directly with the System Admin about device connection, sensor, power, or firmware problems.' ?></p></div><?php if(!$isSystemAdmin && $action!=='new'): ?><a class="button button-primary" href="<?= e(url('admin/device-support.php?action=new')) ?>"><?= icon('plus') ?> New support request</a><?php endif; ?></div>

<?php if($flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if($errors): ?><div class="notice notice-error" role="alert"><div><strong>The support action was not completed.</strong><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>

<?php if($action === 'new'): ?>
<form class="panel record-form" method="post" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="panel-heading"><div><h2>Device problem</h2><p>Include observable symptoms only. Never paste Wi-Fi passwords, API keys, or database credentials.</p></div></div>
    <div class="form-grid">
        <label class="form-field form-span"><span>Device *</span><select name="device_id" required><option value="">Select a registered device</option><?php foreach($devices as $device): ?><option value="<?= (int)$device['id'] ?>"<?= selected($form['device_id'],$device['id']) ?>><?= e($device['device_code'].' · '.$device['name'].((bool)$device['is_active']?'':' (Inactive)')) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>Problem category *</span><select name="category" required><?php foreach(device_support_categories() as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected($form['category'],$value) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>Priority *</span><select name="priority" required><?php foreach(device_support_priorities() as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected($form['priority'],$value) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="form-field form-span"><span>Subject *</span><input name="subject" maxlength="150" minlength="5" required value="<?= e($form['subject']) ?>" placeholder="Example: AQS-001 stopped sending telemetry"></label>
        <label class="form-field form-span"><span>Problem description *</span><textarea name="message" rows="7" maxlength="3000" minlength="10" required placeholder="Describe what happened, when it started, and what the device currently shows."><?= e($form['message']) ?></textarea></label>
    </div>
    <div class="form-actions"><a class="button button-secondary" href="<?= e(url('admin/device-support.php')) ?>">Cancel</a><button class="button button-primary" type="submit">Send to System Admin</button></div>
</form>

<?php elseif($action === 'view' && $request): ?>
<section class="detail-grid support-overview"><article class="panel detail-card"><div class="panel-heading"><h2>Request information</h2><span class="status-badge status-<?= e(strtolower(str_replace('_','-',$request['status']))) ?>"><?= e(device_support_statuses()[$request['status']] ?? $request['status']) ?></span></div><dl class="detail-list"><div><dt>Subject</dt><dd><?= e($request['subject']) ?></dd></div><div><dt>Device</dt><dd><?= e($request['device_code'].' · '.$request['device_name']) ?></dd></div><div><dt>Category</dt><dd><?= e(device_support_categories()[$request['category']] ?? $request['category']) ?></dd></div><div><dt>Priority</dt><dd><span class="status-badge support-priority-<?= e(strtolower($request['priority'])) ?>"><?= e(device_support_priorities()[$request['priority']] ?? $request['priority']) ?></span></dd></div><div><dt>Reported by</dt><dd><?= e($request['requester_name']) ?></dd></div><div><dt>Assigned System Admin</dt><dd><?= e($request['assigned_name'] ?: 'Awaiting response') ?></dd></div><div><dt>Created</dt><dd><?= e(display_date($request['created_at'])) ?></dd></div></dl></article>
<article class="panel detail-card"><div class="panel-heading"><h2>Device context</h2></div><dl class="detail-list"><div><dt>Device status</dt><dd><?= (bool)$request['device_active']?'Active':'Inactive' ?></dd></div><div><dt>Last seen</dt><dd><?= $request['last_seen_at']?e(display_date($request['last_seen_at'])):'Awaiting first connection' ?></dd></div><div><dt>Security reminder</dt><dd>Do not send passwords, device credentials, or database access details.</dd></div></dl></article></section>

<section class="panel section-panel support-thread"><div class="panel-heading"><div><h2>Conversation</h2><p>Messages between the Barangay Admin and System Admin are retained and audited.</p></div></div><div class="support-messages"><?php foreach($request['messages'] as $message): $fromSystem=$message['role_slug']==='system_admin'; ?><article class="support-message<?= $fromSystem?' from-system':'' ?>"><div><strong><?= e($message['full_name']) ?></strong><span><?= e($fromSystem?'System Admin':'Barangay Admin') ?> · <?= e(display_date($message['created_at'])) ?></span></div><p><?= nl2br(e($message['message'])) ?></p></article><?php endforeach; ?></div></section>

<?php if($request['status'] !== 'CLOSED'): ?><form class="panel record-form support-reply" method="post" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= (int)$request['id'] ?>"><div class="panel-heading"><div><h2>Reply</h2><p><?= $isSystemAdmin?'Respond with troubleshooting guidance and update the request status.':'Send additional observations or confirm whether the problem remains.' ?></p></div></div><div class="form-grid"><label class="form-field form-span"><span>Message *</span><textarea name="message" rows="5" minlength="2" maxlength="3000" required><?= e(post_string('message')) ?></textarea></label><?php if($isSystemAdmin): ?><label class="form-field"><span>Support status *</span><select name="status" required><?php foreach(device_support_statuses() as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected(post_string('status')?:$request['status'],$value) ?>><?= e($label) ?></option><?php endforeach; ?></select></label><?php endif; ?></div><div class="form-actions"><a class="button button-secondary" href="<?= e(url('admin/device-support.php')) ?>">Back to requests</a><button class="button button-primary" type="submit">Send reply</button></div></form><?php else: ?><div class="notice notice-info"><strong>This support request is closed.</strong> It remains available as a read-only technical record.</div><?php endif; ?>

<?php else: ?>
<section class="panel section-panel"><div class="panel-heading"><div><h2><?= $isSystemAdmin?'Reported device problems':'Your support requests' ?></h2><p><?= count($requests) ?> request<?= count($requests)===1?'':'s' ?> shown</p></div></div><div class="table-scroll"><table><thead><tr><th>Request</th><th>Device</th><?php if($isSystemAdmin): ?><th>Barangay Admin</th><?php endif; ?><th>Priority</th><th>Status</th><th>Messages</th><th>Updated</th><th>Action</th></tr></thead><tbody><?php foreach($requests as $row): ?><tr><td><strong><?= e($row['subject']) ?></strong><br><span class="muted"><?= e($row['request_code'].' · '.(device_support_categories()[$row['category']]??$row['category'])) ?></span></td><td><?= e($row['device_code']) ?><br><span class="muted"><?= e($row['device_name']) ?></span></td><?php if($isSystemAdmin): ?><td><?= e($row['requester_name']) ?></td><?php endif; ?><td><span class="status-badge support-priority-<?= e(strtolower($row['priority'])) ?>"><?= e(device_support_priorities()[$row['priority']]??$row['priority']) ?></span></td><td><span class="status-badge status-<?= e(strtolower(str_replace('_','-',$row['status']))) ?>"><?= e(device_support_statuses()[$row['status']]??$row['status']) ?></span></td><td><?= (int)$row['message_count'] ?></td><td><?= e(display_date($row['updated_at'])) ?></td><td><a class="table-action" href="<?= e(url('admin/device-support.php?action=view&id='.(int)$row['id'])) ?>">Open</a></td></tr><?php endforeach; ?><?php if(!$requests): ?><tr><td colspan="<?= $isSystemAdmin?8:7 ?>" class="empty-state"><?= $isSystemAdmin?'No device problems have been reported.':'No support requests yet. Use New support request when a device problem needs System Admin help.' ?></td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php';
