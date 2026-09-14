<?php require __DIR__ . '/auth-header.php'; ?>
<div class="welcome-icon"><?= icon('info') ?></div>
<h2>We couldn’t open your workspace.</h2>
<p class="auth-description">The service is temporarily unavailable. Please try again or contact your administrator.</p>
<a class="button button-primary button-full" href="<?= e(url('public/login.php')) ?>">Return to sign in <?= icon('arrow') ?></a>
<?php require __DIR__ . '/auth-footer.php'; ?>
