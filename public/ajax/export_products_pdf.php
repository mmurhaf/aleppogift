<?php
/**
 * Export Products to PDF - AJAX endpoint
 * Fetches all filtered products without pagination for PDF generation
 */

// Start output buffering to catch any errors
ob_start();

header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/../../includes/bootstrap.php');
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
            c.name_en as category_name, 
            b.name_en as brand_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE $whereClause 
        ORDER BY p.featured DESC, p.id DESC
    ";
    
    $products = $db->query($products_query, $params)->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert image paths to base64 for PDF
    foreach ($products as &$product) {
        if (!empty($product['main_image'])) {
            // Try multiple possible paths
            $possiblePaths = [
                __DIR__ . '/../../' . $product['main_image'],
                __DIR__ . '/../' . $product['main_image'],
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($product['main_image'], '/')
            ];
            
            $imageFound = false;
            foreach ($possiblePaths as $imagePath) {
                if (file_exists($imagePath) && is_file($imagePath)) {
                    try {
                        $imageData = @file_get_contents($imagePath);
                        if ($imageData !== false) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $imagePath);
                            finfo_close($finfo);
                            
                            $base64 = base64_encode($imageData);
                            $product['main_image'] = "data:$mimeType;base64,$base64";
                            $imageFound = true;
                            break;
                        }
                    } catch (Exception $e) {
                        error_log("Image conversion error for {$imagePath}: " . $e->getMessage());
                    }
                }
            }
            
            if (!$imageFound) {
                $product['main_image'] = null;
            }
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
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    error_log("Export PDF Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch products: ' . $e->getMessage()
    ]);
}
