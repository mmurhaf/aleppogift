<?php
$root_dir = dirname(dirname(dirname(__DIR__)));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

start_session_safely();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$productId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);
if ($productId === false || $productId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

$db = new Database();
$variations = $db->query(
    "SELECT id, size, color, additional_price, stock
     FROM product_variations
     WHERE product_id = :id
     ORDER BY id ASC",
    ['id' => (int)$productId]
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'variations' => $variations]);
