<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require dirname(__DIR__, 2) . '/includes/mobile-account.php';
api_method('POST');
$owner = mobile_owner();
try {
    mobile_account_change_password($owner['id'], api_body());
} catch (MobileAccountException $error) {
    api_fail($error->getMessage(), $error->status);
}
api_ok(['message' => 'Password updated. Sign in again with your new password.']);
