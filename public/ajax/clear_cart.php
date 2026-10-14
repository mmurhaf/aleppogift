<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/shared_cart.php');
sharedCartWatch();

header('Content-Type: application/json');

$_SESSION['cart'] = [];

echo json_encode([
    'success' => true,
    'message' => 'Cart cleared successfully'
]);
?>
