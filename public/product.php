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
$all_images = []; // Keep all images with their IDs for variation linking

foreach ($images as $img) {
    $all_images[] = $img; // Store all images with IDs
    if ($img['is_main']) {
        $main_image = $img['image_path'];
    } else {
        $gallery_images[] = $img['image_path'];
    }
}

// Fetch variations with image links
$variations = $db->query("
    SELECT pv.*, pi.image_path, pi.id as image_id_ref
    FROM product_variations pv 
    LEFT JOIN product_images pi ON pv.image_id = pi.id
    WHERE pv.product_id = :id
    ORDER BY pi.display_order ASC, pv.id ASC
", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);
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
    
    <!-- Google Fonts for Enhanced Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Modern CSS Architecture -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/enhanced-design.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <link rel="stylesheet" href="../assets/css/product.css">
    <link rel="stylesheet" href="assets/css/width-improvements.css">

    <style>
        .product-page {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .product-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .image-gallery {
            position: relative;
            background: #f8f9fa;
        }
        
        .main-image-wrapper {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            background: white;
            border-radius: 15px;
            margin: 1rem;
        }
        
        .main-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            cursor: zoom-in;
        }
        
        .main-product-image:hover {
            transform: scale(1.05);
        }
        
        .thumbnail-strip {
            padding: 1rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .thumbnail-item {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .thumbnail-item:hover,
        .thumbnail-item.active {
            border-color: #007bff;
            transform: scale(1.05);
        }
        
        .thumbnail-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details {
            padding: 2rem;
        }
        
        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #E67B2E;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .product-price {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .price-main {
            font-size: 2rem;
            font-weight: 700;
            color: #E67B2E;
        }
        
        .price-usd {
            font-size: 1.2rem;
            color: #7f8c8d;
        }
        
        .product-description {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #E67B2E;
        }
        
        .product-specifications {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
        
        .product-specifications h6 {
            color: #28a745;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .spec-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .spec-item:last-child {
            border-bottom: none;
        }
        
        .spec-label {
            font-weight: 500;
            color: #6c757d;
        }
        
        .spec-value {
            font-weight: 600;
            color: #495057;
        }
        
        .product-options {
            margin-bottom: 2rem;
        }
        
        .option-group {
            margin-bottom: 1.5rem;
        }
        
        .option-label {
            font-weight: 600;
            color: #E67B2E;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .option-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .option-select:focus {
            border-color: #E67B2E;
            box-shadow: 0 0 0 0.2rem rgba(230,123,46,0.25);
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quantity-input {
            width: 80px;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            font-size: 1rem;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn-add-cart {
            background: linear-gradient(135deg, #E67B2E 0%, #C66524 100%);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(230,123,46,0.3);
            color: white;
        }
        
        .secondary-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn-secondary {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            background: white;
            color: #6c757d;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-secondary:hover {
            border-color: #E67B2E;
            color: #E67B2E;
            transform: translateY(-1px);
        }
        
        .whatsapp-btn {
            background: #25d366;
            border-color: #25d366;
            color: white;
        }
        
        .whatsapp-btn:hover {
            background: #1db954;
            border-color: #1db954;
            color: white;
        }
        
        @media (max-width: 768px) {
            .product-title {
                font-size: 1.5rem;
            }
            
            .price-main {
                font-size: 1.5rem;
            }
            
            .product-details {
                padding: 1rem;
            }
            
            .secondary-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <div class="product-page">
        <div class="container">
            <!-- Breadcrumb Navigation -->
            <nav class="breadcrumb-nav">
                <div class="d-flex align-items-center justify-content-between">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Products</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name_en']); ?></li>
                    </ol>
                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
            </nav>

            <!-- Main Product Container -->
            <div class="product-container">
                <div class="row g-0">
                    <!-- Image Gallery -->
                    <div class="col-lg-6">
                        <div class="image-gallery">
                            <?php if ($main_image): ?>
                                <div class="main-image-wrapper">
                                    <img id="mainProductImage" 
                                         src="<?php echo str_replace("../", "", $main_image); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name_en']); ?>" 
                                         class="main-product-image">
                                </div>
                            <?php endif; ?>

                            <?php if ($gallery_images): ?>
                                <div class="thumbnail-strip">
                                    <?php 
                                    // Display all images including main image
                                    foreach ($all_images as $index => $img): 
                                        $img_path = str_replace("../", "", $img['image_path']);
                                        $is_active = $img['is_main'] ? 'active' : '';
                                        
                                        // Find variation linked to this image
                                        $linked_variation_id = null;
                                        foreach ($variations as $var) {
                                            if ($var['image_id'] == $img['id']) {
                                                $linked_variation_id = $var['id'];
                                                break;
                                            }
                                        }
                                    ?>
                                        <div class="thumbnail-item <?php echo $is_active; ?>" 
                                             data-image-id="<?php echo $img['id']; ?>"
                                             data-variation-id="<?php echo $linked_variation_id ?? ''; ?>"
                                             onclick="selectImageVariation('<?php echo $img_path; ?>', <?php echo $img['id']; ?>, <?php echo $linked_variation_id ?? 'null'; ?>)">
                                            <img src="<?php echo $img_path; ?>" 
                                                 alt="Product variant" class="thumbnail-image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Product Details -->
                    <div class="col-lg-6">
                        <div class="product-details">
                            <h1 class="product-title"><?php echo htmlspecialchars($product['name_en']); ?></h1>
                            
                            <?php if (!empty($product['name_ar'])): ?>
                                <h2 class="product-title-ar h4 text-muted mb-3" dir="rtl">
                                    <?php echo htmlspecialchars($product['name_ar']); ?>
                                </h2>
                            <?php endif; ?>

                            <div class="product-price">
                                <span class="price-main">
                                    <span class="uae-symbol">د.إ</span><?php echo number_format($product['price']); ?>
                                </span>
                                <span class="price-usd">
                                    $<?php echo number_format($product['price']/3.68, 2); ?>
                                </span>
                            </div>

                            <?php if (!empty($product['description_en'])): ?>
                                <div class="product-description">
                                    <h5><i class="fas fa-info-circle me-2"></i>Product Description</h5>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($product['description_en'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Product Specifications -->
                            <div class="product-specifications mb-4">
                                <h6><i class="fas fa-list-ul me-2"></i>Product Details</h6>
                                <div class="row text-start">
                                    <div class="col-6">
                                        <div class="spec-item">
                                            <span class="spec-label">Stock:</span>
                                            <span class="spec-value <?php echo $product['stock'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $product['stock'] > 0 ? $product['stock'] . ' available' : 'Out of stock'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="spec-item">
                                            <span class="spec-label">Weight:</span>
                                            <span class="spec-value"><?php echo number_format($product['weight'] ?? 0, 3); ?> kg</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($product['sku'])): ?>
                                    <div class="col-6">
                                        <div class="spec-item">
                                            <span class="spec-label">SKU:</span>
                                            <span class="spec-value"><?php echo htmlspecialchars($product['sku']); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form method="post" action="ajax/add_to_cart.php" class="add-to-cart-form">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                                <div class="product-options">
                                    <?php if ($variations): ?>
                                        <div class="option-group">
                                            <label class="option-label">
                                                <i class="fas fa-cog me-1"></i>Choose Variation
                                            </label>
                                            <select name="variation_id" id="variationSelect" class="option-select" required>
                                                <option value="">Select variation...</option>
                                                <?php foreach ($variations as $var): ?>
                                                    <option value="<?php echo $var['id']; ?>" 
                                                            data-image-id="<?php echo $var['image_id'] ?? ''; ?>">
                                                        <?php 
                                                        // Display variation name
                                                        $var_name = [];
                                                        if (!empty($var['color'])) $var_name[] = $var['color'];
                                                        if (!empty($var['size'])) $var_name[] = "Size: " . $var['size'];
                                                        echo htmlspecialchars(implode(' - ', $var_name));
                                                        
                                                        if ($var['additional_price'] > 0) {
                                                            echo ' (+AED ' . number_format($var['additional_price'], 2) . ')';
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>

                                    <div class="quantity-selector">
                                        <label class="option-label mb-0">
                                            <i class="fas fa-sort-numeric-up me-1"></i>Quantity:
                                        </label>
                                        <input type="number" name="quantity" value="1" min="1" max="99" class="quantity-input">
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <button type="submit" name="add_to_cart" class="btn btn-add-cart">
                                        <i class="fas fa-shopping-cart"></i>
                                        Add to Cart
                                    </button>

                                    <div class="secondary-actions">
                                        <a href="https://wa.me/971561125320?text=<?= urlencode('aleppogift: I am interested in this product: ' 
                                           . htmlspecialchars($product['name_en']) . ' - AED ' . number_format($product['price'], 0) . ' - Product Code: https://aleppogift.com/product.php?id=' . $product['id']) ?>" 
                                           target="_blank" class="btn btn-secondary whatsapp-btn">
                                            <i class="fab fa-whatsapp me-1"></i>WhatsApp Inquiry
                                        </a>
                                        
                                        <button type="button" class="btn btn-secondary share-btn" 
                                                onclick="shareProduct('<?= htmlspecialchars($product['name_en']) ?>', '<?= number_format($product['price'], 2) ?>', '<?= $product['id'] ?>', '<?= 'https://aleppogift.com/product.php?id=' . $product['id'] ?>')">
                                            <i class="fas fa-share-alt me-1"></i>Share Product
                                        </button>
                                        
                                        <button type="button" class="btn btn-secondary btn-wishlist" 
                                                data-id="<?= $product['id']; ?>">
                                            <i class="far fa-heart me-1"></i>Add to Wishlist
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <i class="fas fa-shipping-fast fa-2x text-primary mb-2"></i>
                                    <h6>Fast Delivery</h6>
                                    <small class="text-muted">Same day delivery in UAE</small>
                                </div>
                                <div class="col-md-3">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                    <h6>Secure Payment</h6>
                                    <small class="text-muted">100% secure transactions</small>
                                </div>
                                <div class="col-md-3">
                                    <i class="fas fa-undo fa-2x text-warning mb-2"></i>
                                    <h6>Easy Returns</h6>
                                    <small class="text-muted">7-day return policy</small>
                                </div>
                                <div class="col-md-3">
                                    <i class="fas fa-headset fa-2x text-info mb-2"></i>
                                    <h6>24/7 Support</h6>
                                    <small class="text-muted">Customer service available</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/enhanced-main.js"></script>
    
    <script>
        // Function to select image and its corresponding variation
        function selectImageVariation(imageSrc, imageId, variationId) {
            // Change main image
            document.getElementById('mainProductImage').src = imageSrc;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.closest('.thumbnail-item').classList.add('active');
            
            // Auto-select the variation in dropdown if variation exists
            if (variationId) {
                const variationSelect = document.getElementById('variationSelect');
                if (variationSelect) {
                    variationSelect.value = variationId;
                    
                    // Highlight the select to show it was auto-selected
                    variationSelect.style.borderColor = '#E67B2E';
                    variationSelect.style.backgroundColor = '#fff3e0';
                    
                    setTimeout(() => {
                        variationSelect.style.borderColor = '';
                        variationSelect.style.backgroundColor = '';
                    }, 1000);
                }
            }
        }
        
        // Legacy function for backwards compatibility
        function changeMainImage(newSrc) {
            document.getElementById('mainProductImage').src = newSrc;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.closest('.thumbnail-item').classList.add('active');
        }

        // Enhanced add to cart functionality
        $(document).ready(function() {
            $('.add-to-cart-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const button = form.find('button[type="submit"]');
                const originalText = button.html();
                
                // Show loading state
                button.prop('disabled', true);
                button.html('<i class="fas fa-spinner fa-spin me-2"></i>Adding to Cart...');
                
                // Submit form
                $.post('ajax/add_to_cart.php', form.serialize())
                    .done(function(response) {
                        if (response.success) {
                            // Update cart count if exists
                            if ($('#cart-count').length) {
                                $('#cart-count').text(response.cart_count || 0);
                            }
                            
                            // Show success message
                            button.html('<i class="fas fa-check me-2"></i>Added to Cart!');
                            button.removeClass('btn-add-cart').addClass('btn-success');
                            
                            // Show notification
                            if (typeof showNotification === 'function') {
                                showNotification('Product added to cart successfully!', 'success');
                            }
                            
                            // Reset button after 3 seconds
                            setTimeout(function() {
                                button.html(originalText);
                                button.removeClass('btn-success').addClass('btn-add-cart');
                                button.prop('disabled', false);
                            }, 3000);
                            
                        } else {
                            throw new Error(response.message || 'Unknown error');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Add to cart error:', error);
                        button.html('<i class="fas fa-exclamation-triangle me-2"></i>Error - Try Again');
                        button.removeClass('btn-add-cart').addClass('btn-danger');
                        
                        setTimeout(function() {
                            button.html(originalText);
                            button.removeClass('btn-danger').addClass('btn-add-cart');
                            button.prop('disabled', false);
                        }, 3000);
                    });
            });
        });
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>