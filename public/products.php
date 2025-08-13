<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();

// Fetch categories 
$categories = $db->query("SELECT * FROM categories WHERE status=1")->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands
$brands = $db->query("SELECT * FROM brands WHERE status=1")->fetchAll(PDO::FETCH_ASSOC);

// Initialize filters once
$where = ["p.status = 1"];
$params = [];

// Filter by category
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $where[] = "p.category_id = :category_id";
    $params['category_id'] = $_GET['category'];
}

// Filter by brand
if (isset($_GET['brand']) && is_numeric($_GET['brand'])) {
    $where[] = "p.brand_id = :brand_id";
    $params['brand_id'] = $_GET['brand'];
}

// Handle search input
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
} elseif (isset($_GET['s'])) {
    $search = trim($_GET['s']);
}

if ($search !== '') {
    if (is_numeric($search)) {
        $where[] = "p.id = :product_id";
        $params['product_id'] = $search;
    } else {
        $where[] = "(p.name_en LIKE :search OR p.name_ar LIKE :search OR p.description_en LIKE :search OR p.description_ar LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
}

// Pagination
$limit = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Build the WHERE clause
$whereClause = implode(' AND ', $where);

// Fetch total count for pagination
$total_query = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
$total_result = $db->query($total_query, $params)->fetch(PDO::FETCH_ASSOC);
$total_products = $total_result['total'];
$total_pages = ceil($total_products / $limit);

// Fetch products with joins for category and brand names
$products_query = "
    SELECT 
        p.*, 
        c.name_en as category_name, 
        b.name_en as brand_name,
        (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN brands b ON p.brand_id = b.id 
    WHERE $whereClause 
    ORDER BY p.featured DESC, p.id DESC 
    LIMIT :limit OFFSET :offset
";

$params['limit'] = $limit;
$params['offset'] = $offset;
$products = $db->query($products_query, $params)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
     <!--<link rel="stylesheet" href="assets/css/style.css">-->
	<link rel="stylesheet" href="assets/css/index.css">
	<link rel="stylesheet" href="assets/css/enhanced-design.css">
	<link rel="stylesheet" href="assets/css/components.css">
	<link rel="stylesheet" href="assets/css/ui-components.css">
	
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
        <!-- Hero Section -->
        <section class="hero-section modern-hero text-center mb-5">
            <div class="hero-content">
                <div class="hero-badge">🛍️ Our Collection</div>
                <h1 class="hero-title">All Products</h1>
                <p class="hero-subtitle">Discover our complete range of premium gifts and souvenirs</p>
            </div>
        </section>

        <!-- Filters Section -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="filter-card">
                    <h5><i class="fas fa-filter me-2"></i>Filter by Category</h5>
                    <div class="filter-options">
                        <a href="?" class="filter-link <?= !isset($_GET['category']) ? 'active' : '' ?>">All Categories</a>
                        <?php foreach ($categories as $category): ?>
                            <a href="?category=<?= $category['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                               class="filter-link <?= isset($_GET['category']) && $_GET['category'] == $category['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($category['name_en']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="filter-card">
                    <h5><i class="fas fa-tags me-2"></i>Filter by Brand</h5>
                    <div class="filter-options">
                        <a href="?" class="filter-link <?= !isset($_GET['brand']) ? 'active' : '' ?>">All Brands</a>
                        <?php foreach ($brands as $brand): ?>
                            <a href="?brand=<?= $brand['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                               class="filter-link <?= isset($_GET['brand']) && $_GET['brand'] == $brand['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($brand['name_en']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="search-card">
                    <h5><i class="fas fa-search me-2"></i>Search Products</h5>
                    <form method="GET" class="search-form">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search products..." 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-box-open text-muted" style="font-size: 4rem;"></i>
                    <h3 class="mt-3 text-muted">No products found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                    <a href="?" class="btn btn-primary">View All Products</a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="modern-product-card">
                            <div class="product-image-container">
                                <img src="<?= htmlspecialchars($product['main_image'] ?: 'uploads/default-product.jpg') ?>" 
                                     alt="<?= htmlspecialchars($product['name_en']) ?>" 
                                     class="product-image">
                                <?php if ($product['featured']): ?>
                                    <div class="product-badge featured">Featured</div>
                                <?php endif; ?>
                                <div class="product-overlay">
                                    <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </a>
                                </div>
                            </div>
                            <div class="product-info">
                                <div class="product-category"><?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?></div>
                                <h5 class="product-title">
                                    <a href="product.php?id=<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name_en']) ?>
                                    </a>
                                </h5>
                                <div class="product-brand">Brand: <?= htmlspecialchars($product['brand_name'] ?: 'No Brand') ?></div>
                                <div class="product-price">AED <?= number_format($product['price'], 2) ?></div>
                                <div class="product-actions">
                                    <button onclick="addToCart(<?= $product['id'] ?>, 1)" class="btn btn-success btn-sm">
                                        <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                    </button>
                                    <button onclick="shareProduct('<?= addslashes($product['name_en']) ?>', <?= $product['price'] ?>, <?= $product['id'] ?>, window.location.origin + '/product.php?id=<?= $product['id'] ?>')" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Products pagination" class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= isset($_GET['category']) ? '&category='.$_GET['category'] : '' ?><?= isset($_GET['brand']) ? '&brand='.$_GET['brand'] : '' ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?><?= isset($_GET['category']) ? '&category='.$_GET['category'] : '' ?><?= isset($_GET['brand']) ? '&brand='.$_GET['brand'] : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= isset($_GET['category']) ? '&category='.$_GET['category'] : '' ?><?= isset($_GET['brand']) ? '&brand='.$_GET['brand'] : '' ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </main>

    </div>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js"></script>
	<script src="assets/js/enhanced-main.js"></script>
    <script>
        function shareProduct(name, price, id, url) {
            const shareText = `Check out this product at Aleppo Gift: ${name} - AED ${price} - Product Code: ${id}\n${url}`;

            if (navigator.share) {
                navigator.share({
                    title: name,
                    text: shareText,
                    url: url
                }).then(() => {
                    console.log('Thanks for sharing!');
                }).catch(console.error);
            } else {
                // Fallback (if not supported)
                alert("Sharing is not supported in this browser. Please copy the link manually:\n" + url);
            }
        }
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
