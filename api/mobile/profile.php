<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
api_method('GET');
api_ok(['user' => mobile_owner()]);
