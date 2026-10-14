<?php
// File: admin/ajax/search_products.php
// Search products for admin order creation

error_reporting(E_ALL);
ini_set('display_errors', 0);

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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $query = isset($input['query']) ? trim($input['query']) : '';

    if (empty($query) || strlen($query) < 2) {
        echo json_encode(['success' => false, 'error' => 'Query too short']);
        exit;
    }

    $db = new Database();

    // Search products by name or SKU
    $searchTerm = '%' . $query . '%';
    
    $params = [
        'search_en' => $searchTerm,
        'search_ar' => $searchTerm,
        'search_sku' => $searchTerm,
    ];
    $idClause = '';
    if (ctype_digit($query)) {
        $idClause = ' OR p.id = :product_id';
        $params['product_id'] = (int)$query;
    }

    $products = $db->query("
        SELECT 
            p.id,
            p.name_en,
            p.name_ar,
            p.sku,
            p.price,
            p.stock,
            p.weight,
            (SELECT pi.image_path 
             FROM product_images pi 
             WHERE pi.product_id = p.id 
             ORDER BY pi.is_main DESC, pi.id ASC 
             LIMIT 1) as image_path
        FROM products p
        WHERE p.status = 1 
        AND (p.name_en LIKE :search_en 
             OR p.name_ar LIKE :search_ar 
             OR p.sku LIKE :search_sku
             {$idClause})
        ORDER BY p.name_en ASC
        LIMIT 20
    ", $params)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    error_log("Product search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
}
?>
