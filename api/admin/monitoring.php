<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/includes/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/monitoring.php';
header('Content-Type: application/json; charset=utf-8');
function monitoring_api_reply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_THROW_ON_ERROR);exit;}
set_exception_handler(function(Throwable $error):void{error_log('Admin monitoring API: '.$error->getMessage());monitoring_api_reply(500,['success'=>false,'message'=>'Monitoring data is temporarily unavailable.']);});
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');monitoring_api_reply(405,['success'=>false,'message'=>'Use GET.']);}
$user=current_user();if(!$user)monitoring_api_reply(401,['success'=>false,'message'=>'Authentication required.']);
if(!in_array($user['role_slug'],['administrator','environmental_staff'],true))monitoring_api_reply(403,['success'=>false,'message'=>'Access denied.']);
monitoring_api_reply(200,['success'=>true,'data'=>phase3_monitoring_latest()]);
