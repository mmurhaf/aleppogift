<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    require_once(__DIR__ . '/../../includes/bootstrap.php');
    
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid product ID');
    }
    
    $product_id = (int)$_GET['id'];
    $db = new Database();
    
    // Get product details with category and brand info
    $query = "
        SELECT 
            p.*,
            c.name_en as category_name,
            b.name_en as brand_name,
            (SELECT image_path FROM product_images 
             WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE p.id = :product_id AND p.status = 1
    ";
    
    $stmt = $db->query($query, ['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Get additional product images if available
    $images_query = "
        SELECT image_path 
        FROM product_images 
        WHERE product_id = :product_id AND is_main = 0
        ORDER BY sort_order ASC
    ";
    
    try {
        $images_stmt = $db->query($images_query, ['product_id' => $product_id]);
        $additional_images = $images_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // If product_images table doesn't exist, continue without additional images
        $additional_images = [];
    }
    
    // Format the response
    $response = [
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'name_en' => $product['name_en'],
            'name_ar' => $product['name_ar'] ?? '',
            'description_en' => $product['description_en'] ?? '',
            'description_ar' => $product['description_ar'] ?? '',
            'price' => $product['price'],
            'main_image' => $product['main_image'] ?: 'assets/images/no-image.png',
            'category_name' => $product['category_name'] ?? '',
            'brand_name' => $product['brand_name'] ?? '',
            'stock_quantity' => $product['stock_quantity'] ?? 0,
            'additional_images' => $additional_images
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
