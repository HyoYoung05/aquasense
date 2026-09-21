<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/includes/mobile-cors.php';
$siteBase = $config['test_base_url'] ?? null;
if (!is_string($siteBase) || !filter_var($siteBase, FILTER_VALIDATE_URL)) {
    throw new RuntimeException('Set AQUASENSE_TEST_BASE_URL or test_base_url in ignored local.php.');
}
$base = rtrim($siteBase, '/') . '/api/mobile/';
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function request(string $endpoint, string $method, array $headers, ?array $body = null): array {
    global $base;
    $curl = curl_init($base . $endpoint);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException(curl_error($curl));
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $result = ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
    curl_close($curl);
    return $result;
}
if (!$config['mobile_allow_local_web_preview']) {
    exit("Enable mobile_allow_local_web_preview in local.php for these local preview checks.\n");
}
$development = ['environment' => 'development', 'mobile_allow_local_web_preview' => true,
    'mobile_web_origins' => ['https://explicit-development.example.test']];
foreach (['http://localhost:49840', 'http://localhost:51732', 'http://127.0.0.1:49840'] as $allowed) {
    check(mobile_origin_allowed($allowed, $development), 'Development policy accepts dynamic origin: ' . $allowed);
    $preflight = ['Origin: ' . $allowed, 'Access-Control-Request-Method: POST',
        'Access-Control-Request-Headers: content-type,authorization,accept'];
    $reply = request('login.php', 'OPTIONS', $preflight);
    check($reply['status'] === 204 && $reply['body'] === '', 'Preflight succeeds before authentication: ' . $allowed);
    check(str_contains($reply['headers'], 'Access-Control-Allow-Origin: ' . $allowed), 'Exact origin echoed: ' . $allowed);
}
$origin = 'http://localhost:49840';
check(str_contains($reply['headers'], 'Access-Control-Allow-Methods: GET, POST, OPTIONS'), 'Only supported API methods advertised');
check(str_contains($reply['headers'], 'Access-Control-Allow-Headers: Content-Type, Authorization, Accept'), 'JSON, bearer and Accept headers permitted');
check(str_contains($reply['headers'], 'Vary: Origin'), 'Caches vary responses by Origin');
check(!str_contains($reply['headers'], 'Access-Control-Allow-Origin: *'), 'Wildcard CORS is never returned');
check(!str_contains($reply['headers'], 'Access-Control-Allow-Credentials'), 'Browser cookie credentials not enabled');
check(mobile_origin_allowed('https://explicit-development.example.test', $development), 'Explicit development origin preserved');
foreach (['http://localhost', 'http://localhost:0', 'http://localhost:65536',
    'http://localhost.evil.example:49840', 'http://192.168.1.20:49840'] as $invalidLocal) {
    check(!mobile_origin_allowed($invalidLocal, $development), 'Invalid local development origin rejected: ' . $invalidLocal);
}
$production = ['environment' => 'production', 'mobile_allow_local_web_preview' => true,
    'mobile_web_origins' => ['https://owners.example.test']];
check(mobile_origin_allowed('https://owners.example.test', $production), 'Production configured HTTPS origin allowed');
check(!mobile_origin_allowed('http://localhost:49840', $production), 'Production rejects localhost despite preview flag');
check(!mobile_origin_allowed('https://evil.example', $production), 'Production rejects unconfigured HTTPS origin');
$invalidProduction = ['environment' => 'production', 'mobile_allow_local_web_preview' => false,
    'mobile_web_origins' => ['http://owners.example.test', '*']];
check(!mobile_origin_allowed('http://owners.example.test', $invalidProduction), 'Production helper rejects configured HTTP origin');
check(!mobile_origin_allowed('https://evil.example', $invalidProduction), 'Production helper never treats wildcard as arbitrary origin');
foreach (['https://evil.example', 'http://localhost.evil.example:54321', 'null', 'http://192.168.1.20:54321'] as $denied) {
    $reply = request('login.php', 'OPTIONS', ['Origin: ' . $denied, 'Access-Control-Request-Method: POST']);
    check($reply['status'] === 403 && !str_contains($reply['headers'], 'Access-Control-Allow-Origin:'), 'Disallowed origin rejected: ' . $denied);
}
$reply = request('login.php', 'OPTIONS', ['Origin: ' . $origin, 'Access-Control-Request-Method: DELETE']);
check($reply['status'] === 403, 'Unsupported preflight method rejected');
$reply = request('login.php', 'OPTIONS', ['Origin: ' . $origin, 'Access-Control-Request-Method: POST', 'Access-Control-Request-Headers: x-arbitrary']);
check($reply['status'] === 403, 'Unsupported preflight header rejected');
$reply = request('profile.php', 'GET', ['Origin: ' . $origin]);
check($reply['status'] === 401 && str_contains($reply['headers'], 'Access-Control-Allow-Origin: ' . $origin), 'Browser can read authentication errors');
// Uses only the documented fictional development account; revoke the issued token.
$token = null;
try {
    $reply = request('login.php', 'POST', ['Origin: ' . $origin, 'Content-Type: application/json'],
        ['email' => 'owner@aquasense.test', 'password' => 'AquaSense!2026']);
    check($reply['status'] === 200 && str_contains($reply['headers'], 'Access-Control-Allow-Origin: ' . $origin), 'Owner login allowed from local Flutter origin');
    $token = json_decode($reply['body'], true, 32, JSON_THROW_ON_ERROR)['data']['token'];
    $reply = request('dashboard.php', 'GET', ['Origin: ' . $origin, 'Authorization: Bearer ' . $token]);
    check($reply['status'] === 200 && str_contains($reply['headers'], 'Access-Control-Allow-Origin: ' . $origin), 'Authorized dashboard returns browser-readable JSON');
    $reply = request('logout.php', 'POST', ['Origin: ' . $origin, 'Authorization: Bearer ' . $token]);
    check($reply['status'] === 200, 'Browser logout revokes the session');
    check(request('profile.php', 'GET', ['Origin: ' . $origin, 'Authorization: Bearer ' . $token])['status'] === 401,
        'Logged-out browser token rejected');
} finally {
    if ($token !== null) request('logout.php', 'POST', ['Authorization: Bearer ' . $token]);
}
echo "$checks checks passed.\n";
