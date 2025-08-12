<?php
session_start();
header('Content-Type: application/json');

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

echo json_encode([
    'success' => true,
    'count' => $cart_count
]);
?>
