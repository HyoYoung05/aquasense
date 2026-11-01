<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require dirname(__DIR__, 2) . '/includes/mobile-profile-photo.php';
api_method('GET');
mobile_profile_photo_stream(mobile_owner());
