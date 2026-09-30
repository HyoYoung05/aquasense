<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/oil-surrender.php';
require_staff();
$id=query_id();$photo=$id?phase5_photo(db(),$id):null;
if(!$photo){http_response_code(404);exit;}
phase5_stream_photo($photo);
