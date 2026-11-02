<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    api_ok(['user' => mobile_owner()]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: GET, POST');
    api_fail('This request method is not supported.', 405);
}
require dirname(__DIR__, 2) . '/includes/mobile-profile-photo.php';
$owner = mobile_owner();
try {
    $user = mobile_profile_photo_store_bytes(db(), $owner['id'], mobile_profile_photo_json_body());
} catch (MobileProfilePhotoException $error) {
    api_fail($error->getMessage(), $error->status);
}
audit('MOBILE_PROFILE_PHOTO_UPDATED', $owner['id'], 'users', $owner['id']);
api_ok(['user' => $user, 'message' => 'Profile photo updated.']);
