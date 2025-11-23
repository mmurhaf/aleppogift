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

// --- Compatibility for old product links ---
if (isset($_GET['page']) && $_GET['page'] === 'product_details' && isset($_GET['code'])) {
    $_GET['search'] = trim($_GET['code']);
}

// --- Handle search input from 'search' or 's' ---
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
        $where[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar)";
        $params['search_en'] = "%$search%";
        $params['search_ar'] = "%$search%";
    }
}



// now build your query with $where and $params...


// Build final WHERE clause
$whereSQL = "WHERE " . implode(" AND ", $where);

// Count total products for pagination
$countSQL = "SELECT COUNT(*) as total FROM products p $whereSQL";
$totalProducts = $db->query($countSQL, $params)->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination settings
$perPage = 16;
$totalPages = ceil($totalProducts / $perPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $perPage;

// Final SQL query with pagination
$sql = "SELECT p.*, 
        (SELECT image_path FROM product_images 
         WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
        FROM products p 
        $whereSQL 
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset";

// Add pagination parameters as integers
$params['limit'] = (int)$perPage;
$params['offset'] = (int)$offset;

// Execute
$products = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aleppogift - Premium Gifts & Home Decor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts for Enhanced Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Modern CSS Architecture -->
    <link rel="stylesheet" href="assets/css/main.css">
    
    <!-- Index Page Specific Styles -->
    <link rel="stylesheet" href="assets/css/index.css">

</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <!-- Main Content -->
    <main class="container my-4">
        <!-- Hero Section -->
        <section class="hero-section modern-hero text-center mb-5">
            <div class="hero-content">
                <div class="hero-badge">✨ New Collection</div>
                <h1 class="hero-title" style="color : white;">Aleppo Gift - Premium Gifts</h1>
                <p class="hero-subtitle">Discover our unique collection of luxury and branded gifts that bring joy and elegance to every occasion</p>
                <div class="hero-actions">
                    <a href="#products" class="btn btn-primary btn-hero">
                        <i class="fas fa-shopping-bag me-2"></i>Shop Now
                    </a>
                    <a href="#categories" class="btn btn-outline btn-hero">
                        <i class="fas fa-list me-2"></i>Browse Categories
                    </a>
                </div>
            </div>
            <div class="hero-decoration"></div>
        </section>
        <section id="categories" class="mb-5">
            <?php //  require_once('../includes/categories.php') ;?>
			<?php if(1==2){ ?>
            <span class="brand-title">Shop by category: </span> &nbsp;
			<?php foreach ($categories as $cat):?>
			<span class="brand-title">	<a href="#&category=<?= $cat['id']; ?>">		<?= $cat['name_en']; ?> </a> </span> &nbsp;&nbsp;
			<?php endforeach; }?>

            <?php require_once('../includes/brands.php') ;?>
        </section>
        <!-- Enhanced Filter Section -->
        <section id="products" class="mb-5">
            <div class="filter-header mb-4">
                <h2 class="filter-title">Find Your Perfect Gift</h2>
                <p class="filter-subtitle">Use our advanced filters to discover exactly what you're looking for</p>
            </div>
            
            <form method="get" action="" class="modern-filter-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="category" class="filter-label">
                            <i class="fas fa-tags me-2"></i>Category
                        </label>
                        <select name="category" id="category" class="form-control modern-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id']; ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cat['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="brand" class="filter-label">
                            <i class="fas fa-star me-2"></i>Brand
                        </label>
                        <select name="brand" id="brand" class="form-control modern-select">
                            <option value="">All Brands</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id']; ?>" <?= (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($brand['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <label for="search" class="filter-label">
                            <i class="fas fa-search me-2"></i>Search
                        </label>
                        <div class="search-input-wrapper">
                            <input type="text" name="search" id="search" class="form-control search-input" 
                                   placeholder="Search products, brands, or codes..." 
                                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary filter-submit">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <a href="?" class="btn btn-outline reset-filters" title="Clear all filters">
                            <i class="fas fa-undo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </section>
                            
        <!-- Pagination -->
         <section class="mb-4">
<!-- Pagination Section -->
 
            <div class="pagination-container" >
                <div class="pagination-info">Products (<?= $totalProducts ?> found)</div>
                
                <nav class="pagination-nav">
                    <ul class="pagination">
                        <!-- Previous Page Link -->
                        <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- First Page -->
                        <?php if ($totalPages > 0): ?>
                        <li class="page-item <?= $currentPage == 1 ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Ellipsis and middle pages logic -->
                        <?php if ($totalPages > 1): ?>
                            <?php if ($currentPage > 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++): ?>
                                <li class="page-item <?= $currentPage == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages - 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Last Page -->
                        <?php if ($totalPages > 1): ?>
                        <li class="page-item <?= $currentPage == $totalPages ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Next Page Link -->
                        <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </section>

        <!-- Enhanced Products Grid -->
        <div class="products-section">
            <div class="products-header">
                <h2 class="products-title">Our Featured Products</h2>
                <p class="products-subtitle">Handpicked selection of premium gifts</p>
            </div>
            
            <div class="product-grid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card modern-product-card">
                        <!-- Product Image -->
                        <div class="product-image">
                          
                            
                            <img src="<?= $p['main_image'] ?: 'assets/images/no-image.png'; ?>" 
                                 alt="<?= htmlspecialchars($p['name_en']); ?>"
                                 loading="lazy">
                            
                            <!-- Product Badge -->
                            <?php if (!empty($p['on_sale'])): ?>
                                <span class="product-badge">Sale</span>
                            <?php elseif (!empty($p['featured'])): ?>
                                <span class="product-badge badge-featured">Featured</span>
                            <?php endif; ?>
                            
                            <!-- Quick Actions Overlay -->
                            <div class="product-overlay">
                                <button class="quick-view-btn" 
                                        data-id="<?= $p['id']; ?>" 
                                        title="Quick View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-wishlist" 
                                        data-id="<?= $p['id']; ?>" 
                                        title="Add to Wishlist">
                                    <i class="far fa-heart"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Product Content -->
                    <div class="card-body d-flex flex-column">
                    <!-- Product Title -->
                    <h5 class="card-title product-name">
                        <?= htmlspecialchars($p['name_en']); ?>
                    </h5>
                    <h6 class="product-name-ar mb-2">
                       <a href="https://aleppogift.com/product.php?id=<?= $p['id']; ?>" class="text-decoration-none"> 
                         <?= htmlspecialchars($p['name_ar']); ?>
                         </a> 
                    </h6>
                    
                    <!-- Product Price -->
                    <div class="product-price mb-3">
                        <div class="price-main">
                            <span class="price-current">
                                <span class="uae-symbol">&#x00EA;</span><?= number_format($p['price']); ?>
                            </span>
                            <span class="price-usd">$<?= number_format($p['price']/3.68, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Product Actions - Mobile Optimized -->
                    <div class="product-actions mt-auto">
                        <!-- Main Action Row -->
                        <div class="action-row-main d-flex gap-2 mb-2">
                            <form class="add-to-cart-form flex-grow-1" method="post" action="ajax/add_to_cart.php">
                                <input type="hidden" name="product_id" value="<?= $p['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-primary btn-sm w-100 add-to-cart" 
                                        data-id="<?= $p['id']; ?>" 
                                        data-name="<?= htmlspecialchars($p['name_en']); ?>">
                                    <i class="fas fa-shopping-cart me-1"></i>
                                    <span class="d-none d-sm-inline">Add to Cart</span>
                                </button>
                            </form>
                            <a href="https://aleppogift.com/product.php?id=<?= $p['id']; ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-eye"></i>
                                <span class="d-none d-sm-inline ms-1">View</span>
                            </a>
                        </div>
                        
                        <!-- Secondary Action Row -->
                        <div class="action-row-secondary d-flex gap-2 justify-content-center">
                            <a class="btn btn-success btn-sm flex-fill whatsapp-btn" 
                               href="https://wa.me/971561125320?text=<?= urlencode('aleppogift: I am interested in this product: ' 
                               . htmlspecialchars($p['name_en']) . ' - AED ' . number_format($p['price'], 0) . ' - Product Code: https://aleppogift.com/product.php?id=' . $p['id']) ?>" 
                               target="_blank"
                               title="Share on WhatsApp">
                                <i class="fab fa-whatsapp me-1"></i>
                                <span class="d-none d-sm-inline">WhatsApp</span>
                            </a>
                            
                            <button class="btn btn-outline-primary btn-sm flex-fill share-btn" 
                                    title="Share"
                                    onclick="shareProduct(
                                        '<?= htmlspecialchars($p['name_en']) ?>', 
                                        '<?= number_format($p['price'], 2) ?>', 
                                        '<?= $p['id'] ?>',
                                        '<?= 'https://aleppogift.com/product.php?id=' . $p['id'] ?>'
                                    )">
                                <i class="fas fa-share-alt me-1"></i>
                                <span class="d-none d-sm-inline">Share</span>
                            </button>
                            
                            <button class="btn btn-outline-danger btn-sm btn-wishlist" 
                                    data-id="<?= $p['id']; ?>" 
                                    title="Add to Wishlist">
                                <i class="far fa-heart me-1"></i>
                                <span class="d-none d-sm-inline">Save</span>
                            </button>
                        </div>
                    </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="#top" class="btn btn-secondary">Back to Top</a>
            </div>
        </div>
    </main>

    <footer class="footer">
        <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    </footer>
       
</div>  




    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Simplified Cart Preview JavaScript -->
    <script>
        // Simple cart preview toggle
        function toggleCartPreview() {
            const preview = document.getElementById('cartPreview');
            if (preview.style.display === 'none' || preview.style.display === '') {
                showCartPreview();
            } else {
                hideCartPreview();
            }
        }
        
        function showCartPreview() {
            const preview = document.getElementById('cartPreview');
            const container = document.getElementById('cart-items-container');
            
            // Show preview
            preview.style.display = 'block';
            
            // Load cart contents
            fetch('ajax/simple_cart_preview.php')
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading cart:', error);
                    container.innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i><p class="mb-0 small">Error loading cart</p></div>';
                });
                
            // Auto-hide after 10 seconds
            setTimeout(hideCartPreview, 10000);
        }
        
        function hideCartPreview() {
            document.getElementById('cartPreview').style.display = 'none';
        }
        
        // Close cart preview when clicking outside
        document.addEventListener('click', function(event) {
            const preview = document.getElementById('cartPreview');
            const cartButton = document.querySelector('button[onclick="toggleCartPreview()"]');
            
            if (preview && preview.style.display === 'block' && 
                !preview.contains(event.target) && 
                !cartButton.contains(event.target)) {
                hideCartPreview();
            }
        });
        
        // Remove item from cart
        function removeFromCart(productId) {
            if (!confirm('Remove this item from cart?')) return;
            
            fetch('ajax/remove_from_cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    document.getElementById('cart-count').textContent = data.cart_count || 0;
                    // Refresh cart preview
                    showCartPreview();
                    // Show notification
                    alert('Item removed from cart');
                } else {
                    alert('Error removing item from cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error removing item from cart');
            });
        }
        
        // Enhanced add to cart functionality
        $(document).ready(function() {
            // Handle add to cart form submission
            $(document).on('submit', '.add-to-cart-form', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const button = form.find('button[type="submit"]');
                const originalText = button.html();
                
                // Show loading state
                button.prop('disabled', true);
                button.html('<i class="fas fa-spinner fa-spin me-1"></i>Adding...');
                
                // Submit form
                $.post('ajax/add_to_cart.php', form.serialize())
                    .done(function(response) {
                        if (response.success) {
                            // Update cart count
                            $('#cart-count').text(response.cart_count || 0);
                            
                            // Show success message
                            button.html('<i class="fas fa-check me-1"></i>Added!');
                            button.removeClass('btn-primary').addClass('btn-success');
                            
                            // Show cart preview briefly
                            setTimeout(showCartPreview, 500);
                            
                            // Reset button after 2 seconds
                            setTimeout(function() {
                                button.html(originalText);
                                button.removeClass('btn-success').addClass('btn-primary');
                                button.prop('disabled', false);
                            }, 2000);
                            
                        } else {
                            throw new Error(response.message || 'Unknown error');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Add to cart error:', error);
                        button.html('<i class="fas fa-exclamation-triangle me-1"></i>Error');
                        button.removeClass('btn-primary').addClass('btn-danger');
                        
                        setTimeout(function() {
                            button.html(originalText);
                            button.removeClass('btn-danger').addClass('btn-primary');
                            button.prop('disabled', false);
                        }, 3000);
                    });
            });
        });
    </script>
    
	<script src="assets/js/main.js"></script>
	<script src="assets/js/enhanced-main.js"></script>
	<script src="assets/js/enhanced-ui.js"></script>
    <!-- Index Page JavaScript -->
    <script src="assets/js/index.js"></script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>