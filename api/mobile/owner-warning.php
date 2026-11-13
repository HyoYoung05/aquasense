<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/includes/mobile-api.php';
require_once dirname(__DIR__,2).'/includes/owner-warning.php';
api_method('POST');
$owner=mobile_owner();
try {
    $result=owner_warning_create(db(),$owner['id'],api_body());
    $result['message']=$result['already_active']
        ? 'This warning is already active. Barangay personnel can resolve it on the website.'
        : 'Warning sent to the website and your owner app.';
    api_ok($result);
} catch (InvalidArgumentException $error) { api_fail($error->getMessage(),422); }
catch (OutOfBoundsException $error) { api_fail($error->getMessage(),404); }
catch (LengthException $error) { header('Retry-After: 10'); api_fail($error->getMessage(),429); }
