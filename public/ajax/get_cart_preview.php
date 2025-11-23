<?php
session_start();
require_once('../../includes/bootstrap.php');

$cart_html = '';

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $db = new Database();
    $cart_total = 0;
    
    foreach ($_SESSION['cart'] as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        
        // Get product details
        $product = $db->query(
            "SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
             FROM products p WHERE p.id = :id", 
            ['id' => $product_id]
        )->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $item_total = $product['price'] * $quantity;
            $cart_total += $item_total;
            
            $cart_html .= '
            <div class="cart-item">
                <div class="cart-item-image">
                    <img src="' . ($product['main_image'] ? htmlspecialchars($product['main_image']) : 'assets/images/placeholder.jpg') . '" 
                         alt="' . htmlspecialchars($product['name_en']) . '">
                </div>
                <div class="cart-item-details">
                    <div class="cart-item-title">' . htmlspecialchars($product['name_en']) . '</div>
                    <div class="cart-item-price"><img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol" style="width: 0.8em; height: 0.8em; margin-right: 0.25rem; vertical-align: baseline; filter: brightness(0);">' . number_format($product['price'], 2) . '</div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="updateCartQuantity(' . $product_id . ', -1)">-</button>
                        <span class="quantity-display">' . $quantity . '</span>
                        <button class="quantity-btn" onclick="updateCartQuantity(' . $product_id . ', 1)">+</button>
                    </div>
                </div>
            </div>';
        }
    }
    
    $cart_html .= '
    <div class="cart-summary">
        <div class="cart-total">
            <strong>Total: <img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol" style="width: 0.8em; height: 0.8em; margin-right: 0.25rem; vertical-align: baseline; filter: brightness(0);">' . number_format($cart_total, 2) . '</strong>
        </div>
        <div class="d-grid gap-2 mt-3">
            <a href="cart.php" class="btn btn-primary">View Full Cart</a>
            <a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
        </div>
    </div>';
    
} else {
    $cart_html = '<div class="text-center p-4">
        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
        <p class="text-muted">Your cart is empty</p>
        <a href="index.php" class="btn btn-primary btn-sm">Start Shopping</a>
    </div>';
}

echo $cart_html;
?>
