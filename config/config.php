<?php
declare(strict_types=1);

define('APP_VERSION', '0.1.10');

// Override local defaults in ignored config/local.php or with environment variables.
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
if (!is_array($local)) {
    $local = [];
}
$config = array_replace([
    'app_name' => 'AQUASENSE+',
    'base_path' => '/aquasense',
    'timezone' => 'Asia/Manila',
    'db_host' => getenv('AQUASENSE_DB_HOST') ?: 'localhost',
    'db_port' => getenv('AQUASENSE_DB_PORT') ?: '3306',
    'db_name' => getenv('AQUASENSE_DB_NAME') ?: 'aquasense',
    'db_user' => getenv('AQUASENSE_DB_USER') ?: 'root',
    'db_password' => getenv('AQUASENSE_DB_PASSWORD') ?: '',
    'session_timeout' => 1800,
    'login_max_attempts' => 5,
    'login_window_minutes' => 15,
], $local);
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php-error.log');

return $config;
