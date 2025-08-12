<?php
session_start();
require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../includes/Database.php');

try {
    $db = new Database();
    $cart = $_SESSION['cart'] ?? [];

    if (empty($cart)) {
        echo "<p class='text-muted text-center py-3'>Your cart is empty.</p>";
        return;
    }

    $total = 0;
    $total_items = 0;

    foreach ($cart as $item):
        $product = $db->query(
            "SELECT name_en, price FROM products WHERE id = :id AND status = 1", 
            ['id' => $item['product_id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) continue; // Skip if product no longer exists or inactive

        $price = $product['price'];
        
        // Add variation price if applicable
        if (!empty($item['variation_id'])) {
            $variation = $db->query(
                "SELECT additional_price FROM product_variations WHERE id = :id", 
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
<div class="d-flex justify-content-between align-items-center small mb-2">
    <div>
        <strong><?= htmlspecialchars($product['name_en']) ?></strong><br>
        <div class="d-flex align-items-center gap-2 mt-1">
            <button class="btn btn-sm btn-outline-secondary update-qty" 
                    data-id="<?= $item['product_id'] ?>" 
                    data-action="decrease">➖</button>
            <span class="quantity-display"><?= $item['quantity'] ?></span>
            <button class="btn btn-sm btn-outline-secondary update-qty" 
                    data-id="<?= $item['product_id'] ?>" 
                    data-action="increase">➕</button>
        </div>
        <small class="text-muted">AED <?= number_format($lineTotal, 2) ?></small>
    </div>
    <button class="btn btn-sm btn-danger remove-item" 
            data-id="<?= $item['product_id'] ?>" 
            title="Remove from cart">🗑</button>
</div>
<?php endforeach; ?>

<hr>
<div class='d-flex justify-content-between fw-bold mb-2'>
    <span>Total (<?= $total_items ?> items):</span>
    <span>AED <?= number_format($total, 2) ?></span>
</div>

<?php if ($total > 0): ?>
<div class="d-grid gap-2">
    <a href="cart.php" class="btn btn-primary btn-sm">View Full Cart</a>
    <a href="checkout.php" class="btn btn-success btn-sm">Proceed to Checkout</a>
</div>
<?php endif; ?>

<?php
} catch (Exception $e) {
    error_log("Cart preview error: " . $e->getMessage());
    echo "<p class='text-danger text-center py-3'>Error loading cart contents</p>";
}
