<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require_once dirname(__DIR__, 2) . '/includes/oil-surrender.php';

$owner = mobile_owner();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) {
        api_fail('Select a valid oil surrender.', 422);
    }
    $record = phase5_owner_record(db(), $owner['id'], $id);
    if (!$record) {
        api_fail('Oil surrender was not found.', 404);
    }
    api_ok(['surrender' => $record]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    api_fail('This request method is not supported.', 405);
}
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'multipart/form-data') {
    api_fail('Send the submission as multipart/form-data.', 415);
}
try {
    $result = phase5_submit(db(), $owner['id'], $_POST, $_FILES['photo'] ?? []);
    if ($result['created']) {
        http_response_code(201);
    }
    api_ok(['surrender' => $result['record'], 'idempotent_replay' => !$result['created']]);
} catch (Phase5ValidationException $error) {
    api_fail($error->errors[0] ?? 'Check the surrender details and try again.', 422);
} catch (Phase5ConflictException $error) {
    api_fail($error->getMessage(), 409);
} catch (Phase5RateLimitException $error) {
    header('Retry-After: 3600');
    api_fail($error->getMessage(), 429);
}
