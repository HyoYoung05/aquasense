<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    global $config;
    return rtrim($config['base_path'], '/') . '/' . ltrim($path, '/');
}

function request_is_https(): bool
{
    global $config;
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remote, $config['trusted_proxy_ips'], true)) {
        return false;
    }
    $forwarded = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
    return $forwarded === 'https';
}

function storage_path(string $relative = ''): string
{
    global $config;
    if (str_contains($relative, chr(0))
        || preg_match('~(^|[\\/])\.\.([\\/]|$)~', $relative)) {
        throw new InvalidArgumentException('Invalid storage path.');
    }
    $root = rtrim((string) $config['storage_path'], '/\\');
    return $relative === '' ? $root : $root . DIRECTORY_SEPARATOR
        . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function valid_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? null;
    return is_string($token) && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function audit(string $action, ?int $userId = null, ?string $recordType = null, ?int $recordId = null): void
{
    $statement = db()->prepare('INSERT INTO audit_logs (user_id, action, record_type, record_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$userId, $action, $recordType, $recordId, client_ip()]);
}

function client_ip(): string
{
    // Do not trust forwarded headers supplied by clients.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function display_date(string $utc): string
{
    global $config;
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone($config['timezone']))->format('M j, Y · g:i A');
}

function icon(string $name, string $class = ''): string
{
    if ($name === 'drop') {
        return '<img class="icon drop-art ' . e($class) . '" src="'
            . e(url('assets/images/water-drop-splash.png'))
            . '" width="1254" height="1254" alt="" aria-hidden="true" decoding="async">';
    }

    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'store' => '<path d="M3 10 5 4h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M5 13v7h14v-7M10 20v-5h4v5"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'oil' => '<path d="M9 3h6v4l4 4v10H5V11l4-4V3ZM9 7h6M5 14h14"/>',
        'gift' => '<path d="M3 9h18v4H3zM5 13v8h14v-8M12 9v12M12 9C4 9 5 2 8 3c3 0 4 6 4 6Zm0 0c8 0 7-7 4-6-3 0-4 6-4 6Z"/>',
        'ledger' => '<path d="M5 3h14v18H5zM9 7h6M9 11h6M9 15h4M3 6h3M3 12h3M3 18h3"/>',
        'chart' => '<path d="M4 3v18h17M9 16v-5M14 16V6M19 16v-8"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 4v2"/>',
        'settings' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
        'logout' => '<path d="M9 4H4v16h5M10 12h11m-4-4 4 4-4 4"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'leaf' => '<path d="M20 3C8 2 2 9 5 16s17 5 15-13ZM5 21 16 9"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
    ];
    return '<svg class="icon ' . e($class) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? $paths['info']) . '</svg>';
}
