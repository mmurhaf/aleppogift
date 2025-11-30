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

// --- Handle sorting ---
$orderBy = "p.featured DESC, p.id DESC"; // Default sorting
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'new':
            $orderBy = "p.created_at DESC, p.id DESC";
            break;
        case 'best':
            $orderBy = "p.featured DESC, p.id DESC";
            break;
        case 'sale':
            $where[] = "p.discount > 0";
            $orderBy = "p.discount DESC, p.id DESC";
            break;
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
    ORDER BY $orderBy 
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
    <title>Products - aleppogift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
     <!--<link rel="stylesheet" href="assets/css/style.css">-->
	<link rel="stylesheet" href="assets/css/index.css">
	<link rel="stylesheet" href="assets/css/enhanced-design.css">
	<link rel="stylesheet" href="assets/css/components.css">
	<link rel="stylesheet" href="assets/css/ui-components.css">
	<link rel="stylesheet" href="assets/css/header-fixes.css">
	<link rel="stylesheet" href="assets/css/width-improvements.css">
	
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
                        <button type="button" class="btn btn-danger" onclick="exportToPDF()" title="Export results to PDF">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                    </div>
                </div>
            </form>
        </section>

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
                                    <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-light btn-view-details">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </a>
                                    <button onclick="shareProduct('<?= addslashes($product['name_en']) ?>', <?= $product['price'] ?>, <?= $product['id'] ?>, window.location.origin + '/product.php?id=<?= $product['id'] ?>')" class="btn btn-light btn-share-product" title="Share Product">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="product-info">
                                <div class="product-meta">
                                    <span class="product-category">
                                        <i class="fas fa-tag"></i>
                                        <a href="?category=<?= $product['category_id'] ?>" class="text-decoration-none" title="View all <?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?> products">
                                            <?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?>
                                        </a>
                                    </span>
                                    <?php if ($product['brand_name']): ?>
                                        <span class="product-brand">
                                            <i class="fas fa-certificate"></i>
                                            <a href="?brand=<?= $product['brand_id'] ?>" class="text-decoration-none" title="View all <?= htmlspecialchars($product['brand_name']) ?> products">
                                                <?= htmlspecialchars($product['brand_name']) ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="product-title">
                                    <a href="product.php?id=<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name_en']) ?>
                                    </a>
                                </h5>
                                <div class="product-price-wrapper">
                                    <div class="product-price">AED <?= number_format($product['price'], 2) ?></div>
                                    <?php if ($product['stock'] > 0): ?>
                                        <span class="stock-badge in-stock">
                                            <i class="fas fa-check-circle"></i> In Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="stock-badge out-of-stock">
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-actions">
                                    <button onclick="addToCart(<?= $product['id'] ?>, 1)" 
                                            class="btn btn-add-to-cart" 
                                            <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                                        <i class="fas fa-cart-plus me-1"></i> Add to Cart
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
	<script src="assets/js/main.js"></script>
	<script src="assets/js/enhanced-main.js"></script>
    <script>
        function exportToPDF() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            params.delete('page'); // Remove pagination to get all results
            
            // Show loading message
            const loadingDiv = document.createElement('div');
            loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center;';
            loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x mb-3" style="color: #007bff;"></i><h4>Generating PDF...</h4><p>Please wait while we prepare your document</p>';
            document.body.appendChild(loadingDiv);
            
            // Fetch all products for PDF
            fetch('ajax/export_products_pdf.php?' + params.toString())
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    // Check content type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Non-JSON response:', text);
                            throw new Error('Server returned non-JSON response. Check browser console for details.');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        generatePDF(data.products, data.filters);
                    } else {
                        alert('Error: ' + (data.error || 'Failed to fetch products'));
                    }
                    document.body.removeChild(loadingDiv);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating PDF: ' + error.message);
                    if (loadingDiv.parentElement) {
                        document.body.removeChild(loadingDiv);
                    }
                });
        }
        
        function generatePDF(products, filters) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 15;
            const contentWidth = pageWidth - (2 * margin);
            let yPosition = margin;
            
            // Header
            doc.setFontSize(20);
            doc.setTextColor(0, 123, 255);
            doc.text('Product Catalog', pageWidth / 2, yPosition, { align: 'center' });
            yPosition += 10;
            
            // Filters info
            doc.setFontSize(10);
            doc.setTextColor(100, 100, 100);
            let filterText = 'Filters: ';
            if (filters.category) filterText += 'Category: ' + filters.category + ' | ';
            if (filters.brand) filterText += 'Brand: ' + filters.brand + ' | ';
            if (filters.search) filterText += 'Search: ' + filters.search + ' | ';
            filterText += 'Total Products: ' + products.length;
            doc.text(filterText, pageWidth / 2, yPosition, { align: 'center' });
            yPosition += 8;
            
            // Date
            doc.text('Generated: ' + new Date().toLocaleDateString(), pageWidth / 2, yPosition, { align: 'center' });
            yPosition += 12;
            
            // Products grid (2 columns)
            const colWidth = (contentWidth - 10) / 2;
            const imageSize = 60;
            const cardHeight = 95;
            let col = 0;
            
            products.forEach((product, index) => {
                // Check if we need a new page
                if (yPosition + cardHeight > pageHeight - margin) {
                    doc.addPage();
                    yPosition = margin;
                    col = 0;
                }
                
                const xPosition = margin + (col * (colWidth + 10));
                
                // Draw card border
                doc.setDrawColor(200, 200, 200);
                doc.setLineWidth(0.5);
                doc.rect(xPosition, yPosition, colWidth, cardHeight);
                
                // Product image centered at top (if available)
                const imageCenterX = xPosition + (colWidth / 2);
                if (product.main_image) {
                    try {
                        // Check if it's a valid base64 image
                        if (product.main_image.startsWith('data:image')) {
                            doc.addImage(
                                product.main_image,
                                'JPEG',
                                imageCenterX - (imageSize / 2),
                                yPosition + 5,
                                imageSize,
                                imageSize
                            );
                        } else {
                            throw new Error('Invalid image format');
                        }
                    } catch (e) {
                        console.warn('Failed to add image for product', product.id, e);
                        // Show placeholder
                        doc.setFillColor(240, 240, 240);
                        doc.rect(imageCenterX - (imageSize / 2), yPosition + 5, imageSize, imageSize, 'F');
                        doc.setFontSize(8);
                        doc.setTextColor(150, 150, 150);
                        doc.text('No Image', imageCenterX, yPosition + 5 + imageSize/2, { align: 'center' });
                    }
                } else {
                    // Show placeholder for missing images
                    doc.setFillColor(240, 240, 240);
                    doc.rect(imageCenterX - (imageSize / 2), yPosition + 5, imageSize, imageSize, 'F');
                    doc.setFontSize(8);
                    doc.setTextColor(150, 150, 150);
                    doc.text('No Image', imageCenterX, yPosition + 5 + imageSize/2, { align: 'center' });
                }
                
                // Product details below image
                const textWidth = colWidth - 10;
                let textY = yPosition + imageSize + 10;
                
                // Product ID
                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.text('ID: ' + product.id, imageCenterX, textY, { align: 'center' });
                textY += 5;
                
                // Product name (English)
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0);
                doc.setFont(undefined, 'bold');
                const nameLines = doc.splitTextToSize(product.name_en, textWidth);
                const displayNameLines = nameLines.slice(0, 2);
                displayNameLines.forEach(line => {
                    doc.text(line, imageCenterX, textY, { align: 'center' });
                    textY += 4;
                });
                textY += 1;
                
                // Product name (Arabic)
                if (product.name_ar) {
                    doc.setFont(undefined, 'normal');
                    doc.setFontSize(9);
                    doc.setTextColor(80, 80, 80);
                    const nameArLines = doc.splitTextToSize(product.name_ar, textWidth);
                    doc.text(nameArLines.slice(0, 1), imageCenterX, textY, { align: 'center' });
                    textY += 5;
                }
                
                // Category and Brand on same line
                doc.setFont(undefined, 'normal');
                doc.setFontSize(8);
                let categoryBrand = '';
                
                if (product.category_name) {
                    categoryBrand += product.category_name;
                }
                
                if (product.brand_name) {
                    if (categoryBrand) categoryBrand += ' | ';
                    categoryBrand += product.brand_name;
                }
                
                if (categoryBrand) {
                    doc.setTextColor(0, 123, 255);
                    const catBrandLines = doc.splitTextToSize(categoryBrand, textWidth);
                    doc.text(catBrandLines.slice(0, 1), imageCenterX, textY, { align: 'center' });
                }
                
                // Move to next column or row
                col++;
                if (col >= 2) {
                    col = 0;
                    yPosition += cardHeight + 5;
                }
            });
            
            // Footer on last page
            const totalPages = doc.internal.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text('Page ' + i + ' of ' + totalPages, pageWidth / 2, pageHeight - 10, { align: 'center' });
                doc.text('www.aleppogift.com', pageWidth / 2, pageHeight - 6, { align: 'center' });
            }
            
            // Save PDF
            const filename = 'AleppoGift_Products_' + new Date().toISOString().split('T')[0] + '.pdf';
            doc.save(filename);
        }
        
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
