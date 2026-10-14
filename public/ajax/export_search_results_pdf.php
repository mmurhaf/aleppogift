<?php
/**
 * Export Search Results to PDF - AJAX endpoint
 * Fetches all filtered products for PDF generation with grouping logic
 */

// Start output buffering to catch any errors
ob_start();

header('Content-Type: application/json; charset=UTF-8');

try {
    require_once(__DIR__ . '/../../includes/bootstrap.php');
    require_once(__DIR__ . '/../../includes/pdf_catalog_image.php');
} catch (Exception $e) {
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
    
    $has_category = false;
    $has_brand = false;
    $has_search = false;
    
    // Filter by category
    if (isset($_GET['category']) && is_numeric($_GET['category'])) {
        $where[] = "p.category_id = :category_id";
        $params['category_id'] = $_GET['category'];
        
        // Get category name and picture
        $cat = $db->query("SELECT name_en, name_ar, picture FROM categories WHERE id = :id", ['id' => $_GET['category']])->fetch(PDO::FETCH_ASSOC);
        $filters['category'] = $cat['name_en'] ?? 'Unknown';
        $filters['category_ar'] = $cat['name_ar'] ?? 'Unknown';
        $filters['category_picture'] = null;

        // Convert category picture to base64
        if (!empty($cat['picture'])) {
            $picPaths = [
                __DIR__ . '/../../' . ltrim($cat['picture'], '/'),
                __DIR__ . '/../' . ltrim($cat['picture'], '/'),
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($cat['picture'], '/'),
            ];
            $filters['category_picture'] = catalog_pdf_data_url_from_candidates($picPaths, 400, 70);
        }
        $has_category = true;
    }
    
    // Filter by brand
    if (isset($_GET['brand']) && is_numeric($_GET['brand'])) {
        $where[] = "p.brand_id = :brand_id";
        $params['brand_id'] = $_GET['brand'];
        
        // Get brand name and logo
        $brand = $db->query("SELECT name_en, name_ar, logo FROM brands WHERE id = :id", ['id' => $_GET['brand']])->fetch(PDO::FETCH_ASSOC);
        $filters['brand'] = $brand['name_en'] ?? 'Unknown';
        $filters['brand_ar'] = $brand['name_ar'] ?? 'Unknown';
        $filters['brand_logo'] = null;

        // Convert brand logo to base64
        if (!empty($brand['logo'])) {
            $logoPaths = [
                __DIR__ . '/../../' . ltrim($brand['logo'], '/'),
                __DIR__ . '/../' . ltrim($brand['logo'], '/'),
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($brand['logo'], '/'),
            ];
            $filters['brand_logo'] = catalog_pdf_data_url_from_candidates($logoPaths, 400, 70);
        }
        $has_brand = true;
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
        $has_search = true;
        
        if (is_numeric($search)) {
            $where[] = "p.id = :product_id";
            $params['product_id'] = $search;
        } else {
            $where[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar)";
            $params['search_en'] = "%$search%";
            $params['search_ar'] = "%$search%";
        }
    }
    
    // Determine grouping type
    $grouping_type = 'no_grouping';
    if ($has_category && !$has_brand && !$has_search) {
        $grouping_type = 'category';
    } elseif ($has_brand && !$has_category && !$has_search) {
        $grouping_type = 'brand';
    }
    
    // Build the WHERE clause
    $whereClause = implode(' AND ', $where);
    
    // Fetch ALL products without pagination - with prices
    $products_query = "
        SELECT 
            p.id,
            p.name_en,
            p.name_ar,
            p.price,
            p.category_id,
            p.brand_id,
            c.name_en as category_name,
            c.name_ar as category_name_ar,
            b.name_en as brand_name,
            b.name_ar as brand_name_ar,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE $whereClause 
        ORDER BY p.featured DESC, p.id DESC
    ";
    
    $products = $db->query($products_query, $params)->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert image paths to base64 for PDF. The first photo is also
    // kept larger so the cover stays sharp.
    $filters['cover_image'] = null;
    foreach ($products as &$product) {
        if (!empty($product['main_image'])) {
            // Try multiple possible paths
            $possiblePaths = [
                __DIR__ . '/../../' . $product['main_image'],
                __DIR__ . '/../' . $product['main_image'],
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($product['main_image'], '/')
            ];

            if ($filters['cover_image'] === null) {
                $filters['cover_image'] = catalog_pdf_data_url_from_candidates($possiblePaths, 900, 72);
            }
            $product['main_image'] = catalog_pdf_data_url_from_candidates($possiblePaths, 260, 58);
        }
    }
    unset($product); // Break reference
    
    // Organize products by grouping type
    $organized_products = $products;
    $groups = [];
    $group_names = [];
    
    if ($grouping_type === 'category') {
        $groups = [];
        $group_names = [];
        
        foreach ($products as $product) {
            $category_name = $product['category_name'] ?? 'Uncategorized';
            if (!isset($groups[$category_name])) {
                $groups[$category_name] = [];
                $group_names[$category_name] = $product['category_name_ar'] ?? $category_name;
            }
            $groups[$category_name][] = $product;
        }
    } elseif ($grouping_type === 'brand') {
        $groups = [];
        $group_names = [];
        
        foreach ($products as $product) {
            $brand_name = $product['brand_name'] ?? 'Uncategorized';
            if (!isset($groups[$brand_name])) {
                $groups[$brand_name] = [];
                $group_names[$brand_name] = $product['brand_name_ar'] ?? $brand_name;
            }
            $groups[$brand_name][] = $product;
        }
    }
    
    // Clear any output buffer content (errors, warnings, etc.)
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'products' => $organized_products,
        'groups' => $groups,
        'group_names' => $group_names,
        'grouping_type' => $grouping_type,
        'filters' => $filters,
        'total' => count($products)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    error_log("Export Search Results PDF Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch products: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
