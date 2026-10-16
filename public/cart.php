<?php
require_once('../includes/bootstrap.php');

$db = new Database();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Add to Cart (legacy support for direct form submissions)
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
    $quantity = (int)$_POST['quantity'];

    // Validate product
    $product = $db->query(
        "SELECT id FROM products WHERE id = :id AND status = 1", 
        ['id' => $product_id]
    )->fetch(PDO::FETCH_ASSOC);

    if ($product && $quantity > 0) {
        $existing_item = findCartItem($_SESSION['cart'], $product_id, $variation_id);

        if ($existing_item) {
            $_SESSION['cart'][$existing_item['key']]['quantity'] += $quantity;
        } else {
            $bundle_line = findCartItem($_SESSION['cart'], $product_id, $variation_id, 'bundle');
            $_SESSION['cart'][] = [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'added_at' => time(),
                'line_role' => $bundle_line ? 'extra' : 'standalone',
                'bundle_id' => $bundle_line['item']['bundle_id'] ?? null,
            ];
        }
    }
    
    header("Location: cart.php");
    exit;
}


// Generate CSRF token if not exists
if (empty($_SESSION['cart_csrf_token'])) {
    $_SESSION['cart_csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Remove item (POST with CSRF protection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['cart_csrf_token']) {
        $_SESSION['cart_error'] = t('cart.flash_invalid_token');
        header("Location: cart.php");
        exit;
    }
    
    $remove_key = (int)$_POST['remove_item'];
    if (isset($_SESSION['cart'][$remove_key])) {
        unset($_SESSION['cart'][$remove_key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        $_SESSION['cart_success'] = t('cart.flash_item_removed');
    }
    header("Location: cart.php");
    exit;
}

// Admin: stop syncing this session's cart to a shared cart link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['detach_shared_cart'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['cart_csrf_token'], (string)$_POST['csrf_token'])) {
        unset($_SESSION['shared_cart_token']);
        $_SESSION['cart_success'] = t('cart.shared_detached');
    } else {
        $_SESSION['cart_error'] = t('cart.flash_invalid_token');
    }
    header("Location: cart.php");
    exit;
}

// Handle quantity update
if (isset($_POST['update_quantity'])) {
    $key = (int)$_POST['item_key'];
    $new_quantity = (int)$_POST['quantity'];
    
    if (isset($_SESSION['cart'][$key])) {
        if ($new_quantity > 0) {
            $line = $_SESSION['cart'][$key];
            $product = $db->query(
                "SELECT stock FROM products WHERE id = :id",
                ['id' => (int)$line['product_id']]
            )->fetch(PDO::FETCH_ASSOC);
            $stock = $product['stock'] ?? null;
            if (!empty($line['variation_id'])) {
                $variation = $db->query(
                    "SELECT stock FROM product_variations WHERE id = :id",
                    ['id' => (int)$line['variation_id']]
                )->fetch(PDO::FETCH_ASSOC);
                $stock = $variation['stock'] ?? $stock;
            }
            $reserved = cartReservedQuantity($_SESSION['cart'], (int)$line['product_id'], $line['variation_id'] ?? null, $key);
            $available = max(0, (int)$stock - $reserved);
            if ($stock !== null && $new_quantity > $available) {
                $_SESSION['cart_error'] = t('cart.stock_limit', ['stock' => $available]);
            } else {
                $_SESSION['cart'][$key]['quantity'] = $new_quantity;
            }
        } else {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
    }
    header("Location: cart.php");
    exit;
}

// Validate cart items
list($valid_items, $invalid_items) = validateCartItems($db, $_SESSION['cart']);

// Remove invalid items from cart
if (!empty($invalid_items)) {
    foreach ($invalid_items as $invalid) {
        unset($_SESSION['cart'][$invalid['key']]);
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    
    // Set flash message for removed items
    $_SESSION['cart_message'] = t('cart.items_removed_unavailable', ['count' => count($invalid_items)]);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('cart.page_title') ?></title>
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/cart.css')) ?>?v=20261016a">
    
</head>
<body>
    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="container store-main my-4">
        <header class="page-hero">
            <p class="page-kicker"><?= te('cart') ?></p>
            <h1><?= te('cart.hero_title') ?></h1>
            <p class="page-lead"><?= te('cart.hero_lead') ?></p>
        </header>
        <ol class="flow-steps" aria-label="<?= te('cart.order_progress') ?>">
            <li class="is-current"><span>1</span> <?= te('cart') ?></li>
            <li><span>2</span> <?= te('checkout') ?></li>
            <li><span>3</span> <?= te('cart.step_confirmation') ?></li>
        </ol>
        <div class="cart-page">
            <?php if (isset($_SESSION['cart_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= te('close') ?>"></button>
                </div>
                <?php unset($_SESSION['cart_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['cart_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['cart_success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= te('close') ?>"></button>
                </div>
                <?php unset($_SESSION['cart_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['cart_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-times-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['cart_error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= te('close') ?>"></button>
                </div>
                <?php unset($_SESSION['cart_error']); ?>
            <?php endif; ?>

            <?php
            /* Show a notice bar when the cart was loaded from a shared link */
            if (!empty($_SESSION['shared_cart_token'])):
            ?>
            <div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="fas fa-link"></i>
                <div class="flex-grow-1">
                    <?php if (!empty($_SESSION['admin_logged_in'])):
                        $sharedCartInfo = $db->query(
                            "SELECT customer_name FROM shared_carts WHERE token = :token LIMIT 1",
                            ['token' => $_SESSION['shared_cart_token']]
                        )->fetch(PDO::FETCH_ASSOC);
                    ?>
                        <strong><?= !empty($sharedCartInfo['customer_name']) ? te('cart.shared_editing_for', ['name' => $sharedCartInfo['customer_name']]) : te('cart.shared_editing') ?></strong>
                        <?= te('cart.shared_editing_text') ?>
                    <?php else: ?>
                        <strong><?= te('cart.shared_loaded') ?></strong>
                        <?= te('cart.shared_text') ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($_SESSION['admin_logged_in'])): ?>
                <form method="post" action="cart.php" class="mb-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['cart_csrf_token']) ?>">
                    <button type="submit" name="detach_shared_cart" value="1" class="btn btn-sm btn-outline-secondary">
                        <?= te('cart.shared_stop') ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3><?= te('header.cart_empty') ?></h3>
                    <p class="text-muted mb-4"><?= te('cart.empty_text') ?></p>
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i><?= te('cart.start_shopping') ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-toolbar">
                    <div class="cart-stats">
                        <div class="cart-stat">
                            <i class="fas fa-box"></i>
                            <span><?= te(count($_SESSION['cart']) === 1 ? 'cart.items_one' : 'cart.items_many', ['count' => count($_SESSION['cart'])]) ?></span>
                        </div>
                        <div class="cart-stat">
                            <i class="fas fa-layer-group"></i>
                            <span><?= te('cart.total_quantity', ['count' => array_sum(array_column($_SESSION['cart'], 'quantity'))]) ?></span>
                        </div>
                    </div>
                </div>

                <div class="row g-4 align-items-start">
                    <!-- Cart Items Column -->
                    <div class="col-lg-8">
                        <div class="cart-items">
                            <?php
                            $pricedCart = priceCart($db, $_SESSION['cart']);
                            $grandTotal = $pricedCart['total'];
                            $bundleSavings = max(0, round($pricedCart['catalog_total'] - $pricedCart['total'], 2));
                            $renderedKeys = [];

                            $renderCartLine = function ($key, $item) use ($db, $pricedCart, &$renderedKeys) {
                                if (isset($renderedKeys[$key])) {
                                    return;
                                }
                                $renderedKeys[$key] = true;
                                $product = $db->query("SELECT p.* 
                                 , (SELECT image_path FROM product_images 
                                        WHERE product_images.product_id = p.id AND is_main = 1 LIMIT 1) as main_image,
                                    b.name_en as brand_name,
                                    b.id as brand_id
                                    FROM products p
                                    LEFT JOIN brands b ON p.brand_id = b.id
                                    WHERE p.id = :id AND p.status = 1", ['id' => $item['product_id']])->fetch(PDO::FETCH_ASSOC);
                                if (!$product) {
                                    return;
                                }
                                $priced = $pricedCart['lines'][$key] ?? null;
                                $price = $priced['catalog_unit'] ?? $product['price'];
                                $lineTotal = $priced['line_total'] ?? ($price * $item['quantity']);
                                $variationText = "";
                                $variation = null;
                                $display_image = $product['main_image'];
                                if (!empty($item['variation_id'])) {
                                    $variation = $db->query("
                                        SELECT pv.*, pi.image_path as variant_image 
                                        FROM product_variations pv
                                        LEFT JOIN product_images pi ON pv.image_id = pi.id
                                        WHERE pv.id = :id
                                    ", ['id' => $item['variation_id']])->fetch(PDO::FETCH_ASSOC);
                                    if ($variation) {
                                        $variationText = t('cart.variation', ['size' => $variation['size'], 'color' => $variation['color']]);
                                        if ($priced === null) {
                                            $price += $variation['additional_price'];
                                            $lineTotal = $price * $item['quantity'];
                                        }
                                        if (!empty($variation['variant_image'])) {
                                            $display_image = $variation['variant_image'];
                                        }
                                    }
                                }
                                $role = $priced['line_role'] ?? ($item['line_role'] ?? 'standalone');
                                include __DIR__ . '/../includes/cart_line.php';
                            };

                            foreach ($pricedCart['bundles'] as $bundleGroup):
                            ?>
                            <div class="bundle-cart-note <?= !empty($bundleGroup['discount_voided']) ? 'is-voided' : 'is-applied' ?>">
                                <strong><?= htmlspecialchars(loc_field($bundleGroup, 'name')) ?></strong>
                                <span><?= htmlspecialchars($bundleGroup['message']) ?></span>
                            </div>
                            <?php
                                foreach ($bundleGroup['line_keys'] as $key):
                                    if (!isset($_SESSION['cart'][$key])) {
                                        continue;
                                    }
                                    $item = $_SESSION['cart'][$key];
                                    $renderCartLine($key, $item);
                                endforeach;
                            endforeach;

                            foreach ($_SESSION['cart'] as $key => $item):
                                $renderCartLine($key, $item);
                            endforeach;
                            ?>
                        </div>
                    </div>

                    <!-- Cart Summary Column -->
                    <div class="col-lg-4">
                        <div class="cart-summary">
                            <div class="summary-header">
                                <i class="fas fa-receipt"></i>
                                <span><?= te('cart.order_summary') ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span><?= te('cart.subtotal') ?></span>
                                <span><?php echo formatAedUsd($pricedCart['catalog_total']); ?></span>
                            </div>
                            <?php if ($bundleSavings > 0): ?>
                            <div class="summary-row">
                                <span><?= te('cart.bundle_discount') ?></span>
                                <span>-<?php echo formatAedUsd($bundleSavings); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="summary-row">
                                <span><?= te('cart.shipping_label') ?></span>
                                <span><?php echo formatAedUsd(30); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span><?= te('cart.tax') ?></span>
                                <span><?= te('cart.tax_at_checkout') ?></span>
                            </div>
                            
                            <div class="summary-row grand-total">
                                <span><?= te('cart.total') ?></span>
                                <span><?php echo formatAedUsd($grandTotal + 30); ?></span>
                            </div>

                            <button class="btn checkout-btn" onclick="window.location.href='checkout.php'">
                                <i class="fas fa-credit-card me-2"></i><?= te('cart.proceed_checkout') ?>
                            </button>
                            
                            <!-- Export to PDF buttons -->
                            <div class="export-buttons">
                                <button class="btn export-pdf-btn" data-prices="yes" type="button">
                                    <i class="fas fa-file-pdf me-1"></i><?= te('cart.pdf_with_prices') ?>
                                </button>
                                <button class="btn export-pdf-btn" data-prices="no" type="button">
                                    <i class="fas fa-file-pdf me-1"></i><?= te('cart.pdf_without_prices') ?>
                                </button>
                            </div>

                            <?php if (!empty($_SESSION['admin_logged_in'])): ?>
                            <!-- ── Admin: Save & Share Cart ── -->
                            <hr class="my-3">
                            <button type="button" class="btn btn-success w-100" id="shareCartBtn"
                                    data-bs-toggle="modal" data-bs-target="#shareCartModal">
                                <i class="fab fa-whatsapp me-2"></i><?= te('cart.save_share') ?>
                            </button>
                            <a href="admin/shared_carts.php" class="btn btn-outline-secondary w-100 mt-2" style="font-size:.85rem">
                                <i class="fas fa-list me-1"></i><?= te('cart.view_shared') ?>
                            </a>
                            <?php endif; ?>
                            
                            <a href="index.php" class="continue-shopping">
                                <i class="fas fa-arrow-left"></i>
                                <span><?= te('cart.continue_shopping') ?></span>
                            </a>

                            <div class="shipping-info">
                                <h6><i class="fas fa-truck me-2"></i><?= te('cart.shipping_info') ?></h6>
                                <ul class="shipping-list">
                                    <li><i class="fas fa-check"></i><?= te('free_uae') ?></li>
                                    <li><i class="fas fa-check"></i><?= te('cart.next_day') ?></li>
                                    <li><i class="fas fa-check"></i><?= te('cart.cod_uae') ?></li>
                                    <li><i class="fas fa-check"></i><?= te('cart.secure_payment') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=20261016qty"></script>
    <script src="assets/js/enhanced-main.js?v=20261012i18n"></script>

    <script>
        // Enhanced cart interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scroll animations
            const cartItems = document.querySelectorAll('.cart-item');
            cartItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
            });

            // PDF Export functionality
            document.querySelectorAll('.export-pdf-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const includePrices = this.dataset.prices === 'yes' ? 'yes' : 'no';
                    const btnText = this.innerHTML;
                    
                    // Disable button and show loading state
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + <?= json_encode(te('cart.generating_pdf'), JSON_UNESCAPED_UNICODE) ?>;
                    
                    // Create a hidden link to trigger download
                    const url = 'ajax/export_cart_pdf.php?prices=' + includePrices;
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'AleppoGift_Cart_' + new Date().toISOString().split('T')[0] + 
                                   (includePrices === 'yes' ? '_with_prices' : '_without_prices') + '.pdf';
                    
                    // Trigger download
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Reset button after a short delay
                    setTimeout(() => {
                        this.disabled = false;
                        this.innerHTML = btnText;
                        
                        // Show success message
                        showNotification(<?= json_encode(te('cart.pdf_started'), JSON_UNESCAPED_UNICODE) ?>, 'success');
                    }, 1500);
                });
            });

            // Enhanced quantity controls
            // CSRF token for secure operations
            const csrfToken = '<?= htmlspecialchars($_SESSION['cart_csrf_token'] ?? '') ?>';
            
            // Helper function to remove item via POST
            function removeCartItem(itemKey) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'cart.php';
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
                
                const removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_item';
                removeInput.value = itemKey;
                form.appendChild(removeInput);
                
                document.body.appendChild(form);
                form.submit();
            }
            
            let quantitySubmitting = false;

            function submitCartQuantity(itemKey, newQty) {
                if (quantitySubmitting) {
                    return;
                }
                const key = parseInt(itemKey, 10);
                const qty = parseInt(newQty, 10);
                if (!Number.isFinite(key) || key < 0 || !Number.isFinite(qty) || qty < 1) {
                    return;
                }
                quantitySubmitting = true;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'cart.php';
                form.innerHTML =
                    '<input type="hidden" name="update_quantity" value="1">' +
                    '<input type="hidden" name="item_key" value="' + key + '">' +
                    '<input type="hidden" name="quantity" value="' + qty + '">';
                document.body.appendChild(form);
                form.submit();
            }

            document.querySelectorAll('.cart-items .quantity-input').forEach(input => {
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        this.blur();
                    }
                });
                input.addEventListener('wheel', function (event) {
                    event.preventDefault();
                }, { passive: false });
                input.addEventListener('change', function () {
                    if (this.dataset.suppressCommit === '1') {
                        delete this.dataset.suppressCommit;
                        return;
                    }
                    const original = parseInt(this.dataset.qty, 10) || 1;
                    const typed = parseInt(this.value, 10);
                    if (!Number.isFinite(typed) || typed < 1) {
                        this.value = String(original);
                        showNotification(<?= json_encode(t('cart.min_quantity'), JSON_UNESCAPED_UNICODE) ?>, 'info');
                        return;
                    }
                    const newQty = Math.min(typed, 9999);
                    this.value = String(newQty);
                    if (newQty === original) {
                        return;
                    }
                    submitCartQuantity(this.dataset.key, newQty);
                });
            });

            document.querySelectorAll('.cart-items .update-qty').forEach(button => {
                button.addEventListener('mousedown', function () {
                    const input = this.closest('.quantity-control').querySelector('.quantity-input');
                    if (input) {
                        input.dataset.suppressCommit = '1';
                    }
                });
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const itemKey = this.dataset.key || this.dataset.id;
                    const action = this.dataset.action;
                    const quantityControl = this.closest('.quantity-control');
                    const quantityInput = quantityControl.querySelector('.quantity-input');
                    const currentQty = quantityInput
                        ? (parseInt(quantityInput.value, 10) || parseInt(quantityInput.dataset.qty, 10) || 1)
                        : (parseInt(quantityControl.querySelector('.quantity-display').textContent, 10) || 1);
                    let newQty = action === 'increase' ? currentQty + 1 : currentQty - 1;

                    if (newQty < 1) {
                        if (confirm(<?= json_encode(t('cart.remove_confirm_short'), JSON_UNESCAPED_UNICODE) ?>)) {
                            removeCartItem(itemKey);
                        }
                        return;
                    }

                    submitCartQuantity(itemKey, Math.min(newQty, 9999));
                });
            });

            // Enhanced remove buttons - using POST with CSRF
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const itemKey = this.dataset.id;
                    const itemElement = this.closest('.cart-item');
                    
                    if (confirm(<?= json_encode(t('cart.remove_confirm'), JSON_UNESCAPED_UNICODE) ?>)) {
                        itemElement.style.animation = 'fadeOut 0.3s ease-out forwards';
                        setTimeout(() => {
                            removeCartItem(itemKey);
                        }, 300);
                    }
                });
            });
        });

        function updateItemTotal(itemElement, quantity) {
            // This function would calculate and update the item total
            // Implementation depends on your specific requirements
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                to {
                    opacity: 0;
                    transform: translateX(-100px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>

<?php if (!empty($_SESSION['admin_logged_in'])): ?>
<!-- ══════════════════════════════════════════════════════
     Share Cart Modal  (admin only)
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="shareCartModal" tabindex="-1" aria-labelledby="shareCartModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="shareCartModalLabel">
            <i class="fab fa-whatsapp me-2"></i><?= te('cart.save_share') ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= te('close') ?>"></button>
      </div>
      <div class="modal-body">
        <!-- Step 1: Fill details -->
        <div id="scStep1">
          <p class="text-muted small mb-3"><?= te('cart.share_intro') ?></p>
          <div class="mb-3">
            <label class="form-label"><?= te('cart.customer_name') ?></label>
            <input type="text" id="scCustomerName" class="form-control" placeholder="<?= te('cart.customer_name_ph') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label"><?= te('cart.customer_phone') ?></label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
              <input type="text" id="scCustomerPhone" class="form-control" placeholder="+971 50 000 0000">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= te('cart.internal_note') ?> <small class="text-muted"><?= te('cart.admin_only') ?></small></label>
            <textarea id="scNote" class="form-control" rows="2" placeholder="<?= te('cart.note_ph') ?>"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= te('cart.link_expires') ?></label>
            <select id="scExpires" class="form-select">
              <option value="3"><?= te('cart.days', ['count' => 3]) ?></option>
              <option value="7" selected><?= te('cart.days', ['count' => 7]) ?></option>
              <option value="14"><?= te('cart.days', ['count' => 14]) ?></option>
              <option value="30"><?= te('cart.days', ['count' => 30]) ?></option>
              <option value="0"><?= te('cart.never') ?></option>
            </select>
          </div>
        </div>

        <!-- Step 2: Show link -->
        <div id="scStep2" class="d-none">
          <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><strong><?= te('cart.cart_saved') ?></strong> <?= te('cart.share_link_text') ?>
          </div>
          <label class="form-label fw-bold"><?= te('cart.share_link') ?></label>
          <div class="input-group mb-3">
            <input type="text" id="scShareUrl" class="form-control font-monospace" readonly>
            <button class="btn btn-outline-secondary" type="button" id="scCopyBtn" onclick="scCopyLink()">
                <i class="fas fa-copy"></i>
            </button>
          </div>
          <div id="scWaWrapper" class="d-none">
            <a id="scWaLink" href="#" target="_blank" class="btn btn-success w-100">
                <i class="fab fa-whatsapp me-2"></i><?= te('cart.open_whatsapp') ?>
            </a>
          </div>
        </div>

        <!-- Error -->
        <div id="scError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer" id="scFooter">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= te('cancel') ?></button>
        <button type="button" class="btn btn-success" id="scSaveBtn" onclick="scSaveCart()">
            <i class="fas fa-share-alt me-1"></i><?= te('cart.generate_link') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var scText = <?= json_encode([
    'saving' => te('cart.saving'),
    'close' => te('close'),
    'cancel' => te('cancel'),
    'generate' => te('cart.generate_link'),
    'unexpected' => t('cart.unexpected_error'),
    'network' => t('cart.network_error'),
], JSON_UNESCAPED_UNICODE) ?>;

function scSaveCart() {
    var btn = document.getElementById('scSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + scText.saving;
    document.getElementById('scError').classList.add('d-none');

    var formData = new FormData();
    formData.append('customer_name',  document.getElementById('scCustomerName').value.trim());
    formData.append('customer_phone', document.getElementById('scCustomerPhone').value.trim());
    formData.append('note',           document.getElementById('scNote').value.trim());
    formData.append('expires_days',   document.getElementById('scExpires').value);

    fetch(<?= json_encode(store_url('ajax/save_shared_cart.php')) ?>, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function (r) {
        return r.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                var plain = (text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(plain ? plain.slice(0, 180) : scText.network);
            }
        });
    })
    .then(function (data) {
        if (data && data.success) {
            document.getElementById('scShareUrl').value = data.share_url;
            document.getElementById('scStep1').classList.add('d-none');
            document.getElementById('scStep2').classList.remove('d-none');
            document.getElementById('scFooter').innerHTML =
                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + scText.close + '</button>';
            if (data.wa_link) {
                document.getElementById('scWaLink').href = data.wa_link;
                document.getElementById('scWaWrapper').classList.remove('d-none');
            }
        } else {
            var errDiv = document.getElementById('scError');
            errDiv.textContent = (data && data.message) ? data.message : scText.unexpected;
            errDiv.classList.remove('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-share-alt me-1"></i>' + scText.generate;
        }
    })
    .catch(function (err) {
        var errDiv = document.getElementById('scError');
        var msg = (err && err.message) ? err.message : scText.network;
        if (msg === 'Failed to fetch' || msg === 'NetworkError when attempting to fetch resource.') {
            msg = scText.network;
        }
        errDiv.textContent = msg;
        errDiv.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-share-alt me-1"></i>' + scText.generate;
    });
}

function scCopyLink() {
    var input = document.getElementById('scShareUrl');
    navigator.clipboard.writeText(input.value).then(function () {
        var btn = document.getElementById('scCopyBtn');
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
    }).catch(function () {
        input.select();
        document.execCommand('copy');
    });
}

// Reset modal when closed
document.getElementById('shareCartModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('scStep1').classList.remove('d-none');
    document.getElementById('scStep2').classList.add('d-none');
    document.getElementById('scError').classList.add('d-none');
    document.getElementById('scFooter').innerHTML =
        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + scText.cancel + '</button>' +
        '<button type="button" class="btn btn-success" id="scSaveBtn" onclick="scSaveCart()">' +
        '<i class="fas fa-share-alt me-1"></i>' + scText.generate + '</button>';
});
</script>
<?php endif; ?>

</body>
</html>