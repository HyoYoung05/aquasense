<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/includes/mobile-api.php';
require dirname(__DIR__,2).'/includes/mobile-alerts.php';
api_method('GET');$owner=mobile_owner();
try{$data=mobile_alerts($owner['id'],$_GET);}catch(InvalidArgumentException $e){api_fail($e->getMessage(),422);}
if(isset($data['unauthorized_trap']))api_fail('The grease trap was not found.',404);
api_ok($data);
