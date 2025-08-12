<?php
session_start();
require_once('../config/config.php');
require_once('../includes/Database.php');
require_once('../includes/helpers/cart.php');

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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>


    <div class="container">
        
 <?php require_once(__DIR__ . '/../includes/header.php'); ?>
 
		<!-- Cart Preview -->
		<div id="cartPreview" class="card shadow position-absolute end-0 mt-2 me-4 cart-preview" style="display: none;">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0"><i class="fas fa-shopping-cart me-2"></i>Your Cart</h5>
					<button type="button" class="btn-close" aria-label="Close cart" onclick="toggleCart()"></button>
				</div>
				<div id="cart-items-preview">
					<p class="text-muted text-center py-3">Your cart is empty</p>
				</div>
				<div class="d-grid gap-2 mt-3">
					<a href="cart.php" class="btn btn-primary">View Full Cart</a>
					<a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
				</div>
			</div>
		</div>
        

        <div class="cart-container">
            <h2 class="cart-title">Your Shopping Cart</h2>

            <?php if (isset($_SESSION['cart_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['cart_message']); ?>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <p>Your cart is empty</p>
                    <a href="index.php" class="shop-btn">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="cart-items">
                    <div class="cart-header">
                        <div class="header-product">Product</div>
                        <div class="header-variation">Variation</div>
                        <div class="header-qty">Quantity</div>
                        <div class="header-price">Price</div>
                        <div class="header-total">Total</div>
                        <div class="header-remove"></div>
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
                    <div class="cart-item">
                        <div class="item-product">
                            <div class="product-image">
                                <img src="<?php echo str_replace("../", "", $product['main_image']); ?>" alt="<?php echo $product['name_en']; ?>">
                            </div>
                            <div class="product-name"><?php echo $product['name_en']; ?></div>
                        </div>
                        <div class="item-variation"><?php echo $variationText; ?></div>
                        <div class="item-qty"><?php echo $item['quantity']; ?></div>
                        <div class="item-price"><?php echo number_format($price, 2); ?> AED</div>
                        <div class="item-total"><?php echo number_format($total, 2); ?> AED</div>
                        <div class="item-remove">
                            <a href="?remove=<?php echo $key; ?>" class="remove-btn">×</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <div class="summary-row">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value"><?php echo number_format($grandTotal, 2); ?> AED</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Shipping:</span>
                        <span class="summary-value">Calculated at checkout</span>
                    </div>
                    <div class="summary-row grand-total">
                        <span class="summary-label">Total:</span>
                        <span class="summary-value"><?php echo number_format($grandTotal, 2); ?> AED</span>
                    </div>

                    <div class="cart-actions">
                        <a href="index.php" class="continue-btn">Continue Shopping</a>
                        <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
        </footer>
       
</div>  




    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js"></script>
</body>
</html>