<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/reports.php';
require_once dirname(__DIR__).'/includes/report-export.php';
$user=require_staff();$format=strtolower(trim((string)($_GET['format']??'')));
if(!in_array($format,['csv','pdf'],true)){http_response_code(400);exit('Unsupported report format.');}
try{$filters=phase7_report_filters($_GET);$limit=$format==='pdf'?500:10000;$data=phase7_report_data(db(),$filters,true,$limit);if($format==='csv'){phase7_audit_export(db(),$user,'csv',$filters);phase7_csv_response($data,$filters);}phase7_pdf_response($data,$filters,$user);}catch(Phase7ReportValidationException $e){http_response_code(422);header('Content-Type: text/plain; charset=utf-8');exit($e->getMessage());}catch(Throwable $e){error_log((string)$e);http_response_code(500);header('Content-Type: text/plain; charset=utf-8');exit('Unable to generate the report. Please try again.');}
