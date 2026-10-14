<?php
declare(strict_types=1);

/**
 * Merge duplicate products into the oldest selected product and move images over.
 */

// ── EARLY FATAL CATCHER ─────────────────────────────────────────────────────
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
        $msg = 'MERGE-PRODUCTS SHUTDOWN FATAL: type=' . $e['type']
            . ' msg=' . $e['message']
            . ' file=' . $e['file']
            . ' line=' . $e['line'];
        error_log($msg);
        // Output as HTML comment so we can see it in page source even if headers sent
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo "\n<!-- FATAL_DEBUG: " . htmlspecialchars(json_encode($e), ENT_QUOTES) . " -->\n";
    }
});
// ────────────────────────────────────────────────────────────────────────────

$pageTitle = 'Merge Products';
include 'header.php';

function mergeProductsEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mergeProductsRenderDiagnostic(Throwable $exception): void
{
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

function mergeProductsDetectImageSchema(Database $db): array
{
    $flags = [
        'has_main_image_flag' => false,
        'has_display_order' => false,
    ];

    try {
        $columns = $db->fetchAll('SHOW COLUMNS FROM product_images');
        foreach ($columns as $column) {
            $field = $column['Field'] ?? '';
            if ($field === 'is_main') {
                $flags['has_main_image_flag'] = true;
            }
            if ($field === 'display_order') {
                $flags['has_display_order'] = true;
            }
        }
    } catch (Throwable $exception) {
        error_log('merge-products schema detection failed: ' . $exception->getMessage());
    }

    return $flags;
}

function mergeProductsLoadFilterOptions(Database $db, string $table): array
{
    $availableNameColumns = [];

    foreach (['name_en', 'name_ar', 'name'] as $column) {
        try {
            $exists = $db->fetch(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            if ($exists) {
                $availableNameColumns[] = $column;
            }
        } catch (Throwable $exception) {
            error_log(sprintf('merge-products option lookup failed for %s.%s: %s', $table, $column, $exception->getMessage()));
        }
    }

    if ($availableNameColumns === []) {
        return [];
    }

    $nameExpressions = [];
    foreach ($availableNameColumns as $column) {
        $nameExpressions[] = "NULLIF(TRIM({$column}), '')";
    }

    $displayNameExpression = 'COALESCE(' . implode(', ', $nameExpressions) . ", CONCAT('ID #', id))";

    try {
        return $db->fetchAll("SELECT id, {$displayNameExpression} AS display_name FROM {$table} ORDER BY display_name ASC");
    } catch (Throwable $exception) {
        error_log(sprintf('merge-products failed loading %s filter options: %s', $table, $exception->getMessage()));
        return [];
    }
}

function mergeProductsBuildPlaceholders(int $count): string
{
    return implode(', ', array_fill(0, $count, '?'));
}

function mergeProductsNormalizeSelectedIds($rawIds): array
{
    $ids = is_array($rawIds) ? $rawIds : [];
    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique(array_filter($ids, static function (int $id): bool {
        return $id > 0;
    })));
    sort($ids);

    return $ids;
}

function mergeProductsPerformMerge(Database $db, array $selectedIds, array $schemaFlags): array
{
    if (count($selectedIds) < 2) {
        throw new InvalidArgumentException('Please select at least 2 products to merge.');
    }

    $primaryProductId = $selectedIds[0];
    $duplicateIds = array_slice($selectedIds, 1);
    $primaryProduct = $db->fetch('SELECT * FROM products WHERE id = ?', [$primaryProductId]);

    if (!$primaryProduct) {
        throw new RuntimeException('Primary product not found.');
    }

    $placeholders = mergeProductsBuildPlaceholders(count($duplicateIds));
    $imagesToMove = $db->fetchAll(
        "SELECT * FROM product_images WHERE product_id IN ({$placeholders}) ORDER BY id ASC",
        $duplicateIds
    );

    $nextDisplayOrder = 1;
    if ($schemaFlags['has_display_order']) {
        $maxOrder = $db->fetch(
            'SELECT MAX(display_order) AS max_order FROM product_images WHERE product_id = ?',
            [$primaryProductId]
        );
        $nextDisplayOrder = ((int) ($maxOrder['max_order'] ?? 0)) + 1;
    }

    $db->beginTransaction();

    try {
        $movedImagesCount = 0;

        foreach ($imagesToMove as $image) {
            $setClauses = ['product_id = ?'];
            $params = [$primaryProductId];

            if ($schemaFlags['has_display_order']) {
                $setClauses[] = 'display_order = ?';
                $params[] = $nextDisplayOrder;
                $nextDisplayOrder++;
            }

            if ($schemaFlags['has_main_image_flag']) {
                $setClauses[] = 'is_main = 0';
            }

            $params[] = (int) $image['id'];

            $db->query(
                'UPDATE product_images SET ' . implode(', ', $setClauses) . ' WHERE id = ?',
                $params
            );
            $movedImagesCount++;
        }

        $deletedCount = 0;
        foreach ($duplicateIds as $duplicateId) {
            $db->query('DELETE FROM products WHERE id = ?', [$duplicateId]);
            $deletedCount++;
        }

        $db->commit();

        return [
            'primary_id' => $primaryProductId,
            'primary_name' => (string) ($primaryProduct['name_en'] ?? $primaryProduct['name_ar'] ?? 'Unnamed product'),
            'images_moved' => $movedImagesCount,
            'products_deleted' => $deletedCount,
            'total_merged' => count($selectedIds),
        ];
    } catch (Throwable $exception) {
        if ($db->getConnection() instanceof PDO && $db->getConnection()->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function mergeProductsBuildProductQueryFilters(array $input): array
{
    $searchTerm = trim((string) ($input['search'] ?? ''));
    $filterCategory = isset($input['category']) ? (string) $input['category'] : '';
    $filterBrand = isset($input['brand']) ? (string) $input['brand'] : '';

    $whereClauses = [];
    $params = [];

    if ($searchTerm !== '') {
        $whereClauses[] = '(p.id = ? OR p.name_en LIKE ? OR p.sku LIKE ?)';
        $params[] = ctype_digit($searchTerm) ? (int) $searchTerm : 0;
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
    }

    if ($filterCategory === 'null') {
        $whereClauses[] = 'p.category_id IS NULL';
    } elseif ($filterCategory !== '') {
        $whereClauses[] = 'p.category_id = ?';
        $params[] = (int) $filterCategory;
    }

    if ($filterBrand === 'null') {
        $whereClauses[] = 'p.brand_id IS NULL';
    } elseif ($filterBrand !== '') {
        $whereClauses[] = 'p.brand_id = ?';
        $params[] = (int) $filterBrand;
    }

    return [
        'search_term' => $searchTerm,
        'filter_category' => $filterCategory,
        'filter_brand' => $filterBrand,
        'where_sql' => $whereClauses === [] ? '' : 'WHERE ' . implode(' AND ', $whereClauses),
        'params' => $params,
    ];
}

function mergeProductsFetchProducts(Database $db, string $whereSql, array $params): array
{
    $sql = "
        SELECT
            p.*,
            c.name_en AS category_name,
            b.name_en AS brand_name,
            COUNT(pi.id) AS image_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_images pi ON p.id = pi.product_id
        {$whereSql}
        GROUP BY p.id
        ORDER BY p.created_at DESC, p.id ASC
        LIMIT 500
    ";

    return $db->fetchAll($sql, $params);
}

function mergeProductsFetchImagesByProductIds(Database $db, array $productIds, array $schemaFlags): array
{
    if ($productIds === []) {
        return [];
    }

    $orderBy = [];
    if ($schemaFlags['has_main_image_flag']) {
        $orderBy[] = 'is_main DESC';
    }
    if ($schemaFlags['has_display_order']) {
        $orderBy[] = 'display_order ASC';
    }
    $orderBy[] = 'id ASC';

    $images = $db->fetchAll(
        'SELECT * FROM product_images WHERE product_id IN (' . mergeProductsBuildPlaceholders(count($productIds)) . ') ORDER BY product_id ASC, ' . implode(', ', $orderBy),
        $productIds
    );

    $groupedImages = [];
    foreach ($images as $image) {
        $productId = (int) ($image['product_id'] ?? 0);
        if (!isset($groupedImages[$productId])) {
            $groupedImages[$productId] = [];
        }
        $groupedImages[$productId][] = $image;
    }

    return $groupedImages;
}

$bufferLevel = ob_get_level();
ob_start();

try {
    // ── DEBUG: step-by-step trace ────────────────────────────────────────────
    $debugSteps   = [];
    $debugErrors  = [];
    $debugEnabled = true; // set to false to hide the panel

    $dbgStep = function (string $label, $detail = null) use (&$debugSteps): void {
        $debugSteps[] = [
            'label'  => $label,
            'detail' => $detail,
            'time'   => microtime(true),
        ];
    };
    $dbgErr = function (string $label, Throwable $e) use (&$debugErrors): void {
        $debugErrors[] = [
            'label'   => $label,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];
    };
    // ────────────────────────────────────────────────────────────────────────

    $error = '';
    $success = '';
    $mergeResult = null;

    $dbgStep('1. Page init', ['REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'], 'GET' => $_GET, 'POST_keys' => array_keys($_POST)]);

    // DB connection check
    try {
        $dbgStep('2. DB connection test');
        $dbPing = $db->fetch('SELECT 1 AS ok');
        $dbgStep('2. DB connection OK', $dbPing);
    } catch (Throwable $e) {
        $dbgErr('2. DB connection FAILED', $e);
        throw $e;
    }

    // Schema detection
    try {
        $dbgStep('3. Detecting image schema');
        $schemaFlags = mergeProductsDetectImageSchema($db);
        $dbgStep('3. Schema flags', $schemaFlags);
    } catch (Throwable $e) {
        $dbgErr('3. Schema detection FAILED', $e);
        throw $e;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dbgStep('4. POST received', ['action' => $_POST['action'] ?? null, 'product_ids_count' => count($_POST['product_ids'] ?? [])]);

        if (!isset($_POST['csrf_token']) || !verifyCsrfToken((string) $_POST['csrf_token'])) {
            $error = 'Your session token is invalid or expired. Refresh the page and try again.';
            $dbgStep('4a. CSRF check FAILED');
        } elseif (($_POST['action'] ?? '') === 'merge') {
            $dbgStep('4b. CSRF OK, starting merge');
            try {
                $selectedIds = mergeProductsNormalizeSelectedIds($_POST['product_ids'] ?? []);
                $dbgStep('4c. Normalized IDs', $selectedIds);

                $mergeResult = mergeProductsPerformMerge($db, $selectedIds, $schemaFlags);
                $dbgStep('4d. Merge complete', $mergeResult);

                $success = sprintf(
                    'Successfully merged %d products. Moved %d images into product #%d.',
                    $mergeResult['total_merged'],
                    $mergeResult['images_moved'],
                    $mergeResult['primary_id']
                );
            } catch (Throwable $exception) {
                $dbgErr('4. Merge FAILED', $exception);
                $error = 'Failed to merge products: ' . $exception->getMessage();
            }
        }
    } else {
        $dbgStep('4. GET request (no POST)');
    }

    // Filters
    try {
        $dbgStep('5. Building filters');
        $filters = mergeProductsBuildProductQueryFilters($_GET);
        $dbgStep('5. Filters built', ['where_sql' => $filters['where_sql'], 'params' => $filters['params']]);
    } catch (Throwable $e) {
        $dbgErr('5. Filter build FAILED', $e);
        throw $e;
    }

    // Categories
    try {
        $dbgStep('6. Loading category options');
        $categories = mergeProductsLoadFilterOptions($db, 'categories');
        $dbgStep('6. Categories loaded', count($categories) . ' rows');
    } catch (Throwable $e) {
        $dbgErr('6. Categories FAILED', $e);
        throw $e;
    }

    // Brands
    try {
        $dbgStep('7. Loading brand options');
        $brands = mergeProductsLoadFilterOptions($db, 'brands');
        $dbgStep('7. Brands loaded', count($brands) . ' rows');
    } catch (Throwable $e) {
        $dbgErr('7. Brands FAILED', $e);
        throw $e;
    }

    // Products
    try {
        $dbgStep('8. Fetching products');
        $products = mergeProductsFetchProducts($db, $filters['where_sql'], $filters['params']);
        $dbgStep('8. Products fetched', count($products) . ' rows');
    } catch (Throwable $e) {
        $dbgErr('8. Fetch products FAILED', $e);
        throw $e;
    }

    // Total count
    try {
        $dbgStep('9. Total product count');
        $totalProductsCount = (int) ($db->fetch('SELECT COUNT(*) AS cnt FROM products')['cnt'] ?? 0);
        $dbgStep('9. Total products', $totalProductsCount);
    } catch (Throwable $e) {
        $dbgErr('9. Total count FAILED', $e);
        throw $e;
    }

    // Product images
    try {
        $dbgStep('10. Fetching product images');
        $productImages = mergeProductsFetchImagesByProductIds(
            $db,
            array_map(static function (array $product): int {
                return (int) $product['id'];
            }, $products),
            $schemaFlags
        );
        $dbgStep('10. Images fetched', count($productImages) . ' product image groups');
    } catch (Throwable $e) {
        $dbgErr('10. Fetch images FAILED', $e);
        throw $e;
    }

    $dbgStep('11. All data loaded — rendering HTML');
    ?>

    <?php if ($debugEnabled): ?>
    <div class="container-fluid mt-3">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-bug me-2"></i>DEBUG — Step-by-step trace</strong>
                <button class="btn btn-sm btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#debugPanel">Toggle</button>
            </div>
            <div class="collapse show" id="debugPanel">
                <div class="card-body p-2" style="font-size:0.8rem;font-family:monospace;">
                    <?php if ($debugErrors !== []): ?>
                    <div class="alert alert-danger mb-2 p-2">
                        <strong>ERRORS CAUGHT:</strong>
                        <?php foreach ($debugErrors as $err): ?>
                        <div class="mt-1">
                            <span class="badge bg-danger"><?php echo htmlspecialchars($err['label']); ?></span>
                            <?php echo htmlspecialchars($err['message']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars(basename($err['file'])); ?>:<?php echo (int)$err['line']; ?>)</small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <table class="table table-sm table-bordered mb-0" style="font-size:0.78rem;">
                        <thead class="table-dark"><tr><th>#</th><th>Step</th><th>Detail</th><th>ms</th></tr></thead>
                        <tbody>
                        <?php $t0 = $debugSteps[0]['time'] ?? microtime(true); ?>
                        <?php foreach ($debugSteps as $i => $step): ?>
                        <tr class="<?php echo str_contains(strtolower($step['label']), 'fail') ? 'table-danger' : ($i % 2 === 0 ? '' : 'table-light'); ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($step['label']); ?></td>
                            <td><pre class="mb-0" style="white-space:pre-wrap;max-height:80px;overflow:auto;"><?php
                                if ($step['detail'] !== null) {
                                    echo htmlspecialchars(is_string($step['detail']) ? $step['detail'] : json_encode($step['detail'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                }
                            ?></pre></td>
                            <td><?php echo number_format(($step['time'] - $t0) * 1000, 1); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>How to Merge Products:
                            </h6>
                            <ol class="mb-0">
                                <li>Review the products below and identify duplicates.</li>
                                <li>Select at least two products that should be merged.</li>
                                <li>The product with the lowest ID is kept as the primary product.</li>
                                <li>Images from the other selected products are reassigned to the primary product.</li>
                                <li>The duplicate product records are permanently deleted.</li>
                                <li>Edit the primary product afterward to finalize names, category, and brand data if needed.</li>
                            </ol>
                        </div>

                        <?php if ($success !== ''): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <h6 class="alert-heading">
                                <i class="fas fa-check-circle me-2"></i>Merge Successful
                            </h6>
                            <p class="mb-2"><?php echo mergeProductsEscape($success); ?></p>
                            <?php if ($mergeResult !== null): ?>
                            <hr>
                            <div class="row align-items-center g-2">
                                <div class="col-md-6">
                                    <strong>Primary Product:</strong>
                                    #<?php echo (int) $mergeResult['primary_id']; ?> - <?php echo mergeProductsEscape($mergeResult['primary_name']); ?>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <a href="edit_product.php?id=<?php echo (int) $mergeResult['primary_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit me-1"></i>Edit Merged Product
                                    </a>
                                    <a href="../product.php?id=<?php echo (int) $mergeResult['primary_id']; ?>" class="btn btn-sm btn-info" target="_blank" rel="noopener">
                                        <i class="fas fa-eye me-1"></i>View on Site
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo mergeProductsEscape($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form method="get" id="searchForm" class="mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        name="search"
                                        id="searchFilter"
                                        value="<?php echo mergeProductsEscape($filters['search_term']); ?>"
                                        placeholder="Search by ID, name, or SKU..."
                                    >
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="category" id="categoryFilter">
                                        <option value="">All Categories</option>
                                        <option value="null" <?php echo $filters['filter_category'] === 'null' ? 'selected' : ''; ?>>No Category Set</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int) $category['id']; ?>" <?php echo $filters['filter_category'] === (string) $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo mergeProductsEscape((string) $category['display_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="brand" id="brandFilter">
                                        <option value="">All Brands</option>
                                        <option value="null" <?php echo $filters['filter_brand'] === 'null' ? 'selected' : ''; ?>>No Brand Set</option>
                                        <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo (int) $brand['id']; ?>" <?php echo $filters['filter_brand'] === (string) $brand['id'] ? 'selected' : ''; ?>>
                                            <?php echo mergeProductsEscape((string) $brand['display_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2">
                                <?php if ($filters['search_term'] !== '' || $filters['filter_category'] !== '' || $filters['filter_brand'] !== ''): ?>
                                <a href="merge-products.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear Filters
                                </a>
                                <?php endif; ?>
                                <span class="ms-2 text-muted small">
                                    Showing <?php echo count($products); ?> of <?php echo $totalProductsCount; ?> total products<?php echo count($products) >= 500 ? ' (limited to 500 results)' : ''; ?>
                                </span>
                            </div>
                        </form>

                        <form method="post" id="mergeForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="merge">

                            <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-select-all>
                                        <i class="fas fa-check-square me-1"></i>Select All
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-deselect-all>
                                        <i class="fas fa-square me-1"></i>Deselect All
                                    </button>
                                    <span class="ms-3 text-muted" id="selectedCount">0 products selected</span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-warning" id="mergeButton" disabled>
                                        <i class="fas fa-object-group me-1"></i>Merge Selected Products
                                    </button>
                                </div>
                            </div>

                            <div class="row g-3" id="productsGrid">
                                <?php foreach ($products as $product): ?>
                                <?php
                                $productId = (int) $product['id'];
                                $images = $productImages[$productId] ?? [];
                                $displayImages = array_slice($images, 0, 4);
                                ?>
                                <div class="col-lg-3 col-md-4 col-sm-6 product-item">
                                    <div class="card product-merge-card h-100">
                                        <div class="card-header bg-light p-2">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input product-checkbox"
                                                    type="checkbox"
                                                    name="product_ids[]"
                                                    value="<?php echo $productId; ?>"
                                                    id="product_<?php echo $productId; ?>"
                                                >
                                                <label class="form-check-label fw-bold" for="product_<?php echo $productId; ?>">
                                                    ID: <?php echo $productId; ?>
                                                    <?php if ((int) $product['image_count'] > 1): ?>
                                                    <span class="badge bg-info"><?php echo (int) $product['image_count']; ?> images</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="card-body p-2">
                                            <div class="product-images-grid mb-2">
                                                <?php if ($images !== []): ?>
                                                    <?php foreach ($displayImages as $index => $image): ?>
                                                    <?php $isMainImage = $schemaFlags['has_main_image_flag'] && !empty($image['is_main']); ?>
                                                    <div class="product-thumb-wrapper">
                                                        <img
                                                            src="../<?php echo mergeProductsEscape((string) $image['image_path']); ?>"
                                                            alt="Product image"
                                                            class="product-thumb <?php echo $isMainImage ? 'main-image' : ''; ?>"
                                                            data-bs-toggle="tooltip"
                                                            title="<?php echo $isMainImage ? 'Main Image' : 'Image ' . ($index + 1); ?>"
                                                        >
                                                        <?php if ($isMainImage): ?>
                                                        <span class="badge bg-primary main-badge">Main</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>

                                                    <?php if (count($images) > count($displayImages)): ?>
                                                    <div class="product-thumb-wrapper more-images">
                                                        <div class="more-indicator">+<?php echo count($images) - count($displayImages); ?> more</div>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                <div class="text-center text-muted p-3">
                                                    <i class="fas fa-image fa-2x"></i>
                                                    <p class="mb-0 small">No images</p>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="product-info-compact">
                                                <h6 class="mb-1 text-truncate" title="<?php echo mergeProductsEscape((string) ($product['name_en'] ?? '')); ?>">
                                                    <?php echo mergeProductsEscape((string) ($product['name_en'] ?? '')); ?>
                                                </h6>
                                                <p class="mb-1 small text-muted">
                                                    <strong>SKU:</strong> <?php echo mergeProductsEscape((string) ($product['sku'] ?? '')); ?>
                                                </p>
                                                <p class="mb-1 small">
                                                    <strong>Price:</strong>
                                                    <span class="text-success">AED <?php echo number_format((float) ($product['price'] ?? 0), 2); ?></span>
                                                </p>
                                                <div class="mb-1 d-flex flex-wrap gap-1">
                                                    <?php if (empty($product['category_id'])): ?>
                                                    <span class="badge bg-danger">No Category</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-success"><?php echo mergeProductsEscape((string) ($product['category_name'] ?? '')); ?></span>
                                                    <?php endif; ?>

                                                    <?php if (empty($product['brand_id'])): ?>
                                                    <span class="badge bg-danger">No Brand</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-success"><?php echo mergeProductsEscape((string) ($product['brand_name'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mb-0 small text-muted">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo !empty($product['created_at']) ? date('M d, Y', strtotime((string) $product['created_at'])) : 'Unknown date'; ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="card-footer p-2 bg-light">
                                            <div class="btn-group btn-group-sm w-100" role="group">
                                                <a href="edit_product.php?id=<?php echo $productId; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="../product.php?id=<?php echo $productId; ?>" class="btn btn-outline-info" target="_blank" rel="noopener" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if ($products === []): ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No products found matching your filters</h5>
                                        <a href="merge-products.php" class="btn btn-primary mt-3">Clear Filters</a>
                                    </div>
                                </div>
                                <?php endif; ?>
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

    .product-merge-card.is-selected {
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
        transform: scale(1.05);
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
        font-weight: 700;
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
            min-height: 100px;
        }
    }
    </style>

    <script>
    (function () {
        const mergeForm = document.getElementById('mergeForm');
        if (!mergeForm) {
            return;
        }

        const checkboxes = Array.from(document.querySelectorAll('.product-checkbox'));
        const mergeButton = document.getElementById('mergeButton');
        const selectedCount = document.getElementById('selectedCount');
        const selectAllButton = document.querySelector('[data-select-all]');
        const deselectAllButton = document.querySelector('[data-deselect-all]');
        const categoryFilter = document.getElementById('categoryFilter');
        const brandFilter = document.getElementById('brandFilter');
        const searchForm = document.getElementById('searchForm');

        function syncCardState() {
            const selectedIds = [];

            checkboxes.forEach(function (checkbox) {
                const card = checkbox.closest('.product-merge-card');
                if (card) {
                    card.classList.toggle('is-selected', checkbox.checked);
                }
                if (checkbox.checked) {
                    selectedIds.push(Number(checkbox.value));
                }
            });

            selectedCount.textContent = selectedIds.length + ' products selected';
            mergeButton.disabled = selectedIds.length < 2;

            if (selectedIds.length >= 2) {
                const primaryId = Math.min.apply(null, selectedIds);
                mergeButton.innerHTML = '<i class="fas fa-object-group me-1"></i>Merge into Product #' + primaryId;
            } else {
                mergeButton.innerHTML = '<i class="fas fa-object-group me-1"></i>Merge Selected Products';
            }
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', syncCardState);
        });

        if (selectAllButton) {
            selectAllButton.addEventListener('click', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = true;
                });
                syncCardState();
            });
        }

        if (deselectAllButton) {
            deselectAllButton.addEventListener('click', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });
                syncCardState();
            });
        }

        mergeForm.addEventListener('submit', function (event) {
            const selectedIds = checkboxes
                .filter(function (checkbox) {
                    return checkbox.checked;
                })
                .map(function (checkbox) {
                    return Number(checkbox.value);
                });

            if (selectedIds.length < 2) {
                event.preventDefault();
                window.alert('Please select at least 2 products to merge.');
                return;
            }

            const primaryId = Math.min.apply(null, selectedIds);
            const duplicateIds = selectedIds.filter(function (id) {
                return id !== primaryId;
            });

            const confirmationMessage = [
                'You are about to merge ' + selectedIds.length + ' products.',
                '',
                'PRIMARY (Keep): Product #' + primaryId,
                'DELETE: Products #' + duplicateIds.join(', #'),
                '',
                'All images from deleted products will be moved to Product #' + primaryId + '.',
                'This action cannot be undone.',
                '',
                'Continue?'
            ].join('\n');

            if (!window.confirm(confirmationMessage)) {
                event.preventDefault();
            }
        });

        if (categoryFilter && searchForm) {
            categoryFilter.addEventListener('change', function () {
                searchForm.submit();
            });
        }

        if (brandFilter && searchForm) {
            brandFilter.addEventListener('change', function () {
                searchForm.submit();
            });
        }

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            new bootstrap.Tooltip(element);
        });

        syncCardState();
    }());
    </script>
    <?php

    echo ob_get_clean();
} catch (Throwable $exception) {
    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }

    error_log(sprintf(
        'merge-products diagnostic: %s in %s on line %d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    mergeProductsRenderDiagnostic($exception);
}

include 'includes/footer.php';
