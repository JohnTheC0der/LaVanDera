<?php
/** Delivery / rider fee rules. Values live in the `settings` table so the admin can change them. */

function setting($key, $default = null)
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $all[$row['setting_key']] = $row['setting_value'];
        }
    }
    return isset($all[$key]) ? $all[$key] : $default;
}

/**
 * Fee for one delivery leg (customer->store or store->customer):
 *   base fee covers the first base_distance_km, then per_km_fee for every extra km,
 *   rounded to whole pesos.  Defaults: 40 + 10/km beyond 2 km.
 * The customer's Place/Confirm Order page should use this same function for send/return fees.
 */
function calc_delivery_fee($distanceKm)
{
    $base   = (float) setting('base_delivery_fee', 40);
    $baseKm = (float) setting('base_distance_km', 2);
    $perKm  = (float) setting('per_km_fee', 10);
    return round($base + $perKm * max(0, (float) $distanceKm - $baseKm));
}

/**
 * Straight-line (haversine) distance in km between two map points, rounded to 0.1 km.
 * Same formula as the frontend demo (LV.distanceKm). Use it with stores.latitude/longitude
 * and customers.latitude/longitude, then pass the result to calc_delivery_fee().
 */
function distance_km($lat1, $lng1, $lat2, $lng2)
{
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return 0.0;
    }
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return round(2 * $r * asin(sqrt($a)), 1);
}
