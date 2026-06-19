<?php
/**
 * includes/geo.php — lightweight geo helpers for AgriConnect logistics.
 * District centroids for Rwanda + distance (haversine) + ETA estimation.
 */

function rw_districts(): array {
    return [
        'Kigali'    => [-1.9441, 30.0619],
        'Musanze'   => [-1.4998, 29.6349],
        'Huye'      => [-2.5965, 29.7394],
        'Rubavu'    => [-1.6777, 29.2603],
        'Nyagatare' => [-1.2931, 30.3265],
    ];
}

/** [lat, lng] for a district (defaults to Kigali if unknown). */
function geo_point(string $district): array {
    $d = rw_districts();
    return $d[$district] ?? $d['Kigali'];
}

/** Great-circle distance in km between two [lat,lng] points. */
function geo_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371; // earth radius km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Estimated minutes to drive a distance (rural road avg ~45 km/h). */
function geo_eta_min(float $km, float $kmh = 45.0): int {
    return max(1, (int) ceil($km / $kmh * 60));
}

/** Human ETA label, e.g. "1 hr 12 min" or "38 min". */
function geo_eta_label(int $min): string {
    if ($min < 60) return "{$min} min";
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $m ? "{$h} hr {$m} min" : "{$h} hr";
}
