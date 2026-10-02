<?php
declare(strict_types=1);

final class Phase7ReportValidationException extends RuntimeException {}

function phase7_report_definitions(): array
{
    return [
        'overview'=>['title'=>'Compliance overview','description'=>'Cross-system compliance summary and establishment standing.','date'=>'event_timestamp'],
        'establishment'=>['title'=>'Establishment detail','description'=>'One establishment across telemetry, alerts, surrenders, incentives, and ledger events.','date'=>'event_timestamp'],
        'traps'=>['title'=>'Grease trap monitoring','description'=>'Grease-trap capacity, assignment, latest reading, and current monitoring state.','date'=>'created_at'],
        'telemetry'=>['title'=>'Telemetry readings','description'=>'Sensor readings with calibrated fill level and diagnostic measurements.','date'=>'recorded_at'],
        'alerts'=>['title'=>'Alert history','description'=>'Triggered alerts, lifecycle state, acknowledgment, and resolution.','date'=>'first_triggered_at'],
        'surrenders'=>['title'=>'Oil surrender report','description'=>'Submitted oil quantities and review outcomes.','date'=>'surrendered_at'],
        'incentives'=>['title'=>'Incentive distribution report','description'=>'Calculated rice rewards and distribution status.','date'=>'processed_at'],
        'distribution'=>['title'=>'Rice distribution report','description'=>'Rice rewards with distribution status, actor, and completion time.','date'=>'processed_at'],
        'rules'=>['title'=>'Incentive rule history','description'=>'Effective-dated conversion rules and their current state.','date'=>'created_at'],
        'devices'=>['title'=>'Device status report','description'=>'Registered devices, assignments, connectivity, and last contact.','date'=>'created_at'],
        'ledger'=>['title'=>'Compliance ledger report','description'=>'Append-only environmental and program events.','date'=>'event_timestamp'],
        'audit'=>['title'=>'Technical audit log','description'=>'Authenticated staff and system actions for administrative review.','date'=>'created_at'],
    ];
}

function phase7_report_types(): array { return array_keys(phase7_report_definitions()); }

function phase7_report_period(array $input): array
{
    global $config;
    $tz=new DateTimeZone((string)$config['timezone']);
    $now=new DateTimeImmutable('now',$tz);
    $preset=strtolower(trim((string)($input['period']??'last30')));
    $allowed=['today','last7','week','last30','month','custom'];
    if(!in_array($preset,$allowed,true))throw new Phase7ReportValidationException('Select a valid report period.');
    $today=$now->setTime(0,0);
    switch($preset){
        case 'today':$start=$today;$end=$today->modify('+1 day');$label='Today';break;
        case 'last7':$start=$today->modify('-6 days');$end=$today->modify('+1 day');$label='Last 7 days';break;
        case 'week':$start=$today->modify('monday this week');$end=$start->modify('+7 days');$label='This week (Monday-Sunday)';break;
        case 'last30':$start=$today->modify('-29 days');$end=$today->modify('+1 day');$label='Last 30 days';break;
        case 'month':$start=$today->modify('first day of this month');$end=$start->modify('first day of next month');$label='This month';break;
        default:
            $from=trim((string)($input['from']??''));$to=trim((string)($input['to']??''));
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))throw new Phase7ReportValidationException('Custom reports require valid start and end dates.');
            $start=DateTimeImmutable::createFromFormat('!Y-m-d',$from,$tz);$last=DateTimeImmutable::createFromFormat('!Y-m-d',$to,$tz);
            if(!$start||!$last||$start->format('Y-m-d')!==$from||$last->format('Y-m-d')!==$to)throw new Phase7ReportValidationException('Custom report dates are invalid.');
            if($last<$start)throw new Phase7ReportValidationException('The end date cannot be before the start date.');
            if((int)$start->diff($last)->days>366)throw new Phase7ReportValidationException('Custom report periods are limited to 367 days.');
            $end=$last->modify('+1 day');$label=$start->format('M j, Y').' to '.$last->format('M j, Y');
    }
    $utc=new DateTimeZone('UTC');
    return ['preset'=>$preset,'label'=>$label,'from'=>$start->format('Y-m-d'),'to'=>$end->modify('-1 day')->format('Y-m-d'),'start_utc'=>$start->setTimezone($utc)->format('Y-m-d H:i:s'),'end_utc'=>$end->setTimezone($utc)->format('Y-m-d H:i:s')];
}

function phase7_positive_id(mixed $value,string $label): int
{
    if($value===null||$value===''||$value==='0'||$value===0)return 0;
    $validated=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($validated===false)throw new Phase7ReportValidationException($label.' filter is invalid.');
    return (int)$validated;
}

function phase7_report_filters(array $input): array
{
    $defs=phase7_report_definitions();$type=strtolower(trim((string)($input['report']??'overview')));
    if(!isset($defs[$type]))throw new Phase7ReportValidationException('Select a valid report type.');
    $period=phase7_report_period($input);
    $page=phase7_positive_id($input['page']??1,'Page')?:1;
    $pageSize=(int)($input['page_size']??25);if(!in_array($pageSize,[25,50,100],true))$pageSize=25;
    $status=strtoupper(trim((string)($input['status']??'')));
    if(!preg_match('/^[A-Z_]{0,40}$/',$status))throw new Phase7ReportValidationException('Status filter is invalid.');
    $search=trim((string)($input['search']??''));if(mb_strlen($search)>100)throw new Phase7ReportValidationException('Search is limited to 100 characters.');
    $sort=strtolower(trim((string)($input['sort']??'newest')));if(!in_array($sort,['newest','oldest','establishment','status','quantity'],true))$sort='newest';
    $direction=strtolower(trim((string)($input['direction']??'desc')));if(!in_array($direction,['asc','desc'],true))$direction='desc';
    $unit=trim((string)($input['unit']??''));if(!in_array($unit,['','L','kg','g'],true))throw new Phase7ReportValidationException('Unit filter is invalid.');
    return ['report'=>$type,'period'=>$period,'establishment_id'=>phase7_positive_id($input['establishment_id']??0,'Establishment'),
        'device_id'=>phase7_positive_id($input['device_id']??0,'Device'),'grease_trap_id'=>phase7_positive_id($input['grease_trap_id']??0,'Grease trap'),
        'user_id'=>phase7_positive_id($input['user_id']??0,'User'),'status'=>$status,'unit'=>$unit,'search'=>$search,'sort'=>$sort,'direction'=>$direction,'page'=>$page,'page_size'=>$pageSize];
}

function phase7_report_order(string $type,array $f,string $fallback): string
{
    $map=[
      'telemetry'=>['newest'=>'sr.recorded_at','oldest'=>'sr.recorded_at','establishment'=>'e.business_name','status'=>'sr.level_status'],
      'alerts'=>['newest'=>'a.first_triggered_at','oldest'=>'a.first_triggered_at','establishment'=>'e.business_name','status'=>'a.status'],
      'surrenders'=>['newest'=>'os.surrendered_at','oldest'=>'os.surrendered_at','establishment'=>'e.business_name','status'=>'os.status','quantity'=>'os.oil_quantity'],
      'incentives'=>['newest'=>'it.processed_at','oldest'=>'it.processed_at','establishment'=>'e.business_name','status'=>'it.status','quantity'=>'it.rice_quantity'],
      'distribution'=>['newest'=>'it.processed_at','oldest'=>'it.processed_at','establishment'=>'e.business_name','status'=>'it.status','quantity'=>'it.rice_quantity'],
      'ledger'=>['newest'=>'cl.event_timestamp','oldest'=>'cl.event_timestamp','establishment'=>'e.business_name','status'=>'cl.event_type'],
      'audit'=>['newest'=>'al.created_at','oldest'=>'al.created_at','status'=>'al.action'],
    ];
    $column=$map[$type][$f['sort']]??null;if($column===null)return $fallback;
    $direction=$f['sort']==='oldest'?'ASC':strtoupper($f['direction']);return $column.' '.$direction;
}

function phase7_report_options(PDO $pdo): array
{
    return [
        'establishments'=>$pdo->query('SELECT id,business_name FROM establishments ORDER BY business_name')->fetchAll(),
        'devices'=>$pdo->query('SELECT id,device_code FROM devices ORDER BY device_code')->fetchAll(),
        'traps'=>$pdo->query('SELECT id,trap_code FROM grease_traps ORDER BY trap_code')->fetchAll(),
        'users'=>$pdo->query('SELECT id,full_name FROM users ORDER BY full_name')->fetchAll(),
    ];
}

function phase7_establishment_detail(PDO $pdo,array $f): array
{
    $id=(int)$f['establishment_id'];if($id<1)throw new Phase7ReportValidationException('Select an establishment for the detail report.');$start=$f['period']['start_utc'];$end=$f['period']['end_utc'];
    $q=$pdo->prepare('SELECT registration_code,business_name,owner_name,address,contact_number,email,registration_date,is_active FROM establishments WHERE id=?');$q->execute([$id]);$info=$q->fetch();if(!$info)throw new Phase7ReportValidationException('The selected establishment was not found.');
    $q=$pdo->prepare("SELECT g.trap_code,g.name,g.capacity_liters,g.is_active,d.device_code,d.last_seen_at,sr.waste_level_percent latest_level,sr.temperature_c latest_temperature,sr.level_status,sr.recorded_at,(SELECT AVG(x.waste_level_percent) FROM sensor_readings x JOIN device_assignments xa ON xa.id=x.device_assignment_id WHERE xa.grease_trap_id=g.id AND x.recorded_at>=? AND x.recorded_at<?) average_level,(SELECT MAX(x.waste_level_percent) FROM sensor_readings x JOIN device_assignments xa ON xa.id=x.device_assignment_id WHERE xa.grease_trap_id=g.id AND x.recorded_at>=? AND x.recorded_at<?) maximum_level FROM grease_traps g LEFT JOIN device_assignments da ON da.grease_trap_id=g.id AND da.ended_at IS NULL LEFT JOIN devices d ON d.id=da.device_id LEFT JOIN sensor_readings sr ON sr.id=(SELECT z.id FROM sensor_readings z WHERE z.device_assignment_id=da.id ORDER BY z.recorded_at DESC,z.id DESC LIMIT 1) WHERE g.establishment_id=? ORDER BY g.name");$q->execute([$start,$end,$start,$end,$id]);$traps=$q->fetchAll();
    $q=$pdo->prepare("SELECT COUNT(*) total,SUM(a.severity='WARNING') warning_count,SUM(a.severity='CRITICAL') critical_count,SUM(a.status='RESOLVED') resolved_count,SUM(a.status<>'RESOLVED') unresolved_count FROM alerts a JOIN device_assignments da ON da.id=a.device_assignment_id JOIN grease_traps g ON g.id=da.grease_trap_id WHERE g.establishment_id=? AND a.first_triggered_at>=? AND a.first_triggered_at<?");$q->execute([$id,$start,$end]);$alerts=$q->fetch();
    $q=$pdo->prepare("SELECT status,COUNT(*) count FROM oil_surrenders WHERE establishment_id=? AND surrendered_at>=? AND surrendered_at<? GROUP BY status");$q->execute([$id,$start,$end]);$surrenderStates=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $q=$pdo->prepare("SELECT oil_unit,SUM(oil_quantity) total FROM oil_surrenders WHERE establishment_id=? AND surrendered_at>=? AND surrendered_at<? GROUP BY oil_unit");$q->execute([$id,$start,$end]);$oil=$q->fetchAll();
    $q=$pdo->prepare("SELECT status,COUNT(*) count FROM incentive_transactions WHERE establishment_id=? AND processed_at>=? AND processed_at<? GROUP BY status");$q->execute([$id,$start,$end]);$incentiveStates=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $q=$pdo->prepare("SELECT rice_unit,SUM(rice_quantity) total FROM incentive_transactions WHERE establishment_id=? AND processed_at>=? AND processed_at<? GROUP BY rice_unit");$q->execute([$id,$start,$end]);$rice=$q->fetchAll();
    return compact('info','traps','alerts','surrenderStates','oil','incentiveStates','rice');
}

function phase7_add_common_filters(array $f,string $dateColumn,array &$where,array &$params,array $columns=[]): void
{
    $where[]="$dateColumn>=?";$params[]=$f['period']['start_utc'];$where[]="$dateColumn<?";$params[]=$f['period']['end_utc'];
    foreach(['establishment_id','device_id','grease_trap_id','user_id'] as $key){if($f[$key]&&isset($columns[$key])){$where[]=$columns[$key].'=?';$params[]=$f[$key];}}
}

function phase7_fetch(PDO $pdo,string $select,string $from,array $where,array $params,string $order,array $f,bool $export,int $exportLimit): array
{
    $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $q=$pdo->prepare("SELECT COUNT(*) $from$whereSql");$q->execute($params);$total=(int)$q->fetchColumn();
    $limit=$export?min($exportLimit,10000):$f['page_size'];$offset=$export?0:($f['page']-1)*$f['page_size'];
    $q=$pdo->prepare("SELECT $select $from$whereSql ORDER BY $order LIMIT $limit OFFSET $offset");$q->execute($params);
    return ['rows'=>$q->fetchAll(),'total'=>$total,'truncated'=>$export&&$total>$limit];
}

function phase7_report_data(PDO $pdo,array $f,bool $export=false,int $exportLimit=5000): array
{
    $defs=phase7_report_definitions();$type=$f['report'];$where=[];$params=[];$columns=[];$result=[];$date='';$summaryFrom='';
    switch($type){
        case 'telemetry':
            $date='sr.recorded_at';phase7_add_common_filters($f,$date,$where,$params,['establishment_id'=>'e.id','device_id'=>'d.id','grease_trap_id'=>'g.id']);
            if($f['status']){$where[]='sr.level_status=?';$params[]=$f['status'];}
            if($f['search']!==''){$where[]='(e.business_name LIKE ? OR d.device_code LIKE ? OR g.trap_code LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $summaryFrom='FROM sensor_readings sr JOIN device_assignments da ON da.id=sr.device_assignment_id JOIN devices d ON d.id=da.device_id JOIN grease_traps g ON g.id=da.grease_trap_id JOIN establishments e ON e.id=g.establishment_id';
            $columns=['recorded_at'=>'Recorded (Manila)','business_name'=>'Establishment','trap_code'=>'Grease trap','device_code'=>'Device','ultrasonic_distance_cm'=>'Distance (cm)','waste_level_percent'=>'Fill (%)','temperature_c'=>'Temperature (°C)','turbidity_ntu'=>'Turbidity (NTU)','flow_rate_lpm'=>'Flow (L/min)','gas_value'=>'Gas / odor value','level_status'=>'Status','source'=>'Source'];
            $result=phase7_fetch($pdo,"sr.recorded_at,e.business_name,g.trap_code,d.device_code,sr.ultrasonic_distance_cm,sr.waste_level_percent,sr.temperature_c,sr.turbidity_ntu,sr.flow_rate_lpm,sr.gas_value,sr.level_status,IF(sr.is_simulated=1,'SIMULATED',IF(sr.is_test=1,'TEST','DEVICE')) source",'FROM sensor_readings sr JOIN device_assignments da ON da.id=sr.device_assignment_id JOIN devices d ON d.id=da.device_id JOIN grease_traps g ON g.id=da.grease_trap_id JOIN establishments e ON e.id=g.establishment_id',$where,$params,phase7_report_order($type,$f,'sr.recorded_at DESC,sr.id DESC'),$f,$export,$exportLimit);break;
        case 'alerts':
            $date='a.first_triggered_at';phase7_add_common_filters($f,$date,$where,$params,['establishment_id'=>'e.id','device_id'=>'d.id','grease_trap_id'=>'g.id']);
            if($f['status']){$where[]='(a.status=? OR a.severity=? OR a.alert_type=?)';array_push($params,$f['status'],$f['status'],$f['status']);}
            if($f['search']!==''){$where[]='(e.business_name LIKE ? OR a.alert_type LIKE ? OR a.message LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $summaryFrom='FROM alerts a JOIN device_assignments da ON da.id=a.device_assignment_id JOIN devices d ON d.id=da.device_id JOIN grease_traps g ON g.id=da.grease_trap_id JOIN establishments e ON e.id=g.establishment_id LEFT JOIN users au ON au.id=a.acknowledged_by LEFT JOIN users ru ON ru.id=a.resolved_by';
            $columns=['alert_id'=>'Alert ID','first_triggered_at'=>'First triggered','last_triggered_at'=>'Last triggered','business_name'=>'Establishment','trap_code'=>'Grease trap','device_code'=>'Device','alert_type'=>'Alert type','severity'=>'Severity','status'=>'Status','trigger_count'=>'Triggers','acknowledged_by_name'=>'Acknowledged by','resolved_by_name'=>'Resolved by'];
            $result=phase7_fetch($pdo,'a.id alert_id,a.first_triggered_at,a.last_triggered_at,e.business_name,g.trap_code,d.device_code,a.alert_type,a.severity,a.status,a.trigger_count,au.full_name acknowledged_by_name,ru.full_name resolved_by_name','FROM alerts a JOIN device_assignments da ON da.id=a.device_assignment_id JOIN devices d ON d.id=da.device_id JOIN grease_traps g ON g.id=da.grease_trap_id JOIN establishments e ON e.id=g.establishment_id LEFT JOIN users au ON au.id=a.acknowledged_by LEFT JOIN users ru ON ru.id=a.resolved_by',$where,$params,phase7_report_order($type,$f,'a.first_triggered_at DESC,a.id DESC'),$f,$export,$exportLimit);break;
        case 'surrenders':
            $date='os.surrendered_at';phase7_add_common_filters($f,$date,$where,$params,['establishment_id'=>'e.id','device_id'=>'d.id','grease_trap_id'=>'g.id','user_id'=>'rv.id']);
            if($f['status']){$where[]='os.status=?';$params[]=$f['status'];}
            if($f['unit']){$where[]='os.oil_unit=?';$params[]=$f['unit'];}
            if($f['search']!==''){$where[]='(e.business_name LIKE ? OR os.transaction_code LIKE ?)';array_push($params,...array_fill(0,2,'%'.$f['search'].'%'));}
            $summaryFrom='FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id JOIN users u ON u.id=os.submitted_by LEFT JOIN grease_traps g ON g.id=os.grease_trap_id LEFT JOIN devices d ON d.id=os.device_id LEFT JOIN users rv ON rv.id=os.reviewed_by';
            $columns=['surrendered_at'=>'Surrendered','transaction_code'=>'Transaction','business_name'=>'Establishment','owner_name'=>'Owner','oil_quantity'=>'Oil quantity','oil_unit'=>'Oil unit','status'=>'Status','photo_evidence'=>'Photo evidence','sensor_evidence'=>'Sensor evidence','reviewed_by_name'=>'Reviewer','reviewed_at'=>'Reviewed'];
            $result=phase7_fetch($pdo,"os.surrendered_at,os.transaction_code,e.business_name,e.owner_name,os.oil_quantity,os.oil_unit,os.status,IF(EXISTS(SELECT 1 FROM oil_surrender_photos p WHERE p.oil_surrender_id=os.id),'PRESENT','NOT AVAILABLE') photo_evidence,IF(os.related_sensor_reading_id IS NULL,'NOT AVAILABLE','PRESENT') sensor_evidence,rv.full_name reviewed_by_name,os.reviewed_at",'FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id JOIN users u ON u.id=os.submitted_by LEFT JOIN grease_traps g ON g.id=os.grease_trap_id LEFT JOIN devices d ON d.id=os.device_id LEFT JOIN users rv ON rv.id=os.reviewed_by',$where,$params,phase7_report_order($type,$f,'os.surrendered_at DESC,os.id DESC'),$f,$export,$exportLimit);break;
        case 'distribution':
            if(!$f['status']){$where[]="it.status IN ('CALCULATED','APPROVED_FOR_DISTRIBUTION','DISTRIBUTED')";}
            // Fall through to the shared incentive query.
        case 'incentives':
            $date='it.processed_at';phase7_add_common_filters($f,$date,$where,$params,['establishment_id'=>'e.id','user_id'=>'cb.id']);
            if($f['status']){$where[]='it.status=?';$params[]=$f['status'];}
            if($f['unit']){$where[]='it.rice_unit=?';$params[]=$f['unit'];}
            if($f['search']!==''){$where[]='(e.business_name LIKE ? OR it.transaction_code LIKE ? OR it.rule_name_snapshot LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $summaryFrom='FROM incentive_transactions it JOIN oil_surrenders os ON os.id=it.oil_surrender_id JOIN establishments e ON e.id=it.establishment_id JOIN users cb ON cb.id=it.calculated_by LEFT JOIN users db ON db.id=it.distributed_by';
            $columns=['processed_at'=>'Processed','transaction_code'=>'Incentive transaction','surrender_code'=>'Oil surrender transaction','business_name'=>'Establishment','oil_quantity'=>'Oil quantity','oil_unit'=>'Oil unit','rule_name_snapshot'=>'Applied rule','rice_quantity'=>'Rice reward','rice_unit'=>'Rice unit','status'=>'Status','calculated_by_name'=>'Processed by','distributed_by_name'=>'Distributed by','distributed_at'=>'Distributed'];
            $result=phase7_fetch($pdo,'it.processed_at,it.transaction_code,os.transaction_code surrender_code,e.business_name,it.oil_quantity,it.oil_unit,it.rule_name_snapshot,it.rice_quantity,it.rice_unit,it.status,cb.full_name calculated_by_name,db.full_name distributed_by_name,it.distributed_at','FROM incentive_transactions it JOIN oil_surrenders os ON os.id=it.oil_surrender_id JOIN establishments e ON e.id=it.establishment_id JOIN users cb ON cb.id=it.calculated_by LEFT JOIN users db ON db.id=it.distributed_by',$where,$params,phase7_report_order($type,$f,'it.processed_at DESC,it.id DESC'),$f,$export,$exportLimit);break;
        case 'traps':
            foreach(['establishment_id'=>'e.id','device_id'=>'d.id','grease_trap_id'=>'g.id'] as $key=>$column)if($f[$key]){$where[]=$column.'=?';$params[]=$f[$key];}
            if($f['search']!==''){$where[]='(e.business_name LIKE ? OR g.trap_code LIKE ? OR g.name LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            if($f['status']){$where[]='sr.level_status=?';$params[]=$f['status'];}
            $columns=['created_at'=>'Registered','business_name'=>'Establishment','trap_code'=>'Trap','name'=>'Name','capacity_liters'=>'Capacity (L)','device_code'=>'Assigned device','waste_level_percent'=>'Latest fill (%)','temperature_c'=>'Latest temperature (C)','level_status'=>'Current status','recorded_at'=>'Latest reading'];
            $result=phase7_fetch($pdo,'g.created_at,e.business_name,g.trap_code,g.name,g.capacity_liters,d.device_code,sr.waste_level_percent,sr.temperature_c,sr.level_status,sr.recorded_at','FROM grease_traps g JOIN establishments e ON e.id=g.establishment_id LEFT JOIN device_assignments da ON da.grease_trap_id=g.id AND da.ended_at IS NULL LEFT JOIN devices d ON d.id=da.device_id LEFT JOIN sensor_readings sr ON sr.id=(SELECT x.id FROM sensor_readings x WHERE x.device_assignment_id=da.id ORDER BY x.recorded_at DESC,x.id DESC LIMIT 1)',$where,$params,'e.business_name ASC,g.name ASC',$f,$export,$exportLimit);break;
        case 'rules':
            $date='ir.created_at';phase7_add_common_filters($f,$date,$where,$params,['user_id'=>'u.id']);
            if($f['status']==='ACTIVE')$where[]='ir.is_active=1';elseif($f['status']==='INACTIVE')$where[]='ir.is_active=0';
            if($f['search']!==''){$where[]='ir.name LIKE ?';$params[]='%'.$f['search'].'%';}
            $columns=['created_at'=>'Created','name'=>'Rule','minimum_oil_quantity'=>'Oil threshold','oil_unit'=>'Oil unit','rice_reward_quantity'=>'Rice reward','rice_unit'=>'Rice unit','calculation_type'=>'Calculation','effective_date'=>'Effective','end_date'=>'Ends','state'=>'State','created_by_name'=>'Created by'];
            $result=phase7_fetch($pdo,"ir.created_at,ir.name,ir.minimum_oil_quantity,ir.oil_unit,ir.rice_reward_quantity,ir.rice_unit,ir.calculation_type,ir.effective_date,ir.end_date,IF(ir.is_active=1,'ACTIVE','INACTIVE') state,u.full_name created_by_name",'FROM incentive_rules ir LEFT JOIN users u ON u.id=ir.created_by',$where,$params,'ir.effective_date DESC,ir.id DESC',$f,$export,$exportLimit);break;
        case 'devices':
            $date='COALESCE(d.last_seen_at,d.created_at)';phase7_add_common_filters($f,$date,$where,$params,['device_id'=>'d.id','establishment_id'=>'e.id','grease_trap_id'=>'g.id']);
            if($f['search']!==''){$where[]='(d.device_code LIKE ? OR d.name LIKE ? OR e.business_name LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $timeout=(int)$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='device_offline_timeout_minutes'")->fetchColumn();
            if($f['status']){$state="CASE WHEN d.is_active=0 THEN 'INACTIVE' WHEN d.last_seen_at IS NULL THEN 'NOT CONNECTED' WHEN d.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL $timeout MINUTE) THEN 'ONLINE' ELSE 'OFFLINE' END";$where[]="$state=?";$params[]=$f['status'];}
            $columns=['created_at'=>'Registered','device_code'=>'Device','name'=>'Name','firmware_version'=>'Firmware','business_name'=>'Establishment','trap_code'=>'Grease trap','last_seen_at'=>'Last contact','latest_reading_at'=>'Latest reading','latest_fill'=>'Latest fill (%)','state'=>'Connection','is_active_label'=>'Registration'];
            $select="d.created_at,d.device_code,d.name,d.firmware_version,e.business_name,g.trap_code,d.last_seen_at,sr.recorded_at latest_reading_at,sr.waste_level_percent latest_fill,CASE WHEN d.is_active=0 THEN 'INACTIVE' WHEN d.last_seen_at IS NULL THEN 'NOT CONNECTED' WHEN d.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL $timeout MINUTE) THEN 'ONLINE' ELSE 'OFFLINE' END state,IF(d.is_active=1,'ACTIVE','INACTIVE') is_active_label";
            $result=phase7_fetch($pdo,$select,'FROM devices d LEFT JOIN device_assignments da ON da.device_id=d.id AND da.ended_at IS NULL LEFT JOIN grease_traps g ON g.id=da.grease_trap_id LEFT JOIN establishments e ON e.id=g.establishment_id LEFT JOIN sensor_readings sr ON sr.id=(SELECT x.id FROM sensor_readings x WHERE x.device_assignment_id=da.id ORDER BY x.recorded_at DESC,x.id DESC LIMIT 1)',$where,$params,'d.device_code ASC',$f,$export,$exportLimit);break;
        case 'ledger':
            $date='cl.event_timestamp';phase7_add_common_filters($f,$date,$where,$params,['establishment_id'=>'e.id','device_id'=>'d.id','grease_trap_id'=>'g.id','user_id'=>'u.id']);
            if($f['status']){$where[]='cl.event_type=?';$params[]=$f['status'];}
            if($f['search']!==''){$where[]='(cl.event_code LIKE ? OR cl.description LIKE ? OR e.business_name LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $columns=['event_timestamp'=>'Event time','event_code'=>'Event code','event_type'=>'Event type','business_name'=>'Establishment','trap_code'=>'Grease trap','device_code'=>'Device','related_record_type'=>'Record type','related_record_id'=>'Record ID','description'=>'Description','created_by_name'=>'Created by'];
            $result=phase7_fetch($pdo,'cl.event_timestamp,cl.event_code,cl.event_type,e.business_name,g.trap_code,d.device_code,cl.related_record_type,cl.related_record_id,cl.description,u.full_name created_by_name','FROM compliance_ledger cl LEFT JOIN establishments e ON e.id=cl.establishment_id LEFT JOIN grease_traps g ON g.id=cl.grease_trap_id LEFT JOIN devices d ON d.id=cl.device_id LEFT JOIN users u ON u.id=cl.created_by',$where,$params,phase7_report_order($type,$f,'cl.event_timestamp DESC,cl.id DESC'),$f,$export,$exportLimit);break;
        case 'audit':
            $date='al.created_at';phase7_add_common_filters($f,$date,$where,$params,['user_id'=>'u.id']);
            if($f['status']){$where[]='al.action=?';$params[]=$f['status'];}
            if($f['search']!==''){$where[]='(al.action LIKE ? OR al.record_type LIKE ? OR u.full_name LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $columns=['created_at'=>'Created','full_name'=>'User','role_name'=>'Role','action'=>'Action','record_type'=>'Record type','record_id'=>'Record ID','ip_address'=>'IP address'];
            $result=phase7_fetch($pdo,'al.created_at,u.full_name,r.name role_name,al.action,al.record_type,al.record_id,al.ip_address','FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id LEFT JOIN roles r ON r.id=u.role_id',$where,$params,phase7_report_order($type,$f,'al.created_at DESC,al.id DESC'),$f,$export,$exportLimit);break;
        case 'establishment':
            if(!$f['establishment_id'])throw new Phase7ReportValidationException('Select an establishment for the detail report.');
            $date='cl.event_timestamp';phase7_add_common_filters($f,$date,$where,$params,['establishment_id'=>'e.id']);
            $columns=['event_timestamp'=>'Event time','event_type'=>'Event type','business_name'=>'Establishment','description'=>'Description','related_record_type'=>'Record type','related_record_id'=>'Record ID'];
            $result=phase7_fetch($pdo,'cl.event_timestamp,cl.event_type,e.business_name,cl.description,cl.related_record_type,cl.related_record_id','FROM compliance_ledger cl JOIN establishments e ON e.id=cl.establishment_id',$where,$params,'cl.event_timestamp DESC,cl.id DESC',$f,$export,$exportLimit);break;
        default:
            if($f['establishment_id']){$where[]='e.id=?';$params[]=$f['establishment_id'];}
            if($f['status']==='ACTIVE')$where[]='e.is_active=1';elseif($f['status']==='INACTIVE')$where[]='e.is_active=0';
            if($f['search']!==''){$where[]='(e.business_name LIKE ? OR e.registration_code LIKE ? OR e.owner_name LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
            $columns=['created_at'=>'Registered','registration_code'=>'Registration','business_name'=>'Establishment','owner_name'=>'Owner','address'=>'Address','registration_date'=>'Registration date','standing'=>'Standing','grease_traps'=>'Grease traps','active_alerts'=>'Active alerts','approved_surrenders'=>'Approved surrenders','distributed_rewards'=>'Distributed rewards'];
            $select="e.created_at,e.registration_code,e.business_name,e.owner_name,e.address,e.registration_date,IF(e.is_active=1,'ACTIVE','INACTIVE') standing,(SELECT COUNT(*) FROM grease_traps g WHERE g.establishment_id=e.id) grease_traps,(SELECT COUNT(*) FROM alerts a JOIN device_assignments da ON da.id=a.device_assignment_id JOIN grease_traps g ON g.id=da.grease_trap_id WHERE g.establishment_id=e.id AND a.status IN ('ACTIVE','ACKNOWLEDGED')) active_alerts,(SELECT COUNT(*) FROM oil_surrenders os WHERE os.establishment_id=e.id AND os.status='APPROVED') approved_surrenders,(SELECT COUNT(*) FROM incentive_transactions it WHERE it.establishment_id=e.id AND it.status='DISTRIBUTED') distributed_rewards";
            $result=phase7_fetch($pdo,$select,'FROM establishments e',$where,$params,'e.business_name ASC',$f,$export,$exportLimit);break;
    }
    foreach($result['rows'] as &$row){foreach($row as $key=>&$value){if($value!==null&&preg_match('/(_at|^created_at$)$/',$key))$value=display_date((string)$value);}unset($value);}unset($row);
    $pages=max(1,(int)ceil($result['total']/$f['page_size']));
    return ['type'=>$type,'definition'=>$defs[$type],'columns'=>$columns,'rows'=>$result['rows'],'total'=>$result['total'],'truncated'=>$result['truncated'],'page'=>min($f['page'],$pages),'pages'=>$pages,'summary'=>phase7_report_summary($pdo,$f,$result['total'],$where,$params,$summaryFrom)];
}

function phase7_report_summary(PDO $pdo,array $f,int $matching,array $where=[],array $params=[],string $from=''): array
{
    $totals=[['label'=>'Matching records','value'=>number_format($matching)]];
    if($f['report']==='overview')return phase7_overview_summary($pdo,$f,$matching);
    if($f['report']==='establishment'){
        $d=phase7_establishment_detail($pdo,$f);$totals[]=['label'=>'Grease traps','value'=>number_format(count($d['traps']))];$totals[]=['label'=>'Alerts','value'=>number_format((int)$d['alerts']['total'])];
        foreach($d['oil'] as $r)$totals[]=['label'=>'Oil submitted ('.$r['oil_unit'].')','value'=>number_format((float)$r['total'],3).' '.$r['oil_unit']];foreach($d['rice'] as $r)$totals[]=['label'=>'Rice rewards ('.$r['rice_unit'].')','value'=>number_format((float)$r['total'],3).' '.$r['rice_unit']];return $totals;
    }
    if($from!==''&&$f['report']==='telemetry'){
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';$q=$pdo->prepare("SELECT MIN(sr.waste_level_percent) fill_min,MAX(sr.waste_level_percent) fill_max,AVG(sr.waste_level_percent) fill_avg,MIN(sr.temperature_c) temp_min,MAX(sr.temperature_c) temp_max,AVG(sr.temperature_c) temp_avg,MIN(sr.flow_rate_lpm) flow_min,MAX(sr.flow_rate_lpm) flow_max,AVG(sr.flow_rate_lpm) flow_avg $from$whereSql");$q->execute($params);$r=$q->fetch();
        foreach([['Waste level min',$r['fill_min'],'%'],['Waste level max',$r['fill_max'],'%'],['Waste level average',$r['fill_avg'],'%'],['Temperature min',$r['temp_min'],' °C'],['Temperature max',$r['temp_max'],' °C'],['Temperature average',$r['temp_avg'],' °C'],['Flow min',$r['flow_min'],' L/min'],['Flow max',$r['flow_max'],' L/min'],['Flow average',$r['flow_avg'],' L/min']] as [$label,$value,$unit])$totals[]=['label'=>$label,'value'=>$value===null?'No data':number_format((float)$value,2).$unit];
    }
    if($from!==''&&$f['report']==='alerts'){
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';$q=$pdo->prepare("SELECT SUM(a.status='ACTIVE') active_count,SUM(a.status='ACKNOWLEDGED') acknowledged_count,SUM(a.status='RESOLVED') resolved_count,SUM(a.severity='WARNING') warning_count,SUM(a.severity='CRITICAL') critical_count $from$whereSql");$q->execute($params);$r=$q->fetch();foreach([['Active',$r['active_count']],['Acknowledged',$r['acknowledged_count']],['Resolved',$r['resolved_count']],['Warning',$r['warning_count']],['Critical',$r['critical_count']]] as [$label,$value])$totals[]=['label'=>$label,'value'=>number_format((int)$value)];
    }
    if($from!==''&&in_array($f['report'],['surrenders','incentives','distribution'],true)){
        $field=$f['report']==='surrenders'?'os.oil_unit':'it.rice_unit';$amount=$f['report']==='surrenders'?'os.oil_quantity':'it.rice_quantity';$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $q=$pdo->prepare("SELECT $field unit,SUM($amount) total $from$whereSql GROUP BY $field ORDER BY $field");$q->execute($params);
        foreach($q->fetchAll() as $r)$totals[]=['label'=>($f['report']==='surrenders'?'Oil total':'Rice reward total').' ('.$r['unit'].')','value'=>number_format((float)$r['total'],3).' '.$r['unit']];
    }
    return $totals;
}

function phase7_overview_summary(PDO $pdo,array $f,int $matching): array
{
    $conditions=[];$params=[];if($f['establishment_id']){$conditions[]='id=?';$params[]=$f['establishment_id'];}if($f['status']==='ACTIVE')$conditions[]='is_active=1';elseif($f['status']==='INACTIVE')$conditions[]='is_active=0';if($f['search']!==''){$conditions[]='(business_name LIKE ? OR registration_code LIKE ? OR owner_name LIKE ?)';array_push($params,...array_fill(0,3,'%'.$f['search'].'%'));}
    $q=$pdo->prepare('SELECT id,is_active FROM establishments'.($conditions?' WHERE '.implode(' AND ',$conditions):''));$q->execute($params);$sites=$q->fetchAll();$ids=array_map('intval',array_column($sites,'id'));
    $summary=[['label'=>'Registered establishments','value'=>number_format(count($sites))],['label'=>'Active establishments','value'=>number_format(count(array_filter($sites,fn($r)=>(bool)$r['is_active'])))]];
    if(!$ids)return array_merge($summary,[['label'=>'No matching activity','value'=>'No data']]);
    $in=implode(',',$ids);$start=$f['period']['start_utc'];$end=$f['period']['end_utc'];
    $scalar=function(string $sql,array $p=[])use($pdo){$q=$pdo->prepare($sql);$q->execute($p);return (int)$q->fetchColumn();};
    $summary[]=['label'=>'Active grease traps','value'=>number_format($scalar("SELECT COUNT(*) FROM grease_traps WHERE is_active=1 AND establishment_id IN ($in)"))];
    $summary[]=['label'=>'Active devices','value'=>number_format($scalar("SELECT COUNT(DISTINCT d.id) FROM devices d JOIN device_assignments da ON da.device_id=d.id AND da.ended_at IS NULL JOIN grease_traps g ON g.id=da.grease_trap_id WHERE d.is_active=1 AND g.establishment_id IN ($in)"))];
    $timeout=(int)$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='device_offline_timeout_minutes'")->fetchColumn();
    $online=$scalar("SELECT COUNT(DISTINCT d.id) FROM devices d JOIN device_assignments da ON da.device_id=d.id AND da.ended_at IS NULL JOIN grease_traps g ON g.id=da.grease_trap_id WHERE d.is_active=1 AND d.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL $timeout MINUTE) AND g.establishment_id IN ($in)");
    $activeDevices=$scalar("SELECT COUNT(DISTINCT d.id) FROM devices d JOIN device_assignments da ON da.device_id=d.id AND da.ended_at IS NULL JOIN grease_traps g ON g.id=da.grease_trap_id WHERE d.is_active=1 AND g.establishment_id IN ($in)");
    $summary[]=['label'=>'Online devices','value'=>number_format($online)];$summary[]=['label'=>'Offline / not connected','value'=>number_format(max(0,$activeDevices-$online))];
    $summary[]=['label'=>'Sensor readings','value'=>number_format($scalar("SELECT COUNT(*) FROM sensor_readings sr JOIN device_assignments da ON da.id=sr.device_assignment_id JOIN grease_traps g ON g.id=da.grease_trap_id WHERE g.establishment_id IN ($in) AND sr.recorded_at>=? AND sr.recorded_at<?",[$start,$end]))];
    $summary[]=['label'=>'High / critical alerts','value'=>number_format($scalar("SELECT COUNT(*) FROM alerts a JOIN device_assignments da ON da.id=a.device_assignment_id JOIN grease_traps g ON g.id=da.grease_trap_id WHERE g.establishment_id IN ($in) AND a.severity IN ('WARNING','CRITICAL') AND a.first_triggered_at>=? AND a.first_triggered_at<?",[$start,$end]))];
    $summary[]=['label'=>'Overflow alerts','value'=>number_format($scalar("SELECT COUNT(*) FROM alerts a JOIN device_assignments da ON da.id=a.device_assignment_id JOIN grease_traps g ON g.id=da.grease_trap_id WHERE g.establishment_id IN ($in) AND a.alert_type IN ('OVERFLOW','OVERFLOW_WARNING') AND a.first_triggered_at>=? AND a.first_triggered_at<?",[$start,$end]))];
    foreach(['PENDING'=>'Surrenders pending','UNDER_REVIEW'=>'Surrenders under review','APPROVED'=>'Surrenders approved','REJECTED'=>'Surrenders rejected'] as $state=>$label)$summary[]=['label'=>$label,'value'=>number_format($scalar("SELECT COUNT(*) FROM oil_surrenders WHERE establishment_id IN ($in) AND status=? AND surrendered_at>=? AND surrendered_at<?",[$state,$start,$end]))];
    $q=$pdo->prepare("SELECT oil_unit,SUM(oil_quantity) total FROM oil_surrenders WHERE establishment_id IN ($in) AND status='APPROVED' AND surrendered_at>=? AND surrendered_at<? GROUP BY oil_unit ORDER BY oil_unit");$q->execute([$start,$end]);foreach($q->fetchAll() as $r)$summary[]=['label'=>'Qualified oil ('.$r['oil_unit'].')','value'=>number_format((float)$r['total'],3).' '.$r['oil_unit']];
    $summary[]=['label'=>'Incentives processed','value'=>number_format($scalar("SELECT COUNT(*) FROM incentive_transactions WHERE establishment_id IN ($in) AND processed_at>=? AND processed_at<?",[$start,$end]))];
    $summary[]=['label'=>'Rice pending distribution','value'=>number_format($scalar("SELECT COUNT(*) FROM incentive_transactions WHERE establishment_id IN ($in) AND status IN ('CALCULATED','APPROVED_FOR_DISTRIBUTION') AND processed_at>=? AND processed_at<?",[$start,$end]))];
    $q=$pdo->prepare("SELECT rice_unit,SUM(rice_quantity) total FROM incentive_transactions WHERE establishment_id IN ($in) AND status IN ('CALCULATED','APPROVED_FOR_DISTRIBUTION') AND processed_at>=? AND processed_at<? GROUP BY rice_unit ORDER BY rice_unit");$q->execute([$start,$end]);foreach($q->fetchAll() as $r)$summary[]=['label'=>'Rice pending ('.$r['rice_unit'].')','value'=>number_format((float)$r['total'],3).' '.$r['rice_unit']];
    $q=$pdo->prepare("SELECT rice_unit,SUM(rice_quantity) total FROM incentive_transactions WHERE establishment_id IN ($in) AND status='DISTRIBUTED' AND processed_at>=? AND processed_at<? GROUP BY rice_unit ORDER BY rice_unit");$q->execute([$start,$end]);foreach($q->fetchAll() as $r)$summary[]=['label'=>'Rice distributed ('.$r['rice_unit'].')','value'=>number_format((float)$r['total'],3).' '.$r['rice_unit']];
    return $summary;
}

function phase7_query(array $f,array $replace=[]): string
{
    $p=['report'=>$f['report'],'period'=>$f['period']['preset'],'from'=>$f['period']['from'],'to'=>$f['period']['to'],'establishment_id'=>$f['establishment_id'],'device_id'=>$f['device_id'],'grease_trap_id'=>$f['grease_trap_id'],'user_id'=>$f['user_id'],'status'=>$f['status'],'unit'=>$f['unit'],'search'=>$f['search'],'sort'=>$f['sort'],'direction'=>$f['direction'],'page'=>$f['page'],'page_size'=>$f['page_size']];
    return http_build_query(array_replace($p,$replace));
}

function phase7_filter_summary(array $f): string
{
    $parts=[$f['period']['label']];foreach(['establishment_id'=>'establishment','device_id'=>'device','grease_trap_id'=>'grease trap','user_id'=>'user'] as $k=>$label)if($f[$k])$parts[]=$label.' #'.$f[$k];if($f['status'])$parts[]='status '.$f['status'];if($f['unit'])$parts[]='unit '.$f['unit'];if($f['search'])$parts[]='search applied';return implode('; ',$parts);
}

function phase7_audit_export(PDO $pdo,array $user,string $format,array $f): void
{
    audit($format==='csv'?'REPORT_CSV_EXPORTED':'REPORT_PDF_EXPORTED',(int)$user['id'],'report:'.$f['report']);
}
