<?php
declare(strict_types=1);

function phase3_validate_gas_runtime(mixed $runtime): array
{
    if (!is_array($runtime) || array_is_list($runtime)
        || array_diff(array_keys($runtime), ['boot_id', 'state', 'warmup_remaining_ms', 'gas_raw'])) {
        throw new TelemetryException(422, 'invalid_gas_runtime', 'Invalid Gas runtime object.');
    }
    $boot = $runtime['boot_id'] ?? null;
    $state = $runtime['state'] ?? null;
    $remaining = $runtime['warmup_remaining_ms'] ?? null;
    $raw = $runtime['gas_raw'] ?? null;
    if (!is_string($boot) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $boot)
        || !in_array($state, ['WARMING_UP', 'READY', 'ERROR', 'DISABLED'], true)
        || !is_int($remaining) || $remaining < 0 || $remaining > 60000
        || ($state === 'WARMING_UP' ? $remaining === 0 : $remaining !== 0)
        || ($state === 'READY' ? (!is_int($raw) || $raw < 0 || $raw > 4095) : $raw !== null)) {
        throw new TelemetryException(422, 'invalid_gas_runtime', 'Gas runtime state and measurement are inconsistent.');
    }
    return ['boot_id' => strtolower($boot), 'state' => $state,
        'warmup_remaining_ms' => $remaining, 'gas_raw' => $raw];
}

function phase3_gas_runtime_snapshot(array $runtime, DateTimeImmutable $received, ?array $previous): array
{
    $until = null;
    if ($runtime['state'] === 'WARMING_UP') {
        $until = $received->getTimestamp() + (int) ceil($runtime['warmup_remaining_ms'] / 1000);
        // Repeated heartbeats must not restart the same boot's warm-up interval.
        if (($previous['boot_id'] ?? null) === $runtime['boot_id'] && isset($previous['warmup_until'])) {
            $previousUntil = strtotime((string) $previous['warmup_until']);
            if ($previousUntil !== false) $until = min($until, $previousUntil);
        }
    }
    return ['boot_id' => $runtime['boot_id'], 'state' => $runtime['state'],
        'warmup_until' => $until === null ? null : gmdate('Y-m-d\TH:i:s\Z', $until),
        'gas_raw' => $runtime['gas_raw'], 'updated_at' => $received->format('Y-m-d\TH:i:s\Z')];
}
