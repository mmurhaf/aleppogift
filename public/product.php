<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$product_id = (int)$_GET['id'];

// Fetch product
$product = $db->query("SELECT * FROM products WHERE id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header("Location: index.php");
    exit;
}

// Fetch images (ordered by display_order)
$images = $db->query("SELECT * FROM product_images WHERE product_id = :id ORDER BY display_order ASC", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);

// Separate main image
$main_image = null;
$gallery_images = [];

foreach ($images as $img) {
    if ($img['is_main']) {
        $main_image = $img['image_path'];
    } else {
        $gallery_images[] = $img['image_path'];
    }
}

// Fetch variations
$variations = $db->query("SELECT * FROM product_variations WHERE product_id = :id", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name_en']); ?> - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
     <!--<link rel="stylesheet" href="assets/css/style.css">-->
	<link rel="stylesheet" href="assets/css/index.css">
	<link rel="stylesheet" href="assets/css/enhanced-design.css">
	<link rel="stylesheet" href="assets/css/components.css">
	<link rel="stylesheet" href="assets/css/ui-components.css">
    <link rel="stylesheet" href="../assets/css/product.css">
	
	<!-- Google Fonts for Enhanced Typography -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>
    <div class="container">
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

    <!-- Main Content -->
    <main class="container my-4">
    <div class="container">
        <header class="header">
            <a class="navbar-brand" href="index.php">
                <img src="uploads/logo.png" alt="AleppoGift Logo" class="d-inline-block align-text-top" style="height: 40px;">          
            </a>
            <a href="index.php" class="back-link">← Back to Products</a>
        </header>

        <div class="product-detail">
			<div class="product-gallery-container">
				<?php if ($main_image): ?>
					<div class="main-image-container">
						<img id="mainProductImage" src="<?php echo str_replace("../", "", $main_image); ?>" alt="Main Product Image" class="main-image">
					</div>
				<?php endif; ?>

				<?php if ($gallery_images): ?>
					<div class="thumbnail-gallery">
						<?php foreach ($gallery_images as $img): ?>
							<div class="thumbnail-container">
								<img src="<?php echo str_replace("../", "", $img); ?>" alt="Product Thumbnail" class="thumbnail" onclick="changeMainImage(this.src)">
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

            <div class="product-info">
                <h2><?php echo htmlspecialchars($product['name_en']); ?></h2>
                
                <div class="price-section">
                    <span class="price">AED <?php echo number_format($product['price'], 2); ?></span>
                </div>

                <div class="product-description">
                    <?php echo nl2br(htmlspecialchars($product['description_en'])); ?>
                </div>

                <form method="post" action="cart.php" class="add-to-cart-form">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                    <?php if ($variations): ?>
                        <div class="form-group">
                            <label class="form-label">Choose Variation</label>
                            <select name="variation_id" class="form-select" required>
                                <?php foreach ($variations as $var): ?>
                                    <option value="<?php echo $var['id']; ?>">
                                        Size: <?php echo htmlspecialchars($var['size']); ?> / 
                                        Color: <?php echo htmlspecialchars($var['color']); ?> /
                                        +AED <?php echo number_format($var['additional_price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" class="form-input">
                    </div>

                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                        <span class="cart-icon">🛒</span> Add to Cart
                    </button>
                </form>
            </div>
        </div>

        <footer class="footer">
           
            <div class="container-fluid">
							Please read important notes at <a href="terms_of_service.html"> terms of Service</a>
					<div class="arabic-text">  الرجاء قراءة المعلومات الهامة في ملف <a href="terms_of_service.html">شروط الخدمة</a></div>
					</div>
        </footer>
    </main>

    </div>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js"></script>
	<script src="assets/js/enhanced-main.js"></script>
	
	<script>
		function changeMainImage(newSrc) {
			// Set the main image source to the clicked thumbnail's source
			document.getElementById('mainProductImage').src = newSrc;
		}
	</script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>