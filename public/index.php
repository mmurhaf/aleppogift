<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();
$show_prices = get_site_setting($db, 'show_prices', '1') === '1';

// Fetch categories 
$categories = array_values(array_filter(
    $db->query("SELECT * FROM categories WHERE status=1")->fetchAll(PDO::FETCH_ASSOC),
    function ($cat) {
        return !is_placeholder_name($cat['name_en'] ?? '');
    }
));

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
        $where[] = "(p.name_en LIKE :search_en OR p.name_ar LIKE :search_ar OR p.name_ru LIKE :search_ru OR p.name_sp LIKE :search_sp OR p.sku LIKE :search_sku)";
        $params['search_en'] = "%$search%";
        $params['search_ar'] = "%$search%";
        $params['search_ru'] = "%$search%";
        $params['search_sp'] = "%$search%";
        $params['search_sku'] = "%$search%";
    }
}



// now build your query with $where and $params...


// Build final WHERE clause
$whereSQL = "WHERE " . implode(" AND ", $where);

// Count total products for pagination
$countSQL = "SELECT COUNT(*) as total FROM products p $whereSQL";
$totalProducts = $db->query($countSQL, $params)->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination settings
$sort = $_GET['sort'] ?? 'foryou';
$perPage = max(1, (int)store_setting($db, 'products_per_page', '16'));
$totalPages = ceil($totalProducts / $perPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $perPage;

// Final SQL query with pagination - includes brand and category names
$sql = "SELECT p.*, 
        (SELECT image_path FROM product_images 
         WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image,
        b.name_en as brand_name_en,
        b.name_ar as brand_name_ar,
        b.name_ru as brand_name_ru,
        b.name_sp as brand_name_sp,
        c.name_en as category_name_en,
        c.name_ar as category_name_ar,
        c.name_ru as category_name_ru,
        c.name_sp as category_name_sp,
        " . review_stats_select('p') . ",
        " . units_sold_select('p') . "
        FROM products p 
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        $whereSQL 
        ORDER BY " . catalog_order_sql($sort, 'p') . "
        LIMIT :limit OFFSET :offset";

// Add pagination parameters as integers
$params['limit'] = (int)$perPage;
$params['offset'] = (int)$offset;

// Execute
$products = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

$featuredPhotos = [];
try {
    $featuredPhotos = $db->query("SELECT * FROM customer_photos WHERE is_approved = 1 AND is_featured = 1 ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featuredPhotos = [];
}

$featuredTestimonials = $db->query(
        "SELECT *
        FROM testimonials
        WHERE is_approved = 1
            AND (is_featured = 1 OR whatsapp_conversation = 1)
        ORDER BY is_featured DESC, created_at DESC
        LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);

$webPath = function ($path) {
    return implode('/', array_map('rawurlencode', explode('/', ltrim(str_replace(['\\', '../'], ['/', ''], (string)$path), '/'))));
};

$homeCategories = [];
foreach ($categories as $cat) {
    $tileImage = ltrim(str_replace('../', '', trim((string)($cat['picture'] ?? ''))), '/');
    if ($tileImage === '' || stripos($tileImage, 'undefined') !== false || !is_file(__DIR__ . '/' . $tileImage)) {
        $tileImage = (string)$db->query(
            "SELECT pi.image_path FROM products p
             INNER JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
             WHERE p.category_id = :category_id AND " . published_product_sql('p') . "
             ORDER BY p.id DESC LIMIT 1",
            ['category_id' => (int)$cat['id']]
        )->fetchColumn();
    }
    if ($tileImage === '') {
        continue;
    }
    $cat['tile_image'] = $webPath($tileImage);
    $homeCategories[] = $cat;
}

$editorialCategory = null;
foreach (['dinner', 'plate', 'coffee'] as $editorialNeedle) {
    foreach ($homeCategories as $cat) {
        if (stripos($cat['name_en'], $editorialNeedle) !== false) {
            $editorialCategory = $cat;
            break 2;
        }
    }
}
$editorialUrl = $editorialCategory ? 'products.php?category=' . (int)$editorialCategory['id'] : 'products.php';

$newArrivals = $db->query(
    "SELECT p.*,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image,
        b.name_en AS brand_name_en, b.name_ar AS brand_name_ar, b.name_ru AS brand_name_ru, b.name_sp AS brand_name_sp,
        c.name_en AS category_name_en, c.name_ar AS category_name_ar, c.name_ru AS category_name_ru, c.name_sp AS category_name_sp,
        " . review_stats_select('p') . "
     FROM products p
     LEFT JOIN brands b ON p.brand_id = b.id
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE " . published_product_sql('p') . "
     ORDER BY " . catalog_order_sql('newest', 'p') . "
     LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC);

$canonical_query = [];
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $canonical_query['category'] = (int)$_GET['category'];
}
if (isset($_GET['brand']) && is_numeric($_GET['brand'])) {
    $canonical_query['brand'] = (int)$_GET['brand'];
}
if ($canonical_query) {
    if ($currentPage > 1) {
        $canonical_query['page'] = $currentPage;
    }
    $canonical_url = 'https://aleppogift.com/products.php?' . http_build_query($canonical_query);
} else {
    $canonical_url = 'https://aleppogift.com/' . ($currentPage > 1 ? '?page=' . $currentPage : '');
}
$robots_meta = $search !== '' ? 'noindex, follow' : 'index, follow';
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0531879237494766" crossorigin="anonymous"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('catalog.home_title') ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= te('catalog.home_description') ?>">
    <meta name="keywords" content="tableware UAE, plates Dubai, glasses UAE, mugs Dubai, Arabic coffee cups UAE, tea cups Dubai, trays and vases UAE, blankets and pillows Dubai">
    <meta name="author" content="Aleppo Gift">
    <meta name="robots" content="<?= $robots_meta ?>">
    <?= seo_lang_links($canonical_url) ?>
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= te('catalog.home_og_title') ?>">
    <meta property="og:description" content="<?= te('catalog.home_og_description') ?>">
    <meta property="og:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= htmlspecialchars(seo_canonical_url($canonical_url)) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aleppo Gift">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= te('catalog.home_og_title') ?>">
    <meta name="twitter:description" content="<?= te('catalog.home_twitter_description') ?>">
    <meta name="twitter:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    
    <!-- Preconnect to External Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://code.jquery.com">
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="assets/css/main.css?v=20261005" as="style">
    <link rel="preload" href="assets/css/index.css?v=20261005" as="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" as="style">
    
    <!-- Stylesheets -->
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/index.css')) ?>?v=20261007a">


</head>
<body id="top">

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="store-home tg-home">
        <section class="tg-hero">
            <img class="tg-hero-media" src="assets/images/hero-bg.jpg" alt="" fetchpriority="high" width="1408" height="768">
            <div class="tg-hero-copy">
                <p class="tg-eyebrow"><?= te('catalog.hero_badge') ?></p>
                <h1 class="tg-hero-title"><?= te('catalog.hero_title') ?></h1>
                <p class="tg-hero-text"><?= te('catalog.hero_subtitle') ?></p>
                <div class="tg-hero-actions">
                    <a href="#products" class="tg-btn tg-btn-light"><?= te('catalog.shop_products') ?></a>
                    <a href="https://wa.me/<?php echo WHATSAPP_NUMBER; ?>" class="tg-btn tg-btn-ghost-light" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i><?= te('catalog.ask_whatsapp') ?>
                    </a>
                </div>
            </div>
        </section>

        <div class="tg-container">
            <?php if (!empty($homeCategories)): ?>
            <section class="tg-section tg-categories" aria-labelledby="tg-categories-title">
                <header class="tg-section-head is-center">
                    <h2 id="tg-categories-title"><?= te('theme.our_categories') ?></h2>
                    <p><?= te('theme.categories_lead') ?></p>
                </header>
                <div class="tg-carousel" data-tg-carousel>
                    <button type="button" class="tg-carousel-btn is-prev" aria-label="<?= te('catalog.previous') ?>" data-tg-prev><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <div class="tg-carousel-track tg-category-track" data-tg-track>
                        <?php foreach ($homeCategories as $cat): ?>
                            <a class="tg-category-tile" href="<?= htmlspecialchars(store_url('products.php?category=' . (int)$cat['id'])) ?>">
                                <span class="tg-category-media">
                                    <img src="<?= htmlspecialchars($cat['tile_image']) ?>" alt="" loading="lazy" decoding="async" width="400" height="500">
                                </span>
                                <span class="tg-category-name"><?= htmlspecialchars(loc_field($cat, 'name')) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="tg-carousel-btn is-next" aria-label="<?= te('catalog.next') ?>" data-tg-next><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($newArrivals)): ?>
            <section class="tg-section" aria-labelledby="tg-new-title">
                <header class="tg-section-head is-row">
                    <h2 id="tg-new-title"><?= te('theme.new_arrivals') ?></h2>
                    <a class="tg-link" href="<?= htmlspecialchars(store_url('products.php?sort=newest')) ?>"><?= te('theme.view_all') ?></a>
                </header>
                <div class="tg-carousel" data-tg-carousel>
                    <button type="button" class="tg-carousel-btn is-prev" aria-label="<?= te('catalog.previous') ?>" data-tg-prev><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <div class="tg-carousel-track" data-tg-track>
                        <?php foreach ($newArrivals as $p): ?>
                            <div class="tg-carousel-item">
                                <?php include __DIR__ . '/../includes/product_card.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="tg-carousel-btn is-next" aria-label="<?= te('catalog.next') ?>" data-tg-next><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <section class="tg-editorial" aria-labelledby="tg-editorial-title">
            <div class="tg-editorial-copy">
                <p class="tg-eyebrow is-dark"><?= $editorialCategory ? htmlspecialchars(loc_field($editorialCategory, 'name')) : te('shop') ?></p>
                <h2 id="tg-editorial-title"><?= te('theme.editorial_title') ?></h2>
                <p><?= te('theme.editorial_text') ?></p>
                <a class="tg-btn" href="<?= htmlspecialchars(store_url($editorialUrl)) ?>"><?= te('theme.shop_now') ?></a>
            </div>
            <a class="tg-editorial-media" href="<?= htmlspecialchars(store_url($editorialUrl)) ?>" tabindex="-1" aria-hidden="true">
                <img src="assets/images/hero-bg0.jpg" alt="" loading="lazy" decoding="async" width="1024" height="576">
            </a>
        </section>

        <div class="tg-container">
            <?php $homeBundles = loadHomeBundles($db); ?>
            <?php if (!empty($homeBundles)): ?>
            <section id="bundles" class="tg-section bundle-section">
                <header class="tg-section-head is-center">
                    <h2><?= te('catalog.bundles') ?></h2>
                    <p><?= te('catalog.bundles_subtitle') ?></p>
                </header>
                <div class="bundle-grid">
                    <?php foreach ($homeBundles as $bundle): ?>
                        <?php
                        $bundleImage = $bundle['image_path'] ?: ($bundle['items'][0]['image_path'] ?? '');
                        $bundleImage = $bundleImage ? str_replace('../', '', $bundleImage) : 'assets/images/no-image.png';
                        $canAdd = empty($bundle['unavailable']);
                        ?>
                        <article class="bundle-card">
                            <div class="bundle-card-media">
                                <img src="<?= htmlspecialchars($bundleImage) ?>" alt="<?= htmlspecialchars(loc_field($bundle, 'name')) ?>" loading="lazy">
                                <span class="bundle-discount"><?= te('catalog.percent_off', ['percent' => rtrim(rtrim(number_format((float)$bundle['discount_percent'], 2, '.', ''), '0'), '.')]) ?></span>
                            </div>
                            <div class="bundle-card-body">
                                <h3><?= htmlspecialchars(loc_field($bundle, 'name')) ?></h3>
                                <?php if (!function_exists('is_placeholder_arabic_name') || !is_placeholder_arabic_name($bundle['name_ar'] ?? '')): ?>
                                    <p class="bundle-name-ar" dir="rtl"><?= htmlspecialchars($bundle['name_ar']) ?></p>
                                <?php endif; ?>
                                <?php if (loc_field($bundle, 'description') !== ''): ?>
                                    <p class="bundle-description"><?= htmlspecialchars(loc_field($bundle, 'description')) ?></p>
                                <?php endif; ?>
                                <ul class="bundle-items">
                                    <?php foreach ($bundle['items'] as $bundleItem): ?>
                                        <li><?= (int)$bundleItem['quantity'] ?> × <?= htmlspecialchars(loc_field($bundleItem, 'name')) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($show_prices): ?>
                                    <p class="bundle-prices">
                                        <s><?= format_store_money((float)$bundle['catalog_total']) ?></s>
                                        <strong><?= format_store_money((float)$bundle['discounted_total']) ?></strong>
                                    </p>
                                <?php endif; ?>
                                <?php if ($canAdd): ?>
                                    <form class="add-bundle-form" method="post" action="ajax/add_bundle_to_cart.php">
                                        <input type="hidden" name="bundle_id" value="<?= (int)$bundle['id'] ?>">
                                        <button type="submit" class="btn btn-primary"><?= te('catalog.add_bundle') ?></button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary" disabled><?= te('catalog.add_bundle') ?></button>
                                    <p class="bundle-unavailable"><?= htmlspecialchars(implode('. ', $bundle['unavailable'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section id="products" class="tg-section products-section">
                <header class="tg-section-head is-center">
                    <h2><?= te('catalog.shop_collection') ?></h2>
                    <p><?= te('catalog.products_count', ['count' => (int)$totalProducts]) ?></p>
                </header>

                <?php if (!empty($categories)): ?>
                <nav class="category-rail" aria-label="<?= te('catalog.shop_by_category') ?>">
                    <a class="category-chip<?= empty($_GET['category']) ? ' is-active' : '' ?>" href="<?= htmlspecialchars(store_url('#products')) ?>"><?= te('catalog.all') ?></a>
                    <?php foreach ($categories as $cat): ?>
                        <a class="category-chip<?= (isset($_GET['category']) && (string)$_GET['category'] === (string)$cat['id']) ? ' is-active' : '' ?>"
                           href="<?= htmlspecialchars(store_url('?category=' . (int)$cat['id'] . '#products')) ?>">
                            <?= htmlspecialchars(loc_field($cat, 'name')) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>

                <form method="get" action="#products" class="catalog-toolbar modern-filter-form">
                    <div class="catalog-toolbar-grid">
                        <div class="filter-group search-group">
                            <label for="search" class="visually-hidden"><?= te('search') ?></label>
                            <input type="text" name="search" id="search" class="form-control search-input"
                                   placeholder="<?= te('header.search_products') ?>"
                                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="filter-group">
                            <label for="category" class="visually-hidden"><?= te('catalog.category') ?></label>
                            <select name="category" id="category" class="form-control modern-select">
                                <option value=""><?= te('catalog.all_categories') ?></option>
                                <?php foreach ($categories as $cat): ?>
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
                                    <option value="<?= $brand['id']; ?>" <?= (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(loc_field($brand, 'name')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="catalog-toolbar-actions">
                            <button type="submit" class="btn btn-primary filter-submit"><?= te('search') ?></button>
                            <a href="?" class="btn reset-filters" title="<?= te('catalog.clear_filters') ?>"><?= te('catalog.reset') ?></a>
                            <button type="button" class="btn catalog-pdf" onclick="exportSearchToPDF()" title="<?= te('catalog.export_pdf') ?>">PDF</button>
                        </div>
                    </div>
                </form>

                <details class="availability-notice">
                    <summary>
                        <span class="availability-notice-icon" aria-hidden="true"><i class="fas fa-circle-info"></i></span>
                        <span class="notice-summary-copy">
                            <strong><?= te('catalog.notice_title') ?></strong>
                            <span><?= te('catalog.notice_summary_availability') ?></span>
                            <span><?= te('catalog.notice_summary_payment') ?></span>
                        </span>
                    </summary>
                    <div class="availability-notice-text">
                        <span class="notice-lang-en">
                            <?= te('catalog.notice_confirm_on') ?>
                            <a href="https://wa.me/<?php echo WHATSAPP_NUMBER; ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-whatsapp"></i> <?= te('catalog.whatsapp') ?>
                            </a>.
                            <?= te('catalog.notice_place_order') ?>
                            <strong class="notice-cod-highlight"><?= te('catalog.notice_cod') ?></strong>
                        </span>
                    </div>
                </details>

                <div class="product-grid">
                    <?php foreach ($products as $p): ?>
                        <?php include __DIR__ . '/../includes/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($totalPages > 1): ?>
            <nav class="pagination-container" aria-label="<?= te('catalog.pagination') ?>">
                <div class="pagination-info"><?= te('catalog.products_found', ['count' => (int)$totalProducts]) ?></div>
                <ul class="pagination">
                    <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $currentPage - 1]))) ?>#products" aria-label="<?= te('catalog.previous') ?>">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <li class="page-item <?= $currentPage == 1 ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => 1]))) ?>#products">1</a>
                    </li>
                    <?php if ($currentPage > 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++): ?>
                        <li class="page-item <?= $currentPage == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>#products"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages - 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item <?= $currentPage == $totalPages ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $totalPages]))) ?>#products"><?= $totalPages ?></a>
                    </li>
                    <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $currentPage + 1]))) ?>#products" aria-label="<?= te('catalog.next') ?>">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

            <section id="categories" class="tg-section home-brands">
                <?php require_once(__DIR__ . '/../includes/brands.php'); ?>
            </section>

            <?php include(__DIR__ . '/../includes/social_proof_widgets.php'); ?>

            <?php if (!empty($featuredPhotos)): ?>
            <section class="tg-section home-gallery">
                <header class="tg-section-head is-center">
                    <h2><?= te('catalog.customer_gallery') ?></h2>
                </header>
                <div class="tg-gallery-grid">
                    <?php foreach ($featuredPhotos as $photo): ?>
                        <img src="<?= htmlspecialchars($photo['photo_path']) ?>" alt="<?= htmlspecialchars((string)$photo['customer_name']) ?>" loading="lazy"<?= !empty($photo['privacy_blurred']) ? ' class="is-blurred"' : '' ?>>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($featuredTestimonials)): ?>
            <section class="tg-section trust-teaser">
                <header class="tg-section-head is-center">
                    <p class="tg-eyebrow is-dark"><i class="fab fa-whatsapp" aria-hidden="true"></i> <?= te('catalog.real_proof') ?></p>
                    <h2><?= te('catalog.trust_title') ?></h2>
                    <p><?= te('catalog.trust_text') ?></p>
                </header>
                <div class="tg-testimonial-grid">
                    <?php foreach ($featuredTestimonials as $testimonial): ?>
                    <article class="trust-proof-card">
                        <?php if (!empty($testimonial['whatsapp_screenshot_path']) && !empty($testimonial['privacy_blurred'])): ?>
                            <a href="<?= htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>"
                                     alt="<?= te('catalog.proof_alt', ['name' => $testimonial['customer_name']]) ?>"
                                     class="trust-proof-media" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <div class="trust-proof-body">
                            <p class="trust-proof-quote">&ldquo;<?= htmlspecialchars(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $testimonial['testimonial_text'])), 0, 180, '...')) ?>&rdquo;</p>
                            <p class="trust-proof-author">
                                <strong><?= htmlspecialchars($testimonial['customer_name']) ?></strong>
                                <?php if (!empty($testimonial['customer_location'])): ?>
                                    <span><?= htmlspecialchars($testimonial['customer_location']) ?></span>
                                <?php endif; ?>
                            </p>
                            <div class="trust-proof-badges">
                                <?php if (!empty($testimonial['whatsapp_conversation'])): ?>
                                    <span><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp</span>
                                <?php endif; ?>
                                <?php if (!empty($testimonial['privacy_blurred'])): ?>
                                    <span><?= te('catalog.privacy_protected') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <div class="tg-center-actions">
                    <a href="testimonials.php" class="tg-btn tg-btn-outline"><?= te('catalog.read_testimonials') ?></a>
                    <a href="https://wa.me/971561125320" class="tg-btn" target="_blank" rel="noopener noreferrer"><?= te('catalog.chat_whatsapp') ?></a>
                </div>
            </section>
            <?php endif; ?>

            <section class="tg-section tg-services" aria-labelledby="tg-services-title">
                <header class="tg-section-head is-center">
                    <h2 id="tg-services-title"><?= te('theme.our_services') ?></h2>
                </header>
                <ul class="tg-services-grid">
                    <li>
                        <span class="tg-service-icon" aria-hidden="true"><i class="fas fa-hand-holding-dollar"></i></span>
                        <h3><?= te('catalog.promise_cod') ?></h3>
                        <p><?= te('theme.service_cod_text') ?></p>
                    </li>
                    <li>
                        <span class="tg-service-icon" aria-hidden="true"><i class="fas fa-truck-fast"></i></span>
                        <h3><?= te('catalog.promise_delivery') ?></h3>
                        <p><?= te('theme.service_delivery_text') ?></p>
                    </li>
                    <li>
                        <span class="tg-service-icon" aria-hidden="true"><i class="fab fa-whatsapp"></i></span>
                        <h3><?= te('catalog.promise_confirm') ?></h3>
                        <p><?= te('theme.service_whatsapp_text') ?></p>
                    </li>
                    <li>
                        <span class="tg-service-icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                        <h3><?= te('catalog.promise_currency') ?></h3>
                        <p><?= te('theme.service_currency_text') ?></p>
                    </li>
                </ul>
            </section>

            <section class="tg-seo-intro">
                <h2><?= te('theme.intro_title') ?></h2>
                <p><?= te('theme.intro_text') ?></p>
            </section>
        </div>
    </main>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>  




    <!-- JSON-LD Structured Data for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Store",
      "name": "Aleppo Gift",
      "description": "Premium Gifts & Home Decor in UAE",
      "url": "https://aleppogift.com",
      "telephone": "+971561125320",
      "address": {
        "@type": "PostalAddress",
        "addressCountry": "AE"
      },
      "priceRange": "$$",
      "image": "https://aleppogift.com/assets/images/og-image.jpg"
    }
    </script>

    <!-- JavaScript - jQuery loaded without defer since inline scripts depend on it -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="assets/js/catalog-pdf.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    
    <script>
        const CATALOG_I18N = <?= json_encode([
            'generating' => t('catalog.pdf_generating'),
            'wait' => t('catalog.pdf_wait'),
            'failed' => t('catalog.pdf_failed'),
            'error' => t('catalog.pdf_error'),
            'errorGenerating' => t('catalog.pdf_error_generating'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        function exportSearchToPDF() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            params.delete('page'); // Remove pagination to get all results
            
            // Show loading message
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'pdf-loading-overlay';
            loadingDiv.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:99999;';
            loadingDiv.innerHTML = '<div style="background:#fff;padding:36px 40px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.25);text-align:center;max-width:320px;width:90%;"><i class="fas fa-spinner fa-spin fa-3x mb-3" style="color:#007bff;"></i><h4 style="margin:16px 0 8px;"></h4><p style="margin:0;color:#666;"></p></div>';
            loadingDiv.querySelector('h4').textContent = CATALOG_I18N.generating;
            loadingDiv.querySelector('p').textContent = CATALOG_I18N.wait;
            document.body.appendChild(loadingDiv);
            
            // Fetch all products for PDF
            fetch('ajax/export_search_results_pdf.php?' + params.toString())
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
                            groups: data.groups,
                            groupingType: data.grouping_type,
                            groupNames: data.group_names || {},
                            filters: data.filters,
                            title: 'Search Results',
                            filenamePrefix: 'AleppoGift_SearchResults'
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
        
        // Add to cart is handled by assets/js/storefront.js.
    </script>
    
	<script defer src="<?= htmlspecialchars(store_url('assets/js/main.js')) ?>?v=20261012i18n"></script>
	<script defer src="<?= htmlspecialchars(store_url('assets/js/enhanced-main.js')) ?>?v=20261012i18n"></script>
	<script defer src="<?= htmlspecialchars(store_url('assets/js/enhanced-ui.js')) ?>?v=20261012i18n"></script>
    <!-- Index Page JavaScript -->
    <script defer src="<?= htmlspecialchars(store_url('assets/js/index.js')) ?>?v=20261012i18n"></script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
