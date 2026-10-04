<?php
declare(strict_types=1);
require_once __DIR__ . '/alert-engine.php';

const MOBILE_ALERT_PAGE_SIZE = 25;

function mobile_alert_row(array $row): array
{
    foreach (['id', 'establishment_id', 'grease_trap_id'] as $key) $row[$key] = (int) $row[$key];
    foreach (['sensor_value', 'threshold_value'] as $key) $row[$key] = $row[$key] === null ? null : (float) $row[$key];
    $row['trigger_count'] = (int) $row['trigger_count'];
    $row['label'] = phase4_alert_label((string) $row['alert_type']);
    foreach (['first_triggered_at', 'last_triggered_at', 'acknowledged_at', 'resolved_at'] as $key) {
        $row[$key] = $row[$key] === null ? null : str_replace(' ', 'T', (string) $row[$key]) . 'Z';
    }
    return $row;
}

function mobile_alert_date_bounds(string $range, ?string $from, ?string $to): array
{
    $utc = new DateTimeZone('UTC');
    $now = new DateTimeImmutable('now', $utc);
    $local = new DateTimeZone('Asia/Manila');
    if ($range === 'today') return [(new DateTimeImmutable('today', $local))->setTimezone($utc), $now->modify('+1 second')];
    if ($range === '7d') return [$now->modify('-7 days'), $now->modify('+1 second')];
    if ($range === '30d') return [$now->modify('-30 days'), $now->modify('+1 second')];
    if ($range !== 'custom' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from ?? '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to ?? '')) {
        throw new InvalidArgumentException('Choose Today, 7 Days, 30 Days, or a valid custom range.');
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $local);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $local);
    if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $start > $end || $start->diff($end)->days > 30) throw new InvalidArgumentException('Custom alert range cannot exceed 31 days.');
    return [$start->setTimezone($utc), $end->modify('+1 day')->setTimezone($utc)];
}

function mobile_alerts(int $ownerId, array $input): array
{
    $status = strtoupper((string) ($input['status'] ?? 'UNRESOLVED'));
    $severity = strtoupper((string) ($input['severity'] ?? 'ALL'));
    $range = strtolower((string) ($input['range'] ?? '30d'));
    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
    $trapId = isset($input['grease_trap_id']) && $input['grease_trap_id'] !== ''
        ? filter_var($input['grease_trap_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : null;
    if (!in_array($status, ['ALL','UNRESOLVED','ACTIVE','ACKNOWLEDGED','RESOLVED'], true) || !in_array($severity, ['ALL','INFO','WARNING','CRITICAL'], true) || !$page || ($trapId === false)) {
        throw new InvalidArgumentException('One or more alert filters are invalid.');
    }
    [$start, $end] = mobile_alert_date_bounds($range, $input['from'] ?? null, $input['to'] ?? null);
    $pdo = db();
    $trapsQ = $pdo->prepare('SELECT g.id,g.name,e.business_name FROM grease_traps g JOIN establishments e ON e.id=g.establishment_id WHERE e.owner_user_id=? AND e.is_active=1 AND g.is_active=1 ORDER BY e.business_name,g.name');
    $trapsQ->execute([$ownerId]); $traps = $trapsQ->fetchAll();
    foreach ($traps as &$trap) $trap['id'] = (int) $trap['id']; unset($trap);
    if ($trapId !== null && !in_array((int)$trapId, array_column($traps, 'id'), true)) return ['unauthorized_trap'=>true];

    $base = ' FROM alerts al JOIN device_assignments a ON a.id=al.device_assignment_id JOIN devices d ON d.id=a.device_id JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id WHERE e.owner_user_id=? AND e.is_active=1 AND g.is_active=1';
    $where = ' AND al.last_triggered_at>=? AND al.last_triggered_at<?'; $params = [$ownerId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')];
    if ($status === 'UNRESOLVED') $where .= " AND al.status IN ('ACTIVE','ACKNOWLEDGED')";
    elseif ($status !== 'ALL') { $where .= ' AND al.status=?'; $params[]=$status; }
    if ($severity !== 'ALL') { $where .= ' AND al.severity=?'; $params[]=$severity; }
    if ($trapId !== null) { $where .= ' AND g.id=?'; $params[]=(int)$trapId; }
    $countQ=$pdo->prepare('SELECT COUNT(*)'.$base.$where);$countQ->execute($params);$total=(int)$countQ->fetchColumn();
    $pages=max(1,(int)ceil($total/MOBILE_ALERT_PAGE_SIZE)); if($page>$pages)$page=$pages; $offset=($page-1)*MOBILE_ALERT_PAGE_SIZE;
    $sql='SELECT al.id,al.alert_type,al.severity,al.status,al.message,al.sensor_name,al.sensor_value,al.threshold_value,al.trigger_count,al.first_triggered_at,al.last_triggered_at,al.acknowledged_at,al.resolved_at,e.id establishment_id,e.business_name,g.id grease_trap_id,g.name grease_trap_name,d.device_code'.$base.$where." ORDER BY FIELD(al.severity,'CRITICAL','WARNING','INFO'),al.last_triggered_at DESC,al.id DESC LIMIT ".MOBILE_ALERT_PAGE_SIZE.' OFFSET '.$offset;
    $q=$pdo->prepare($sql);$q->execute($params);$alerts=array_map('mobile_alert_row',$q->fetchAll());
    $summaryQ=$pdo->prepare("SELECT SUM(al.status='ACTIVE') active_count,SUM(al.status='ACKNOWLEDGED') acknowledged_count,SUM(al.status='RESOLVED') resolved_count,SUM(al.status IN ('ACTIVE','ACKNOWLEDGED')) unresolved_count,SUM(al.status IN ('ACTIVE','ACKNOWLEDGED') AND al.severity='CRITICAL') critical_count,SUM(al.status IN ('ACTIVE','ACKNOWLEDGED') AND al.severity='WARNING') warning_count,SUM(al.status IN ('ACTIVE','ACKNOWLEDGED') AND al.severity='INFO') info_count".$base.' AND al.last_triggered_at>=? AND al.last_triggered_at<?');
    $summaryQ->execute([$ownerId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')]);$summary=$summaryQ->fetch();foreach($summary as $k=>$v)$summary[$k]=(int)$v;
    return ['alerts'=>$alerts,'summary'=>$summary,'traps'=>$traps,'range'=>$range,'from'=>$start->format(DATE_ATOM),'to'=>$end->modify('-1 second')->format(DATE_ATOM),'page'=>$page,'pages'=>$pages,'total'=>$total,'page_size'=>MOBILE_ALERT_PAGE_SIZE];
}

function mobile_alert_detail(int $ownerId, int $id): ?array
{
    $q=db()->prepare('SELECT al.id,al.alert_type,al.severity,al.status,al.message,al.sensor_name,al.sensor_value,al.threshold_value,al.trigger_count,al.first_triggered_at,al.last_triggered_at,al.acknowledged_at,al.resolved_at,e.id establishment_id,e.business_name,g.id grease_trap_id,g.name grease_trap_name,d.device_code FROM alerts al JOIN device_assignments a ON a.id=al.device_assignment_id JOIN devices d ON d.id=a.device_id JOIN grease_traps g ON g.id=a.grease_trap_id JOIN establishments e ON e.id=g.establishment_id WHERE al.id=? AND e.owner_user_id=? AND e.is_active=1 AND g.is_active=1');
    $q->execute([$id,$ownerId]);$row=$q->fetch();return $row?mobile_alert_row($row):null;
}
