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
        // Check if item already exists in cart
        $existing_item = findCartItem($_SESSION['cart'], $product_id, $variation_id);
        
        if ($existing_item) {
            $_SESSION['cart'][$existing_item['key']]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][] = [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'added_at' => time()
            ];
        }
    }
    
    header("Location: cart.php");
    exit;
}

// Handle Remove item
if (isset($_GET['remove'])) {
    $remove_key = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$remove_key])) {
        unset($_SESSION['cart'][$remove_key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
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
            $_SESSION['cart'][$key]['quantity'] = $new_quantity;
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
    $_SESSION['cart_message'] = count($invalid_items) . ' item(s) were removed from your cart due to availability issues.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts for Enhanced Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Main CSS files to match index.php -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/enhanced-design.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <link rel="stylesheet" href="assets/css/cart.css">
</head>
<body>
    <?php require_once(__DIR__ . '/../includes/header.php'); ?>
    <div class="container">

    <!-- Main Content -->
    <main class="container my-4">        
        <!-- Hero Section -->
        <section class="hero-section modern-hero text-center mb-5">
            <div class="hero-content">
                <div class="hero-badge">🛒 Shopping Cart</div>
                <h1 class="hero-title">Your Cart</h1>
                <p class="hero-subtitle">Review your items before checkout</p>
            </div>
        </section>

        <div class="modern-cart-container">
            <?php if (isset($_SESSION['cart_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['cart_message']); ?>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart-modern">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Your cart is empty</h3>
                    <p class="text-muted">Start shopping to add items to your cart</p>
                    <a href="index.php" class="btn btn-primary btn-hero">
                        <i class="fas fa-shopping-bag me-2"></i>Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <!-- Cart Items Column -->
                    <div class="col-lg-8">
                        <div class="cart-items-modern">
                            <div class="cart-header-modern">
                                <h4><i class="fas fa-shopping-cart me-2"></i>Cart Items (<?= array_sum(array_column($_SESSION['cart'], 'quantity')) ?> items)</h4>
                            </div>

                            <?php
                            $grandTotal = 0;
                            foreach ($_SESSION['cart'] as $key => $item):
                                $product = $db->query("SELECT * 
                                 , (SELECT image_path FROM product_images 
                                        WHERE product_images.product_id = products.id AND is_main = 1 LIMIT 1) as main_image 
                                    FROM products WHERE id = :id AND status = 1", ['id' => $item['product_id']])->fetch(PDO::FETCH_ASSOC);
                                
                                if (!$product) {
                                    // Skip invalid products - they were already removed above
                                    continue;
                                }
                                
                                $price = $product['price'];
                                $variationText = "";
                                
                                if (!empty($item['variation_id'])) {
                                    $variation = $db->query("SELECT * FROM product_variations WHERE id = :id", ['id' => $item['variation_id']])->fetch(PDO::FETCH_ASSOC);

                                    if ($variation) {
                                        $variationText = "Size: {$variation['size']} / Color: {$variation['color']}";
                                        $price += $variation['additional_price'];
                                    } else {
                                        $variationText = "Variation not found";
                                    }
                                }

                                $total = $price * $item['quantity'];
                                $grandTotal += $total;
                            ?>
                            <div class="cart-item-modern" data-product-id="<?= $product['id'] ?>">
                                <div class="item-image">
                                    <img src="<?php echo str_replace("../", "", $product['main_image'] ?: 'uploads/default-product.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name_en']); ?>" 
                                         class="product-image">
                                </div>
                                <div class="item-details">
                                    <h5 class="item-title">
                                        <a href="product.php?id=<?= $product['id'] ?>">
                                            <?php echo htmlspecialchars($product['name_en']); ?>
                                        </a>
                                    </h5>
                                    <?php if ($variationText): ?>
                                        <p class="item-variation"><i class="fas fa-tags me-1"></i><?php echo $variationText; ?></p>
                                    <?php endif; ?>
                                    <div class="item-price">
                                        <span class="current-price">AED <?php echo number_format($price, 2); ?></span>
                                        <span class="text-muted"> × <?php echo $item['quantity']; ?></span>
                                    </div>
                                </div>
                                <div class="item-controls">
                                    <div class="quantity-controls">
                                        <button class="btn btn-outline-secondary btn-sm update-qty" 
                                                data-id="<?= $product['id'] ?>" 
                                                data-action="decrease">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <span class="quantity-display"><?= $item['quantity'] ?></span>
                                        <button class="btn btn-outline-secondary btn-sm update-qty" 
                                                data-id="<?= $product['id'] ?>" 
                                                data-action="increase">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div class="item-total-price">
                                        <strong>AED <?php echo number_format($total, 2); ?></strong>
                                    </div>
                                    <button class="btn btn-outline-danger btn-sm remove-item" 
                                            data-id="<?= $product['id'] ?>" 
                                            title="Remove from cart">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cart Summary Column -->
                    <div class="col-lg-4">
                        <div class="cart-summary-modern">
                            <h4><i class="fas fa-receipt me-2"></i>Order Summary</h4>
                            
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span class="fw-bold">AED <?php echo number_format($grandTotal, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping:</span>
                                <span class="text-muted">Calculated at checkout</span>
                            </div>
                            
                            <hr>
                            
                            <div class="summary-row grand-total">
                                <span class="fw-bold">Total:</span>
                                <span class="fw-bold text-success">AED <?php echo number_format($grandTotal, 2); ?></span>
                            </div>

                            <div class="cart-actions-modern">
                                <a href="checkout.php" class="btn btn-success btn-lg w-100 mb-3">
                                    <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                                </a>
                                <a href="index.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                                </a>
                            </div>

                            <!-- Shipping Info -->
                            <div class="shipping-info">
                                <h6><i class="fas fa-truck me-2"></i>Shipping Information</h6>
                                <ul class="shipping-list">
                                    <li><i class="fas fa-check text-success me-2"></i>30 Dirhams shipping to UAE</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Next day delivery available</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Cash on delivery (UAE only)</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Online Payment available</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </main>

    </div>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/enhanced-main.js"></script>
</body>
</html>