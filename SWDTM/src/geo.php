<?php

declare(strict_types=1);

/**
 * Oblicza odległość między dwoma punktami na Ziemi.
 *
 * @param float $lat1 Szerokość geograficzna punktu 1
 * @param float $lon1 Długość geograficzna punktu 1
 * @param float $lat2 Szerokość geograficzna punktu 2
 * @param float $lon2 Długość geograficzna punktu 2
 * @return float Odległość w kilometrach
 */
function haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadius = 6371; // w kilometrach

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}
