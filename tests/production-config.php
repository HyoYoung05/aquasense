<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$privateRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aquasense-production-check';
putenv('AQUASENSE_IGNORE_LOCAL_CONFIG=1');
putenv('AQUASENSE_APP_ENV=production');
putenv('AQUASENSE_BASE_PATH=');
putenv('AQUASENSE_DB_HOST=db.internal.example');
putenv('AQUASENSE_DB_PORT=3306');
putenv('AQUASENSE_DB_NAME=aquasense_production');
putenv('AQUASENSE_DB_USER=aquasense_app');
putenv('AQUASENSE_DB_PASSWORD=test-placeholder-not-used');
putenv('AQUASENSE_STORAGE_PATH=' . $privateRoot . DIRECTORY_SEPARATOR . 'uploads');
putenv('AQUASENSE_LOG_PATH=' . $privateRoot . DIRECTORY_SEPARATOR . 'aquasense.log');
putenv('AQUASENSE_MOBILE_WEB_ORIGINS=https://owners.example.test');
$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/includes/functions.php';
$checks = 0;
function check_production(bool $result, string $label): void {
    global $checks;
    if (!$result) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
check_production($config['environment'] === 'production', 'Production environment selected');
check_production($config['require_https'] === true, 'HTTPS is mandatory');
check_production($config['mobile_allow_local_web_preview'] === false, 'Local browser preview forced off');
check_production($config['db_host'] === 'db.internal.example', 'Database host comes from environment');
check_production($config['db_password'] === 'test-placeholder-not-used', 'Database secret comes from environment');
check_production($config['storage_path'] === $privateRoot . DIRECTORY_SEPARATOR . 'uploads', 'Storage path is configurable');
check_production($config['log_path'] === $privateRoot . DIRECTORY_SEPARATOR . 'aquasense.log', 'Log path is configurable');
check_production($config['mobile_web_origins'] === ['https://owners.example.test'], 'Exact HTTPS web origin parsed');
$_SERVER = ['REMOTE_ADDR' => '203.0.113.5', 'HTTPS' => 'on'];
check_production(request_is_https(), 'Direct HTTPS detected');
$_SERVER = ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_PROTO' => 'https'];
check_production(!request_is_https(), 'Untrusted forwarded protocol ignored');
check_production(storage_path('surrenders/photo.jpg')
    === $privateRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'surrenders'
    . DIRECTORY_SEPARATOR . 'photo.jpg', 'Portable private storage path constructed');
try {
    storage_path('../config/local.php');
    check_production(false, 'Storage traversal rejected');
} catch (InvalidArgumentException) {
    check_production(true, 'Storage traversal rejected');
}
echo $checks . " checks passed.\n";
