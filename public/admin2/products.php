<?php
// File: admin/products.php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

// Handle delete request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $db->query("DELETE FROM product_images WHERE product_id = :id", ['id' => $id]);
        $db->query("DELETE FROM products WHERE id = :id", ['id' => $id]);
    }
    header("Location: products.php");
    exit;
}

// Handle duplicate request
if (isset($_GET['duplicate'])) {
    $id = (int)$_GET['duplicate'];
    if ($id > 0) {
        try {
            // Get the original product
            $original = $db->query("SELECT * FROM products WHERE id = :id", ['id' => $id])->fetch(PDO::FETCH_ASSOC);
            
            if ($original) {
                // Remove the id and modify the name and SKU to indicate it's a copy
                unset($original['id']);
                $original['name_en'] = $original['name_en'] . ' (Copy)';
                $original['name_ar'] = $original['name_ar'] . ' (نسخة)';
                $original['sku'] = $original['sku'] . '_COPY_' . time();
                $original['status'] = 0; // Set as inactive by default
                
                // Build the INSERT query dynamically
                $columns = array_keys($original);
                $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
                
                $sql = "INSERT INTO products (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $db->query($sql, $original);
                
                // Get the new product ID using a SELECT query
                $new_product = $db->query("SELECT id FROM products WHERE sku = :sku", ['sku' => $original['sku']])->fetch(PDO::FETCH_ASSOC);
                
                if ($new_product) {
                    $new_id = $new_product['id'];
                    
                    // Copy product images if any exist
                    $images = $db->query("SELECT * FROM product_images WHERE product_id = :id", ['id' => $id])->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($images as $image) {
                        unset($image['id']);
                        $image['product_id'] = $new_id;
                        
                        $img_columns = array_keys($image);
                        $img_placeholders = array_map(function($col) { return ':' . $col; }, $img_columns);
                        
                        $img_sql = "INSERT INTO product_images (" . implode(', ', $img_columns) . ") VALUES (" . implode(', ', $img_placeholders) . ")";
                        $db->query($img_sql, $image);
                    }
                    
                    // Redirect to edit the new product
                    header("Location: edit_product.php?id=" . $new_id);
                    exit;
                }
            }
        } catch (Exception $e) {
            // Log error or handle it appropriately
            error_log("Product duplication error: " . $e->getMessage());
        }
    }
    header("Location: products.php");
    exit;
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$brand_filter = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

/**
 * Load filter options with a robust display-name fallback.
 * Uses INFORMATION_SCHEMA to check column existence (compatible with native prepared statements).
 */
function loadFilterOptions(Database $db, string $table): array {
    $availableNameColumns = [];
    foreach (['name_en', 'name_ar', 'name'] as $column) {
        try {
            $exists = $db->query(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col",
                ['tbl' => $table, 'col' => $column]
            )->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                $availableNameColumns[] = $column;
            }
        } catch (Exception $e) {
            // Keep trying other columns.
        }
    }

    if (empty($availableNameColumns)) {
        return [];
    }

    $nameParts = [];
    foreach ($availableNameColumns as $column) {
        $nameParts[] = "NULLIF(TRIM({$column}), '')";
    }

    $displayNameExpression = "COALESCE(" . implode(', ', $nameParts) . ", CONCAT('ID #', id))";

    try {
        return $db->query("SELECT id, {$displayNameExpression} AS display_name FROM {$table} ORDER BY display_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed loading {$table} filter options: " . $e->getMessage());
        return [];
    }
}

// Fetch categories and brands for the filter dropdowns.
$categories = loadFilterOptions($db, 'categories');
$brands = loadFilterOptions($db, 'brands');

// Pagination settings
$products_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1

// Build WHERE clause for filters
$where_conditions = [];
$params = [];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = :category_id";
    $params['category_id'] = $category_filter;
}

if ($brand_filter > 0) {
    $where_conditions[] = "p.brand_id = :brand_id";
    $params['brand_id'] = $brand_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.name_en LIKE :search OR p.name_ar LIKE :search OR p.sku LIKE :search OR p.id LIKE :search)";
    $params['search'] = '%' . $search_query . '%';
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
}

// Get total count of products (with filter)
$count_sql = "SELECT COUNT(*) as count FROM products p" . $where_clause;
$total_products = $db->query($count_sql, $params)->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total_products / $products_per_page);

// Calculate offset for SQL query
$offset = ($current_page - 1) * $products_per_page;

// Add pagination parameters
$params['limit'] = $products_per_page;
$params['offset'] = $offset;

// Get products for current page with their images and category names
try {
    $products = $db->query("
        SELECT p.*, 
               c.name_en as category_name,
               (SELECT pi.image_path 
                FROM product_images pi 
                WHERE pi.product_id = p.id 
                ORDER BY pi.id ASC 
                LIMIT 1) as image_path
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        " . $where_clause . "
        ORDER BY p.id DESC 
        LIMIT :limit OFFSET :offset
    ", $params)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback: get products without JOIN if it fails
    $products = $db->query("
        SELECT * FROM products p
        " . $where_clause . "
        ORDER BY id DESC 
        LIMIT :limit OFFSET :offset
    ", $params)->fetchAll(PDO::FETCH_ASSOC);
    
    // Add image_path and category_name columns manually
    foreach ($products as &$product) {
        try {
            $image_result = $db->query("
                SELECT image_path FROM product_images 
                WHERE product_id = :id 
                ORDER BY id ASC 
                LIMIT 1
            ", ['id' => $product['id']])->fetch(PDO::FETCH_ASSOC);
            
            $product['image_path'] = $image_result ? $image_result['image_path'] : null;
            
            // Get category name
            $category_result = $db->query("
                SELECT name_en FROM categories 
                WHERE id = :id
            ", ['id' => $product['category_id']])->fetch(PDO::FETCH_ASSOC);
            
            $product['category_name'] = $category_result ? $category_result['name_en'] : 'No Category';
        } catch (Exception $e2) {
            $product['image_path'] = null;
            $product['category_name'] = 'No Category';
        }
    }
}
?>
<?php
$page_title = 'Products';
require_once __DIR__ . '/includes/header.php';
?>
<style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
            --border-color: #e3e6f0;
            --text-muted: #858796;
        }
        * {
            transition: all 0.3s ease;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #2e3338;
        }
        .container-fluid {
            padding: 2rem 1rem;
        }
        .dashboard-header {
            background: white;
            box-shadow: 0 2px 8px rgba(58, 59, 69, 0.12);
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        .dashboard-header h1 {
            color: #2e3338;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        .breadcrumb-item.active {
            color: var(--text-muted);
        }
        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .breadcrumb-item a:hover {
            color: #3662bf;
            text-decoration: underline;
        }
        .add-product-btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.4rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(78, 115, 223, 0.2);
        }
        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.3);
        }
        
        /* Filter Section */
        .row > .col-md-6 {
            margin-bottom: 1rem;
        }
        .input-group {
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
            border-radius: 0.4rem;
            overflow: hidden;
        }
        .input-group .form-control {
            border-color: var(--border-color);
            padding: 0.75rem 1rem;
        }
        .input-group .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.1);
        }
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
        .btn-outline-secondary {
            border-color: var(--border-color);
            color: #5a5c69;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            border-radius: 0.4rem;
        }
        .btn-outline-secondary:hover {
            background-color: #f8f9fc;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .dropdown-menu {
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            padding: 0.5rem 0;
        }
        .dropdown-item {
            padding: 0.7rem 1.25rem;
            color: #2e3338;
            border-radius: 0.3rem;
            margin: 0.2rem 0.5rem;
        }
        .dropdown-item:hover,
        .dropdown-item.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Products Table */
        .product-table {
            background-color: white;
            border-radius: 0.6rem;
            box-shadow: 0 2px 8px rgba(58, 59, 69, 0.12);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .product-table th {
            background: linear-gradient(135deg, #f8f9fc 0%, #f1f3f9 100%);
            border-bottom: 2px solid var(--border-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
            padding: 1.2rem 1rem;
        }
        .product-table td {
            padding: 1.2rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }
        .product-table tbody tr:hover {
            background-color: #f8f9fc;
        }
        .product-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Product Image */
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.35rem;
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease;
        }
        .product-image:hover {
            transform: scale(1.05);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            display: inline-block;
        }
        .badge-active {
            background-color: rgba(28, 200, 138, 0.15);
            color: #108742;
        }
        .badge-inactive {
            background-color: rgba(108, 117, 125, 0.15);
            color: #5a5c69;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 0.35rem 0.65rem;
            font-size: 0.8rem;
            margin: 0.15rem;
            border-radius: 0.3rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .action-btn:hover {
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
            transform: translateY(-1px);
        }
        .btn-primary.action-btn {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-success.action-btn {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        .btn-danger.action-btn {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        /* Pagination */
        .pagination {
            gap: 0.25rem;
            margin-top: 2rem;
        }
        .page-link {
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            padding: 0.6rem 0.8rem;
            border-radius: 0.3rem;
            transition: all 0.2s ease;
        }
        .page-link:hover:not(.disabled) {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .page-item.disabled .page-link {
            color: #bec3d0;
        }
        
        /* Empty State */
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem;
            }
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }
            .col-md-6 {
                width: 100% !important;
            }
            .btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }
            .btn-group .btn {
                width: 100%;
            }
            .product-table th,
            .product-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }
            .action-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }
            .table-responsive {
                font-size: 0.85rem;
            }
        }
    </style>
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="h3 mb-2">Product Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Products</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="add_product.php" class="btn btn-primary add-product-btn">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </a>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="row g-3">
                <div class="col-lg-6 col-md-6">
                    <form method="get" class="w-100">
                        <div class="input-group search-container w-100">
                            <input type="text" name="search" class="form-control" placeholder="Search by name, SKU, or ID..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php if ($category_filter > 0): ?>
                                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                            <?php endif; ?>
                            <?php if ($brand_filter > 0): ?>
                                <input type="hidden" name="brand" value="<?php echo $brand_filter; ?>">
                            <?php endif; ?>
                            <button class="btn btn-outline-secondary" type="submit" title="Search products">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="?<?php echo http_build_query(['category' => $category_filter, 'brand' => $brand_filter]); ?>" 
                                   class="btn btn-outline-danger" title="Clear search">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="d-flex gap-2 flex-wrap">
                        <!-- Category Filter -->
                        <div class="btn-group flex-grow-1" role="group">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-filter me-2"></i>
                                <?php 
                                if ($category_filter > 0) {
                                    $selected_category = array_filter($categories, function($cat) use ($category_filter) {
                                        return $cat['id'] == $category_filter;
                                    });
                                    echo !empty($selected_category) ? htmlspecialchars(array_values($selected_category)[0]['display_name']) : 'Category';
                                } else {
                                    echo 'All Categories';
                                }
                                ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item <?php echo $category_filter == 0 ? 'active' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])); ?>">All Categories</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php foreach ($categories as $category): ?>
                                <li><a class="dropdown-item <?php echo $category_filter == $category['id'] ? 'active' : ''; ?>" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['category' => $category['id'], 'page' => 1])); ?>">
                                    <?php echo htmlspecialchars($category['display_name']); ?>
                                </a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <!-- Brand Filter -->
                        <div class="btn-group flex-grow-1" role="group">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-star me-2"></i>
                                <?php 
                                if ($brand_filter > 0) {
                                    $selected_brand = array_filter($brands, function($brand) use ($brand_filter) {
                                        return $brand['id'] == $brand_filter;
                                    });
                                    echo !empty($selected_brand) ? htmlspecialchars(array_values($selected_brand)[0]['display_name']) : 'Brand';
                                } else {
                                    echo 'All Brands';
                                }
                                ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item <?php echo $brand_filter == 0 ? 'active' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['brand' => '', 'page' => 1])); ?>">All Brands</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php foreach ($brands as $brand): ?>
                                <li><a class="dropdown-item <?php echo $brand_filter == $brand['id'] ? 'active' : ''; ?>" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['brand' => $brand['id'], 'page' => 1])); ?>">
                                    <?php echo htmlspecialchars($brand['display_name']); ?>
                                </a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <!-- Clear Filters -->
                        <?php if ($category_filter > 0 || $brand_filter > 0): ?>
                        <a href="?<?php echo http_build_query(array_diff_key($_GET, ['category' => '', 'brand' => ''])); ?>" 
                           class="btn btn-outline-danger btn-sm" 
                           title="Clear all filters">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card shadow mb-4 product-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><i class="fas fa-image me-2"></i>Image</th>
                                <th><i class="fas fa-tag me-2"></i>Product</th>
                                <th style="width: 120px;"><i class="fas fa-barcode me-2"></i>SKU</th>
                                <th style="width: 140px;"><i class="fas fa-folder me-2"></i>Category</th>
                                <th style="width: 100px;"><i class="fas fa-money-bill me-2"></i>Price</th>
                                <th style="width: 80px;"><i class="fas fa-boxes me-2"></i>Stock</th>
                                <th style="width: 90px;"><i class="fas fa-weight me-2"></i>Weight</th>
                                <th style="width: 90px;"><i class="fas fa-circle-info me-2"></i>Status</th>
                                <th style="width: 80px;"><i class="fas fa-star me-2"></i>Featured</th>
                                <th style="width: 160px;"><i class="fas fa-cog me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-box-open fa-4x mb-3" style="color: #bec3d0;"></i>
                                        <h5 class="mt-3">No products found</h5>
                                        <?php if (!empty($search_query)): ?>
                                        <p>No products found matching <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong></p>
                                        <a href="?<?php echo http_build_query(['category' => $category_filter, 'brand' => $brand_filter]); ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-times me-1"></i>Clear Search
                                        </a>
                                        <?php elseif ($category_filter > 0 || $brand_filter > 0): ?>
                                        <p>No products found with the selected filters.</p>
                                        <a href="?<?php echo http_build_query(array_diff_key($_GET, ['category' => '', 'brand' => ''])); ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-arrow-left me-1"></i>View All Products
                                        </a>
                                        <?php else: ?>
                                        <p>Start by adding your first product.</p>
                                        <a href="add_product.php" class="btn btn-sm btn-primary">
                                            <i class="fas fa-plus me-1"></i>Add Product
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $default_image = 'data:image/svg+xml;base64,' . base64_encode('
                                        <svg width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="60" height="60" fill="#f8f9fa" stroke="#dee2e6"/>
                                            <text x="30" y="30" font-family="Arial" font-size="10" fill="#6c757d" text-anchor="middle">No</text>
                                            <text x="30" y="42" font-family="Arial" font-size="10" fill="#6c757d" text-anchor="middle">Image</text>
                                        </svg>
                                    ');
                                    
                                    $image_path = $default_image;
                                    
                                    if (!empty($p['image_path'])) {
                                        $full_image_path = $root_dir . '/public/' . $p['image_path'];
                                        
                                        if (file_exists($full_image_path)) {
                                            $image_path = '../' . $p['image_path'];
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                         alt="<?php echo htmlspecialchars($p['name_en']); ?>" 
                                         class="product-image"
                                         onerror="this.src='<?php echo $default_image; ?>'">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars(substr($p['name_en'], 0, 40)); ?></strong>
                                    <?php if (strlen($p['name_en']) > 40): ?>
                                    <span title="<?php echo htmlspecialchars($p['name_en']); ?>">...</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="background: #f8f9fc; padding: 0.25rem 0.5rem; border-radius: 0.3rem; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($p['sku']); ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>AED <?php echo number_format($p['price'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?php echo $p['stock'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $p['stock']; ?> units
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo number_format($p['weight'] ?? 0, 2); ?> kg
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $p['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $p['status'] ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $p['featured'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $p['featured'] ? '★ Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_product.php?id=<?php echo $p['id']; ?>" 
                                           class="btn btn-primary action-btn" 
                                           title="Edit product">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="products.php?duplicate=<?php echo $p['id']; ?>" 
                                           class="btn btn-success action-btn" 
                                           title="Duplicate product"
                                           onclick="return confirm('Create a copy of this product?')">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        <a href="products.php?delete=<?php echo $p['id']; ?>" 
                                           class="btn btn-danger action-btn" 
                                           title="Delete product"
                                           onclick="return confirm('Delete this product permanently?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_products > 0): ?>
        <nav aria-label="Products pagination" class="mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <small class="text-muted">
                        Showing <strong><?php echo ($offset + 1); ?></strong>–<strong><?php echo min($offset + $products_per_page, $total_products); ?></strong> 
                        of <strong><?php echo $total_products; ?></strong> products
                    </small>
                </div>
                <small class="text-muted">
                    Page <strong><?php echo $current_page; ?></strong> of <strong><?php echo max(1, $total_pages); ?></strong>
                </small>
            </div>
            
            <ul class="pagination justify-content-center mb-0">
                <?php
                $query_params = $_GET;
                unset($query_params['page']);
                $base_query = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                ?>
                
                <?php if ($current_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo $base_query; ?>" tabindex="-1">
                        <i class="fas fa-chevron-left me-2"></i>Previous
                    </a>
                </li>
                <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link"><i class="fas fa-chevron-left me-2"></i>Previous</span>
                </li>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1' . $base_query . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $base_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor;

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $base_query . '">' . $total_pages . '</a></li>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo $base_query; ?>">
                        Next<i class="fas fa-chevron-right ms-2"></i>
                    </a>
                </li>
                <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link">Next<i class="fas fa-chevron-right ms-2"></i></span>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <script>
        // Enhanced delete confirmation
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Delete this product permanently? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
        
        // Add hover effect to table rows
        document.querySelectorAll('.product-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fc';
            });
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>