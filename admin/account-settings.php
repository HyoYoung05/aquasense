<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/website-account.php';
$user = require_staff();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        http_response_code(403);
        $errors[] = 'The form expired. Reload this page and try again.';
    } else {
        try {
            website_account_update(db(), (int) $user['id'], $_POST);
            session_regenerate_id(true);
            flash('success', post_string('section') === 'password' ? 'Your password was changed.' : 'Your contact details were saved. Use your email or contact number to sign in.');
            redirect('admin/account-settings.php');
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        }
    }
}
$query = db()->prepare('SELECT email,contact_number FROM users WHERE id=?');
$query->execute([$user['id']]);
$account = $query->fetch();
$pageTitle = 'Settings';
$activeNav = 'account-settings';
$flash = consume_flash();
require dirname(__DIR__) . '/includes/header.php';
$passwordField = static function(string $id, string $label, string $name, bool $new = false): void { ?>
    <div class="form-field"><label for="<?= e($id) ?>"><?= e($label) ?> *</label><div class="password-field">
        <input id="<?= e($id) ?>" type="password" name="<?= e($name) ?>" required maxlength="<?= $new ? '128' : '1024' ?>"<?= $new ? ' minlength="8"' : '' ?> autocomplete="<?= $new ? 'new-password' : 'current-password' ?>">
        <button class="password-toggle" type="button" data-password-toggle data-password-target="<?= e($id) ?>" aria-label="Show <?= e(strtolower($label)) ?>" aria-pressed="false"><?= icon('eye') ?></button>
    </div></div>
<?php }; ?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Settings</div><h1>Settings</h1><p>Manage your own sign-in details and find help for the website.</p></div></div>
<?php if ($flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Your account</h2><p><?= e($user['full_name']) ?> &middot; <?= e(workspace_role_label($user)) ?></p></div></div></section>
<form method="post" class="panel record-form">
    <?= csrf_field() ?><input type="hidden" name="section" value="contact">
    <div class="panel-heading"><div><h2>Email and contact number</h2><p>Email is optional. A contact number is required. Enter your current password to save changes.</p></div></div>
    <div class="form-grid">
        <label class="form-field"><span>Email address</span><input type="email" name="email" maxlength="190" autocomplete="email" value="<?= e(($_POST['section'] ?? '') === 'contact' ? post_string('email') : $account['email']) ?>"></label>
        <label class="form-field"><span>Contact number *</span><input type="tel" name="contact_number" required maxlength="30" autocomplete="tel" value="<?= e(($_POST['section'] ?? '') === 'contact' ? post_string('contact_number') : $account['contact_number']) ?>"></label>
        <?php $passwordField('contact-password', 'Current password', 'current_password'); ?>
    </div><div class="form-actions"><button class="button button-primary" type="submit">Save contact details</button></div>
</form>
<form method="post" class="panel record-form">
    <?= csrf_field() ?><input type="hidden" name="section" value="password">
    <div class="panel-heading"><div><h2>Password</h2><p>Replace a temporary password or choose a new one.</p></div></div>
    <div class="form-grid">
        <?php $passwordField('current-password', 'Current password', 'current_password'); ?>
        <?php $passwordField('new-password', 'New password', 'new_password', true); ?>
        <?php $passwordField('confirm-password', 'Confirm new password', 'password_confirmation', true); ?>
    </div><p class="threshold-help">Use 8–128 characters with an uppercase letter, a lowercase letter, a number, and a symbol. Keep your password private.</p>
    <div class="form-actions"><button class="button button-primary" type="submit">Change password</button></div>
</form>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Help and guides</h2><p>Read the walkthrough and user manual for your role.</p></div><a class="button button-secondary" href="<?= e(url('admin/user-guide.php')) ?>">Open user manual</a></div></section>
<?php if (can_manage_administration($user)): ?>
<section class="panel section-panel"><div class="panel-heading"><div><h2>Alert Settings</h2><p>Manage shared alert limits and oil surrender photo settings.</p></div><a class="button button-secondary" href="<?= e(url('admin/settings.php')) ?>">Open Alert Settings</a></div></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
