<?php
declare(strict_types=1);

function mobile_local_development_origin(string $origin): bool
{
    if (preg_match('~^http://(?:localhost|127\.0\.0\.1):([0-9]{1,5})$~D', $origin, $match) !== 1) {
        return false;
    }
    $port = (int) $match[1];
    return $port >= 1 && $port <= 65535;
}

function mobile_origin_allowed(string $origin, array $settings): bool
{
    $configured = in_array($origin, $settings['mobile_web_origins'] ?? [], true);
    if (($settings['environment'] ?? 'development') === 'production') {
        return $configured && str_starts_with($origin, 'https://');
    }
    return $configured || (!empty($settings['mobile_allow_local_web_preview'])
        && mobile_local_development_origin($origin));
}

function mobile_preflight_allowed(string $method, string $headerLine): bool
{
    $requested = array_values(array_unique(array_filter(array_map(
        static fn (string $header): string => strtolower(trim($header)),
        explode(',', $headerLine)
    ))));
    return in_array($method, ['GET', 'POST'], true)
        && array_diff($requested, ['accept', 'authorization', 'content-type']) === [];
}

function mobile_log_cors(array $settings, string $method, string $origin, bool $allowed, bool $handled): void
{
    if (($settings['environment'] ?? 'development') !== 'development'
        || ($origin === '' && $method !== 'OPTIONS')) {
        return;
    }
    $safeOrigin = preg_replace('/[\x00-\x1F\x7F]/', '?', substr($origin, 0, 200));
    error_log(sprintf('Mobile API CORS method=%s origin=%s allowed=%s options_handled=%s',
        preg_replace('/[^A-Z]/', '', strtoupper($method)),
        $safeOrigin === '' ? '(none)' : $safeOrigin,
        $allowed ? 'yes' : 'no',
        $handled ? 'yes' : 'no'));
}
