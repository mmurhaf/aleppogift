<?php
// Ensure session is started first and only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../includes/Database.php');

// Set proper headers to prevent caching issues
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Additional debug information to track the issue
error_log("Cart Preview Debug - Session ID: " . session_id());
error_log("Cart Preview Debug - Request Time: " . date('Y-m-d H:i:s'));
error_log("Cart Preview Debug - Cart data: " . print_r($_SESSION['cart'] ?? [], true));
error_log("Cart Preview Debug - Session file: " . session_save_path() . '/sess_' . session_id());

// Ensure session is writable and readable
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    error_log("Cart Preview Debug - Initialized empty cart array");
}

// Embed a small timestamp marker in the returned HTML to help debug empty responses
echo "<!-- CART_PREVIEW_TS: " . date('c') . " -->\n";

try {
    // Include cart helpers
    require_once(__DIR__ . '/../../includes/cart_helpers.php');
    
    // Validate session and cart
    validateCartSession();
    
    $db = new Database();
    if (!$db->conn) {
        error_log("Cart Preview Error: Failed to connect to the database.");
        echo "<p class='text-danger text-center py-3'>Error loading cart: Database connection failed</p>";
        return;
    }
    $cart = $_SESSION['cart'] ?? [];

    if (empty($cart)) {
        echo "<div class='text-center py-4'>
                <div class='empty-cart-icon-small mb-3'>
                    <i class='fas fa-shopping-cart' style='font-size: 2rem; color: #6c757d;'></i>
                </div>
                <p class='text-muted mb-0'>Your cart is empty</p>
              </div>";
        return;
    }

    $total = 0;
    $total_items = 0;
    $processed_items = 0;
    $skipped_items = 0;

    foreach ($cart as $item):
        // Get product with image and status in a single query
        $product = $db->query(
            "SELECT p.id, p.name_en, p.price, p.status,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
             FROM products p WHERE p.id = :id", 
            ['id' => $item['product_id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            error_log("Cart Preview: Product ID {$item['product_id']} not found in database");
            $skipped_items++;
            continue; // Skip if product doesn't exist
        }
        
        if ($product['status'] != 1) {
            error_log("Cart Preview: Product ID {$item['product_id']} is inactive (status: {$product['status']})");
            $skipped_items++;
            continue; // Skip if product is inactive
        }

        $processed_items++;

        $price = $product['price'];
        
        // Add variation price if applicable
        if (!empty($item['variation_id'])) {
            $variation = $db->query(
                "SELECT additional_price, size, color FROM product_variations WHERE id = :id", 
                ['id' => $item['variation_id']]
            )->fetch(PDO::FETCH_ASSOC);
            
            if ($variation) {
                $price += $variation['additional_price'];
            }
        }

        $lineTotal = $item['quantity'] * $price;
        $total += $lineTotal;
        $total_items += $item['quantity'];
?>
<div class="cart-item-preview d-flex align-items-center p-3 border-bottom" data-product-id="<?= $item['product_id'] ?>" style="gap: 12px;">
    <div class="item-image-small">
        <img src="<?= htmlspecialchars(str_replace("../", "", $product['main_image'] ?: 'uploads/default-product.jpg')) ?>" 
             alt="<?= htmlspecialchars($product['name_en']) ?>"
             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #e9ecef;">
    </div>
    <div class="item-info flex-grow-1" style="min-width: 0;">
        <div class="item-name fw-semibold text-truncate" style="font-size: 0.9rem; color: #2c3e50;">
            <?= htmlspecialchars($product['name_en']) ?>
        </div>
        <?php if (!empty($variation)): ?>
            <div class="item-variation text-muted small mb-1">
                <i class="fas fa-tag me-1" style="font-size: 0.7rem;"></i>
                <?= htmlspecialchars($variation['size']) ?> / <?= htmlspecialchars($variation['color']) ?>
            </div>
        <?php endif; ?>
        <div class="item-price text-success fw-bold" style="font-size: 0.9rem;">
            AED <?= number_format($lineTotal, 2) ?>
        </div>
    </div>
    <div class="item-actions d-flex flex-column align-items-center" style="gap: 8px;">
        <div class="quantity-controls-small d-flex align-items-center bg-light rounded-pill px-2 py-1" style="gap: 6px;">
            <button class="btn btn-sm update-qty" 
                    data-id="<?= $item['product_id'] ?>" 
                    data-action="decrease"
                    style="width: 24px; height: 24px; padding: 0; display: flex; align-items: center; justify-content: center; border: none; background: transparent; color: #6c757d;">
                <i class="fas fa-minus" style="font-size: 0.7rem;"></i>
            </button>
            <span class="quantity-display fw-bold text-center" style="min-width: 24px; font-size: 0.85rem; color: #2c3e50;">
                <?= $item['quantity'] ?>
            </span>
            <button class="btn btn-sm update-qty" 
                    data-id="<?= $item['product_id'] ?>" 
                    data-action="increase"
                    style="width: 24px; height: 24px; padding: 0; display: flex; align-items: center; justify-content: center; border: none; background: transparent; color: #6c757d;">
                <i class="fas fa-plus" style="font-size: 0.7rem;"></i>
            </button>
        </div>
        <button class="btn btn-sm text-danger remove-item" 
                data-id="<?= $item['product_id'] ?>" 
                title="Remove from cart"
                style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; border: none; background: transparent; opacity: 0.7; transition: opacity 0.2s;"
                onmouseover="this.style.opacity='1'" 
                onmouseout="this.style.opacity='0.7'">
            <i class="fas fa-trash" style="font-size: 0.75rem;"></i>
        </button>
    </div>
</div>
<?php endforeach; ?>

<?php 
// Log summary for debugging
error_log("Cart Preview Summary - Total cart items: " . count($cart) . ", Processed: $processed_items, Skipped: $skipped_items");

// Show message if no items could be processed
if ($processed_items == 0 && count($cart) > 0): ?>
    <div class="text-center py-4">
        <div class="empty-cart-icon-small mb-3">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ffc107;"></i>
        </div>
        <p class="text-warning mb-2">Cart items are temporarily unavailable</p>
        <small class="text-muted">Some products may have been removed or are inactive</small>
    </div>
<?php endif; ?>

<div class="cart-summary p-3 bg-light rounded-3 mt-3">
    <div class='d-flex justify-content-between align-items-center fw-bold mb-3' style="font-size: 1.1rem;">
        <span style="color: #2c3e50;">Total (<?= $total_items ?> item<?= $total_items != 1 ? 's' : '' ?>):</span>
        <span class="text-success" style="font-size: 1.2rem;">AED <?= number_format($total, 2) ?></span>
    </div>

    <?php if ($total > 0 && $processed_items > 0): ?>
    <div class="d-grid gap-2">
        <a href="cart.php" class="btn btn-outline-primary" style="border-radius: 12px; padding: 10px; font-weight: 600; border-width: 2px;">
            <i class="fas fa-shopping-cart me-2"></i>View Full Cart
        </a>
        <a href="checkout.php" class="btn btn-success" style="border-radius: 12px; padding: 12px; font-weight: 600; background: linear-gradient(135deg, #28a745, #20c997); border: none; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
            <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
} catch (Exception $e) {
    error_log("Cart preview error: " . $e->getMessage());
    echo "<p class='text-danger text-center py-3'>Error loading cart contents</p>";
}
