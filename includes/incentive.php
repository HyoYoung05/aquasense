<?php
declare(strict_types=1);
require_once __DIR__ . '/compliance.php';

final class Phase6ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
final class Phase6ConflictException extends RuntimeException {}

function phase6_validate_date(?string $value): ?string
{
    if ($value === null || trim($value) === '') return null;
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function phase6_validate_rule(array $input): array
{
    $errors=[];$name=trim((string)($input['name']??''));
    if($name===''||mb_strlen($name)>150)$errors[]='Rule name is required and must be 150 characters or fewer.';
    $threshold=filter_var($input['minimum_oil_quantity']??null,FILTER_VALIDATE_FLOAT);
    if($threshold===false||(float)$threshold<=0||(float)$threshold>9999999.999)$errors[]='Oil threshold must be greater than zero.';
    $reward=filter_var($input['rice_reward_quantity']??null,FILTER_VALIDATE_FLOAT);
    if($reward===false||(float)$reward<=0||(float)$reward>9999999.999)$errors[]='Rice reward must be greater than zero.';
    $oilUnit=(string)($input['oil_unit']??'');if(!in_array($oilUnit,['L','kg'],true))$errors[]='Oil unit must be L or kg.';
    $riceUnit=(string)($input['rice_unit']??'');if(!in_array($riceUnit,['kg','g'],true))$errors[]='Rice unit must be kg or g.';
    $type=(string)($input['calculation_type']??'');if(!in_array($type,['FIXED_PER_THRESHOLD','FIXED_TRANSACTION'],true))$errors[]='Select a supported calculation type.';
    $from=phase6_validate_date((string)($input['effective_date']??''));if($from===null)$errors[]='Enter a valid effective start date.';
    $until=phase6_validate_date((string)($input['end_date']??''));if(($input['end_date']??'')!==''&&$until===null)$errors[]='Enter a valid effective end date.';
    if($from!==null&&$until!==null&&$until<=$from)$errors[]='Effective end date must be after the start date.';
    if($errors)throw new Phase6ValidationException($errors);
    return ['name'=>$name,'minimum_oil_quantity'=>round((float)$threshold,3),'oil_unit'=>$oilUnit,
        'rice_reward_quantity'=>round((float)$reward,3),'rice_unit'=>$riceUnit,
        'calculation_type'=>$type,'effective_date'=>$from,'end_date'=>$until,
        'is_active'=>!empty($input['is_active']),'is_test'=>!empty($input['is_test'])];
}

function phase6_assert_no_overlap(PDO $pdo,array $rule,?int $excludeId=null):void
{
    if(!$rule['is_active'])return;
    $sql="SELECT id,name FROM incentive_rules WHERE is_active=1 AND oil_unit=?
      AND effective_date<=? AND COALESCE(end_date,'9999-12-31')>=?";
    $params=[$rule['oil_unit'],$rule['end_date']??'9999-12-31',$rule['effective_date']];
    if($excludeId!==null){$sql.=' AND id<>?';$params[]=$excludeId;}
    $q=$pdo->prepare($sql.' LIMIT 1');$q->execute($params);$conflict=$q->fetch();
    if($conflict)throw new Phase6ValidationException(['This active period overlaps rule “'.$conflict['name'].'” for '.$rule['oil_unit'].'. Deactivate or adjust one period.']);
}

function phase6_audit(PDO $pdo,string $action,int $userId,string $recordType,int $recordId):void
{
    $q=$pdo->prepare('INSERT INTO audit_logs(user_id,action,record_type,record_id,ip_address)VALUES(?,?,?,?,?)');
    $q->execute([$userId,$action,$recordType,$recordId,client_ip()]);
}

function phase6_save_rule(PDO $pdo,array $input,int $actorId,?int $id=null):int
{
    $rule=phase6_validate_rule($input);$pdo->beginTransaction();
    try{
        phase6_assert_no_overlap($pdo,$rule,$id);
        if($id===null){$q=$pdo->prepare('INSERT INTO incentive_rules(name,minimum_oil_quantity,oil_unit,rice_reward_quantity,rice_unit,calculation_type,effective_date,end_date,is_active,is_test,created_by)VALUES(?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$rule['name'],$rule['minimum_oil_quantity'],$rule['oil_unit'],$rule['rice_reward_quantity'],$rule['rice_unit'],$rule['calculation_type'],$rule['effective_date'],$rule['end_date'],$rule['is_active'], $rule['is_test'],$actorId]);$id=(int)$pdo->lastInsertId();$action='INCENTIVE_RULE_CREATED';}
        else{$q=$pdo->prepare('UPDATE incentive_rules SET name=?,minimum_oil_quantity=?,oil_unit=?,rice_reward_quantity=?,rice_unit=?,calculation_type=?,effective_date=?,end_date=?,is_active=?,is_test=? WHERE id=?');$q->execute([$rule['name'],$rule['minimum_oil_quantity'],$rule['oil_unit'],$rule['rice_reward_quantity'],$rule['rice_unit'],$rule['calculation_type'],$rule['effective_date'],$rule['end_date'],$rule['is_active'],$rule['is_test'],$id]);if(!$q->rowCount()&&!(int)$pdo->query('SELECT COUNT(*) FROM incentive_rules WHERE id='.(int)$id)->fetchColumn())throw new Phase6ValidationException(['Incentive rule was not found.']);$action='INCENTIVE_RULE_UPDATED';}
        phase6_audit($pdo,$action,$actorId,'incentive_rules',$id);$pdo->commit();return$id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function phase6_set_rule_active(PDO $pdo,int $id,bool $active,int $actorId):void
{
    $pdo->beginTransaction();try{$q=$pdo->prepare('SELECT * FROM incentive_rules WHERE id=? FOR UPDATE');$q->execute([$id]);$rule=$q->fetch();if(!$rule)throw new Phase6ValidationException(['Incentive rule was not found.']);$rule['is_active']=$active;phase6_assert_no_overlap($pdo,$rule,$id);$q=$pdo->prepare('UPDATE incentive_rules SET is_active=? WHERE id=? AND is_active<>?');$q->execute([$active?1:0,$id,$active?1:0]);if(!$q->rowCount())throw new Phase6ConflictException('The rule already has that status.');phase6_audit($pdo,$active?'INCENTIVE_RULE_ACTIVATED':'INCENTIVE_RULE_DEACTIVATED',$actorId,'incentive_rules',$id);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function phase6_applicable_rule(PDO $pdo,float $quantity,string $unit,?string $onDate=null):?array
{
    $date=$onDate??gmdate('Y-m-d');$q=$pdo->prepare("SELECT * FROM incentive_rules WHERE is_active=1 AND oil_unit=? AND minimum_oil_quantity<=? AND effective_date<=? AND (end_date IS NULL OR end_date>=?) ORDER BY effective_date DESC,id DESC LIMIT 2");$q->execute([$unit,$quantity,$date,$date]);$rules=$q->fetchAll();if(count($rules)>1)throw new Phase6ConflictException('More than one incentive rule applies. Resolve the rule overlap before processing.');return$rules[0]??null;
}

function phase6_calculate(array $rule,float $quantity):array
{
    $threshold=(float)$rule['minimum_oil_quantity'];$reward=(float)$rule['rice_reward_quantity'];
    if($quantity<$threshold)throw new Phase6ValidationException(['No active incentive rule applies to this surrender quantity.']);
    $blocks=$rule['calculation_type']==='FIXED_PER_THRESHOLD'?(int)floor($quantity/$threshold):1;
    $result=round($blocks*$reward,3);if($blocks<1||$result<=0)throw new Phase6ValidationException(['The configured rule produces no reward for this surrender.']);
    return ['qualifying_blocks'=>$blocks,'rice_quantity'=>$result];
}

function phase6_transaction_code():string{return'INC-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));}

function phase6_process_incentive(PDO $pdo,int $surrenderId,int $actorId,bool $simulateFailure=false):array
{
    $pdo->beginTransaction();try{
        $q=$pdo->prepare('SELECT os.*,e.owner_user_id FROM oil_surrenders os JOIN establishments e ON e.id=os.establishment_id WHERE os.id=? FOR UPDATE');$q->execute([$surrenderId]);$surrender=$q->fetch();if(!$surrender)throw new Phase6ValidationException(['Oil surrender was not found.']);
        if($surrender['status']!=='APPROVED')throw new Phase6ValidationException(['Only APPROVED oil surrenders can receive an incentive.']);
        $q=$pdo->prepare('SELECT id FROM incentive_transactions WHERE oil_surrender_id=?');$q->execute([$surrenderId]);if($q->fetchColumn()!==false)throw new Phase6ConflictException('An incentive has already been processed for this oil surrender.');
        $rule=phase6_applicable_rule($pdo,(float)$surrender['oil_quantity'],$surrender['oil_unit']);if(!$rule)throw new Phase6ValidationException(['No active incentive rule applies to this surrender quantity unit.']);$calc=phase6_calculate($rule,(float)$surrender['oil_quantity']);
        $q=$pdo->prepare("INSERT INTO incentive_transactions(transaction_code,oil_surrender_id,establishment_id,owner_user_id,rule_id,oil_quantity,oil_unit,rule_name_snapshot,oil_threshold_snapshot,rule_reward_snapshot,calculation_type_snapshot,qualifying_blocks,rice_quantity,rice_unit,status,calculated_by,processed_at)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'CALCULATED',?,UTC_TIMESTAMP())");
        $q->execute([phase6_transaction_code(),$surrenderId,$surrender['establishment_id'],$surrender['submitted_by'],$rule['id'],$surrender['oil_quantity'],$surrender['oil_unit'],$rule['name'],$rule['minimum_oil_quantity'],$rule['rice_reward_quantity'],$rule['calculation_type'],$calc['qualifying_blocks'],$calc['rice_quantity'],$rule['rice_unit'],$actorId]);$id=(int)$pdo->lastInsertId();
        compliance_append($pdo,'INCENTIVE_CALCULATED',(int)$surrender['establishment_id'],'incentive_transactions',$id,'Rice incentive calculated from an approved oil surrender under the configured rule.', $actorId,$surrender['grease_trap_id']? (int)$surrender['grease_trap_id']:null,$surrender['device_id']?(int)$surrender['device_id']:null,'INCENTIVE_CALCULATED:'.$id);
        phase6_audit($pdo,'INCENTIVE_PROCESSED',$actorId,'incentive_transactions',$id);
        if($simulateFailure)throw new RuntimeException('Simulated transaction failure.');
        $pdo->commit();return phase6_incentive_record($pdo,$id);
    }catch(PDOException$e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000')throw new Phase6ConflictException('An incentive has already been processed for this oil surrender.');throw$e;}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function phase6_incentive_record(PDO $pdo,int $id):?array
{
    $q=$pdo->prepare('SELECT it.*,os.transaction_code surrender_code,e.business_name,e.owner_name,u.full_name processor_name,du.full_name distributor_name FROM incentive_transactions it JOIN oil_surrenders os ON os.id=it.oil_surrender_id JOIN establishments e ON e.id=it.establishment_id JOIN users u ON u.id=it.calculated_by LEFT JOIN users du ON du.id=it.distributed_by WHERE it.id=?');$q->execute([$id]);return$q->fetch()?:null;
}

function phase6_distribute(PDO $pdo,int $id,int $actorId,int $expectedVersion,string $notes):array
{
    $notes=trim($notes);if(mb_strlen($notes)>2000)throw new Phase6ValidationException(['Distribution notes must be 2000 characters or fewer.']);$pdo->beginTransaction();try{$record=phase6_incentive_record($pdo,$id);if(!$record)throw new Phase6ValidationException(['Incentive transaction was not found.']);if((int)$record['status_version']!==$expectedVersion)throw new Phase6ConflictException('This incentive changed after the page was opened. Reload before continuing.');if(!in_array($record['status'],['CALCULATED','APPROVED_FOR_DISTRIBUTION'],true))throw new Phase6ConflictException('Only a pending reward can be marked distributed.');$q=$pdo->prepare("UPDATE incentive_transactions SET status='DISTRIBUTED',distributed_by=?,distributed_at=UTC_TIMESTAMP(),distribution_notes=?,status_version=status_version+1 WHERE id=? AND status=? AND status_version=?");$q->execute([$actorId,$notes===''?null:$notes,$id,$record['status'],$expectedVersion]);if($q->rowCount()!==1)throw new Phase6ConflictException('Another administrator updated this incentive. Reload before continuing.');compliance_append($pdo,'INCENTIVE_DISTRIBUTED',(int)$record['establishment_id'],'incentive_transactions',$id,'Rice incentive distribution was confirmed by Barangay personnel.',$actorId,null,null,'INCENTIVE_DISTRIBUTED:'.$id);phase6_audit($pdo,'INCENTIVE_DISTRIBUTED',$actorId,'incentive_transactions',$id);$pdo->commit();return phase6_incentive_record($pdo,$id);}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function phase6_owner_incentives(PDO $pdo,int $ownerId):array
{
    $q=$pdo->prepare("SELECT it.rice_unit,SUM(CASE WHEN it.status<>'CANCELLED' THEN it.rice_quantity ELSE 0 END) earned,SUM(CASE WHEN it.status='DISTRIBUTED' THEN it.rice_quantity ELSE 0 END) distributed,SUM(CASE WHEN it.status IN ('CALCULATED','APPROVED_FOR_DISTRIBUTION') THEN it.rice_quantity ELSE 0 END) pending FROM incentive_transactions it JOIN establishments e ON e.id=it.establishment_id WHERE it.owner_user_id=? AND e.owner_user_id=? GROUP BY it.rice_unit ORDER BY it.rice_unit");$q->execute([$ownerId,$ownerId]);$summary=[];foreach($q->fetchAll()as$row)$summary[]=['unit'=>$row['rice_unit'],'earned'=>(float)$row['earned'],'distributed'=>(float)$row['distributed'],'pending'=>(float)$row['pending']];
    $q=$pdo->prepare('SELECT it.transaction_code,it.oil_quantity,it.oil_unit,it.rice_quantity,it.rice_unit,it.status,it.processed_at,it.distributed_at,os.transaction_code surrender_code,e.business_name FROM incentive_transactions it JOIN oil_surrenders os ON os.id=it.oil_surrender_id JOIN establishments e ON e.id=it.establishment_id WHERE it.owner_user_id=? AND e.owner_user_id=? ORDER BY it.processed_at DESC,it.id DESC LIMIT 200');$q->execute([$ownerId,$ownerId]);$transactions=[];foreach($q->fetchAll()as$row){$row['oil_quantity']=(float)$row['oil_quantity'];$row['rice_quantity']=(float)$row['rice_quantity'];$row['processed_at']=str_replace(' ','T',$row['processed_at']).'Z';$row['distributed_at']=$row['distributed_at']?str_replace(' ','T',$row['distributed_at']).'Z':null;$transactions[]=$row;}return['summary'=>$summary,'transactions'=>$transactions];
}
