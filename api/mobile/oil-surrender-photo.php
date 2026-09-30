<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require_once dirname(__DIR__, 2) . '/includes/oil-surrender.php';
api_method('GET');
$owner = mobile_owner();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    api_fail('Photo evidence was not found.', 404);
}
$photo = phase5_photo(db(), $id);
if (!$photo || (int) $photo['owner_user_id'] !== $owner['id'] || (int) $photo['submitted_by'] !== $owner['id']) {
    api_fail('Photo evidence was not found.', 404);
}
phase5_stream_photo($photo);
