<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/oil-surrender.php';
$user=require_staff();
$id=query_id();$photo=$id?phase5_photo(db(),$id):null;
if(!$photo){http_response_code(404);exit;}
$download=($_GET['download']??'')==='1';
if($download)audit('OIL_SURRENDER_PHOTO_DOWNLOADED',(int)$user['id'],'oil_surrender_photos',(int)$photo['id']);
phase5_stream_photo($photo,$download);
