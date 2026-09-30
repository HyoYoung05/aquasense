<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require_once dirname(__DIR__, 2) . '/includes/oil-surrender.php';
api_method('GET');
$owner = mobile_owner();
$status = isset($_GET['status']) && $_GET['status'] !== '' ? strtoupper(trim((string) $_GET['status'])) : null;
try {
    api_ok(['surrenders' => phase5_owner_history(db(), $owner['id'], $status)]);
} catch (Phase5ValidationException $error) {
    api_fail($error->errors[0], 422);
}
