<?php
declare(strict_types=1);

// Pure calculation shared by the device endpoint and integration tests.
function ultrasonic_test_level(float $distance, array $calibration): array
{
    $empty = (float) $calibration['empty_distance_cm'];
    $full = (float) $calibration['full_distance_cm'];
    $warning = (float) $calibration['warning_percent'];
    $critical = (float) $calibration['critical_percent'];
    if (!is_finite($distance) || $distance < 2 || $distance > 400
        || $full < 2 || $empty <= $full || $empty > 400
        || $warning <= 0 || $warning >= $critical || $critical >= 100) {
        throw new InvalidArgumentException('Invalid ultrasonic distance or calibration.');
    }
    $percent = round(max(0, min(100, 100 * ($empty - $distance) / ($empty - $full))), 1);
    return ['waste_level_percent' => $percent,
        'status' => $percent >= $critical ? 'CRITICAL' : ($percent >= $warning ? 'WARNING' : 'NORMAL')];
}
