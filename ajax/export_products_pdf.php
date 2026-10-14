<?php
/**
 * Export Products to PDF - AJAX endpoint
 * Fetches all filtered products without pagination for PDF generation
 */

// Increase limits for bulk image export
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '120');

// Start output buffering to catch any errors
ob_start();

header('Content-Type: application/json; charset=UTF-8');

try {
    // Smart path detection: works on both production (ajax/ direct) and local dev (public/ajax/)
    $bootstrap_dir = dirname(__DIR__);
    if (!file_exists($bootstrap_dir . '/includes/bootstrap.php')) {
        $bootstrap_dir = dirname(dirname(__DIR__));
    }
    require_once($bootstrap_dir . '/includes/bootstrap.php');
    require_once($bootstrap_dir . '/includes/pdf_catalog_image.php');
} catch (\Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Bootstrap error: ' . $e->getMessage()
    ]);
    exit;
}

try {
    $db = new Database();
    
    // Initialize filters
    $where = ["p.status = 1"];
    $params = [];
    $filters = [
        'category' => null,
        'brand' => null,
        'search' => null
    ];
    
    // Filter by category
    if (isset($_GET['category']) && is_numeric($_GET['category'])) {
        $where[] = "p.category_id = :category_id";
        $params['category_id'] = $_GET['category'];
        
        // Get category name
        $cat = $db->query("SELECT name_en FROM categories WHERE id = :id", ['id' => $_GET['category']])->fetch(PDO::FETCH_ASSOC);
        $filters['category'] = $cat['name_en'] ?? 'Unknown';
    }
    
    // Filter by brand
    if (isset($_GET['brand']) && is_numeric($_GET['brand'])) {
        $where[] = "p.brand_id = :brand_id";
        $params['brand_id'] = $_GET['brand'];
        
        // Get brand name
        $brand = $db->query("SELECT name_en FROM brands WHERE id = :id", ['id' => $_GET['brand']])->fetch(PDO::FETCH_ASSOC);
        $filters['brand'] = $brand['name_en'] ?? 'Unknown';
    }
    
    // Handle search input
    $search = '';
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    } elseif (isset($_GET['s'])) {
        $search = trim($_GET['s']);
    }
    
    if ($search !== '') {
        $filters['search'] = $search;
        
        if (is_numeric($search)) {
            $where[] = "p.id = :product_id";
            $params['product_id'] = $search;
        } else {
            $where[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar)";
            $params['search_en'] = "%$search%";
            $params['search_ar'] = "%$search%";
        }
    }
    
    // Build the WHERE clause
    $whereClause = implode(' AND ', $where);
    
    // Fetch ALL products without pagination
    $products_query = "
        SELECT 
            p.id,
            p.name_en,
            p.name_ar,
            p.price,
            c.name_en as category_name, 
            b.name_en as brand_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) as main_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE $whereClause 
        ORDER BY p.featured DESC, p.id DESC
    ";
    
    $products = $db->query($products_query, $params)->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert image paths to base64 for PDF (resize to max 200px to save memory)
    foreach ($products as &$product) {
        if (!empty($product['main_image'])) {
            // Try multiple possible paths
            $possiblePaths = [
                $bootstrap_dir . '/public/' . $product['main_image'],
                $bootstrap_dir . '/' . $product['main_image'],
                __DIR__ . '/../' . $product['main_image'],
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($product['main_image'], '/')
            ];
            
            $product['main_image'] = catalog_pdf_data_url_from_candidates($possiblePaths, 260, 58);
        }
    }
    unset($product); // Break reference
    
    // Clear any output buffer content (errors, warnings, etc.)
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'filters' => $filters,
        'total' => count($products)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\Throwable $e) {
    // Clear output buffer
    if (ob_get_level() > 0) ob_end_clean();
    
    error_log("Export PDF Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch products: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
