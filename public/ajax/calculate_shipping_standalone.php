<?php
header('Content-Type: application/json');

// Minimal shipping calculation without external dependencies
function calculateShippingCostStandalone($country, $city = '', $totalWeight = 1) {
    $cost = 0;
    $country = strtolower(trim($country));
    $city = strtolower(trim($city));

    if ($country === 'united arab emirates') {
        $cost = ($city === 'al gharbia') ? 60 : 30;
    } elseif ($country === 'oman') {
        $cost = 70 + max(0, $totalWeight - 5) * 10;
    } elseif (in_array($country, ['kuwait', 'saudi arabia', 'qatar', 'bahrain'])) {
        $numberOfParcels = ceil($totalWeight / 8);
        $cost = 0;
        $remainingWeight = $totalWeight;
        
        for ($i = 0; $i < $numberOfParcels; $i++) {
            $parcelWeight = min(8, $remainingWeight);
            $parcelCost = 120 + max(0, $parcelWeight - 1) * 30;
            $cost += $parcelCost;
            $remainingWeight -= $parcelWeight;
        }
    } elseif (in_array($country, ['germany', 'france', 'italy', 'spain', 'united kingdom'])) {
        $cost = 220 + max(0, $totalWeight - 1) * 70;
    } else {
        $cost = 300 + max(0, $totalWeight - 1) * 80;
    }

    return is_numeric($cost) && $cost >= 0 ? $cost : 30;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    $country = $_POST['country'] ?? 'united arab emirates';
    $city = $_POST['city'] ?? '_';
    $totalWeight = isset($_POST['totalWeight']) ? floatval($_POST['totalWeight']) : 1;

    if (empty($country)) {
        throw new Exception('Country is required');
    }

    if ($totalWeight <= 0) {
        throw new Exception('Invalid weight');
    }

    $cost = calculateShippingCostStandalone($country, $city, $totalWeight);

    echo json_encode([
        'success' => true,
        'country' => $country,
        'city' => $city,
        'totalWeight' => $totalWeight,
        'shippingAED' => number_format($cost, 2),
        'shippingCost' => $cost,
        'currency' => 'AED',
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

exit;
?>