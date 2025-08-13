<?php
session_start();
require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../includes/Database.php');

try {
    $db = new Database();
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

    foreach ($cart as $item):
        $product = $db->query(
            "SELECT p.name_en, p.price, 
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
             FROM products p WHERE p.id = :id AND p.status = 1", 
            ['id' => $item['product_id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) continue; // Skip if product no longer exists or inactive

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
<div class="cart-item-preview" data-product-id="<?= $item['product_id'] ?>">
    <div class="item-image-small">
        <img src="<?= htmlspecialchars($product['main_image'] ?: 'uploads/default-product.jpg') ?>" 
             alt="<?= htmlspecialchars($product['name_en']) ?>">
    </div>
    <div class="item-info flex-grow-1">
        <div class="item-name"><?= htmlspecialchars($product['name_en']) ?></div>
        <?php if (!empty($variation)): ?>
            <div class="item-variation text-muted small">
                <?= htmlspecialchars($variation['size']) ?> / <?= htmlspecialchars($variation['color']) ?>
            </div>
        <?php endif; ?>
        <div class="item-price text-success">
            <small>AED <?= number_format($lineTotal, 2) ?></small>
        </div>
    </div>
    <div class="item-actions d-flex flex-column align-items-center gap-1">
        <div class="quantity-controls-small d-flex align-items-center gap-1">
            <button class="btn btn-sm btn-outline-secondary update-qty" 
                    data-id="<?= $item['product_id'] ?>" 
                    data-action="decrease"
                    style="width: 25px; height: 25px; padding: 0; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-minus" style="font-size: 0.7rem;"></i>
            </button>
            <span class="quantity-display px-2" style="font-size: 0.9rem; font-weight: 600;">
                <?= $item['quantity'] ?>
            </span>
            <button class="btn btn-sm btn-outline-secondary update-qty" 
                    data-id="<?= $item['product_id'] ?>" 
                    data-action="increase"
                    style="width: 25px; height: 25px; padding: 0; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-plus" style="font-size: 0.7rem;"></i>
            </button>
        </div>
        <button class="btn btn-sm btn-outline-danger remove-item" 
                data-id="<?= $item['product_id'] ?>" 
                title="Remove from cart"
                style="width: 25px; height: 25px; padding: 0; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-trash" style="font-size: 0.7rem;"></i>
        </button>
    </div>
</div>
<?php endforeach; ?>

<hr class="my-3">

<div class='d-flex justify-content-between align-items-center fw-bold mb-3'>
    <span>Total (<?= $total_items ?> item<?= $total_items != 1 ? 's' : '' ?>):</span>
    <span class="text-success">AED <?= number_format($total, 2) ?></span>
</div>

<?php if ($total > 0): ?>
<div class="d-grid gap-2">
    <a href="cart.php" class="btn btn-primary btn-sm" style="border-radius: 8px;">
        <i class="fas fa-shopping-cart me-2"></i>View Full Cart
    </a>
    <a href="checkout.php" class="btn btn-success btn-sm" style="border-radius: 8px;">
        <i class="fas fa-credit-card me-2"></i>Checkout
    </a>
</div>
<?php endif; ?>

<?php
} catch (Exception $e) {
    error_log("Cart preview error: " . $e->getMessage());
    echo "<p class='text-danger text-center py-3'>Error loading cart contents</p>";
}
