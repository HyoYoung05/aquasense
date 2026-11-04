<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Account help';
require dirname(__DIR__) . '/includes/auth-header.php';
?>
<div class="welcome-icon"><?= icon('mail') ?></div>
<span class="eyebrow">ACCOUNT SUPPORT</span>
<h2>Need help signing in?</h2>
<p class="auth-description">Contact your Barangay administrator for help accessing your account.</p>
<div class="notice"><?= icon('info') ?><div><strong>Ask for a new password.</strong><p>Your Barangay Admin can give you a temporary password. No reset email will be sent. Keep your new password private.</p></div></div>
<a class="button button-primary button-full" href="<?= e(url('public/login.php')) ?>">Back to sign in <?= icon('arrow') ?></a>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
