<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../includes/Database.php');

// Set proper headers
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $db = new Database();
    $cart = $_SESSION['cart'] ?? [];
    
    if (empty($cart)) {
        echo '<div class="text-center p-3">
                <i class="fas fa-shopping-cart text-muted" style="font-size: 2rem;"></i>
                <p class="text-muted mt-2 mb-0">Your cart is empty</p>
              </div>';
        exit;
    }
    
    $total = 0;
    $totalItems = 0;
    
    foreach ($cart as $item) {
        // Get product details
        $product = $db->query(
            "SELECT p.id, p.name_en, p.price, p.status,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
             FROM products p WHERE p.id = :id AND p.status = 1", 
            ['id' => $item['product_id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) continue;
        
        $lineTotal = $item['quantity'] * $product['price'];
        $total += $lineTotal;
        $totalItems += $item['quantity'];
        
        // Clean image path
        $imagePath = $product['main_image'] ? str_replace('../', '', $product['main_image']) : 'uploads/default-product.jpg';
        ?>
        
        <div class="cart-item border-bottom p-2">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= htmlspecialchars($imagePath) ?>" 
                     alt="<?= htmlspecialchars($product['name_en']) ?>"
                     class="rounded" 
                     style="width: 50px; height: 50px; object-fit: cover;">
                     
                <div class="flex-grow-1 min-width-0">
                    <div class="fw-semibold small text-truncate">
                        <?= htmlspecialchars($product['name_en']) ?>
                    </div>
                    <div class="text-success small">
                        <?= $item['quantity'] ?> × AED <?= number_format($product['price'], 2) ?>
                    </div>
                </div>
                
                <div class="text-end">
                    <div class="fw-bold small">AED <?= number_format($lineTotal, 2) ?></div>
                    <button class="btn btn-sm btn-outline-danger" 
                            onclick="removeFromCart(<?= $product['id'] ?>)"
                            title="Remove">
                        <i class="fas fa-trash" style="font-size: 0.7rem;"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    if ($totalItems > 0) {
        ?>
        <div class="p-3 bg-light">
            <div class="d-flex justify-content-between fw-bold mb-2">
                <span>Total (<?= $totalItems ?> items):</span>
                <span class="text-success">AED <?= number_format($total, 2) ?></span>
            </div>
            <div class="d-grid gap-2">
                <a href="cart.php" class="btn btn-outline-primary btn-sm">View Cart</a>
                <a href="checkout.php" class="btn btn-success btn-sm">Checkout</a>
            </div>
        </div>
        <?php
    }
    
} catch (Exception $e) {
    error_log("Cart preview error: " . $e->getMessage());
    echo '<div class="text-center p-3 text-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <p class="mb-0 small">Error loading cart</p>
          </div>';
}
?>
