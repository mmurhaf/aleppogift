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

// Fetch all categories for the filter dropdown
$categories = $db->query("SELECT id, name_en, name_ar FROM categories ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all brands for the filter dropdown
$brands = $db->query("SELECT id, name_en, name_ar FROM brands ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .dashboard-header {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
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
        .product-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .product-table th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
        }
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            margin: 0 0.25rem;
        }
        .add-product-btn {
            padding: 0.5rem 1rem;
            font-weight: 600;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .search-container {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Product Management</h1>
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
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group search-container">
                        <input type="text" class="form-control" placeholder="Search products...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <?php 
                            if ($category_filter > 0) {
                                $selected_category = array_filter($categories, function($cat) use ($category_filter) {
                                    return $cat['id'] == $category_filter;
                                });
                                echo !empty($selected_category) ? htmlspecialchars(array_values($selected_category)[0]['name_en']) : 'Filter by Category';
                            } else {
                                echo 'Filter by Category';
                            }
                            ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item <?php echo $category_filter == 0 ? 'active' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])); ?>">All Categories</a></li>
                            <?php foreach ($categories as $category): ?>
                            <li><a class="dropdown-item <?php echo $category_filter == $category['id'] ? 'active' : ''; ?>" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['category' => $category['id'], 'page' => 1])); ?>">
                                <?php echo htmlspecialchars($category['name_en']); ?>
                            </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="btn-group ms-2">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <?php 
                            if ($brand_filter > 0) {
                                $selected_brand = array_filter($brands, function($brand) use ($brand_filter) {
                                    return $brand['id'] == $brand_filter;
                                });
                                echo !empty($selected_brand) ? htmlspecialchars(array_values($selected_brand)[0]['name_en']) : 'Filter by Brand';
                            } else {
                                echo 'Filter by Brand';
                            }
                            ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item <?php echo $brand_filter == 0 ? 'active' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['brand' => '', 'page' => 1])); ?>">All Brands</a></li>
                            <?php foreach ($brands as $brand): ?>
                            <li><a class="dropdown-item <?php echo $brand_filter == $brand['id'] ? 'active' : ''; ?>" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['brand' => $brand['id'], 'page' => 1])); ?>">
                                <?php echo htmlspecialchars($brand['name_en']); ?>
                            </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="btn-group ms-2">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Sort By
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">Newest First</a></li>
                            <li><a class="dropdown-item" href="#">Price: Low to High</a></li>
                            <li><a class="dropdown-item" href="#">Price: High to Low</a></li>
                            <li><a class="dropdown-item" href="#">Most Popular</a></li>
                        </ul>
                    </div>
                    <?php if ($category_filter > 0 || $brand_filter > 0): ?>
                    <a href="?<?php echo http_build_query(array_diff_key($_GET, ['category' => '', 'brand' => ''])); ?>" 
                       class="btn btn-outline-danger btn-sm ms-2" 
                       title="Clear all filters">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <?php endif; ?>
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
                                <th width="80px">Image</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Weight</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-box-open fa-3x mb-3"></i>
                                        <h5>No products found</h5>
                                        <?php if ($category_filter > 0 || $brand_filter > 0): ?>
                                        <p>No products found with the selected filters.</p>
                                        <a href="?<?php echo http_build_query(array_diff_key($_GET, ['category' => '', 'brand' => ''])); ?>" class="btn btn-primary">
                                            <i class="fas fa-arrow-left me-1"></i>View All Products
                                        </a>
                                        <?php else: ?>
                                        <p>Start by adding your first product.</p>
                                        <a href="add_product.php" class="btn btn-primary">
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
                                        // The image_path from database is like: uploads/products/1759429166_0_WhatsApp Image 2025-10-02 at 7.50.05 PM.jpeg
                                        $full_image_path = $root_dir . '/public/' . $p['image_path'];
                                        
                                        if (file_exists($full_image_path)) {
                                            // Convert to relative web path from admin directory
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
                                    <strong><?php echo htmlspecialchars($p['name_en']); ?></strong>
                                </td>
                                <td>
                                    <div class="text-muted small"><?php echo $p['sku']; ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($p['category_name'] ?? 'No Category'); ?>
                                    </span>
                                </td>
                                <td>AED <?php echo number_format($p['price'], 2); ?></td>
                                <td>
                                    <?php 
                                    echo $p['stock']; 
                                    ?>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <?php echo number_format($p['weight'] ?? 0, 3); ?> kg
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $p['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $p['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $p['featured'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $p['featured'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_product.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-primary action-btn" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="products.php?duplicate=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-success action-btn" 
                                       title="Duplicate Product"
                                       onclick="return confirm('This will create a copy of this product. Continue?')">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                    <a href="products.php?delete=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-danger action-btn" 
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this product?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <a href="view_product.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-secondary action-btn" 
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php
                // Build query string for pagination links (preserve filters)
                $query_params = $_GET;
                unset($query_params['page']); // Remove page parameter
                $base_query = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                ?>
                
                <?php if ($current_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo $base_query; ?>" tabindex="-1">Previous</a>
                </li>
                <?php else: ?>
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                </li>
                <?php endif; ?>

                <?php
                // Show page numbers
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                // Show first page if we're not starting from page 1
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1' . $base_query . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                // Show page numbers around current page
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $base_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor;

                // Show last page if we're not ending at the last page
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $base_query . '">' . $total_pages . '</a></li>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo $base_query; ?>">Next</a>
                </li>
                <?php else: ?>
                <li class="page-item disabled">
                    <a class="page-link" href="#">Next</a>
                </li>
                <?php endif; ?>
            </ul>
            
            <!-- Pagination Info -->
            <div class="text-center mt-3">
                <small class="text-muted">
                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $products_per_page, $total_products); ?> 
                    of <?php echo $total_products; ?> products
                    <?php if ($category_filter > 0): ?>
                    in selected category
                    <?php endif; ?>
                    (Page <?php echo $current_page; ?> of <?php echo max(1, $total_pages); ?>)
                </small>
            </div>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirm before delete
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this product?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>