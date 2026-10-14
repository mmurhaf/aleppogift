<?php
// File: admin/ajax/auto_create_variants.php
// Automatically creates product variations for each product image

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(dirname(__DIR__)));

require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

header('Content-Type: application/json');

try {
    require_admin_login();
    
    $db = new Database();
    
    // Get product ID from POST
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    if ($product_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid product ID'
        ]);
        exit;
    }
    
    // Verify product exists and get stock
    $product = $db->query("SELECT id, name_en, stock FROM products WHERE id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
        exit;
    }
    
    // Use parent product stock for variants (fallback to 0 if null)
    $variant_stock = isset($product['stock']) ? (int)$product['stock'] : 0;
    
    // Get all images for this product
    $images = $db->query(
        "SELECT id, image_path, is_main FROM product_images WHERE product_id = :id ORDER BY display_order ASC, id ASC",
        ['id' => $product_id]
    )->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($images)) {
        echo json_encode([
            'success' => false,
            'message' => 'No images found for this product'
        ]);
        exit;
    }
    
    // Check if variants already exist for these images
    $existing_variants = $db->query(
        "SELECT image_id FROM product_variations WHERE product_id = :id AND image_id IS NOT NULL",
        ['id' => $product_id]
    )->fetchAll(PDO::FETCH_ASSOC);
    
    $existing_image_ids = array_column($existing_variants, 'image_id');
    
    $created_count = 0;
    $skipped_count = 0;
    $created_variants = [];
    
    foreach ($images as $index => $image) {
        // Skip if variant already exists for this image
        if (in_array($image['id'], $existing_image_ids)) {
            $skipped_count++;
            continue;
        }
        
        // Create variant name based on image
        $variant_name = "Image Variant " . ($index + 1);
        if ($image['is_main']) {
            $variant_name = "Main Image Variant";
        }
        
        // Insert new variation linked to this image
        $db->query(
            "INSERT INTO product_variations (product_id, image_id, size, color, additional_price, stock) 
             VALUES (:product_id, :image_id, :size, :color, :additional_price, :stock)",
            [
                'product_id' => $product_id,
                'image_id' => $image['id'],
                'size' => null,
                'color' => $variant_name,
                'additional_price' => 0.00,
                'stock' => $variant_stock
            ]
        );
        
        $variant_id = $db->lastInsertId();
        
        $created_variants[] = [
            'id' => $variant_id,
            'image_id' => $image['id'],
            'name' => $variant_name
        ];
        
        $created_count++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully created {$created_count} variant(s)" . ($skipped_count > 0 ? " ({$skipped_count} already existed)" : ""),
        'created_count' => $created_count,
        'skipped_count' => $skipped_count,
        'variants' => $created_variants
    ]);
    
} catch (Exception $e) {
    error_log("Auto-create variants error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating variants: ' . $e->getMessage()
    ]);
}
