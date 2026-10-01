<?php
declare(strict_types=1);

function compliance_event_code(): string
{
    return 'LED-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function compliance_append(PDO $pdo, string $eventType, ?int $establishmentId,
    ?string $recordType, ?int $recordId, string $description, ?int $createdBy,
    ?int $greaseTrapId = null, ?int $deviceId = null, ?string $dedupeKey = null): int
{
    $query = $pdo->prepare('INSERT INTO compliance_ledger
        (event_code,event_type,establishment_id,grease_trap_id,device_id,related_record_type,
         related_record_id,description,event_timestamp,dedupe_key,created_by)
        VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?,?)');
    $query->execute([compliance_event_code(), $eventType, $establishmentId, $greaseTrapId,
        $deviceId, $recordType, $recordId, $description, $dedupeKey, $createdBy]);
    return (int) $pdo->lastInsertId();
}

function compliance_event_label(string $eventType): string
{
    return ucwords(strtolower(str_replace('_', ' ', $eventType)));
}
