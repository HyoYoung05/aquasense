<?php
declare(strict_types=1);

// last_seen_at is written in UTC by authenticated heartbeats and telemetry.
function device_contact_is_fresh(?string $lastSeen, int $freshnessSeconds, ?int $now = null): bool
{
    if ($lastSeen === null || $lastSeen === '' || $freshnessSeconds <= 0) return false;
    $seen = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $lastSeen, new DateTimeZone('UTC'));
    if (!$seen || $seen->format('Y-m-d H:i:s') !== $lastSeen) return false;
    $age = ($now ?? time()) - $seen->getTimestamp();
    return $age >= 0 && $age < $freshnessSeconds;
}
