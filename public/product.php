<?php
require_once('../config/config.php');
require_once('../includes/Database.php');

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
    <link rel="stylesheet" href="../assets/css/product.css">
</head>
<body>
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
    </div>
	
	<script>
		function changeMainImage(newSrc) {
			// Set the main image source to the clicked thumbnail's source
			document.getElementById('mainProductImage').src = newSrc;
		}
		</script>
</body>
</html>