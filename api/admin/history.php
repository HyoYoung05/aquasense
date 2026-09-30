<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/includes/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/monitoring.php';
header('Content-Type: application/json; charset=utf-8');
function history_api_reply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_THROW_ON_ERROR);exit;}
set_exception_handler(function(Throwable $error):void{error_log('Admin history API: '.$error->getMessage());history_api_reply(500,['success'=>false,'message'=>'Historical telemetry is temporarily unavailable.']);});
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');history_api_reply(405,['success'=>false,'message'=>'Use GET.']);}
$user=current_user();if(!$user)history_api_reply(401,['success'=>false,'message'=>'Authentication required.']);
if(!in_array($user['role_slug'],['administrator','environmental_staff'],true))history_api_reply(403,['success'=>false,'message'=>'Access denied.']);
$trap=filter_input(INPUT_GET,'grease_trap_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$trap)history_api_reply(422,['success'=>false,'message'=>'Select a grease trap.']);
try{$data=phase3_monitoring_history((int)$trap,(string)($_GET['range']??'today'),isset($_GET['from'])?(string)$_GET['from']:null,isset($_GET['to'])?(string)$_GET['to']:null,max(1,(int)($_GET['page']??1)));history_api_reply(200,['success'=>true,'data'=>$data]);}
catch(InvalidArgumentException $error){history_api_reply(422,['success'=>false,'message'=>$error->getMessage()]);}
