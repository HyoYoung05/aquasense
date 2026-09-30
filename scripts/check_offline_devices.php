<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/alert-engine.php';
try{$pdo=db();$pdo->beginTransaction();$result=phase4_check_offline_devices($pdo);$pdo->commit();echo json_encode(['success'=>true,'data'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;}
catch(Throwable $error){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Offline checker: '.$error->getMessage());fwrite(STDERR,"Offline check failed.\n");exit(1);}
