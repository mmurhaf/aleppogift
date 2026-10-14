<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();
$show_prices = get_site_setting($db, 'show_prices', '1') === '1';

// Fetch categories 
$categories = $db->query("SELECT * FROM categories WHERE status=1")->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands
$brands = $db->query("SELECT * FROM brands WHERE status=1")->fetchAll(PDO::FETCH_ASSOC);

// Initialize filters once
$where = [published_product_sql('p')];
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
        $where[] = "(p.id = :product_id OR p.sku LIKE :search_sku)";
        $params['product_id'] = $search;
        $params['search_sku'] = "%$search%";
    } else {
        $where[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar OR p.sku LIKE :search_sku)";
        $params['search_en'] = "%$search%";
        $params['search_ar'] = "%$search%";
        $params['search_sku'] = "%$search%";
    }
}
if (isset($_GET['min_price']) && $_GET['min_price'] !== '') {
    $where[] = 'p.price >= :min_price';
    $params['min_price'] = (float)$_GET['min_price'];
}
if (isset($_GET['max_price']) && $_GET['max_price'] !== '') {
    $where[] = 'p.price <= :max_price';
    $params['max_price'] = (float)$_GET['max_price'];
}

// --- Handle sorting ---
$sort = $_GET['sort'] ?? 'foryou';
$orderBy = catalog_order_sql($sort, 'p');

// Pagination
$limit = max(1, (int)store_setting($db, 'products_per_page', '12'));
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
        c.name_en as category_name_en,
        c.name_ar as category_name_ar,
        c.name_ru as category_name_ru,
        c.name_sp as category_name_sp,
        b.name_en as brand_name_en,
        b.name_ar as brand_name_ar,
        b.name_ru as brand_name_ru,
        b.name_sp as brand_name_sp,
        (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image,
        " . review_stats_select('p') . ",
        " . units_sold_select('p') . "
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

// Build dynamic meta information
$page_title = t('catalog.products_title');
$page_description = t('catalog.products_description');
$page_keywords = "tableware UAE, plates Dubai, glasses UAE, mugs Dubai, Arabic coffee cups UAE, trays and vases UAE, blankets and pillows UAE";
$canonical_url = "https://aleppogift.com/products.php";
$listing_title = t('all_products');
$listing_lead = t('catalog.products_lead');

// Dynamic title and description based on filters
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $cat_info = $db->query("SELECT name_en, name_ar, name_ru, name_sp FROM categories WHERE id = :id", ['id' => $_GET['category']])->fetch(PDO::FETCH_ASSOC);
    if ($cat_info) {
        $listing_title = loc_field($cat_info, 'name');
        $listing_lead = t('catalog.category_lead', ['name' => loc_field($cat_info, 'name')]);
        $page_title = t('catalog.category_meta_title', ['name' => loc_field($cat_info, 'name')]);
        $page_description = t('catalog.category_meta_description', ['name' => loc_field($cat_info, 'name')]);
        $page_keywords = htmlspecialchars($cat_info['name_en']) . ", tableware UAE, home accessories UAE, Aleppo Gift";
        $canonical_url .= "?category=" . $_GET['category'];
    }
}

if (isset($_GET['brand']) && is_numeric($_GET['brand'])) {
    $brand_info = $db->query("SELECT name_en, name_ar, name_ru, name_sp FROM brands WHERE id = :id", ['id' => $_GET['brand']])->fetch(PDO::FETCH_ASSOC);
    if ($brand_info) {
        $listing_title = loc_field($brand_info, 'name');
        $listing_lead = t('catalog.brand_lead', ['name' => loc_field($brand_info, 'name')]);
        $page_title = t('catalog.brand_meta_title', ['name' => loc_field($brand_info, 'name')]);
        $page_description = t('catalog.brand_meta_description', ['name' => loc_field($brand_info, 'name')]);
        $page_keywords = htmlspecialchars($brand_info['name_en']) . ", tableware UAE, cups and glasses UAE, Aleppo Gift";
        $canonical_url .= (strpos($canonical_url, '?') !== false ? '&' : '?') . "brand=" . $_GET['brand'];
    }
}

if ($search !== '') {
    $listing_title = t('catalog.search_title', ['query' => $search]);
    $listing_lead = t('catalog.search_lead', ['query' => $search]);
    $page_title = t('catalog.search_meta_title', ['query' => $search]);
    $page_description = t('catalog.search_meta_description', ['query' => $search]);
    $page_keywords = htmlspecialchars($search) . ", tableware UAE, home accessories UAE, Aleppo Gift";
}

if ($page > 1) {
    $canonical_url .= (strpos($canonical_url, '?') !== false ? '&' : '?') . 'page=' . $page;
}
$robots_meta = $search !== '' ? 'noindex, follow' : 'index, follow';
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="keywords" content="<?php echo $page_keywords; ?>">
    <meta name="author" content="Aleppo Gift">
    <meta name="robots" content="<?= $robots_meta ?>">
    <?= seo_lang_links($canonical_url) ?>
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= htmlspecialchars(seo_canonical_url($canonical_url)) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aleppo Gift">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="twitter:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/index.css')) ?>?v=20261007a">

</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="container store-main catalog-page my-4">
        <header class="page-hero">
            <p class="page-kicker"><?= te('shop') ?></p>
            <h1><?= htmlspecialchars($listing_title) ?></h1>
            <p class="page-lead"><?= htmlspecialchars($listing_lead) ?></p>
        </header>

        <?php if (!empty($categories)): ?>
        <nav class="category-rail" aria-label="<?= te('catalog.shop_by_category') ?>">
            <a class="category-chip<?= empty($_GET['category']) ? ' is-active' : '' ?>" href="products.php"><?= te('catalog.all') ?></a>
            <?php foreach ($categories as $cat): ?>
                <?php if (is_placeholder_name($cat['name_en'] ?? '')) continue; ?>
                <a class="category-chip<?= (isset($_GET['category']) && (string)$_GET['category'] === (string)$cat['id']) ? ' is-active' : '' ?>"
                   href="?category=<?= (int)$cat['id'] ?>">
                    <?= htmlspecialchars(loc_field($cat, 'name')) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <section id="products" class="products-section">
            <div class="products-header products-header-row">
                <div>
                    <h2 class="products-title"><?= te('catalog.products_count', ['count' => (int)$total_products]) ?></h2>
                    <p class="products-subtitle"><?= te('catalog.page_of', ['page' => (int)$page, 'pages' => max(1, (int)$total_pages)]) ?></p>
                </div>
            </div>

            <form method="get" action="" class="catalog-toolbar modern-filter-form">
                <div class="filter-group">
                    <select name="sort" class="form-control">
                        <?php foreach (['foryou' => t('for_you'), 'newest' => t('newest'), 'price_asc' => t('price_low'), 'price_desc' => t('price_high'), 'rating' => t('best_rated'), 'bestseller' => t('best_seller')] as $sortKey => $sortLabel): ?>
                            <option value="<?= $sortKey ?>" <?= $sort === $sortKey ? 'selected' : '' ?>><?= htmlspecialchars($sortLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group"><input class="form-control" type="number" name="min_price" placeholder="<?= te('catalog.min_aed') ?>" value="<?= htmlspecialchars((string)($_GET['min_price'] ?? '')) ?>"></div>
                <div class="filter-group"><input class="form-control" type="number" name="max_price" placeholder="<?= te('catalog.max_aed') ?>" value="<?= htmlspecialchars((string)($_GET['max_price'] ?? '')) ?>"></div>
                <div class="catalog-toolbar-grid">
                    <div class="filter-group search-group">
                        <label for="search" class="visually-hidden"><?= te('search') ?></label>
                        <input type="text" name="search" id="search" class="form-control search-input"
                               placeholder="<?= te('header.search_products') ?>"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="category" class="visually-hidden"><?= te('catalog.category') ?></label>
                        <select name="category" id="category" class="form-control modern-select">
                            <option value=""><?= te('catalog.all_categories') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if (is_placeholder_name($cat['name_en'] ?? '')) continue; ?>
                                <option value="<?= $cat['id']; ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(loc_field($cat, 'name')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="brand" class="visually-hidden"><?= te('catalog.brand') ?></label>
                        <select name="brand" id="brand" class="form-control modern-select">
                            <option value=""><?= te('catalog.all_brands') ?></option>
                            <?php foreach ($brands as $brand): ?>
                                <?php if (is_placeholder_name($brand['name_en'] ?? '')) continue; ?>
                                <option value="<?= $brand['id']; ?>" <?= (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(loc_field($brand, 'name')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="catalog-toolbar-actions">
                        <button type="submit" class="btn btn-primary filter-submit"><?= te('search') ?></button>
                        <a href="products.php" class="btn reset-filters" title="<?= te('catalog.clear_filters') ?>"><?= te('catalog.reset') ?></a>
                        <button type="button" class="btn catalog-pdf" onclick="exportToPDF()" title="<?= te('catalog.export_pdf') ?>">PDF</button>
                    </div>
                </div>
            </form>

            <?php if (empty($products)): ?>
                <div class="empty-cart text-center py-5 px-3">
                    <div class="empty-cart-icon mb-2"><i class="fas fa-box-open"></i></div>
                    <h3><?= te('catalog.no_products') ?></h3>
                    <p><?= te('catalog.no_products_hint') ?></p>
                    <a href="products.php" class="btn btn-primary"><?= te('catalog.view_all') ?></a>
                </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $p): ?>
                    <?php include __DIR__ . '/../includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($total_pages > 1):
            $pageQuery = $_GET;
            unset($pageQuery['page']);
        ?>
        <nav class="pagination-container" aria-label="<?= te('catalog.pagination') ?>">
            <div class="pagination-info"><?= te('catalog.products_count', ['count' => (int)$total_products]) ?></div>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($pageQuery, ['page' => max(1, $page - 1)]))) ?>" aria-label="<?= te('catalog.previous') ?>">&laquo;</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($pageQuery, ['page' => $i]))) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($pageQuery, ['page' => min($total_pages, $page + 1)]))) ?>" aria-label="<?= te('catalog.next') ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </main>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.plugin.arabic.min.js"></script>
    <script src="assets/js/catalog-pdf.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
	<script src="assets/js/main.js?v=20261012i18n"></script>
	<script src="assets/js/enhanced-main.js?v=20261012i18n"></script>
    <script>
        const CATALOG_I18N = <?= json_encode([
            'generating' => t('catalog.pdf_generating'),
            'wait' => t('catalog.pdf_wait'),
            'failed' => t('catalog.pdf_failed'),
            'error' => t('catalog.pdf_error'),
            'errorGenerating' => t('catalog.pdf_error_generating'),
            'shareText' => t('catalog.share_text'),
            'shareUnsupported' => t('catalog.share_unsupported'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        function exportToPDF() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            params.delete('page'); // Remove pagination to get all results
            
            // Show loading message
            const loadingDiv = document.createElement('div');
            loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center;';
            loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x mb-3" style="color: #007bff;"></i><h4></h4><p></p>';
            loadingDiv.querySelector('h4').textContent = CATALOG_I18N.generating;
            loadingDiv.querySelector('p').textContent = CATALOG_I18N.wait;
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
                .then(async data => {
                    if (data.success) {
                        await AleppoCatalogPdf.download({
                            products: data.products,
                            filters: data.filters,
                            title: 'Product Catalog',
                            filenamePrefix: 'AleppoGift_Products',
                            includeArabic: true
                        });
                    } else {
                        alert(CATALOG_I18N.error.replace('{message}', data.error || CATALOG_I18N.failed));
                    }
                    document.body.removeChild(loadingDiv);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(CATALOG_I18N.errorGenerating.replace('{message}', error.message));
                    if (loadingDiv.parentElement) {
                        document.body.removeChild(loadingDiv);
                    }
                });
        }
        
        
        document.querySelectorAll('.add-to-cart-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = form.querySelector('.add-to-cart');
                var productId = (form.querySelector('[name="product_id"]') || {}).value;
                var quantity = (form.querySelector('[name="quantity"]') || {}).value || '1';
                if (typeof window.addToCart === 'function') {
                    window.addToCart(productId, quantity, button);
                }
            });
        });

        function shareProduct(name, price, id, url) {
            const shareText = CATALOG_I18N.shareText
                .replace('{name}', name)
                .replace('{price}', price)
                .replace('{id}', id) + '\n' + url;

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
                alert(CATALOG_I18N.shareUnsupported + "\n" + url);
            }
        }
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
