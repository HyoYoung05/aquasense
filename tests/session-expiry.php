<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';
session_name('aquasense_expiry_test');
session_start();
try {
    // Exactly the inactivity limit must expire before any database access is attempted.
    $_SESSION = ['user_id' => 1, 'last_activity' => time() - (int) $config['session_timeout']];
    $before = session_id();
    $user = current_user();
    if ($user !== null || isset($_SESSION['user_id']) || session_id() === $before
        || !str_contains($_SESSION['notice'] ?? '', 'expired')) {
        throw new RuntimeException('Session inactivity expiry failed.');
    }
    echo "PASS: Inactivity limit clears authentication, rotates the session, and explains expiry.\n";
} finally {
    $_SESSION = [];
    session_destroy();
}
