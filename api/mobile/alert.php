<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/includes/mobile-api.php';
require dirname(__DIR__,2).'/includes/mobile-alerts.php';
api_method('GET');$owner=mobile_owner();$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)api_fail('Choose a valid alert.',422);$alert=mobile_alert_detail($owner['id'],(int)$id);if(!$alert)api_fail('The alert was not found.',404);api_ok(['alert'=>$alert]);
