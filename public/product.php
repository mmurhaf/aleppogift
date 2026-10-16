<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();
$show_prices = get_site_setting($db, 'show_prices', '1') === '1';

if (!isset($_GET['id'])) {
    header('Location: ' . store_url('products.php'), true, 301);
    exit;
}

$product_id = (int)$_GET['id'];

// Fetch product
$product = $db->query("SELECT * FROM products WHERE id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC);
if (!$product || (isset($product['listing_status']) && $product['listing_status'] !== '' && $product['listing_status'] !== 'published') || (int)$product['status'] !== 1) {
    ob_end_clean();
    require __DIR__ . '/404.php';
    exit;
}
$reviewSummary = $db->fetch('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count FROM product_reviews WHERE product_id = ? AND is_approved = 1', [$product_id]);

// Fetch images (ordered by display_order)
$images = $db->query("SELECT * FROM product_images WHERE product_id = :id ORDER BY display_order ASC", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);

// Separate main image
$main_image = null;
$gallery_images = [];
$all_images = []; // Keep all images with their IDs for variation linking

foreach ($images as $img) {
    $all_images[] = $img; // Store all images with IDs
    if ($img['is_main']) {
        $main_image = $img['image_path'];
    } else {
        $gallery_images[] = $img['image_path'];
    }
}

// Fetch variations with image links
$variations = $db->query("
    SELECT pv.*, pi.image_path, pi.id as image_id_ref
    FROM product_variations pv 
    LEFT JOIN product_images pi ON pv.image_id = pi.id
    WHERE pv.product_id = :id
    ORDER BY pi.display_order ASC, pv.id ASC
", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);

// Fetch category and brand for breadcrumbs and SEO
$category = $db->query("SELECT name_en, name_ar, name_ru, name_sp FROM categories WHERE id = :id", ['id' => $product['category_id']])->fetch(PDO::FETCH_ASSOC);
$brand = $db->query("SELECT name_en, name_ar, name_ru, name_sp FROM brands WHERE id = :id", ['id' => $product['brand_id']])->fetch(PDO::FETCH_ASSOC);

// Fetch related products (same category)
$same_category_products = $db->query(" 
    SELECT 
        p.id,
        p.name_en,
        p.name_ar,
        p.name_ru,
        p.name_sp,
        p.price,
        p.sale_price,
        p.stock,
        p.category_id,
        p.brand_id,
        c.name_en AS category_name_en,
        c.name_ar AS category_name_ar,
        c.name_ru AS category_name_ru,
        c.name_sp AS category_name_sp,
        b.name_en AS brand_name_en,
        b.name_ar AS brand_name_ar,
        b.name_ru AS brand_name_ru,
        b.name_sp AS brand_name_sp,
        COALESCE(
            (
                SELECT pi.image_path
                FROM product_images pi
                WHERE pi.product_id = p.id AND pi.is_main = 1
                ORDER BY pi.display_order ASC, pi.id ASC
                LIMIT 1
            ),
            (
                SELECT pi2.image_path
                FROM product_images pi2
                WHERE pi2.product_id = p.id
                ORDER BY pi2.display_order ASC, pi2.id ASC
                LIMIT 1
            ),
            'uploads/default-product.jpg'
        ) AS main_image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.category_id = :category_id
      AND p.id != :current_id
    AND p.status = 1
    AND (p.listing_status IS NULL OR p.listing_status = '' OR p.listing_status = 'published')
    ORDER BY " . visit_order_sql('p') . "
    LIMIT 6
", [
    'category_id' => (int)$product['category_id'],
    'current_id' => $product_id
])->fetchAll(PDO::FETCH_ASSOC);

// Fetch related products (same brand)
$same_brand_products = [];
if (!empty($product['brand_id'])) {
    $same_brand_products = $db->query(" 
        SELECT 
            p.id,
            p.name_en,
            p.name_ar,
            p.name_ru,
            p.name_sp,
            p.price,
            p.sale_price,
            p.stock,
            p.category_id,
            p.brand_id,
            c.name_en AS category_name_en,
            c.name_ar AS category_name_ar,
            c.name_ru AS category_name_ru,
            c.name_sp AS category_name_sp,
            b.name_en AS brand_name_en,
            b.name_ar AS brand_name_ar,
            b.name_ru AS brand_name_ru,
            b.name_sp AS brand_name_sp,
            COALESCE(
                (
                    SELECT pi.image_path
                    FROM product_images pi
                    WHERE pi.product_id = p.id AND pi.is_main = 1
                    ORDER BY pi.display_order ASC, pi.id ASC
                    LIMIT 1
                ),
                (
                    SELECT pi2.image_path
                    FROM product_images pi2
                    WHERE pi2.product_id = p.id
                    ORDER BY pi2.display_order ASC, pi2.id ASC
                    LIMIT 1
                ),
                'uploads/default-product.jpg'
            ) AS main_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.brand_id = :brand_id
          AND p.id != :current_id
        AND p.status = 1
        AND (p.listing_status IS NULL OR p.listing_status = '' OR p.listing_status = 'published')
        ORDER BY " . visit_order_sql('p') . "
        LIMIT 3
    ", [
        'brand_id' => (int)$product['brand_id'],
        'current_id' => $product_id
    ])->fetchAll(PDO::FETCH_ASSOC);
}

// Deduplicate: remove products from category list that appear in brand list
$brand_product_ids = array_map(function($p) { return (int)$p['id']; }, $same_brand_products);
$same_category_products = array_filter($same_category_products, function($p) use ($brand_product_ids) {
    return !in_array((int)$p['id'], $brand_product_ids);
});
$same_category_products = array_values($same_category_products); // Re-index array

// Build SEO data
$product_name_raw = loc_field($product, 'name');
$product_description_raw = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(loc_field($product, 'description')), ENT_QUOTES, 'UTF-8')));
$category_name_raw = $category ? loc_field($category, 'name') : t('product.default_category');
$brand_name_raw = $brand ? loc_field($brand, 'name') : 'Aleppo Gift';

if ($product_description_raw === '') {
    $product_description_raw = t('product.default_description', ['name' => $product_name_raw, 'category' => $category_name_raw]);
}

$product_meta_description_raw = $product_description_raw;
if (mb_strlen($product_meta_description_raw, 'UTF-8') > 155) {
    $product_meta_description_raw = rtrim(mb_substr($product_meta_description_raw, 0, 152, 'UTF-8')) . '...';
} else {
    $product_meta_description_raw = trim($product_meta_description_raw . ' ' . t('product.meta_suffix'));
}
$product_name = htmlspecialchars($product_name_raw, ENT_QUOTES, 'UTF-8');
$product_description = htmlspecialchars($product_description_raw, ENT_QUOTES, 'UTF-8');
$product_meta_description = htmlspecialchars($product_meta_description_raw, ENT_QUOTES, 'UTF-8');
$base_public_url = "https://aleppogift.com";
$products_url = $base_public_url . "/products.php";
$product_url = $base_public_url . "/product.php?id=" . $product_id;
$product_image = $main_image ? "https://aleppogift.com/" . $main_image : "https://aleppogift.com/assets/images/og-image.jpg";
$original_price = (float)$product['price'];
$price = product_effective_price($product);
$on_sale = product_is_on_sale($product);
$in_stock = $product['stock'] > 0 ? 'InStock' : 'OutOfStock';
$category_name = htmlspecialchars($category_name_raw, ENT_QUOTES, 'UTF-8');
$brand_name = htmlspecialchars($brand_name_raw, ENT_QUOTES, 'UTF-8');

// Build structured data with json_encode to ensure valid JSON-LD output.
$schema_images = [$product_image];
foreach ($gallery_images as $img) {
    $schema_images[] = 'https://aleppogift.com/' . ltrim($img, '/');
}

$product_schema = [
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $product_name_raw,
    'image' => $schema_images,
    'description' => $product_description_raw,
    'sku' => (string)$product_id,
    'brand' => [
        '@type' => 'Brand',
        'name' => html_entity_decode($brand_name, ENT_QUOTES, 'UTF-8')
    ],
    'offers' => [
        '@type' => 'Offer',
        'url' => $product_url,
        'priceCurrency' => 'AED',
        'price' => number_format($price, 2, '.', ''),
        'availability' => 'https://schema.org/' . $in_stock,
        'itemCondition' => 'https://schema.org/NewCondition'
    ]
];

if ($on_sale) {
    $product_schema['offers']['priceValidUntil'] = date('Y-12-31');
}
if (!empty($reviewSummary['review_count'])) {
    $product_schema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => (string)$reviewSummary['avg_rating'],
        'reviewCount' => (string)$reviewSummary['review_count'],
    ];
}

if (!empty($category_name)) {
    $product_schema['category'] = html_entity_decode($category_name, ENT_QUOTES, 'UTF-8');
}

$breadcrumb_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => t('home'),
            'item' => 'https://aleppogift.com/'
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => t('product.products'),
            'item' => $products_url
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => html_entity_decode($category_name, ENT_QUOTES, 'UTF-8'),
            'item' => $products_url . '?category=' . (int)$product['category_id']
        ],
        [
            '@type' => 'ListItem',
            'position' => 4,
            'name' => $product_name_raw,
            'item' => $product_url
        ]
    ]
];

$jsonLdFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('product.page_title', ['name' => $product_name_raw, 'category' => $category_name_raw]) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo $product_meta_description; ?>">
    <meta name="keywords" content="<?= te('product.meta_keywords', ['name' => $product_name_raw, 'category' => $category_name_raw, 'brand' => $brand_name_raw]) ?>">
    <meta name="author" content="Aleppo Gift">
    <meta name="robots" content="index, follow">
    <?= seo_lang_links($product_url) ?>
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo $product_name; ?> | <?php echo $category_name; ?> - Aleppo Gift">
    <meta property="og:description" content="<?php echo $product_meta_description; ?>">
    <meta property="og:image" content="<?php echo $product_image; ?>">
    <meta property="og:url" content="<?= htmlspecialchars(seo_canonical_url($product_url)) ?>">
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="Aleppo Gift">
    <meta property="og:price:amount" content="<?php echo number_format($price, 2); ?>">
    <meta property="og:price:currency" content="AED">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $product_name; ?> | <?php echo $category_name; ?> - Aleppo Gift">
    <meta name="twitter:description" content="<?php echo $product_meta_description; ?>">
    <meta name="twitter:image" content="<?php echo $product_image; ?>">
    
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/product.css')) ?>?v=20261006a">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@500;600&display=swap" rel="stylesheet">
    
    <!-- Product Schema.org Structured Data -->
    <script type="application/ld+json">
        <?php echo json_encode($product_schema, $jsonLdFlags); ?>
    </script>
    
    <!-- Breadcrumb Schema -->
    <script type="application/ld+json">
        <?php echo json_encode($breadcrumb_schema, $jsonLdFlags); ?>
    </script>

    <style>
        .product-page {
            background: linear-gradient(180deg, #fff8ee 0%, #f6efe6 100%);
            min-height: 100vh;
            padding: 2rem 0 3rem;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .product-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .image-gallery {
            position: relative;
            background: #f8f9fa;
        }
        
        .main-image-wrapper {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            background: white;
            border-radius: 15px;
            margin: 1rem;
        }
        
        .main-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            cursor: zoom-in;
        }
        
        .main-product-image:hover {
            transform: scale(1.05);
        }
        
        .thumbnail-strip {
            padding: 1rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .thumbnail-item {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .thumbnail-item:hover,
        .thumbnail-item.active {
            border-color: #E67B2E;
            transform: scale(1.05);
        }
        
        .thumbnail-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details {
            padding: 1.75rem 1.85rem 1.6rem;
            container-type: inline-size;
        }

        .product-heading {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.2rem;
            margin-bottom: 0.85rem;
        }
        
        .product-page .product-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.7rem, 2.4vw, 2.15rem);
            font-weight: 700;
            color: #2c1505;
            margin: 0;
            line-height: 1.15;
            display: block;
            overflow: visible;
            -webkit-line-clamp: unset;
        }

        .product-title-ar {
            margin: 0;
            align-self: flex-end;
            max-width: 100%;
            font-family: "Noto Naskh Arabic", "Segoe UI", Tahoma, sans-serif;
            font-size: 1.15rem;
            font-weight: 600;
            color: #9a7048;
            line-height: 1.6;
        }

        .product-meta-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .product-category-badge,
        .product-brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.32rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .product-category-badge {
            background: #fff4eb;
            color: #c45e16;
            border-color: rgba(230, 123, 46, 0.28);
        }

        .product-brand-badge {
            background: #f3faf5;
            color: #1b7a43;
            border-color: rgba(27, 122, 67, 0.22);
        }

        .product-category-badge i,
        .product-brand-badge i {
            font-size: 0.75rem;
        }

        .product-category-badge a:hover,
        .product-brand-badge a:hover {
            text-decoration: underline !important;
        }
        
        .product-page .product-price {
            display: flex;
            align-items: center;
            flex-direction: row;
            gap: 0.75rem;
            margin: 0 0 1.15rem;
            padding: 0.8rem 1rem;
            background: linear-gradient(135deg, #fff8f2 0%, #fffdfb 72%);
            border: 1px solid rgba(230, 123, 46, 0.2);
            border-radius: 14px;
        }
        
        .product-page .price-main {
            display: inline-flex;
            align-items: center;
            gap: 0.12em;
            font-size: 2.15rem;
            font-weight: 700;
            color: #E67B2E;
            line-height: 1;
            letter-spacing: -0.03em;
            background: transparent;
            padding: 0;
        }

        .product-page .price-old {
            display: inline-flex;
            align-items: center;
            gap: 0.12em;
            font-size: 1.15rem;
            font-weight: 500;
            color: #9a8f86;
            text-decoration: line-through;
            line-height: 1;
        }

        .product-page .sale-tag {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9a3412;
        }

        .product-page .price-main .uae-symbol {
            width: 0.62em !important;
            height: 0.62em !important;
            margin: 0 0.02em 0 0 !important;
            vertical-align: -0.06em !important;
            filter: invert(55%) sepia(72%) saturate(620%) hue-rotate(348deg) brightness(96%) contrast(92%);
        }
        
        .product-page .price-usd {
            font-size: 0.92rem;
            font-weight: 600;
            color: #8d6b58;
            background: #fff;
            border: 1px solid #efe4da;
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            line-height: 1.2;
        }
        
        .product-description {
            background: #fffdfb;
            border-radius: 14px;
            padding: 1.1rem 1.15rem;
            margin-bottom: 1rem;
            border: 1px solid #efe4da;
            border-left: 3px solid #E67B2E;
        }

        .product-description h5 {
            color: #5c4636;
            font-size: 0.95rem;
            margin-bottom: 0.45rem;
        }
        
        .product-specifications {
            background: transparent;
            padding: 0;
            border: 0;
            border-radius: 0;
            margin-bottom: 1rem !important;
        }
        
        .product-specifications h6 {
            color: #8a6a52;
            font-weight: 700;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.65rem;
        }

        .spec-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .spec-card {
            background: #fff;
            border: 1px solid #efe4da;
            border-radius: 12px;
            padding: 0.7rem 0.75rem 0.65rem;
            min-width: 0;
        }

        .spec-icon {
            display: inline-flex;
            color: #E67B2E;
            font-size: 0.82rem;
            margin-bottom: 0.28rem;
        }
        
        .spec-label {
            display: block;
            font-weight: 600;
            font-size: 0.68rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #9a8172;
            margin-bottom: 0.12rem;
        }
        
        .spec-value {
            display: block;
            font-weight: 700;
            font-size: 0.92rem;
            color: #2c2118;
            line-height: 1.3;
        }

        .spec-value.in-stock {
            color: #1b7a43;
        }

        .spec-value.out-stock {
            color: #c0392b;
        }
        
        .product-page .add-to-cart-form {
            background: #fffdfb;
            border: 1px solid #efe4da;
            border-radius: 16px;
            padding: 1rem 1rem 0.9rem;
            box-shadow: none;
        }

        .product-options {
            margin-bottom: 0.85rem;
        }
        
        .option-group {
            margin-bottom: 0.85rem;
        }
        
        .option-label {
            font-weight: 700;
            color: #c45e16;
            margin-bottom: 0.4rem;
            display: block;
            font-size: 0.88rem;
        }
        
        .product-page .option-select {
            width: 100%;
            appearance: none;
            -webkit-appearance: none;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%23C45E16' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.9rem center;
            background-size: 14px;
            padding: 0.78rem 2.4rem 0.78rem 0.9rem;
            border: 1.5px solid #eadfd4;
            border-radius: 12px;
            font-size: 0.98rem;
            font-weight: 600;
            color: #2c2118;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .option-select:focus {
            outline: none;
            border-color: #E67B2E;
            box-shadow: 0 0 0 3px rgba(230, 123, 46, 0.16);
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0;
        }

        .qty-stepper {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid #eadfd4;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .qty-btn {
            width: 40px;
            height: 42px;
            border: 0;
            background: #fff6ee;
            color: #c45e16;
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }

        .qty-btn:hover {
            background: #ffe8d4;
        }
        
        .product-page .quantity-input {
            width: 52px;
            height: 42px;
            padding: 0;
            border: 0;
            border-radius: 0;
            text-align: center;
            font-size: 1rem;
            font-weight: 700;
            color: #2c2118;
            background: #fff;
            -moz-appearance: textfield;
        }

        .product-page .quantity-input:focus {
            outline: none;
        }

        .product-page .quantity-input::-webkit-outer-spin-button,
        .product-page .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            margin-top: 0.95rem;
        }
        
        .product-page .btn-add-cart {
            width: 100%;
            background: linear-gradient(135deg, #f08a3a 0%, #d86a1e 100%) !important;
            border: none !important;
            color: white !important;
            padding: 0.95rem 1.25rem !important;
            border-radius: 14px !important;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 10px 20px rgba(216, 106, 30, 0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .product-page .btn-add-cart:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(216, 106, 30, 0.34);
            color: white !important;
        }
        
        .product-page .secondary-actions {
            display: grid;
            grid-template-columns: 1.25fr 1fr 1fr;
            gap: 0.5rem;
        }
        
        .product-page .secondary-actions .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-height: 44px;
            padding: 0.5rem 0.55rem;
            border: 1.5px solid #eadfd4;
            background: #fff;
            color: #6d5648;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            line-height: 1.2;
            text-align: center;
            white-space: normal;
            transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease;
        }
        
        .product-page .secondary-actions .btn-secondary:hover {
            border-color: #E67B2E;
            color: #c45e16;
            background: #fff8f2;
            transform: none;
        }
        
        .product-page .secondary-actions .whatsapp-btn {
            background: #25d366;
            border-color: #25d366;
            color: #fff;
        }
        
        .product-page .secondary-actions .whatsapp-btn:hover {
            background: #1ebe5d;
            border-color: #1ebe5d;
            color: #fff;
        }

        .purchase-assurance {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.9rem;
            list-style: none;
            margin: 0.85rem 0 0;
            padding: 0.75rem 0 0;
            border-top: 1px dashed #efe0d4;
            color: #7a6558;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .purchase-assurance li {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .purchase-assurance i {
            color: #E67B2E;
        }

        .related-section {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .related-block {
            background: #ffffff;
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
        }

        .related-title {
            font-family: 'Playfair Display', serif;
            color: #E67B2E;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .related-card {
            background: #fff;
            border: 1px solid #eef1f4;
            border-radius: 14px;
            height: 100%;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .related-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.08);
        }

        .related-image-wrap {
            height: 190px;
            background: #f8f9fa;
            overflow: hidden;
        }

        .related-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .related-body {
            padding: 1rem;
        }

        .related-name {
            font-weight: 600;
            color: #343a40;
            line-height: 1.35;
            min-height: 2.7em;
            margin-bottom: 0.5rem;
        }

        .related-meta {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .related-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .related-price {
            color: #E67B2E;
            font-weight: 700;
        }

        .related-stock {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .related-stock.in {
            color: #198754;
        }

        .related-stock.out {
            color: #dc3545;
        }

        .related-view-btn {
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
        }

        .related-empty {
            background: #f8f9fa;
            border: 1px dashed #d5dbe1;
            border-radius: 10px;
            color: #6c757d;
            padding: 1rem;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .product-page .product-title {
                font-size: 1.55rem;
            }

            .product-heading {
                gap: 0.15rem;
            }
            
            .product-page .price-main {
                font-size: 1.7rem;
            }
            
            .product-details {
                padding: 1.1rem;
            }

            .related-image-wrap {
                height: 170px;
            }
        }

        @container (max-width: 400px) {
            .spec-grid,
            .product-page .secondary-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <div class="product-page">
        <div class="container">
            <!-- Breadcrumb Navigation -->
            <nav class="breadcrumb-nav" aria-label="<?= te('product.products') ?>">
                <div class="product-crumb-row">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home" aria-hidden="true"></i> <?= te('home') ?></a></li>
                        <li class="breadcrumb-item"><a href="products.php"><?= te('product.products') ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars(loc_field($product, 'name')); ?></li>
                    </ol>
                    <a href="products.php" class="btn btn-outline-primary btn-sm product-back-link">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> <?= te('product.back_to_products') ?>
                    </a>
                </div>
            </nav>

            <!-- Main Product Container -->
            <div class="product-container">
                <div class="row g-0">
                    <!-- Image Gallery -->
                    <div class="col-lg-6">
                        <div class="image-gallery">
                            <?php 
                            // Determine the default image to display
                            $default_image = $main_image;
                            // If variations exist, use the first variation's image if available
                            if (!empty($variations) && !empty($variations[0]['image_path'])) {
                                $default_image = $variations[0]['image_path'];
                            }
                            ?>
                            <?php if ($default_image): ?>
                                <div class="main-image-wrapper">
                                    <img id="mainProductImage" 
                                         src="<?php echo str_replace("../", "", $default_image); ?>" 
                                         alt="<?= te('product.main_image_alt', ['name' => $product_name_raw, 'brand' => $brand_name_raw, 'category' => $category_name_raw]) ?>" 
                                         class="main-product-image">
                                </div>
                            <?php endif; ?>

                            <?php if ($gallery_images): ?>
                                <div class="thumbnail-strip">
                                    <?php 
                                    // Display all images including main image
                                    foreach ($all_images as $index => $img): 
                                        $img_path = str_replace("../", "", $img['image_path']);
                                        
                                        // Set active class for the first variant's image if variations exist
                                        $is_active = '';
                                        if (!empty($variations) && isset($variations[0]['image_id']) && $variations[0]['image_id'] == $img['id']) {
                                            $is_active = 'active';
                                        } elseif (empty($variations) && $img['is_main']) {
                                            // Fallback to main image if no variations
                                            $is_active = 'active';
                                        }
                                        
                                        // Find variation linked to this image
                                        $linked_variation_id = null;
                                        foreach ($variations as $var) {
                                            if ($var['image_id'] == $img['id']) {
                                                $linked_variation_id = $var['id'];
                                                break;
                                            }
                                        }
                                    ?>
                                        <div class="thumbnail-item <?php echo $is_active; ?>" 
                                             data-image-id="<?php echo $img['id']; ?>"
                                             data-variation-id="<?php echo $linked_variation_id ?? ''; ?>"
                                            onclick="selectImageVariation(this, '<?php echo $img_path; ?>', <?php echo $img['id']; ?>, <?php echo $linked_variation_id ?? 'null'; ?>)">
                                            <img src="<?php echo $img_path; ?>" 
                                                 alt="<?= te('product.thumb_alt', ['name' => $product_name_raw, 'n' => $index + 1, 'total' => count($all_images)]) ?>" class="thumbnail-image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Product Details -->
                    <div class="col-lg-6">
                        <div class="product-details">
                            <div class="product-heading">
                                <h1 class="product-title"><?php echo htmlspecialchars(loc_field($product, 'name')); ?></h1>

                                <?php if (store_lang() === 'en' && !is_placeholder_arabic_name($product['name_ar'] ?? '')): ?>
                                    <p class="product-title-ar" dir="rtl" lang="ar">
                                        <?php echo htmlspecialchars($product['name_ar']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="product-meta-info">
                                <?php if (!empty($category_name_raw) && !is_placeholder_name(is_array($category) ? ($category['name_en'] ?? '') : '')): ?>
                                    <div class="product-category-badge">
                                        <i class="fas fa-layer-group"></i>
                                        <a href="products.php?category=<?= (int)$product['category_id'] ?>" class="text-decoration-none" style="color: inherit;">
                                            <?= htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($brand_name) && $brand_name !== 'Aleppo Gift'): ?>
                                    <div class="product-brand-badge">
                                        <i class="fas fa-certificate"></i>
                                        <a href="products.php?brand=<?= (int)$product['brand_id'] ?>" class="text-decoration-none" style="color: inherit;">
                                            <?= htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($show_prices): ?>
                            <div class="product-price<?= $on_sale ? ' is-offer' : '' ?>">
                                <?php if ($on_sale): ?>
                                    <span class="sale-tag"><?= te('sale') ?></span>
                                    <?= format_store_price_pair($original_price, 'price-old', null, false) ?>
                                <?php endif; ?>
                                <?= format_store_price_pair((float)$price, 'price-main') ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($product['description_en'])): ?>
                                <div class="product-description">
                                    <h5><i class="fas fa-info-circle me-2"></i><?= te('product.description_heading') ?></h5>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($product['description_en'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Product Specifications -->
                            <div class="product-specifications">
                                <h6><i class="fas fa-list-ul me-2"></i><?= te('product.details_heading') ?></h6>
                                <div class="spec-grid">
                                    <div class="spec-card">
                                        <span class="spec-icon"><i class="fas fa-boxes-stacked"></i></span>
                                        <span class="spec-label"><?= te('product.stock') ?></span>
                                        <span class="spec-value <?php echo $product['stock'] > 0 ? 'in-stock' : 'out-stock'; ?>">
                                            <?php echo $product['stock'] > 0 ? te('product.available_count', ['count' => $product['stock']]) : te('out_of_stock'); ?>
                                        </span>
                                    </div>
                                    <div class="spec-card">
                                        <span class="spec-icon"><i class="fas fa-weight-hanging"></i></span>
                                        <span class="spec-label"><?= te('product.weight') ?></span>
                                        <span class="spec-value"><?= te('product.weight_kg', ['weight' => number_format($product['weight'] ?? 0, 3)]) ?></span>
                                    </div>
                                    <?php if (!empty($product['sku'])): ?>
                                    <div class="spec-card">
                                        <span class="spec-icon"><i class="fas fa-barcode"></i></span>
                                        <span class="spec-label"><?= te('product.sku') ?></span>
                                        <span class="spec-value"><?php echo htmlspecialchars($product['sku']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form method="post" action="ajax/add_to_cart.php" class="add-to-cart-form purchase-panel">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                                <div class="product-options">
                                    <?php if ($variations): ?>
                                        <div class="option-group">
                                            <label class="option-label">
                                                <i class="fas fa-cog me-1"></i><?= te('product.choose_variation') ?>
                                            </label>
                                            <select name="variation_id" id="variationSelect" class="option-select" required>
                                                <?php 
                                                foreach ($variations as $index => $var): 
                                                    $is_first = ($index === 0);
                                                    $selected = $is_first ? 'selected' : '';
                                                    $var_image_path = !empty($var['image_path']) ? str_replace("../", "", $var['image_path']) : '';
                                                ?>
                                                    <option value="<?php echo $var['id']; ?>" 
                                                            data-image-id="<?php echo $var['image_id'] ?? ''; ?>"
                                                            data-image-path="<?php echo $var_image_path; ?>"
                                                            <?php echo $selected; ?>>
                                                        <?php 
                                                        // Display variation name
                                                        $var_name = [];
                                                        if (!empty($var['color'])) $var_name[] = $var['color'];
                                                        if (!empty($var['size'])) $var_name[] = t('product.size_value', ['size' => $var['size']]);
                                                        echo htmlspecialchars(implode(' - ', $var_name));
                                                        
                                                        if ($var['additional_price'] > 0) {
                                                            echo ' (+' . format_store_money((float)$var['additional_price']) . ')';
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>

                                    <div class="quantity-selector">
                                        <label class="option-label mb-0" for="productQuantity"><?= te('product.quantity') ?></label>
                                        <div class="qty-stepper">
                                            <button type="button" class="qty-btn" data-qty="-1" aria-label="<?= te('product.decrease_qty') ?>">−</button>
                                            <input type="number" id="productQuantity" name="quantity" value="1" min="1" max="99" class="quantity-input" inputmode="numeric">
                                            <button type="button" class="qty-btn" data-qty="1" aria-label="<?= te('product.increase_qty') ?>">+</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <button type="submit" name="add_to_cart" class="btn btn-add-cart">
                                        <i class="fas fa-shopping-cart"></i>
                                        <?= te('add_cart') ?>
                                    </button>

                                    <div class="secondary-actions">
                                        <a href="https://wa.me/971561125320?text=<?= urlencode(t('product.whatsapp_message', [
                                           'name' => $product['name_en'], 'price' => number_format((float)$price, 0), 'url' => $product_url])) ?>" 
                                           target="_blank" class="btn btn-secondary whatsapp-btn">
                                            <i class="fab fa-whatsapp me-1"></i><?= te('product.whatsapp_inquiry') ?>
                                        </a>
                                        
                                        <button type="button" class="btn btn-secondary share-btn" 
                                                onclick="shareProduct(<?php echo json_encode($product['name_en'] ?? '', $jsonLdFlags); ?>, <?php echo json_encode(number_format((float)$price, 2), $jsonLdFlags); ?>, <?php echo json_encode((string)$product['id'], $jsonLdFlags); ?>, <?php echo json_encode($product_url, $jsonLdFlags); ?>)">
                                            <i class="fas fa-share-alt me-1"></i><?= te('product.share') ?>
                                        </button>
                                        
                                        <button type="button" class="btn btn-secondary btn-wishlist" 
                                                data-id="<?= $product['id']; ?>">
                                            <i class="far fa-heart me-1"></i><?= te('wishlist') ?>
                                        </button>
                                    </div>
                                </div>
                                <ul class="purchase-assurance">
                                    <li><i class="fas fa-truck-fast"></i> <?= te('product.same_day_uae') ?></li>
                                    <li><i class="fas fa-lock"></i> <?= te('product.secure_checkout') ?></li>
                                    <li><i class="fas fa-rotate-left"></i> <?= te('product.returns_7day') ?></li>
                                </ul>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products: Same Category -->
            <section class="related-section">
                <div class="related-block">
                    <h3 class="related-title">
                        <i class="fas fa-layer-group me-2"></i><?= te('product.more_from_category') ?>
                    </h3>
                    <?php if (!empty($same_category_products)): ?>
                        <div class="product-grid tg-related-grid">
                            <?php foreach ($same_category_products as $p): ?>
                                <?php include __DIR__ . '/../includes/product_card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="related-empty"><?= te('product.no_category_products') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($same_category_products) || !empty($same_category_products)): ?>
                        <div class="mt-3 text-center">
                            <a href="products.php?category=<?= (int)$product['category_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-folder-open me-1"></i><?= te('product.view_all_in', ['category' => $category_name_raw]) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Related Products: Same Brand -->
            <section class="related-section">
                <div class="related-block">
                    <h3 class="related-title">
                        <i class="fas fa-certificate me-2"></i><?= te('product.more_from_brand') ?>
                    </h3>
                    <?php if (!empty($same_brand_products)): ?>
                        <div class="product-grid tg-related-grid">
                            <?php foreach ($same_brand_products as $p): ?>
                                <?php include __DIR__ . '/../includes/product_card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="related-empty"><?= te('product.no_brand_products') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($same_brand_products)): ?>
                        <div class="mt-3 text-center">
                            <a href="products.php?brand=<?= (int)$product['brand_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-certificate me-1"></i><?= te('product.view_all_brand', ['brand' => $brand_name_raw]) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Additional Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card tg-product-services">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <i class="fas fa-shipping-fast fa-2x text-primary mb-2"></i>
                                    <h6><?= te('product.fast_delivery') ?></h6>
                                    <small class="text-muted"><?= te('product.fast_delivery_text') ?></small>
                                </div>
                                <div class="col-md-3">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                    <h6><?= te('product.secure_payment') ?></h6>
                                    <small class="text-muted"><?= te('product.secure_payment_text') ?></small>
                                </div>
                                <div class="col-md-3">
                                    <i class="fas fa-undo fa-2x text-warning mb-2"></i>
                                    <h6><?= te('product.easy_returns') ?></h6>
                                    <small class="text-muted"><?= te('product.easy_returns_text') ?></small>
                                </div>
                                <div class="col-md-3">
                                    <i class="fas fa-headset fa-2x text-info mb-2"></i>
                                    <h6><?= te('product.support') ?></h6>
                                    <small class="text-muted"><?= te('product.support_text') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/product_reviews_block.php'; ?>
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=20261016qty"></script>
    <script src="assets/js/enhanced-main.js?v=20261012i18n"></script>
    
    <script>
        const productI18n = <?= json_encode([
            'shareText' => t('product.share_text'),
            'linkCopied' => t('product.link_copied'),
            'addingToCart' => t('product.adding_to_cart'),
            'addedToCart' => t('product.added_to_cart'),
            'addedToCartBtn' => t('product.added_to_cart_btn'),
            'addedSuccess' => t('product.added_success'),
            'errorTryAgain' => t('product.error_try_again'),
        ], $jsonLdFlags) ?>;

        // Function to select image and its corresponding variation
        function selectImageVariation(triggerElement, imageSrc, imageId, variationId) {
            // Change main image
            const mainImage = document.getElementById('mainProductImage');
            if (mainImage) {
                mainImage.src = imageSrc;
            }
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-item').forEach(item => {
                item.classList.remove('active');
            });
            if (triggerElement && triggerElement.classList) {
                triggerElement.classList.add('active');
            }
            
            // Auto-select the variation in dropdown if variation exists
            if (variationId) {
                const variationSelect = document.getElementById('variationSelect');
                if (variationSelect) {
                    variationSelect.value = variationId;
                    
                    // Highlight the select to show it was auto-selected
                    variationSelect.style.borderColor = '#E67B2E';
                    variationSelect.style.backgroundColor = '#fff3e0';
                    
                    setTimeout(() => {
                        variationSelect.style.borderColor = '';
                        variationSelect.style.backgroundColor = '';
                    }, 1000);
                }
            }
        }
        
        // Legacy function for backwards compatibility
        function changeMainImage(newSrc, triggerElement = null) {
            const mainImage = document.getElementById('mainProductImage');
            if (mainImage) {
                mainImage.src = newSrc;
            }
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-item').forEach(item => {
                item.classList.remove('active');
            });
            if (triggerElement && triggerElement.classList) {
                triggerElement.classList.add('active');
            }
        }

        // Fallback share handler for pages that do not load index.js share helpers.
        if (typeof window.shareProduct !== 'function') {
            window.shareProduct = function(name, price, id, url) {
                const text = `${name} - AED ${price} - ${url}`;

                if (navigator.share) {
                    navigator.share({
                        title: name,
                        text: productI18n.shareText.replace('{name}', name),
                        url: url
                    }).catch(() => {
                        // Ignore user-cancelled share dialog.
                    });
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        if (typeof showNotification === 'function') {
                            showNotification(productI18n.linkCopied, 'success');
                        } else {
                            alert(productI18n.linkCopied);
                        }
                    }).catch(function() {
                        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
                    });
                } else {
                    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
                }
            };
        }

        // Handle variation change to update main image
        $(document).ready(function() {
            document.querySelectorAll('.qty-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const input = this.closest('.qty-stepper').querySelector('.quantity-input');
                    if (!input) {
                        return;
                    }
                    const delta = parseInt(this.getAttribute('data-qty'), 10) || 0;
                    const min = parseInt(input.min, 10) || 1;
                    const max = parseInt(input.max, 10) || 99;
                    const current = parseInt(input.value, 10) || min;
                    input.value = Math.min(max, Math.max(min, current + delta));
                });
            });

            // Add event listener for variation select change
            $('#variationSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const imagePath = selectedOption.data('image-path');
                const imageId = selectedOption.data('image-id');
                
                // Update main image if variation has an associated image
                if (imagePath) {
                    $('#mainProductImage').attr('src', imagePath);
                    
                    // Update active thumbnail if image ID is available
                    if (imageId) {
                        $('.thumbnail-item').removeClass('active');
                        $('.thumbnail-item[data-image-id="' + imageId + '"]').addClass('active');
                    }
                }
            });
            
            // Enhanced add to cart functionality
            $('.add-to-cart-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const button = form.find('button[type="submit"]');
                const originalText = button.html();
                
                // Show loading state
                button.prop('disabled', true);
                button.html('<i class="fas fa-spinner fa-spin me-2"></i>' + productI18n.addingToCart);
                
                // Submit form
                $.post('ajax/add_to_cart.php', form.serialize())
                    .done(function(response) {
                        if (response.success) {
                            // Update cart count if exists
                            const addedCount = response.count != null ? response.count : (response.cart_count || 0);
                            if (typeof window.updateCartCount === 'function') {
                                window.updateCartCount(addedCount);
                            } else if ($('#cart-count-mobile').length) {
                                $('#cart-count-mobile').text(addedCount);
                            }
                            if (typeof window.showToast === 'function') {
                                window.showToast(response.message || productI18n.addedToCart, 'success');
                            }
                            if (typeof window.loadCartPreview === 'function') {
                                window.loadCartPreview();
                            }
                            
                            // Show success message
                            button.html('<i class="fas fa-check me-2"></i>' + productI18n.addedToCartBtn);
                            button.removeClass('btn-add-cart').addClass('btn-success');
                            
                            // Show notification
                            if (typeof showNotification === 'function') {
                                showNotification(productI18n.addedSuccess, 'success');
                            }
                            
                            // Reset button after 3 seconds
                            setTimeout(function() {
                                button.html(originalText);
                                button.removeClass('btn-success').addClass('btn-add-cart');
                                button.prop('disabled', false);
                            }, 3000);
                            
                        } else {
                            throw new Error(response.message || 'Unknown error');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Add to cart error:', error);
                        button.html('<i class="fas fa-exclamation-triangle me-2"></i>' + productI18n.errorTryAgain);
                        button.removeClass('btn-add-cart').addClass('btn-danger');
                        
                        setTimeout(function() {
                            button.html(originalText);
                            button.removeClass('btn-danger').addClass('btn-add-cart');
                            button.prop('disabled', false);
                        }, 3000);
                    });
            });
        });
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>