<?php
// File: admin/bulk_edit_prices.php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();
$has_cost_price = false;

// Add cost_price column if it doesn't exist
try {
    $columns = $db->query("SHOW COLUMNS FROM products LIKE 'cost_price'")->fetchAll();
    if (!empty($columns)) {
        $has_cost_price = true;
    } else {
        $db->query("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT NULL AFTER price");
        $has_cost_price = true;
    }
} catch (Exception $e) {
    error_log("Error adding cost_price column: " . $e->getMessage());
    $has_cost_price = false;
}

// Handle bulk update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['bulk_update']) || isset($_POST['single_update_id']))) {
    $updated_count = 0;
    $error_count = 0;
    $products_to_update = $_POST['products'] ?? [];

    if (isset($_POST['single_update_id'])) {
        $single_update_id = (int)$_POST['single_update_id'];
        if (isset($products_to_update[$single_update_id])) {
            $products_to_update = [$single_update_id => $products_to_update[$single_update_id]];
        } else {
            $products_to_update = [];
        }
    }
    
    foreach ($products_to_update as $product_id => $data) {
        try {
            $updates = [];
            $params = ['id' => (int)$product_id];

            // Numeric fields
            if (isset($data['price']) && $data['price'] !== '') {
                $updates[] = "price = :price";
                $params['price'] = floatval($data['price']);
            }
            if ($has_cost_price && isset($data['cost_price']) && $data['cost_price'] !== '') {
                $updates[] = "cost_price = :cost_price";
                $params['cost_price'] = floatval($data['cost_price']);
            }
            if (isset($data['stock']) && $data['stock'] !== '') {
                $updates[] = "stock = :stock";
                $params['stock'] = max(0, (int)$data['stock']);
            }

            // Name fields
            if (isset($data['name_en']) && trim($data['name_en']) !== '') {
                $updates[] = "name_en = :name_en";
                $params['name_en'] = trim($data['name_en']);
            }
            if (isset($data['name_ar'])) {
                $updates[] = "name_ar = :name_ar";
                $params['name_ar'] = trim($data['name_ar']);
            }

            // Category / Brand (0 = unassign → NULL)
            if (isset($data['category_id'])) {
                $updates[] = "category_id = :category_id";
                $params['category_id'] = (int)$data['category_id'] > 0 ? (int)$data['category_id'] : null;
            }
            if (isset($data['brand_id'])) {
                $updates[] = "brand_id = :brand_id";
                $params['brand_id'] = (int)$data['brand_id'] > 0 ? (int)$data['brand_id'] : null;
            }

            if (!empty($updates)) {
                $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = :id";
                $db->query($sql, $params);
                $updated_count++;
            }
        } catch (Exception $e) {
            error_log("Error updating product $product_id: " . $e->getMessage());
            $error_count++;
        }
    }
    
    if (isset($_POST['single_update_id'])) {
        if ($updated_count > 0) {
            $success_message = "Product updated successfully.";
        } else {
            $error_message = "No changes were saved for this product.";
        }
        if ($error_count > 0) {
            $error_message = "Failed to update this product.";
        }
    } else {
        if ($updated_count > 0) {
            $success_message = "Successfully updated $updated_count product(s).";
            if ($error_count > 0) {
                $success_message .= " Failed to update $error_count product(s).";
            }
        } else {
            $error_message = "No products were updated.";
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$brand_filter = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch all categories for the filter dropdown
$categories = [];
try {
    $categories = $db->query("SELECT id, name_en, name_ar FROM categories ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bulk edit categories query failed: " . $e->getMessage());
}

// Fetch all brands for the filter dropdown
$brands = [];
try {
    $brands = $db->query("SELECT id, name_en, name_ar FROM brands ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bulk edit brands query failed: " . $e->getMessage());
}

// Pagination settings
$products_per_page = 50;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);

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
    $where_conditions[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar OR p.sku LIKE :search_sku OR CAST(p.id AS CHAR) LIKE :search_id)";
    $search_like = '%' . $search_query . '%';
    $params['search_en'] = $search_like;
    $params['search_ar'] = $search_like;
    $params['search_sku'] = $search_like;
    $params['search_id'] = $search_like;
}

if ($status_filter !== '') {
    $where_conditions[] = "p.status = :status";
    $params['status'] = (int)$status_filter;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
}

// Get total count of products (with filter)
$count_sql = "SELECT COUNT(*) as count FROM products p" . $where_clause;
$total_products = 0;
try {
    $count_result = $db->query($count_sql, $params)->fetch(PDO::FETCH_ASSOC);
    $total_products = isset($count_result['count']) ? (int)$count_result['count'] : 0;
} catch (Exception $e) {
    error_log("Bulk edit count query failed: " . $e->getMessage());
    $error_message = "Unable to load products right now. Please try again.";
}

$total_pages = max(1, (int)ceil($total_products / $products_per_page));

// Calculate offset for SQL query
$offset = ($current_page - 1) * $products_per_page;

// Add pagination parameters
$params['limit'] = $products_per_page;
$params['offset'] = $offset;

$cost_price_select = $has_cost_price ? 'p.cost_price' : 'NULL as cost_price';

// Get products for current page
$products = [];
try {
    $products = $db->query("
        SELECT p.id, p.sku, p.name_en, p.name_ar, p.price, " . $cost_price_select . ", p.stock, p.status,
               p.category_id, p.brand_id,
               c.name_en as category_name,
               b.name_en as brand_name,
               pi.image_path
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN (
            SELECT product_id, MIN(image_path) as image_path
            FROM product_images
            WHERE is_main = 1
            GROUP BY product_id
        ) pi ON p.id = pi.product_id
        " . $where_clause . "
        ORDER BY p.id DESC 
        LIMIT :limit OFFSET :offset
    ", $params)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bulk edit products query failed: " . $e->getMessage());
    $products = [];
    if (!isset($error_message)) {
        $error_message = "Unable to load products right now. Please try again.";
    }
}
?>
<?php
$page_title = 'Bulk Edit Products';
require_once __DIR__ . '/includes/header.php';
?>
<style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .page-header {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
        }
        .filter-section {
            background-color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .table-container {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .table thead th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        #pricesTable {
            table-layout: fixed;
            width: 100%;
            min-width: 1750px;
        }
        .name-input {
            font-size: 0.78rem;
            padding: 0.25rem 0.4rem;
        }
        .select-editable {
            font-size: 0.78rem;
        }
        #pricesTable td {
            vertical-align: middle;
        }
        .price-input {
            width: 100px;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
        }
        .stock-field {
            width: 90px;
        }
        .product-name-cell {
            min-width: 260px;
        }
        .product-name-clamp {
            display: -webkit-box;
            line-clamp: 2;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.35;
            max-height: 2.7em;
            font-weight: 700;
            color: #1f2937;
        }
        .editable-cluster {
            background-color: #f8fbff;
        }
        .sticky-col {
            position: sticky;
            background-color: #f8fbff;
            z-index: 4;
            box-shadow: -1px 0 0 #dbe8ff inset;
        }
        .table thead th.sticky-col {
            z-index: 12;
            background-color: #eef4ff;
        }
        .sticky-action {
            right: 0;
        }
        .cluster-start {
            border-left: 2px solid #dbe8ff;
        }
        .cluster-end {
            border-right: 2px solid #dbe8ff;
        }
        .row-save-btn {
            min-width: 88px;
            white-space: nowrap;
        }
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
            text-transform: uppercase;
        }
        .badge-active {
            background-color: var(--success-color);
            color: white;
        }
        .badge-inactive {
            background-color: #6c757d;
            color: white;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profit-margin {
            font-size: 0.75rem;
            color: #858796;
        }
        .profit-positive {
            color: var(--success-color);
        }
        .profit-negative {
            color: var(--danger-color);
        }
        .changed-row {
            background-color: #fff3cd;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.35rem;
            border: 1px solid #dee2e6;
        }
        @media (max-width: 991.98px) {
            .product-image {
                width: 50px;
                height: 50px;
            }
            .price-input {
                width: 80px;
                font-size: 0.8rem;
                padding: 0.25rem 0.35rem;
            }
            .stock-field {
                width: 70px;
            }
            .name-input {
                font-size: 0.75rem;
            }
            #pricesTable {
                min-width: 1200px;
            }
            .page-header h1 {
                font-size: 1.1rem;
            }
            .page-header p {
                font-size: 0.8rem;
            }
        }
        @media (max-width: 575.98px) {
            .alert-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            .page-header {
                padding: 1rem;
            }
            .filter-section {
                padding: 1rem;
            }
            .btn-lg {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
        }
        /* ── Responsive card layout on mobile ── */
        @media (max-width: 767.98px) {
            .table-responsive {
                overflow-x: unset !important;
                max-height: none !important;
            }
            #pricesTable {
                min-width: 0 !important;
                table-layout: auto !important;
            }
            #pricesTable thead {
                display: none;
            }
            #pricesTable tbody tr.product-row {
                display: block;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
                background: white;
                box-shadow: 0 1px 4px rgba(0,0,0,0.08);
                overflow: hidden;
            }
            /* no-products row */
            #pricesTable tbody tr:not(.product-row) td {
                display: block;
                text-align: center;
            }
            #pricesTable td {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                border: none;
                border-bottom: 1px solid #f3f3f3;
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
            #pricesTable td:last-child {
                border-bottom: none;
            }
            #pricesTable td::before {
                content: attr(data-label);
                font-weight: 700;
                font-size: 0.7rem;
                text-transform: uppercase;
                color: #5a5c69;
                min-width: 90px;
                flex-shrink: 0;
            }
            #pricesTable td.sticky-col {
                position: static !important;
                box-shadow: none !important;
            }
            #pricesTable td input,
            #pricesTable td select {
                width: 100%;
            }
            .price-input,
            .stock-field {
                width: 100% !important;
            }
            .row-save-btn {
                width: 100%;
            }
            .product-image {
                width: 56px !important;
                height: 56px !important;
            }
        }
    </style>

    <div class="container-fluid py-4">
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show alert-container" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show alert-container" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!$has_cost_price): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-triangle-exclamation me-2"></i>
                Cost Price column is not available in this database. Price and stock editing will still work.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-edit me-2"></i>Bulk Edit Products
                        </h1>
                        <p class="text-muted mb-0">Update product names, categories, brands, prices, cost prices and stock in bulk</p>
                    </div>
                    <div>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Products
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-search me-1"></i>Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Name, SKU, ID..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-tags me-1"></i>Category</label>
                            <select name="category" class="form-select">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name_en']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-trademark me-1"></i>Brand</label>
                            <select name="brand" class="form-select">
                                <option value="0">All Brands</option>
                                <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>" 
                                        <?php echo $brand_filter == $brand['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand['name_en']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-toggle-on me-1"></i>Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <a href="bulk_edit_prices.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results Info -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="text-muted">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $products_per_page, $total_products); ?> 
                            of <?php echo $total_products; ?> products
                        </span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-info" onclick="selectChanged()">
                            <i class="fas fa-check-double me-1"></i>Select Changed Only
                        </button>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <form method="POST" action="" id="bulkEditForm">
                <div class="table-container">
                    <div class="table-responsive" style="max-height: min(600px, 60vh); overflow-y: auto;">
                        <table class="table table-hover mb-0" id="pricesTable">
                            <thead>
                                <tr>
                                    <th width="60px">ID</th>
                                    <th width="70px">Image</th>
                                    <th width="100px">SKU</th>
                                    <th width="230px">Product Name <small class="d-block text-muted fw-normal" style="font-size:0.65em">EN / AR</small></th>
                                    <th width="150px">Category</th>
                                    <th width="140px">Brand</th>
                                    <th width="100px" class="editable-cluster cluster-start">Stock</th>
                                    <th width="120px" class="editable-cluster">
                                        <i class="fas fa-dollar-sign me-1"></i>Price (AED)
                                    </th>
                                    <th width="120px" class="editable-cluster">
                                        <i class="fas fa-coins me-1"></i>Cost Price (AED)
                                    </th>
                                    <th width="100px">Margin</th>
                                    <th width="80px">Status</th>
                                    <th width="110px" class="editable-cluster cluster-end sticky-col sticky-action">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3"></i>
                                            <h5>No products found</h5>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                <tr data-product-id="<?php echo $product['id']; ?>" class="product-row">
                                    <td data-label="ID"><?php echo $product['id']; ?></td>
                                    <td data-label="Image">
                                        <?php 
                                        $default_image = 'data:image/svg+xml;base64,' . base64_encode('
                                            <svg width="50" height="50" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="50" height="50" fill="#f8f9fa" stroke="#dee2e6"/>
                                                <text x="25" y="25" font-family="Arial" font-size="8" fill="#6c757d" text-anchor="middle">No</text>
                                                <text x="25" y="35" font-family="Arial" font-size="8" fill="#6c757d" text-anchor="middle">Image</text>
                                            </svg>
                                        ');
                                        
                                        $image_path = $default_image;
                                        
                                        if (!empty($product['image_path'])) {
                                            $full_image_path = $root_dir . '/public/' . $product['image_path'];
                                            
                                            if (file_exists($full_image_path)) {
                                                $image_path = '../' . $product['image_path'];
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name_en']); ?>" 
                                             class="product-image"
                                             onerror="this.src='<?php echo $default_image; ?>'">
                                    </td>
                                    <td data-label="SKU">
                                        <small class="text-muted"><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td class="product-name-cell" data-label="Name">
                                        <input type="text"
                                               name="products[<?php echo $product['id']; ?>][name_en]"
                                               class="form-control name-input text-editable mb-1"
                                               placeholder="Name (EN)"
                                               value="<?php echo htmlspecialchars($product['name_en'] ?? ''); ?>"
                                               data-original="<?php echo htmlspecialchars($product['name_en'] ?? ''); ?>"
                                               onchange="markChanged(this)">
                                        <input type="text"
                                               name="products[<?php echo $product['id']; ?>][name_ar]"
                                               class="form-control name-input text-editable"
                                               placeholder="&#x627;&#x644;&#x627;&#x633;&#x645; (AR)"
                                               dir="rtl"
                                               value="<?php echo htmlspecialchars($product['name_ar'] ?? ''); ?>"
                                               data-original="<?php echo htmlspecialchars($product['name_ar'] ?? ''); ?>"
                                               onchange="markChanged(this)">
                                    </td>
                                    <td data-label="Category">
                                        <select name="products[<?php echo $product['id']; ?>][category_id]"
                                                class="form-select form-select-sm select-editable"
                                                data-original="<?php echo (int)($product['category_id'] ?? 0); ?>"
                                                onchange="markChanged(this)">
                                            <option value="0">&#8212; None &#8212;</option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ((int)($product['category_id'] ?? 0)) === (int)$cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name_en']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td data-label="Brand">
                                        <select name="products[<?php echo $product['id']; ?>][brand_id]"
                                                class="form-select form-select-sm select-editable"
                                                data-original="<?php echo (int)($product['brand_id'] ?? 0); ?>"
                                                onchange="markChanged(this)">
                                            <option value="0">&#8212; None &#8212;</option>
                                            <?php foreach ($brands as $brand): ?>
                                            <option value="<?php echo $brand['id']; ?>" <?php echo ((int)($product['brand_id'] ?? 0)) === (int)$brand['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($brand['name_en']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="editable-cluster cluster-start text-center" data-label="Stock">
                                        <input type="number" 
                                               name="products[<?php echo $product['id']; ?>][stock]" 
                                               class="form-control price-input stock-field" 
                                               step="1" 
                                               min="0"
                                               value="<?php echo (int)$product['stock']; ?>"
                                               data-original="<?php echo (int)$product['stock']; ?>"
                                               onchange="markChanged(this)">
                                    </td>
                                    <td class="editable-cluster" data-label="Price (AED)">
                                        <input type="number" 
                                               name="products[<?php echo $product['id']; ?>][price]" 
                                               class="form-control price-input price-field" 
                                               step="0.01" 
                                               min="0"
                                               value="<?php echo number_format($product['price'], 2, '.', ''); ?>"
                                               data-original="<?php echo number_format($product['price'], 2, '.', ''); ?>"
                                               onchange="markChanged(this)">
                                    </td>
                                    <td class="editable-cluster" data-label="Cost Price">
                                        <input type="number" 
                                               name="products[<?php echo $product['id']; ?>][cost_price]" 
                                               class="form-control price-input cost-price-field" 
                                               step="0.01" 
                                               min="0"
                                               <?php echo !$has_cost_price ? 'disabled' : ''; ?>
                                               value="<?php echo $product['cost_price'] ? number_format($product['cost_price'], 2, '.', '') : ''; ?>"
                                               data-original="<?php echo $product['cost_price'] ? number_format($product['cost_price'], 2, '.', '') : ''; ?>"
                                               onchange="markChanged(this)">
                                    </td>
                                    <td class="margin-cell" data-label="Margin">
                                        <?php 
                                        if ($product['price'] && $product['cost_price']) {
                                            $margin = (($product['price'] - $product['cost_price']) / $product['price']) * 100;
                                            $margin_class = $margin > 0 ? 'profit-positive' : 'profit-negative';
                                            echo '<span class="profit-margin ' . $margin_class . '">' . number_format($margin, 1) . '%</span>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge <?php echo $product['status'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $product['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="editable-cluster cluster-end sticky-col sticky-action" data-label="Action">
                                        <button type="submit"
                                                name="single_update_id"
                                                value="<?php echo $product['id']; ?>"
                                                class="btn btn-sm btn-primary row-save-btn">
                                            <i class="fas fa-save me-1"></i>Save
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="bulk_update" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>Save Changed in Bulk
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="resetChanges()">
                            <i class="fas fa-undo me-2"></i>Reset Changes
                        </button>
                    </div>
                    <div>
                        <span id="changedCount" class="text-muted">0 products changed</span>
                    </div>
                </div>
            </form>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Products pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    // Build query string for pagination links
                    $query_params = [];
                    if ($category_filter > 0) $query_params['category'] = $category_filter;
                    if ($brand_filter > 0) $query_params['brand'] = $brand_filter;
                    if (!empty($search_query)) $query_params['search'] = $search_query;
                    if ($status_filter !== '') $query_params['status'] = $status_filter;
                    $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                    ?>
                    
                    <!-- Previous Page -->
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo $query_string; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    // Show page numbers
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . $query_string . '">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = $i == $current_page ? 'active' : '';
                        echo '<li class="page-item ' . $active . '">';
                        echo '<a class="page-link" href="?page=' . $i . $query_string . '">' . $i . '</a>';
                        echo '</li>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $query_string . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo $query_string; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

    <script>
        let changedRows = new Set();

        function markChanged(input) {
            const row = input.closest('tr');
            const original = input.dataset.original;
            const current = input.value;
            
            if (current !== original) {
                row.classList.add('changed-row');
                changedRows.add(row.dataset.productId);
            } else {
                // Check if any other input in this row is changed
                const inputs = row.querySelectorAll('.price-field, .cost-price-field, .stock-field, .text-editable, .select-editable');
                let hasChanges = false;
                inputs.forEach(inp => {
                    if (inp.value !== inp.dataset.original) {
                        hasChanges = true;
                    }
                });
                
                if (!hasChanges) {
                    row.classList.remove('changed-row');
                    changedRows.delete(row.dataset.productId);
                }
            }
            
            updateChangedCount();
            calculateMargin(row);
        }

        function calculateMargin(row) {
            const priceInput = row.querySelector('.price-field');
            const costInput = row.querySelector('.cost-price-field');
            const marginCell = row.querySelector('.margin-cell');
            
            const price = parseFloat(priceInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            
            if (price > 0 && cost > 0) {
                const margin = ((price - cost) / price) * 100;
                const marginClass = margin > 0 ? 'profit-positive' : 'profit-negative';
                marginCell.innerHTML = '<span class="profit-margin ' + marginClass + '">' + margin.toFixed(1) + '%</span>';
            } else {
                marginCell.innerHTML = '<span class="text-muted">-</span>';
            }
        }

        function updateChangedCount() {
            document.getElementById('changedCount').textContent = changedRows.size + ' products changed';
        }

        function resetChanges() {
            if (!confirm('Are you sure you want to reset all changes?')) {
                return;
            }
            
            const inputs = document.querySelectorAll('.price-field, .cost-price-field, .stock-field, .text-editable, .select-editable');
            inputs.forEach(input => {
                input.value = input.dataset.original;
                const row = input.closest('tr');
                row.classList.remove('changed-row');
            });
            
            changedRows.clear();
            updateChangedCount();
            
            // Recalculate all margins
            document.querySelectorAll('.product-row').forEach(row => {
                calculateMargin(row);
            });
        }

        function selectChanged() {
            const allRows = document.querySelectorAll('.product-row');
            allRows.forEach(row => {
                if (row.classList.contains('changed-row')) {
                    row.style.display = '';
                } else {
                    row.style.display = row.style.display === 'none' ? '' : 'none';
                }
            });
            
            // Update button text
            const btn = event.target.closest('button');
            const showing = Array.from(allRows).some(row => row.style.display === 'none');
            btn.innerHTML = showing ? 
                '<i class="fas fa-eye me-1"></i>Show All' : 
                '<i class="fas fa-check-double me-1"></i>Select Changed Only';
        }

        // Confirm before leaving page if there are unsaved changes
        window.addEventListener('beforeunload', function (e) {
            if (changedRows.size > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Prevent form submission on Enter key in input fields
        document.querySelectorAll('.price-input, .text-editable').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        });

        // Auto dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-container');
            alerts.forEach(alert => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            });
        }, 5000);
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
