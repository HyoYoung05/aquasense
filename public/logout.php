<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    $pageTitle = 'Sign out';
    require dirname(__DIR__) . '/includes/auth-header.php';
    echo '<h2>Sign out securely.</h2><p>Use the sign-out button in your workspace to end your session.</p><a class="button button-primary" href="' . e(url()) . '">Return to workspace</a>';
    require dirname(__DIR__) . '/includes/auth-footer.php';
    exit;
}
if (!valid_csrf()) {
    http_response_code(403);
    $pageTitle = 'Form expired';
    require dirname(__DIR__) . '/includes/auth-header.php';
    echo '<h2>This form has expired.</h2><p>Return to your workspace and try signing out again.</p><a class="button button-primary" href="' . e(url()) . '">Return to workspace</a>';
    require dirname(__DIR__) . '/includes/auth-footer.php';
    exit;
}
// End access even if the database is unavailable; report audit failure to the server log.
$auditFailed = false;
try {
    if (!empty($_SESSION['user_id'])) {
        audit('LOGOUT', (int) $_SESSION['user_id'], 'users', (int) $_SESSION['user_id']);
    }
} catch (Throwable $exception) {
    error_log('Logout audit failed: ' . (string) $exception);
    $auditFailed = true;
}
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000, 'path' => $cookie['path'],
    'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax',
]);
session_destroy();
session_start();
session_regenerate_id(true);
$_SESSION['notice'] = $auditFailed
    ? 'You have signed out. Your activity could not be recorded; please inform your administrator.'
    : 'You have signed out successfully.';
redirect('public/login.php');
