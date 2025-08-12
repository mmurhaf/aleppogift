<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Ensure no whitespace before this line
require_once(__DIR__ . '/../../includes/shipping.php');



// Fallback defaults
$country = $_POST['country'] ?? 'united arab emirates';
$city = $_POST['city'] ?? '_';
$totalWeight = isset($_POST['totalWeight']) ? floatval($_POST['totalWeight']) : 1;

// Ensure function exists
if (!function_exists('calculateShippingCost')) {
    echo json_encode(['success' => false, 'error' => 'Function calculateShippingCost() not found']);
    exit;
}

$cost = calculateShippingCost($country, $city, $totalWeight);

if (!is_numeric($cost)) {
    echo json_encode(['success' => false, 'error' => 'Invalid shipping cost']);
    exit;
}

echo json_encode([
    'success' => true,
    'country' => $country,
    'city' => $city,
    'totalWeight' => $totalWeight,
    'shippingAED' => number_format($cost, 2),
    'shippingCost' => $cost,
    'currency' => 'AED'
]);
exit;
