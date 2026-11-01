<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require dirname(__DIR__, 2) . '/includes/mobile-profile-photo.php';
api_method('POST');
$owner = mobile_owner();
try {
    $user = mobile_profile_photo_store(db(), $owner['id'], $_FILES['photo'] ?? []);
} catch (MobileProfilePhotoException $error) {
    api_fail($error->getMessage(), $error->status);
}
audit('MOBILE_PROFILE_PHOTO_UPDATED', $owner['id'], 'users', $owner['id']);
api_ok(['user' => $user, 'message' => 'Profile photo updated.']);
