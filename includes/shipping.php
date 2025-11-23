<?php
// File: includes/shipping.php  

require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/Database.php');

function calculateShippingCost($country, $city = '', $totalWeight = 1) {
    $cost = 0;

    $country = strtolower(trim($country));
    $city = strtolower(trim($city));

    if ($country === 'united arab emirates') {
        $cost = ($city === 'al gharbia') ? 60 : 30;
    } elseif ($country === 'oman') {
        // All Oman cities: AED 70 for 5kg, AED 10/kg above 5kg
        $cost = 70 + max(0, $totalWeight - 5) * 10;
    } elseif (in_array($country, ['kuwait', 'saudi arabia', 'qatar', 'bahrain'])) {
        // Outside UAE: Every 8kg is a separate parcel
        // Each parcel: AED 120 for first kg + AED 30/kg for additional kg
        $numberOfParcels = ceil($totalWeight / 8);
        $cost = 0;
        
        $remainingWeight = $totalWeight;
        for ($i = 0; $i < $numberOfParcels; $i++) {
            $parcelWeight = min(8, $remainingWeight);
            // AED 120 for first kg + AED 30 for each additional kg in this parcel
            $parcelCost = 120 + max(0, $parcelWeight - 1) * 30;
            $cost += $parcelCost;
            $remainingWeight -= $parcelWeight;
        }
    } elseif (in_array($country, ['germany', 'france', 'italy', 'spain', 'united kingdom'])) {
        $cost = 220 + max(0, $totalWeight - 1) * 70;
    } else {
        $cost = 300 + max(0, $totalWeight - 1) * 80;
    }

    // Validate the calculated cost
    if (!is_numeric($cost) || $cost < 0) {
        error_log("Invalid shipping cost calculated for country: $country, city: $city, weight: $totalWeight");
        return 30; // Default fallback cost
    }

    return $cost;
}
