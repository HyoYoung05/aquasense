<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/alert-engine.php';

header('Content-Type: application/json; charset=utf-8');

function notification_summary_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    notification_summary_reply(405, ['success' => false, 'message' => 'Use GET.']);
}

$user = current_user();
if (!$user) {
    notification_summary_reply(401, ['success' => false, 'message' => 'Authentication required.']);
}
if (!in_array($user['role_slug'], ['system_admin', 'administrator', 'environmental_staff'], true)) {
    notification_summary_reply(403, ['success' => false, 'message' => 'Access denied.']);
}

notification_summary_reply(200, [
    'success' => true,
    'data' => phase4_unresolved_alert_summary(db()),
]);
