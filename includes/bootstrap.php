<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/functions.php';
require_once dirname(__DIR__) . '/config/database.php';

set_exception_handler(function (Throwable $exception): void {
    // Details stay in the protected server log, never in the HTTP response.
    error_log((string) $exception);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "AQUASENSE+ could not complete the request. Check the protected application log.\n");
        exit(1);
    }
    http_response_code(503);
    $pageTitle = 'Service unavailable';
    require __DIR__ . '/error.php';
    exit;
});

if (PHP_SAPI !== 'cli') {
    if ($config['require_https'] && !request_is_https()) {
        http_response_code(426);
        header('Upgrade: TLS/1.2');
        header('Content-Type: text/plain; charset=utf-8');
        exit('AQUASENSE+ requires HTTPS.');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('Cache-Control: no-store, private');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('aquasense_session');
    session_cache_limiter('');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim($config['base_path'], '/') . '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if ($config['environment'] === 'production') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

require_once __DIR__ . '/auth.php';
