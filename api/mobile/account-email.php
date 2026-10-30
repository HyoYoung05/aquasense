<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require dirname(__DIR__, 2) . '/includes/mobile-account.php';
api_method('POST');
$owner = mobile_owner();
try {
    $user = mobile_account_change_email($owner['id'], api_body());
} catch (MobileAccountException $error) {
    api_fail($error->getMessage(), $error->status);
}
api_ok(['user' => $user, 'message' => 'Email address updated.']);
