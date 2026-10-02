<?php
declare(strict_types=1);

define('APP_VERSION', '0.9.0');

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$readEnvironment = static function (string $name): ?string {
    $value = getenv($name);
    return $value === false || trim($value) === '' ? null : trim($value);
};
$readList = static function (?string $value): array {
    return $value === null ? [] : array_values(array_filter(array_map('trim', explode(',', $value))));
};

// Machine and production secrets belong in server environment variables or the
// ignored local.php. AQUASENSE_IGNORE_LOCAL_CONFIG supports deployment checks.
$ignoreLocal = $readEnvironment('AQUASENSE_IGNORE_LOCAL_CONFIG') === '1';
$local = !$ignoreLocal && is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
if (!is_array($local)) {
    $local = [];
}
$environmentOverrides = array_filter([
    'environment' => $readEnvironment('AQUASENSE_APP_ENV'),
    'base_path' => $readEnvironment('AQUASENSE_BASE_PATH'),
    'db_host' => $readEnvironment('AQUASENSE_DB_HOST'),
    'db_port' => $readEnvironment('AQUASENSE_DB_PORT'),
    'db_name' => $readEnvironment('AQUASENSE_DB_NAME'),
    'db_user' => $readEnvironment('AQUASENSE_DB_USER'),
    'db_password' => $readEnvironment('AQUASENSE_DB_PASSWORD'),
    'storage_path' => $readEnvironment('AQUASENSE_STORAGE_PATH'),
    'log_path' => $readEnvironment('AQUASENSE_LOG_PATH'),
    'trusted_proxy_ips' => $readEnvironment('AQUASENSE_TRUSTED_PROXY_IPS') === null
        ? null : $readList($readEnvironment('AQUASENSE_TRUSTED_PROXY_IPS')),
    'mobile_web_origins' => $readEnvironment('AQUASENSE_MOBILE_WEB_ORIGINS') === null
        ? null : $readList($readEnvironment('AQUASENSE_MOBILE_WEB_ORIGINS')),
    'test_base_url' => $readEnvironment('AQUASENSE_TEST_BASE_URL'),
    'telemetry_min_interval_seconds' => $readEnvironment('AQUASENSE_TELEMETRY_MIN_INTERVAL_SECONDS'),
    'telemetry_max_body_bytes' => $readEnvironment('AQUASENSE_TELEMETRY_MAX_BODY_BYTES'),
    'telemetry_future_skew_seconds' => $readEnvironment('AQUASENSE_TELEMETRY_FUTURE_SKEW_SECONDS'),
    'telemetry_max_past_seconds' => $readEnvironment('AQUASENSE_TELEMETRY_MAX_PAST_SECONDS'),
    'telemetry_distance_min_cm' => $readEnvironment('AQUASENSE_TELEMETRY_DISTANCE_MIN_CM'),
    'telemetry_distance_max_cm' => $readEnvironment('AQUASENSE_TELEMETRY_DISTANCE_MAX_CM'),
], static fn (mixed $value): bool => $value !== null);
$config = array_replace([
    'app_name' => 'AQUASENSE+',
    'environment' => 'development',
    'base_path' => '',
    'timezone' => 'Asia/Manila',
    'db_host' => null,
    'db_port' => '3306',
    'db_name' => null,
    'db_user' => null,
    'db_password' => null,
    'storage_path' => dirname(__DIR__) . '/uploads',
    'log_path' => dirname(__DIR__) . '/logs/php-error.log',
    'trusted_proxy_ips' => [],
    'mobile_web_origins' => [],
    'test_base_url' => null,
    'session_timeout' => 1800,
    'mobile_token_lifetime_seconds' => 86400,
    // Explicit opt-in for Flutter's localhost browser preview; disable in deployment.
    'mobile_allow_local_web_preview' => false,
    'login_max_attempts' => 5,
    'login_window_minutes' => 15,
    'telemetry_min_interval_seconds' => 2,
    'telemetry_max_body_bytes' => 4096,
    'telemetry_future_skew_seconds' => 300,
    'telemetry_max_past_seconds' => 604800,
    'telemetry_poll_seconds' => 8,
    'telemetry_distance_min_cm' => 2,
    'telemetry_distance_max_cm' => 400,
], $local, $environmentOverrides);

if (!in_array($config['environment'], ['development', 'production'], true)) {
    throw new RuntimeException('AQUASENSE_APP_ENV must be development or production.');
}
$config['base_path'] = rtrim((string) $config['base_path'], '/');
if ($config['base_path'] !== '' && !str_starts_with($config['base_path'], '/')) {
    throw new RuntimeException('AQUASENSE_BASE_PATH must be empty or begin with /.');
}
foreach (['trusted_proxy_ips', 'mobile_web_origins'] as $listKey) {
    if (!is_array($config[$listKey])) {
        throw new RuntimeException($listKey . ' must be an array.');
    }
}
foreach (['telemetry_min_interval_seconds','telemetry_max_body_bytes','telemetry_future_skew_seconds','telemetry_max_past_seconds','telemetry_poll_seconds'] as $numberKey) {
    if (!is_numeric($config[$numberKey]) || (int)$config[$numberKey] < 1) {
        throw new RuntimeException($numberKey . ' must be a positive integer.');
    }
    $config[$numberKey] = (int)$config[$numberKey];
}
foreach(['telemetry_distance_min_cm','telemetry_distance_max_cm'] as $rangeKey){if(!is_numeric($config[$rangeKey]))throw new RuntimeException($rangeKey.' must be numeric.');$config[$rangeKey]=(float)$config[$rangeKey];}
if($config['telemetry_distance_min_cm']<0||$config['telemetry_distance_max_cm']<=$config['telemetry_distance_min_cm'])throw new RuntimeException('Telemetry distance range is invalid.');
$config['require_https'] = $config['environment'] === 'production';
if ($config['environment'] === 'production') {
    $config['mobile_allow_local_web_preview'] = false;
    foreach ($config['mobile_web_origins'] as $allowedOrigin) {
        if (!str_starts_with($allowedOrigin, 'https://')) {
            throw new RuntimeException('Production mobile web origins must use HTTPS.');
        }
    }
    $explicitStorage = $readEnvironment('AQUASENSE_STORAGE_PATH') !== null
        || array_key_exists('storage_path', $local);
    if (!$explicitStorage) {
        throw new RuntimeException('Production requires an explicit AQUASENSE_STORAGE_PATH.');
    }
}
date_default_timezone_set($config['timezone']);
ini_set('error_log', (string) $config['log_path']);

return $config;
