<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (current_user()) {
    redirect('admin/dashboard.php');
}
$errors = [];
$email = '';
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(post_string('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!valid_csrf()) {
        http_response_code(403);
        $errors['form'] = 'This form has expired. Please try signing in again.';
    } else {
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($password === '' || strlen($password) > 1024) {
            $errors['password'] = 'Enter your password (up to 1,024 characters).';
        }
        if (!$errors) {
            $error = attempt_login($email, $password);
            if ($error === null) {
                redirect('admin/dashboard.php');
            }
            $errors['form'] = $error;
        }
    }
}
$pageTitle = 'Sign in';
$authPageClass = 'auth-login';
require dirname(__DIR__) . '/includes/auth-header.php';
?>
<div class="login-intro">
<div class="welcome-icon"><?= icon('shield') ?></div>
<span class="eyebrow">BARANGAY OPERATIONS</span>
<h2>Welcome back.</h2>
<p class="auth-description">Sign in to your AQUASENSE+ workspace.</p>
<div class="development-note"><span class="status-dot"></span><div><strong>Phase 1 · Website foundation</strong><p>This local prototype is not connected to monitoring devices.</p></div></div>
</div>
<div class="login-fields">
<?php if ($notice): ?><div class="notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (isset($errors['form'])): ?><div class="notice notice-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('public/login.php')) ?>" class="login-form">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email address <span class="required">*</span></label>
        <input id="email" name="email" type="email" autocomplete="username" placeholder="you@example.com" maxlength="190" required value="<?= e($email) ?>" <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
        <?php if (isset($errors['email'])): ?><span id="email-error" class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <div class="field-label"><label for="password">Password <span class="required">*</span></label><a href="<?= e(url('public/forgot-password.php')) ?>">Forgot password?</a></div>
        <div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" maxlength="1024" required <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>><button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-password-toggle><?= icon('eye') ?></button></div>
        <?php if (isset($errors['password'])): ?><span id="password-error" class="field-error"><?= e($errors['password']) ?></span><?php endif; ?>
    </div>
    <button class="button button-primary button-full" type="submit">Sign in <?= icon('arrow') ?></button>
</form>
<div class="access-note"><?= icon('shield') ?><p>Access is limited to authorized administrators and environmental staff.</p></div>
</div>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
