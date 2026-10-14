<?php
// File: includes/shipping.php  

require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/Database.php');

function calculateShippingCost($country, $city = '', $totalWeight = 1, $merchandiseTotal = null, $sameDay = false) {
    $cost = 0;

    $country = strtolower(trim($country));
    $city = strtolower(trim($city));
    $uae = in_array($country, ['united arab emirates', 'uae'], true);

    if ($uae) {
        $sameDayCity = function_exists('same_day_city') ? same_day_city($city) : in_array($city, ['dubai', 'abu dhabi', 'al ain'], true);
        if ($sameDay && $sameDayCity) {
            $fee = 50;
            if (function_exists('same_day_fee') && class_exists('Database')) {
                try {
                    $fee = same_day_fee(new Database());
                } catch (Throwable $e) {
                    $fee = 50;
                }
            }
            return max(0, (float)$fee);
        }
        $cost = ($city === 'al gharbia') ? 60 : 30;
        if ($merchandiseTotal !== null && !$sameDay) {
            $threshold = defined('FREE_SHIPPING_THRESHOLD') ? (float)FREE_SHIPPING_THRESHOLD : 500;
            if (function_exists('free_shipping_threshold') && class_exists('Database')) {
                try {
                    $threshold = free_shipping_threshold(new Database());
                } catch (Throwable $e) {
                    $threshold = 500;
                }
            }
            if ((float)$merchandiseTotal >= $threshold) {
                return 0;
            }
        }
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
