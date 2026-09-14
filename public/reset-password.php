<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
// Intentionally no token processing until secure issuance and email delivery exist.
http_response_code(501);
$pageTitle = 'Password reset';
require dirname(__DIR__) . '/includes/auth-header.php';
?>
<div class="welcome-icon"><?= icon('shield') ?></div>
<h2>Password reset is not available yet.</h2>
<p class="auth-description">Self-service recovery is planned for a later update. Contact your Barangay administrator for account assistance.</p>
<a class="button button-primary button-full" href="<?= e(url('public/login.php')) ?>">Back to sign in <?= icon('arrow') ?></a>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
