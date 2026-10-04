<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
require dirname(__DIR__, 2) . '/includes/mobile-monitoring.php';
api_method('GET');
$owner = mobile_owner();
api_ok(mobile_monitoring($owner['id']));
