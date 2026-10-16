<?php
// Ensure session is started first and only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/shared_cart.php');
sharedCartWatch();

require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../includes/Database.php');
require_once(__DIR__ . '/../../includes/functions.php');
require_once(__DIR__ . '/../../includes/currencies.php');
require_once(__DIR__ . '/../../includes/store_features.php');

// Set proper headers to prevent caching issues
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Debug logging - only enabled when DEBUG_MODE is true
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("Cart Preview Debug - Session ID: " . session_id());
    error_log("Cart Preview Debug - Request Time: " . date('Y-m-d H:i:s'));
    error_log("Cart Preview Debug - Cart data: " . print_r($_SESSION['cart'] ?? [], true));
}

// Ensure session is writable and readable
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Quick validation - remove invalid items
foreach ($_SESSION['cart'] as $key => $item) {
    if (!isset($item['product_id']) || $item['product_id'] < 1) {
        unset($_SESSION['cart'][$key]);
    }
}
$_SESSION['cart'] = array_values($_SESSION['cart']);

try {
    // Include cart helpers
    require_once(__DIR__ . '/../../includes/helpers/bundles.php');
    require_once(__DIR__ . '/../../includes/cart_helpers.php');
    
    // Validate session and cart
    validateCartSession();
    
    $db = new Database();
    if (!$db->conn) {
        error_log("Cart Preview Error: Failed to connect to the database.");
        echo "<p class='text-danger text-center py-3'>" . te('cart.preview_db_error') . "</p>";
        return;
    }
    $cart = $_SESSION['cart'] ?? [];
    session_write_close();

    if (empty($cart)) {
        $shopUrl = function_exists('store_url') ? store_url('#products') : 'index.php#products';
        echo "<div class='cart-preview-empty'>
                <i class='fas fa-shopping-bag' aria-hidden='true'></i>
                <p>" . te('header.cart_empty') . "</p>
                <a class='btn btn-primary btn-sm' href='" . htmlspecialchars($shopUrl) . "'>" . te('header.browse_products') . "</a>
              </div>";
        return;
    }

    $pricedCart = priceCart($db, $cart);
    $total = 0;
    $total_items = 0;
    $processed_items = 0;
    $skipped_items = 0;
    $rendered_keys = [];

    $renderPreviewItem = function ($key, $item) use ($db, $pricedCart, &$total, &$total_items, &$processed_items, &$skipped_items, &$rendered_keys) {
        if (isset($rendered_keys[$key])) {
            return;
        }
        $rendered_keys[$key] = true;
        // Get product with image and status in a single query
        $product = $db->query(
            "SELECT p.id, p.name_en, p.name_ar, p.price, p.status,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
             FROM products p WHERE p.id = :id", 
            ['id' => $item['product_id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            error_log("Cart Preview: Product ID {$item['product_id']} not found in database");
            $skipped_items++;
            return;
        }
        
        if ($product['status'] != 1) {
            error_log("Cart Preview: Product ID {$item['product_id']} is inactive (status: {$product['status']})");
            $skipped_items++;
            return;
        }

        $processed_items++;

        $priced = $pricedCart['lines'][$key] ?? null;
        $price = $priced['catalog_unit'] ?? $product['price'];
        $display_image = $product['main_image'];
        $variation = null;
        
        // Add variation price if applicable and get variant image
        if (!empty($item['variation_id'])) {
            $variation = $db->query(
                "SELECT pv.additional_price, pv.size, pv.color, pi.image_path as variant_image
                 FROM product_variations pv
                 LEFT JOIN product_images pi ON pv.image_id = pi.id
                 WHERE pv.id = :id", 
                ['id' => $item['variation_id']]
            )->fetch(PDO::FETCH_ASSOC);
            
            if ($variation && $priced === null) {
                $price += $variation['additional_price'];
                
                // Use variant-specific image if available
                if (!empty($variation['variant_image'])) {
                    $display_image = $variation['variant_image'];
                }
            }
        }

        $lineTotal = $priced['line_total'] ?? ($item['quantity'] * $price);
        $total += $lineTotal;
        $total_items += $item['quantity'];
        
        // Use helper function to resolve image path
        $resolved_image = function_exists('resolveImagePath') 
            ? resolveImagePath($display_image) 
            : ($display_image ?: 'uploads/default-product.jpg');
        $image_src = function_exists('store_url') ? store_url($resolved_image) : $resolved_image;
        $product_name = loc_field($product, 'name');
        if ($processed_items === 1) {
            echo '<div class="cart-preview-list">';
        }
?>
<div class="cart-preview-item" data-product-id="<?= (int)$item['product_id'] ?>">
    <img src="<?= htmlspecialchars($image_src) ?>"
         alt="<?= htmlspecialchars($product_name) ?>"
         onerror="this.src='<?= htmlspecialchars(function_exists('store_url') ? store_url('assets/images/no-image.png') : 'assets/images/no-image.png') ?>'">
    <div class="item-copy">
        <div class="item-name"><?= htmlspecialchars($product_name) ?></div>
        <?php if (!empty($priced['bundle_name_en'])): ?>
            <div class="item-meta"><?= htmlspecialchars($priced['bundle_name_en']) ?></div>
        <?php endif; ?>
        <?php if (!empty($variation)): ?>
            <div class="item-meta"><?= htmlspecialchars(trim(($variation['size'] ?? '') . ' ' . ($variation['color'] ?? ''))) ?></div>
        <?php endif; ?>
        <div class="cart-preview-qty" aria-label="<?= te('cart.quantity') ?>">
            <button type="button" class="update-qty" data-key="<?= (int)$key ?>" data-id="<?= (int)$item['product_id'] ?>" data-action="decrease" aria-label="<?= te('cart.decrease_qty') ?>">−</button>
            <input type="number" class="quantity-input" min="1" max="9999" step="1" inputmode="numeric" autocomplete="off"
                   value="<?= (int)$item['quantity'] ?>" data-qty="<?= (int)$item['quantity'] ?>" data-key="<?= (int)$key ?>" data-id="<?= (int)$item['product_id'] ?>"
                   aria-label="<?= te('cart.quantity') ?>">
            <button type="button" class="update-qty" data-key="<?= (int)$key ?>" data-id="<?= (int)$item['product_id'] ?>" data-action="increase" aria-label="<?= te('cart.increase_qty') ?>">+</button>
        </div>
    </div>
    <div class="cart-preview-side">
        <div class="line-total"><?= format_store_money($lineTotal) ?></div>
        <button type="button" class="cart-preview-remove remove-item" data-key="<?= (int)$key ?>" data-id="<?= (int)$item['product_id'] ?>" title="<?= te('cart.remove_from_cart') ?>" aria-label="<?= te('cart.remove_named', ['name' => $product_name]) ?>">
            <i class="fas fa-trash" aria-hidden="true"></i>
        </button>
    </div>
</div>
<?php
    };

    foreach ($pricedCart['bundles'] as $bundleGroup) {
        echo '<div class="item-meta" style="padding:0.35rem 0.75rem;">'
            . htmlspecialchars(loc_field($bundleGroup, 'name') . ' — ' . $bundleGroup['message'])
            . '</div>';
        foreach ($bundleGroup['line_keys'] as $key) {
            if (!isset($cart[$key])) {
                continue;
            }
            $renderPreviewItem($key, $cart[$key]);
        }
    }
    foreach ($cart as $key => $item) {
        $renderPreviewItem($key, $item);
    }
?>

<?php 
// Log summary for debugging
error_log("Cart Preview Summary - Total cart items: " . count($cart) . ", Processed: $processed_items, Skipped: $skipped_items");

// Show message if no items could be processed
if ($processed_items == 0 && count($cart) > 0): ?>
    <div class="text-center py-4">
        <div class="empty-cart-icon-small mb-3">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ffc107;"></i>
        </div>
        <p class="text-warning mb-2"><?= te('cart.preview_unavailable') ?></p>
        <small class="text-muted"><?= te('cart.preview_unavailable_hint') ?></small>
    </div>
<?php endif; ?>

<?php if ($processed_items > 0): ?>
</div>
<div class="cart-preview-footer">
    <div class="cart-preview-total">
        <span><?= te($total_items != 1 ? 'cart.items_many' : 'cart.items_one', ['count' => (int)$total_items]) ?></span>
        <strong><?= format_store_money($total) ?></strong>
    </div>
    <div class="cart-preview-actions">
        <a href="<?= htmlspecialchars(function_exists('store_url') ? store_url('cart.php') : 'cart.php') ?>" class="btn btn-outline-secondary"><?= te('cart.view_cart') ?></a>
        <a href="<?= htmlspecialchars(function_exists('store_url') ? store_url('checkout.php') : 'checkout.php') ?>" class="btn btn-primary"><?= te('checkout') ?></a>
    </div>
</div>
<?php endif; ?>

<?php
} catch (Exception $e) {
    error_log("Cart preview error: " . $e->getMessage());
    echo "<p class='text-danger text-center py-3'>" . te('cart.preview_error') . "</p>";
}
