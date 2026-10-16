<?php
// File: admin/products.php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/functions.php');

require_admin_login();

$db = new Database();
require_once $root_dir . '/includes/store_features.php';

if (!empty($_POST['bulk_delete_products']) && !empty($_POST['selected_products'])) {
    $skipped = [];
    $deleted = 0;
    foreach ((array)$_POST['selected_products'] as $productId) {
        $productId = (int)$productId;
        $used = $db->fetch('SELECT id FROM order_items WHERE product_id = ? LIMIT 1', [$productId]);
        if ($used) {
            $skipped[] = $productId;
            continue;
        }
        $images = $db->fetchAll('SELECT image_path FROM product_images WHERE product_id = ?', [$productId]);
        foreach ($images as $image) {
            $path = $root_dir . '/public/' . ltrim((string)$image['image_path'], '/');
            if (is_file($path)) {
                unlink($path);
            }
        }
        $db->query('DELETE FROM product_images WHERE product_id = ?', [$productId]);
        $db->query('DELETE FROM products WHERE id = ?', [$productId]);
        $deleted++;
    }
    $bulkMessage = "Deleted $deleted product(s).";
    if ($skipped) {
        $bulkMessage .= ' Skipped products already on orders: ' . implode(', ', $skipped) . '.';
    }
}

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
$name_issue = isset($_GET['name_issue']) ? $_GET['name_issue'] : '';
if (!in_array($name_issue, ['placeholder_ar', 'long_en'], true)) {
    $name_issue = '';
}

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
    $where_conditions[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar OR p.sku LIKE :search_sku OR CAST(p.id AS CHAR) LIKE :search_id)";
    $search_like = '%' . $search_query . '%';
    $params['search_en'] = $search_like;
    $params['search_ar'] = $search_like;
    $params['search_sku'] = $search_like;
    $params['search_id'] = $search_like;
}

if ($name_issue === 'placeholder_ar') {
    $where_conditions[] = "(p.name_ar IS NULL OR TRIM(p.name_ar) = '' OR p.name_ar REGEXP '^New Product[[:space:]]*#[0-9]+$')";
} elseif ($name_issue === 'long_en') {
    $where_conditions[] = "CHAR_LENGTH(TRIM(p.name_en)) > 80";
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
               b.name_en as brand_name,
               (SELECT pi.image_path 
                FROM product_images pi 
                WHERE pi.product_id = p.id 
                ORDER BY pi.id ASC 
                LIMIT 1) as image_path
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
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
            $product['brand_name'] = '';
            if (!empty($product['brand_id'])) {
                $brand_result = $db->query("
                    SELECT name_en FROM brands
                    WHERE id = :id
                ", ['id' => $product['brand_id']])->fetch(PDO::FETCH_ASSOC);
                $product['brand_name'] = $brand_result ? $brand_result['name_en'] : '';
            }
        } catch (Exception $e2) {
            $product['image_path'] = null;
            $product['category_name'] = 'No Category';
            $product['brand_name'] = '';
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
        body.admin-layout .product-table {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 16px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        body.admin-layout .product-table:hover {
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06) !important;
        }
        body.admin-layout .product-table > .card-body {
            padding: 0 !important;
        }
        .catalog-notice {
            margin: 0;
            padding: 0.75rem 1.15rem;
            background: #fff7ed;
            color: #9a3412;
            border-bottom: 1px solid rgba(230, 126, 34, 0.16);
            font-size: 0.88rem;
            font-weight: 600;
        }
        .catalog-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1.15rem;
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
        }
        .catalog-toolbar-left,
        .catalog-toolbar-right {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            min-width: 0;
        }
        .select-all-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            font-size: 0.84rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            user-select: none;
        }
        .catalog-check {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #e67e22;
            cursor: pointer;
        }
        .selection-count {
            font-size: 0.8rem;
            font-weight: 700;
            color: #94a3b8;
        }
        .selection-count.has-selection {
            color: #c2410c;
        }
        body.admin-layout .product-table .catalog-toolbar .btn {
            box-shadow: none;
        }
        body.admin-layout .product-table .table-responsive {
            border-radius: 0;
        }
        .catalog-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        body.admin-layout .product-table .catalog-grid thead th {
            background: #fffaf6 !important;
            color: #8a5a22 !important;
            border-bottom: 1px solid rgba(230, 126, 34, 0.16) !important;
            font-size: 0.7rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase;
            padding: 0.8rem 1rem !important;
            white-space: nowrap;
            vertical-align: middle;
        }
        body.admin-layout .product-table .catalog-grid tbody td {
            padding: 0.8rem 1rem !important;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            background-color: transparent !important;
            box-shadow: none !important;
            --bs-table-bg: transparent;
            --bs-table-accent-bg: transparent;
        }
        body.admin-layout .product-table .catalog-grid tbody tr:last-child td {
            border-bottom: none;
        }
        body.admin-layout .product-table .catalog-grid tbody tr:hover td {
            background-color: #fffaf5 !important;
        }
        body.admin-layout .product-table .catalog-grid tbody tr.is-selected td,
        body.admin-layout .product-table .catalog-grid tbody tr.is-selected:hover td {
            background-color: #fff4e8 !important;
        }
        .catalog-grid .col-check {
            width: 52px;
            text-align: center;
        }
        .catalog-grid .col-num {
            text-align: right;
            white-space: nowrap;
        }
        .catalog-grid .col-status {
            width: 120px;
        }
        .catalog-grid .col-actions {
            width: 140px;
            text-align: right;
        }
        .product-cell {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            min-width: 220px;
        }
        .product-thumb {
            width: 56px;
            height: 56px;
            flex: 0 0 56px;
            object-fit: cover;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e8edf3;
        }
        .product-copy {
            min-width: 0;
        }
        .product-title {
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
        }
        .product-title a {
            color: #1e293b;
            font-weight: 800;
            font-size: 0.95rem;
            line-height: 1.35;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-title a:hover {
            color: #c2410c;
        }
        .featured-mark {
            color: #f59e0b;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            flex: 0 0 auto;
        }
        .product-meta {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.55rem;
            margin-top: 0.28rem;
            color: #64748b;
            font-size: 0.78rem;
            min-width: 0;
        }
        .product-meta .sku,
        .product-meta .meta-fixed {
            flex: 0 0 auto;
            white-space: nowrap;
        }
        .product-meta .meta-grow {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .product-meta .sku {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            color: #475569;
            background: #f8fafc;
            border-radius: 6px;
            padding: 0.05rem 0.35rem;
        }
        .name-flags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-top: 0.4rem;
        }
        .name-flag {
            display: inline-flex;
            align-items: center;
            padding: 0.12rem 0.45rem;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.01em;
        }
        .price-cell {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
        }
        .price-currency {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #c2410c;
        }
        .price-value {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
        }
        .price-was {
            font-size: 0.72rem;
            color: #94a3b8;
            text-decoration: line-through;
        }
        .price-sale {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #9a3412;
        }
        .stock-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.15rem;
        }
        .stock-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.2rem;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .stock-ok {
            background: #ecfdf3;
            color: #166534;
        }
        .stock-low {
            background: #fff7ed;
            color: #c2410c;
        }
        .stock-out {
            background: #fef2f2;
            color: #b91c1c;
        }
        .stock-label,
        .weight-label {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 700;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .status-pill.is-on {
            background: #ecfdf3;
            color: #166534;
        }
        .status-pill.is-off {
            background: #f1f5f9;
            color: #64748b;
        }
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }
        .row-actions {
            display: inline-flex;
            justify-content: flex-end;
            gap: 0.35rem;
        }
        body.admin-layout .product-table .icon-btn {
            width: 34px;
            height: 34px;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px !important;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            box-shadow: none !important;
            text-decoration: none;
        }
        body.admin-layout .product-table .icon-btn:hover {
            transform: none;
            box-shadow: none !important;
        }
        body.admin-layout .product-table .icon-btn.edit:hover {
            color: #c2410c;
            border-color: rgba(230, 126, 34, 0.4);
            background: #fff7ed;
        }
        body.admin-layout .product-table .icon-btn.copy:hover {
            color: #15803d;
            border-color: #bbf7d0;
            background: #f0fdf4;
        }
        body.admin-layout .product-table .icon-btn.delete:hover {
            color: #b91c1c;
            border-color: #fecaca;
            background: #fef2f2;
        }
        .catalog-empty {
            padding: 3.5rem 1rem;
            text-align: center;
            color: #64748b;
        }
        .catalog-empty i {
            color: #fdba74;
            margin-bottom: 0.75rem;
        }
        .catalog-empty h5 {
            color: #1e293b;
            font-weight: 800;
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
            .catalog-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .catalog-toolbar-left,
            .catalog-toolbar-right {
                justify-content: space-between;
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
                            <select name="name_issue" class="form-select" style="max-width: 220px;" title="Name quality" onchange="this.form.submit()">
                                <option value="">All names</option>
                                <option value="placeholder_ar" <?php echo $name_issue === 'placeholder_ar' ? 'selected' : ''; ?>>Placeholder Arabic</option>
                                <option value="long_en" <?php echo $name_issue === 'long_en' ? 'selected' : ''; ?>>Long English title</option>
                            </select>
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
                <form method="post" id="product-catalog-form" onsubmit="return confirm('Delete the selected products?');">
                    <?php if (!empty($bulkMessage)): ?>
                    <div class="catalog-notice"><?= htmlspecialchars($bulkMessage) ?></div>
                    <?php endif; ?>
                    <div class="catalog-toolbar">
                        <div class="catalog-toolbar-left">
                            <label class="select-all-label" for="select-all-products">
                                <input type="checkbox" id="select-all-products" class="catalog-check">
                                <span>Select page</span>
                            </label>
                            <span class="selection-count" id="selection-count">None selected</span>
                        </div>
                        <div class="catalog-toolbar-right">
                            <button class="btn btn-outline-danger btn-sm" id="bulk-delete-btn" name="bulk_delete_products" value="1" type="submit" disabled>
                                <i class="fas fa-trash-alt me-1"></i>Delete selected
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                    <table class="table catalog-grid mb-0">
                        <thead>
                            <tr>
                                <th class="col-check"></th>
                                <th>Product</th>
                                <th class="col-num">Price</th>
                                <th class="col-num">Stock</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="catalog-empty">
                                        <i class="fas fa-box-open fa-3x"></i>
                                        <h5>No products found</h5>
                                        <?php if (!empty($search_query)): ?>
                                        <p>No products found matching <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong></p>
                                        <a href="?<?php echo http_build_query(['category' => $category_filter, 'brand' => $brand_filter]); ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-times me-1"></i>Clear Search
                                        </a>
                                        <?php elseif ($category_filter > 0 || $brand_filter > 0 || $name_issue !== ''): ?>
                                        <p>No products found with the selected filters.</p>
                                        <a href="products.php" class="btn btn-sm btn-primary">
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
                            <?php
                                $stock = (int)($p['stock'] ?? 0);
                                if ($stock <= 0) {
                                    $stock_class = 'stock-out';
                                    $stock_label = 'Out of stock';
                                } elseif ($stock <= 5) {
                                    $stock_class = 'stock-low';
                                    $stock_label = 'Low stock';
                                } else {
                                    $stock_class = 'stock-ok';
                                    $stock_label = 'In stock';
                                }
                                $default_image = 'data:image/svg+xml;base64,' . base64_encode('
                                    <svg width="56" height="56" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="56" height="56" rx="12" fill="#f8fafc"/>
                                        <path d="M18 36l6-7 5 5 4-4 7 8H18z" fill="#fdba74"/>
                                        <circle cx="34" cy="22" r="3" fill="#fdba74"/>
                                    </svg>
                                ');
                                $image_path = $default_image;
                                if (!empty($p['image_path'])) {
                                    $full_image_path = $root_dir . '/public/' . $p['image_path'];
                                    if (file_exists($full_image_path)) {
                                        $image_path = '../' . $p['image_path'];
                                    }
                                }
                                $meta_bits = array_filter([
                                    $p['category_name'] ?? '',
                                    $p['brand_name'] ?? '',
                                ], function ($value) {
                                    return trim((string)$value) !== '' && $value !== 'No Category';
                                });
                            ?>
                            <tr>
                                <td class="col-check">
                                    <input type="checkbox" class="catalog-check" name="selected_products[]" value="<?php echo (int)$p['id']; ?>" aria-label="Select <?php echo htmlspecialchars($p['name_en']); ?>">
                                </td>
                                <td>
                                    <div class="product-cell">
                                        <img src="<?php echo htmlspecialchars($image_path); ?>"
                                             alt=""
                                             class="product-thumb"
                                             onerror="this.src='<?php echo $default_image; ?>'">
                                        <div class="product-copy">
                                            <div class="product-title">
                                                <a href="edit_product.php?id=<?php echo (int)$p['id']; ?>" title="<?php echo htmlspecialchars($p['name_en']); ?>">
                                                    <?php echo htmlspecialchars($p['name_en']); ?>
                                                </a>
                                                <?php if (!empty($p['featured'])): ?>
                                                <i class="fas fa-star featured-mark" title="Featured"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-meta">
                                                <span class="sku"><?php echo htmlspecialchars($p['sku'] ?: 'No SKU'); ?></span>
                                                <?php if ($meta_bits): ?>
                                                <span class="meta-grow"><?php echo htmlspecialchars(implode(' · ', $meta_bits)); ?></span>
                                                <?php endif; ?>
                                                <?php if ((float)($p['weight'] ?? 0) > 0): ?>
                                                <span class="meta-fixed"><?php echo number_format((float)$p['weight'], 2); ?> kg</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (strlen(trim((string)($p['name_en'] ?? ''))) > 80 || is_placeholder_arabic_name($p['name_ar'] ?? '')): ?>
                                            <div class="name-flags">
                                                <?php if (strlen(trim((string)($p['name_en'] ?? ''))) > 80): ?>
                                                <span class="name-flag">Long title</span>
                                                <?php endif; ?>
                                                <?php if (is_placeholder_arabic_name($p['name_ar'] ?? '')): ?>
                                                <span class="name-flag">Arabic placeholder</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="col-num">
                                    <div class="price-cell">
                                        <?php if (product_is_on_sale($p)): ?>
                                        <span class="price-sale">SALE</span>
                                        <span class="price-value"><?php echo number_format(product_effective_price($p), 2); ?></span>
                                        <span class="price-was">AED <?php echo number_format($p['price'], 2); ?></span>
                                        <?php else: ?>
                                        <span class="price-currency">AED</span>
                                        <span class="price-value"><?php echo number_format($p['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-num">
                                    <div class="stock-stack">
                                        <span class="stock-pill <?php echo $stock_class; ?>"><?php echo $stock; ?></span>
                                        <span class="stock-label"><?php echo $stock_label; ?></span>
                                    </div>
                                </td>
                                <td class="col-status">
                                    <span class="status-pill <?php echo $p['status'] ? 'is-on' : 'is-off'; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo $p['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="col-actions">
                                    <div class="row-actions">
                                        <a href="edit_product.php?id=<?php echo (int)$p['id']; ?>"
                                           class="icon-btn edit"
                                           title="Edit product"
                                           aria-label="Edit product">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="products.php?duplicate=<?php echo (int)$p['id']; ?>"
                                           class="icon-btn copy"
                                           title="Duplicate product"
                                           aria-label="Duplicate product"
                                           onclick="return confirm('Create a copy of this product?')">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        <a href="products.php?delete=<?php echo (int)$p['id']; ?>"
                                           class="icon-btn delete"
                                           title="Delete product"
                                           aria-label="Delete product"
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
                </form>
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
        (function () {
            const form = document.getElementById('product-catalog-form');
            if (!form) return;

            const selectAll = document.getElementById('select-all-products');
            const countEl = document.getElementById('selection-count');
            const deleteBtn = document.getElementById('bulk-delete-btn');
            const boxes = () => Array.from(form.querySelectorAll('input[name="selected_products[]"]'));

            function sync() {
                const all = boxes();
                const selected = all.filter((box) => box.checked);
                all.forEach((box) => {
                    const row = box.closest('tr');
                    if (row) row.classList.toggle('is-selected', box.checked);
                });
                if (countEl) {
                    countEl.textContent = selected.length === 0
                        ? 'None selected'
                        : selected.length + (selected.length === 1 ? ' product selected' : ' products selected');
                    countEl.classList.toggle('has-selection', selected.length > 0);
                }
                if (deleteBtn) deleteBtn.disabled = selected.length === 0;
                if (selectAll) {
                    selectAll.checked = all.length > 0 && selected.length === all.length;
                    selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
                    selectAll.disabled = all.length === 0;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    boxes().forEach((box) => { box.checked = selectAll.checked; });
                    sync();
                });
            }

            form.addEventListener('change', (event) => {
                if (event.target.matches('input[name="selected_products[]"]')) sync();
            });

            sync();
        })();
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>