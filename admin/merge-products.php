<?php
/**
 * Bulk Product Merger
 * Dubai Gift - Master Copy Brands E-commerce
 * Merge duplicate products and consolidate their images
 */

$pageTitle = "Merge Products";
include 'header.php';

// XSS protection helper function
function mergeProductsEscape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function renderMergeProductsDiagnostic(Throwable $exception) {
    $traceLines = explode("\n", $exception->getTraceAsString());
    $tracePreview = array_slice($traceLines, 0, 8);
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger mt-4" role="alert">
                    <h4 class="alert-heading mb-3">
                        <i class="fas fa-bug me-2"></i>
                        Temporary Diagnostic Output
                    </h4>
                    <p class="mb-3">The Merge Products page threw an exception. This diagnostic is visible only to authenticated admins and should be removed after the issue is fixed.</p>
                    <div class="mb-2"><strong>Message:</strong> <?php echo mergeProductsEscape($exception->getMessage()); ?></div>
                    <div class="mb-2"><strong>File:</strong> <?php echo mergeProductsEscape($exception->getFile()); ?></div>
                    <div class="mb-3"><strong>Line:</strong> <?php echo (int) $exception->getLine(); ?></div>
                    <details>
                        <summary class="fw-semibold">Stack trace preview</summary>
                        <pre class="mt-3 mb-0 p-3 bg-dark text-light rounded small"><?php echo mergeProductsEscape(implode("\n", $tracePreview)); ?></pre>
                    </details>
                </div>
            </div>
        </div>
    </div>
    <?php
}

$mergeProductsOutputBufferLevel = ob_get_level();
ob_start();

try {

$error = '';
$success = '';
$mergeResult = null;

$productImageColumns = [];
try {
    foreach ($db->fetchAll("SHOW COLUMNS FROM product_images") as $column) {
        if (isset($column['Field'])) {
            $productImageColumns[$column['Field']] = true;
        }
    }
} catch (Exception $e) {
    $productImageColumns = [];
}

$hasMainImageFlag = isset($productImageColumns['is_main']);
$hasDisplayOrder = isset($productImageColumns['display_order']);

// Handle merge operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
    
    if (isset($_POST['action']) && $_POST['action'] === 'merge') {
        $productIds = $_POST['product_ids'] ?? [];
        
        // Validate selection
        if (count($productIds) < 2) {
            $error = "Please select at least 2 products to merge.";
        } else {
            // Convert to integers and sort (lowest ID will be kept as primary)
            $productIds = array_map('intval', $productIds);
            sort($productIds);
            
            $primaryProductId = $productIds[0]; // Lowest ID
            $duplicateIds = array_slice($productIds, 1); // All others
            
            try {
                $db->beginTransaction();
                
                // Get primary product details
                $primaryProduct = $db->fetch("SELECT * FROM products WHERE id = ?", [$primaryProductId]);
                
                if (!$primaryProduct) {
                    throw new Exception("Primary product not found.");
                }
                
                // Get all images from duplicate products
                $duplicateIdsStr = implode(',', $duplicateIds);
                $imagesToMove = $db->fetchAll(
                    "SELECT * FROM product_images WHERE product_id IN ($duplicateIdsStr)"
                );
                
                // Get max display_order for primary product
                $nextOrder = 1;
                if ($hasDisplayOrder) {
                    $maxOrder = $db->fetch(
                        "SELECT MAX(display_order) as max_order FROM product_images WHERE product_id = ?",
                        [$primaryProductId]
                    );
                    $nextOrder = ($maxOrder['max_order'] ?? 0) + 1;
                }
                
                // Move images from duplicate products to primary product
                $movedImagesCount = 0;
                foreach ($imagesToMove as $image) {
                    $updateFields = ['product_id = ?'];
                    $updateParams = [$primaryProductId];

                    if ($hasDisplayOrder) {
                        $updateFields[] = 'display_order = ?';
                        $updateParams[] = $nextOrder;
                    }

                    if ($hasMainImageFlag) {
                        $updateFields[] = 'is_main = 0';
                    }

                    $updateParams[] = $image['id'];

                    $db->query(
                        "UPDATE product_images SET " . implode(', ', $updateFields) . " WHERE id = ?",
                        $updateParams
                    );
                    
                    $movedImagesCount++;
                    if ($hasDisplayOrder) {
                        $nextOrder++;
                    }
                }
                
                // Delete duplicate products
                $deletedCount = 0;
                foreach ($duplicateIds as $duplicateId) {
                    $db->query("DELETE FROM products WHERE id = ?", [$duplicateId]);
                    $deletedCount++;
                }
                
                $db->commit();
                
                $mergeResult = [
                    'primary_id' => $primaryProductId,
                    'primary_name' => $primaryProduct['name_en'],
                    'images_moved' => $movedImagesCount,
                    'products_deleted' => $deletedCount,
                    'total_merged' => count($productIds)
                ];
                
                $success = "Successfully merged {$mergeResult['total_merged']} products! Moved {$movedImagesCount} images to product #{$primaryProductId}.";
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Failed to merge products: " . $e->getMessage();
            }
        }
    }
}

// Get all products with their images for display
$searchTerm     = trim($_GET['search'] ?? '');
$filterCategory = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : '';
$filterBrand    = isset($_GET['brand'])    && $_GET['brand']    !== '' ? $_GET['brand']    : '';

$whereClauses = [];
$queryParams  = [];

if ($searchTerm !== '') {
    $whereClauses[] = '(p.id = ? OR p.name_en LIKE ? OR p.sku LIKE ?)';
    $queryParams[]  = is_numeric($searchTerm) ? (int)$searchTerm : 0;
    $queryParams[]  = '%' . $searchTerm . '%';
    $queryParams[]  = '%' . $searchTerm . '%';
}
if ($filterCategory === 'null') {
    $whereClauses[] = 'p.category_id IS NULL';
} elseif ($filterCategory !== '') {
    $whereClauses[] = 'p.category_id = ?';
    $queryParams[]  = (int)$filterCategory;
}
if ($filterBrand === 'null') {
    $whereClauses[] = 'p.brand_id IS NULL';
} elseif ($filterBrand !== '') {
    $whereClauses[] = 'p.brand_id = ?';
    $queryParams[]  = (int)$filterBrand;
}

$whereSQL = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$productsQuery = "
    SELECT 
        p.*,
        c.name_en as category_name,
        b.name_en as brand_name,
        COUNT(pi.id) as image_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN product_images pi ON p.id = pi.product_id
    $whereSQL
    GROUP BY p.id
    ORDER BY p.created_at DESC, p.id ASC
    LIMIT 500
";

$products = $db->fetchAll($productsQuery, $queryParams);
$totalProductsCount = $db->fetch("SELECT COUNT(*) as cnt FROM products")['cnt'] ?? 0;

// Get images for each product
$productImages = [];
foreach ($products as $product) {
    $imageOrderBy = [];
    if ($hasMainImageFlag) {
        $imageOrderBy[] = 'is_main DESC';
    }
    if ($hasDisplayOrder) {
        $imageOrderBy[] = 'display_order ASC';
    }
    $imageOrderBy[] = 'id ASC';

    $images = $db->fetchAll(
        "SELECT * FROM product_images WHERE product_id = ? ORDER BY " . implode(', ', $imageOrderBy),
        [$product['id']]
    );
    $productImages[$product['id']] = $images;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-object-group me-2"></i>
                        Merge Duplicate Products
                    </h5>
                    <div>
                        <a href="products.php" class="btn btn-light btn-sm me-2">
                            <i class="fas fa-list me-1"></i> All Products
                        </a>
                        <a href="bulk-upload-images.php#productsMenu" class="btn btn-primary btn-sm">
                            <i class="fas fa-cloud-upload-alt me-1"></i> Bulk Upload
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    
                    <!-- Instructions -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i>How to Merge Products:
                        </h6>
                        <ol class="mb-0">
                            <li>Review products below and identify duplicates (same product with multiple images)</li>
                            <li>Check the boxes for products you want to merge together</li>
                            <li>The product with the <strong>lowest ID (oldest)</strong> will be kept as the primary product</li>
                            <li>All images from other products will be moved to the primary product</li>
                            <li>Duplicate product records will be permanently deleted</li>
                            <li>You can then edit the primary product to add proper name, category, brand, etc.</li>
                        </ol>
                    </div>

                    <!-- Success Message -->
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <h6 class="alert-heading">
                            <i class="fas fa-check-circle me-2"></i>Merge Successful!
                        </h6>
                        <p class="mb-2"><?php echo $success; ?></p>
                        <?php if ($mergeResult): ?>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Primary Product:</strong> #<?php echo $mergeResult['primary_id']; ?> - <?php echo mergeProductsEscape($mergeResult['primary_name']); ?>
                            </div>
                            <div class="col-md-6">
                                <a href="edit_product.php?id=<?php echo $mergeResult['primary_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit Merged Product
                                </a>
                                <a href="../product.php?id=<?php echo $mergeResult['primary_id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-eye"></i> View on Site
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Error Message -->
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Server-side Search Form -->
                    <form method="GET" id="searchForm" class="mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="search" id="searchFilter"
                                       value="<?php echo mergeProductsEscape($searchTerm); ?>"
                                       placeholder="Search by ID, Name, or SKU...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" name="category" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <option value="null" <?php echo $filterCategory === 'null' ? 'selected' : ''; ?>>No Category Set</option>
                                    <?php
                                    $categories = $db->fetchAll("SELECT * FROM categories ORDER BY name_en");
                                    foreach ($categories as $cat):
                                    ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>><?php echo mergeProductsEscape($cat['name_en']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" name="brand" id="brandFilter">
                                    <option value="">All Brands</option>
                                    <option value="null" <?php echo $filterBrand === 'null' ? 'selected' : ''; ?>>No Brand Set</option>
                                    <?php
                                    $brands = $db->fetchAll("SELECT * FROM brands ORDER BY name_en");
                                    foreach ($brands as $brand):
                                    ?>
                                    <option value="<?php echo $brand['id']; ?>" <?php echo $filterBrand == $brand['id'] ? 'selected' : ''; ?>><?php echo mergeProductsEscape($brand['name_en']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <?php if ($searchTerm !== '' || $filterCategory !== '' || $filterBrand !== ''): ?>
                        <div class="mt-2">
                            <a href="merge-products.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            <span class="ms-2 text-muted small">Showing <?php echo count($products); ?> of <?php echo $totalProductsCount; ?> total products</span>
                        </div>
                        <?php else: ?>
                        <div class="mt-2">
                            <span class="text-muted small">Showing <?php echo count($products); ?> of <?php echo $totalProductsCount; ?> total products (up to 500 at a time — use search to find others)</span>
                        </div>
                        <?php endif; ?>
                    </form>

                    <!-- Merge Form -->
                    <form method="POST" id="mergeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="merge">

                        <!-- Quick Actions -->
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                    <i class="fas fa-check-square"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                                    <i class="fas fa-square"></i> Deselect All
                                </button>
                                <span class="ms-3 text-muted" id="selectedCount">0 products selected</span>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-warning" id="mergeButton" disabled>
                                    <i class="fas fa-object-group me-1"></i> Merge Selected Products
                                </button>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="row g-3" id="productsGrid">
                            <?php foreach ($products as $product): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 product-item" 
                                 data-id="<?php echo $product['id']; ?>"
                                 data-name="<?php echo mergeProductsEscape($product['name_en']); ?>"
                                 data-sku="<?php echo mergeProductsEscape($product['sku']); ?>"
                                 data-category="<?php echo $product['category_id'] ?? 'null'; ?>"
                                 data-brand="<?php echo $product['brand_id'] ?? 'null'; ?>">
                                <div class="card product-merge-card h-100">
                                    <!-- Selection Checkbox -->
                                    <div class="card-header bg-light p-2">
                                        <div class="form-check">
                                            <input class="form-check-input product-checkbox" 
                                                   type="checkbox" 
                                                   name="product_ids[]" 
                                                   value="<?php echo $product['id']; ?>" 
                                                   id="product_<?php echo $product['id']; ?>"
                                                   onchange="updateSelection()">
                                            <label class="form-check-label fw-bold" for="product_<?php echo $product['id']; ?>">
                                                ID: <?php echo $product['id']; ?>
                                                <?php if ($product['image_count'] > 1): ?>
                                                <span class="badge bg-info"><?php echo $product['image_count']; ?> images</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Images -->
                                    <div class="card-body p-2">
                                        <div class="product-images-grid mb-2">
                                            <?php 
                                            $images = $productImages[$product['id']] ?? [];
                                            $displayLimit = 4;
                                            $imageCount = count($images);
                                            
                                            if ($imageCount > 0):
                                                $displayImages = array_slice($images, 0, $displayLimit);
                                                foreach ($displayImages as $idx => $image): 
                                                    $isMainImage = !empty($image['is_main']);
                                            ?>
                                            <div class="product-thumb-wrapper">
                                                <img src="../<?php echo mergeProductsEscape($image['image_path']); ?>" 
                                                     alt="Product image" 
                                                     class="product-thumb <?php echo $isMainImage ? 'main-image' : ''; ?>"
                                                     data-bs-toggle="tooltip"
                                                     title="<?php echo $isMainImage ? 'Main Image' : 'Image ' . ($idx + 1); ?>">
                                                <?php if ($isMainImage): ?>
                                                <span class="badge bg-primary main-badge">Main</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php 
                                                endforeach;
                                                
                                                if ($imageCount > $displayLimit):
                                            ?>
                                            <div class="product-thumb-wrapper more-images">
                                                <div class="more-indicator">
                                                    +<?php echo ($imageCount - $displayLimit); ?> more
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php else: ?>
                                            <div class="text-center text-muted p-3">
                                                <i class="fas fa-image fa-2x"></i>
                                                <p class="mb-0 small">No images</p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Product Info -->
                                        <div class="product-info-compact">
                                            <h6 class="mb-1 text-truncate" title="<?php echo mergeProductsEscape($product['name_en']); ?>">
                                                <?php echo mergeProductsEscape($product['name_en']); ?>
                                            </h6>
                                            <p class="mb-1 small text-muted">
                                                <strong>SKU:</strong> <?php echo mergeProductsEscape($product['sku']); ?>
                                            </p>
                                            <p class="mb-1 small">
                                                <strong>Price:</strong> 
                                                <span class="text-success">AED <?php echo number_format($product['price'], 2); ?></span>
                                            </p>
                                            <div class="mb-1">
                                                <?php if (empty($product['category_id'])): ?>
                                                <span class="badge bg-danger">No Category</span>
                                                <?php else: ?>
                                                <span class="badge bg-success"><?php echo mergeProductsEscape($product['category_name']); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if (empty($product['brand_id'])): ?>
                                                <span class="badge bg-danger">No Brand</span>
                                                <?php else: ?>
                                                <span class="badge bg-success"><?php echo mergeProductsEscape($product['brand_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0 small text-muted">
                                                <i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <div class="card-footer p-2 bg-light">
                                        <div class="btn-group btn-group-sm w-100" role="group">
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-outline-info" 
                                               target="_blank"
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- No Results Message -->
                        <div id="noResults" class="text-center py-5" style="display: none;">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No products found matching your filters</h5>
                            <a href="merge-products.php" class="btn btn-primary mt-3">
                                Clear Filters
                            </a>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
.product-merge-card {
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.product-merge-card:has(.product-checkbox:checked) {
    border-color: #ffc107;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
    background-color: #fffbf0;
}

.product-images-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 5px;
    min-height: 120px;
}

.product-thumb-wrapper {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.product-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
}

.product-thumb:hover {
    transform: scale(1.1);
}

.product-thumb.main-image {
    border: 2px solid #0d6efd;
}

.main-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 0.65rem;
}

.more-images {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
}

.more-indicator {
    text-align: center;
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: bold;
}

.product-info-compact h6 {
    font-size: 0.9rem;
    line-height: 1.2;
}

.product-info-compact p {
    margin-bottom: 0.25rem;
}

.product-info-compact .badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
}

@media (max-width: 767px) {
    .product-images-grid {
        grid-template-columns: repeat(2, 1fr);
        min-height: 100px;
    }
}
</style>

<script>
// Update selection count and button state
function updateSelection() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count + ' products selected';
    document.getElementById('mergeButton').disabled = count < 2;
    
    if (count >= 2) {
        // Show which product will be primary (lowest ID)
        const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
        const primaryId = Math.min(...ids);
        document.getElementById('mergeButton').innerHTML = 
            `<i class="fas fa-object-group me-1"></i> Merge into Product #${primaryId}`;
    } else {
        document.getElementById('mergeButton').innerHTML = 
            '<i class="fas fa-object-group me-1"></i> Merge Selected Products';
    }
}

// Select all visible products
function selectAll() {
    document.querySelectorAll('.product-item:not([style*="display: none"]) .product-checkbox').forEach(cb => {
        cb.checked = true;
    });
    updateSelection();
}

// Deselect all products
function deselectAll() {
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateSelection();
}

// Confirm before merge
document.getElementById('mergeForm').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const count = checkboxes.length;
    
    if (count < 2) {
        e.preventDefault();
        alert('Please select at least 2 products to merge.');
        return;
    }
    
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
    const primaryId = Math.min(...ids);
    const duplicateIds = ids.filter(id => id !== primaryId);
    
    const message = `You are about to merge ${count} products:\n\n` +
                   `PRIMARY (Keep): Product #${primaryId}\n` +
                   `DELETE: Products #${duplicateIds.join(', #')}\n\n` +
                   `All images from deleted products will be moved to Product #${primaryId}.\n` +
                   `This action cannot be undone!\n\n` +
                   `Continue?`;
    
    if (!confirm(message)) {
        e.preventDefault();
    }
});

// Submit search form when dropdowns change
document.getElementById('categoryFilter').addEventListener('change', function() {
    document.getElementById('searchForm').submit();
});
document.getElementById('brandFilter').addEventListener('change', function() {
    document.getElementById('searchForm').submit();
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
    $mergeProductsPageHtml = ob_get_clean();
    echo $mergeProductsPageHtml;
} catch (Throwable $exception) {
    while (ob_get_level() > $mergeProductsOutputBufferLevel) {
        ob_end_clean();
    }

    error_log(sprintf(
        'merge-products diagnostic: %s in %s on line %d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    renderMergeProductsDiagnostic($exception);
}
?>

<?php include 'includes/footer.php'; ?>
