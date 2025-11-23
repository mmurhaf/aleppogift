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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Main CSS files -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/enhanced-design.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <link rel="stylesheet" href="assets/css/cart.css">
    <link rel="stylesheet" href="assets/css/width-improvements.css">
    <link rel="stylesheet" href="assets/css/full-width-fix.css">
    
    <style>
        :root {
            --cart-primary: #E67B2E;
            --cart-primary-dark: #C66524;
            --cart-secondary: #f8f9fa;
            --cart-accent: #28a745;
            --cart-danger: #dc3545;
            --cart-border: #e9ecef;
            --cart-shadow: 0 2px 20px rgba(0,0,0,0.08);
            --cart-shadow-hover: 0 4px 30px rgba(0,0,0,0.12);
            --cart-black: #000000;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Inter', sans-serif;
        }

        .modern-hero {
            background: linear-gradient(135deg, #E67B2E 0%, #C66524 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
        }

        .modern-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="g" cx="20" cy="20" r="20"><stop offset="0" stop-color="rgba(255,255,255,.1)"/><stop offset="1" stop-color="transparent"/></radialGradient></defs><rect width="100" height="20" fill="url(%23g)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .cart-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--cart-shadow);
            margin-top: -2rem;
            position: relative;
            z-index: 10;
        }

        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--cart-border);
        }

        .cart-header h2 {
            font-family: 'Playfair Display', serif;
            color: var(--cart-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-stats {
            display: flex;
            gap: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .cart-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            border-radius: 15px;
            border: 2px dashed var(--cart-border);
        }

        .empty-cart-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }

        .empty-cart h3 {
            font-family: 'Playfair Display', serif;
            color: var(--cart-primary);
            margin-bottom: 1rem;
        }

        .cart-item {
            background: white;
            border: 1px solid var(--cart-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cart-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--cart-primary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--cart-shadow-hover);
        }

        .cart-item:hover::before {
            transform: scaleY(1);
        }

        .item-content {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            align-items: center;
        }

        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .cart-item:hover .item-image img {
            transform: scale(1.05);
        }

        .item-details h5 {
            font-weight: 600;
            color: var(--cart-primary);
            margin-bottom: 0.5rem;
        }

        .item-details h5 a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .item-details h5 a:hover {
            color: var(--cart-primary-dark);
        }

        .item-variation {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--cart-accent);
        }

        .item-controls {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            border: 2px solid var(--cart-border);
            border-radius: 25px;
            overflow: hidden;
            background: white;
        }

        .quantity-control button {
            border: none;
            background: none;
            padding: 0.5rem 0.75rem;
            color: var(--cart-primary);
            transition: all 0.3s ease;
        }

        .quantity-control button:hover {
            background: var(--cart-primary);
            color: white;
        }

        .quantity-display {
            padding: 0.5rem 1rem;
            font-weight: 600;
            min-width: 50px;
            text-align: center;
            background: var(--cart-secondary);
        }

        .item-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--cart-primary);
        }

        .remove-btn {
            background: none;
            border: 2px solid var(--cart-danger);
            color: var(--cart-danger);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: var(--cart-danger);
            color: white;
            transform: scale(1.1);
        }

        .cart-summary {
            background: white;
            color: var(--cart-black);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--cart-shadow);
            position: sticky;
            top: 2rem;
            border: 2px solid var(--cart-primary);
        }

        .summary-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--cart-primary);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            color: var(--cart-black);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .grand-total {
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid rgba(0,0,0,0.1);
            color: var(--cart-primary);
        }

        .checkout-btn {
            background: var(--cart-primary);
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin: 1.5rem 0 1rem;
        }

        .checkout-btn:hover {
            background: var(--cart-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(230, 123, 46, 0.3);
        }

        .continue-shopping {
            color: var(--cart-black);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .continue-shopping:hover {
            background: rgba(0,0,0,0.05);
            color: var(--cart-black);
            text-decoration: none;
        }

        .export-buttons {
            margin-top: 1rem;
        }

        .export-pdf-btn {
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 0.75rem 1rem;
        }

        .export-pdf-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .export-pdf-btn .fas {
            transition: transform 0.3s ease;
        }

        .export-pdf-btn:hover .fas {
            transform: scale(1.1);
        }

        .shipping-info {
            background: rgba(0,0,0,0.05);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
            color: var(--cart-black);
        }

        .shipping-info h6 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--cart-primary);
        }

        .shipping-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .shipping-list li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--cart-black);
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .cart-container {
                margin: 1rem;
                padding: 1rem;
                margin-top: -1rem;
            }
            
            .item-content {
                grid-template-columns: 80px 1fr;
                gap: 1rem;
            }
            
            .item-controls {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                margin-top: 1rem;
            }
            
            .cart-stats {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-up {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <!-- Hero Section -->
    <section class="modern-hero text-center">
        <div class="container-fluid" style="max-width: 1600px;">
            <div class="hero-content">
                <div class="hero-badge">🛒 Shopping Cart</div>
                <h1 class="hero-title">Your Cart</h1>
                <p class="hero-subtitle">Review your items and proceed to checkout</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container-fluid" style="max-width: 1600px;">
        <div class="cart-container fade-in">
            <?php if (isset($_SESSION['cart_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['cart_message']); ?>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Your cart is empty</h3>
                    <p class="text-muted mb-4">Discover amazing products from Dubai and start shopping today!</p>
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-header">
                    <h2>
                        <i class="fas fa-shopping-cart"></i>
                        Shopping Cart
                    </h2>
                    <div class="cart-stats">
                        <div class="cart-stat">
                            <i class="fas fa-box"></i>
                            <span><?= count($_SESSION['cart']) ?> <?= count($_SESSION['cart']) === 1 ? 'item' : 'items' ?></span>
                        </div>
                        <div class="cart-stat">
                            <i class="fas fa-layer-group"></i>
                            <span><?= array_sum(array_column($_SESSION['cart'], 'quantity')) ?> total quantity</span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Cart Items Column -->
                    <div class="col-lg-8">
                        <div class="cart-items">
                            <?php
                            $grandTotal = 0;
                            foreach ($_SESSION['cart'] as $key => $item):
                                $product = $db->query("SELECT * 
                                 , (SELECT image_path FROM product_images 
                                        WHERE product_images.product_id = products.id AND is_main = 1 LIMIT 1) as main_image 
                                    FROM products WHERE id = :id AND status = 1", ['id' => $item['product_id']])->fetch(PDO::FETCH_ASSOC);
                                
                                if (!$product) {
                                    continue;
                                }
                                
                                $price = $product['price'];
                                $variationText = "";
                                
                                if (!empty($item['variation_id'])) {
                                    $variation = $db->query("SELECT * FROM product_variations WHERE id = :id", ['id' => $item['variation_id']])->fetch(PDO::FETCH_ASSOC);

                                    if ($variation) {
                                        $variationText = "Size: {$variation['size']} / Color: {$variation['color']}";
                                        $price += $variation['additional_price'];
                                    }
                                }

                                $total = $price * $item['quantity'];
                                $grandTotal += $total;
                            ?>
                            <div class="cart-item slide-up" data-product-id="<?= $product['id'] ?>">
                                <div class="item-content">
                                    <div class="item-image">
                                        <img src="<?php echo str_replace("../", "", $product['main_image'] ?: 'uploads/default-product.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name_en']); ?>">
                                    </div>
                                    <div class="item-details">
                                        <h5>
                                            <a href="product.php?id=<?= $product['id'] ?>">
                                                <?php echo htmlspecialchars($product['name_en']); ?>
                                            </a>
                                        </h5>
                                        <?php if ($variationText): ?>
                                            <p class="item-variation">
                                                <i class="fas fa-tags me-1"></i><?php echo $variationText; ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="item-price">
                                            AED <?php echo number_format($price, 2); ?> each
                                        </div>
                                    </div>
                                    <div class="item-controls">
                                        <div class="quantity-control">
                                            <button class="update-qty" data-id="<?= $product['id'] ?>" data-action="decrease">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="quantity-display"><?= $item['quantity'] ?></span>
                                            <button class="update-qty" data-id="<?= $product['id'] ?>" data-action="increase">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <div class="item-total">
                                            AED <?php echo number_format($total, 2); ?>
                                        </div>
                                        <button class="remove-btn remove-item" data-id="<?= $product['id'] ?>" title="Remove from cart">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cart Summary Column -->
                    <div class="col-lg-4">
                        <div class="cart-summary">
                            <div class="summary-header">
                                <i class="fas fa-receipt"></i>
                                <span>Order Summary</span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span>AED <?php echo number_format($grandTotal, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping:</span>
                                <span>AED 30.00</span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax:</span>
                                <span>Calculated at checkout</span>
                            </div>
                            
                            <div class="summary-row grand-total">
                                <span>Total:</span>
                                <span>AED <?php echo number_format($grandTotal + 30, 2); ?></span>
                            </div>

                            <button class="btn checkout-btn" onclick="window.location.href='checkout.php'">
                                <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                            </button>
                            
                            <!-- Export to PDF buttons -->
                            <div class="export-buttons mt-3">
                                <button class="btn btn-outline-primary w-100 mb-2 export-pdf-btn" data-prices="yes">
                                    <i class="fas fa-file-pdf me-2"></i>Export with Prices
                                </button>
                                <button class="btn btn-outline-secondary w-100 export-pdf-btn" data-prices="no">
                                    <i class="fas fa-file-pdf me-2"></i>Export without Prices
                                </button>
                            </div>
                            
                            <a href="index.php" class="continue-shopping">
                                <i class="fas fa-arrow-left"></i>
                                <span>Continue Shopping</span>
                            </a>

                            <div class="shipping-info">
                                <h6><i class="fas fa-truck me-2"></i>Shipping Information</h6>
                                <ul class="shipping-list">
                                    
                                    <li><i class="fas fa-check text-success me-2"></i>Next day delivery available</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Cash on delivery (UAE only)</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Secure online payment</li>
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
    <script src="assets/js/main.js"></script>
    <script src="assets/js/enhanced-main.js"></script>

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
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
                    
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
                        showNotification('PDF export started! Check your downloads folder.', 'success');
                    }, 1500);
                });
            });

            // Enhanced quantity controls
            document.querySelectorAll('.update-qty').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.id;
                    const action = this.dataset.action;
                    const quantityDisplay = this.closest('.quantity-control').querySelector('.quantity-display');
                    const currentQty = parseInt(quantityDisplay.textContent);
                    
                    let newQty = action === 'increase' ? currentQty + 1 : currentQty - 1;
                    
                    if (newQty < 1) {
                        if (confirm('Remove this item from cart?')) {
                            // Handle remove
                            window.location.href = `?remove=${productId}`;
                        }
                        return;
                    }
                    
                    // Update quantity via AJAX or form submission
                    quantityDisplay.textContent = newQty;
                    
                    // Update total price
                    updateItemTotal(this.closest('.cart-item'), newQty);
                });
            });

            // Enhanced remove buttons
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.id;
                    const itemElement = this.closest('.cart-item');
                    
                    if (confirm('Are you sure you want to remove this item from your cart?')) {
                        itemElement.style.animation = 'fadeOut 0.3s ease-out forwards';
                        setTimeout(() => {
                            window.location.href = `?remove=${productId}`;
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
</body>
</html>