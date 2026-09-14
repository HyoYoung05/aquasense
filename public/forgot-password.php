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
<div class="notice"><?= icon('info') ?><div><strong>Password recovery is coming later.</strong><p>Email delivery and self-service password resets are not enabled in this Phase 1 prototype. No reset email will be sent.</p></div></div>
<a class="button button-primary button-full" href="<?= e(url('public/login.php')) ?>">Back to sign in <?= icon('arrow') ?></a>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
