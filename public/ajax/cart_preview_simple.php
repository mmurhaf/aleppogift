<?php
/**
 * Simplified Cart Preview
 * Fast loading cart preview with minimal queries
 */
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo '<div class="text-center py-4">
                <i class="fas fa-shopping-cart mb-2" style="font-size: 2rem; color: #ccc;"></i>
                <p class="text-muted">Your cart is empty</p>
              </div>';
        return;
    }
    
    $db = new Database();
    $cart = $_SESSION['cart'];
    $total = 0;
    $item_count = 0;
    
    // Get all product IDs for single query
    $product_ids = array_column($cart, 'product_id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    
    // Single query to get all products
    $sql = "SELECT id, name_en, price, 
                   (SELECT image_path FROM product_images 
                    WHERE product_id = products.id AND is_main = 1 LIMIT 1) as image
            FROM products 
            WHERE id IN ($placeholders) AND status = 1";
    
    $stmt = $db->conn->prepare($sql);
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create product lookup
    $product_lookup = [];
    foreach ($products as $product) {
        $product_lookup[$product['id']] = $product;
    }
    
    echo '<div class="cart-preview-items">';
    
    foreach ($cart as $item) {
        if (!isset($product_lookup[$item['product_id']])) {
            continue; // Skip invalid products
        }
        
        $product = $product_lookup[$item['product_id']];
        $item_total = $product['price'] * $item['quantity'];
        $total += $item_total;
        $item_count += $item['quantity'];
        
        $image = $product['image'] ? 'uploads/products/' . $product['image'] : 'assets/images/no-image.jpg';
        
        echo '<div class="cart-preview-item d-flex align-items-center mb-2">
                <img src="' . htmlspecialchars($image) . '" alt="Product" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                <div class="flex-grow-1">
                    <div class="fw-bold" style="font-size: 0.9rem;">' . htmlspecialchars($product['name_en']) . '</div>
                    <div class="text-muted" style="font-size: 0.8rem;">Qty: ' . $item['quantity'] . ' × AED ' . number_format($product['price'], 2) . '</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">AED ' . number_format($item_total, 2) . '</div>
                </div>
              </div>';
    }
    
    echo '</div>';
    
    if ($item_count > 0) {
        echo '<hr class="my-3">
              <div class="d-flex justify-content-between align-items-center mb-3">
                  <strong>Total (' . $item_count . ' items):</strong>
                  <strong class="text-success">AED ' . number_format($total, 2) . '</strong>
              </div>';
    }
    
} catch (Exception $e) {
    echo '<div class="text-center py-3 text-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Error loading cart</p>
          </div>';
}
?>
