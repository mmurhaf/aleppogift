<?php
/**
 * Dynamic Sitemap Generator for Aleppo Gift
 * Served at /sitemap.xml (rewrite in .htaccess) and /sitemap.php.
 * URLs must match the canonical URLs emitted by the storefront pages.
 */

error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/xml; charset=utf-8');

$db = null;
try {
    require_once(__DIR__ . '/includes/bootstrap.php');
    if (!class_exists('Database')) {
        require_once(__DIR__ . '/includes/Database.php');
    }
    $db = new Database();
} catch (Throwable $e) {
    $db = null;
}

$baseUrl = 'https://aleppogift.com';

function sitemap_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** [store language key => hreflang code], English first. */
function sitemap_languages(): array
{
    if (function_exists('store_languages')) {
        return array_map(function ($meta) {
            return $meta['html'];
        }, store_languages());
    }
    return ['en' => 'en'];
}

function sitemap_lang_url(string $url, string $hreflang): string
{
    if ($hreflang === 'en') {
        return $url;
    }
    return $url . (strpos($url, '?') !== false ? '&' : '?') . 'lang=' . $hreflang;
}

/** Emits one <url> per language version of $loc (the English URL), each listing all alternates. */
function sitemap_url(string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5', array $images = []): void
{
    $languages = sitemap_languages();
    foreach ($languages as $hreflang) {
        echo "\n    <url>";
        echo "\n        <loc>" . sitemap_xml(sitemap_lang_url($loc, $hreflang)) . "</loc>";
        if ($lastmod) {
            echo "\n        <lastmod>" . sitemap_xml($lastmod) . "</lastmod>";
        }
        echo "\n        <changefreq>" . $changefreq . "</changefreq>";
        echo "\n        <priority>" . $priority . "</priority>";
        if (count($languages) > 1) {
            foreach ($languages as $alt) {
                echo "\n        <xhtml:link rel=\"alternate\" hreflang=\"" . $alt . "\" href=\"" . sitemap_xml(sitemap_lang_url($loc, $alt)) . "\"/>";
            }
            echo "\n        <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . sitemap_xml($loc) . "\"/>";
        }
        foreach ($images as $image) {
            echo "\n        <image:image>";
            echo "\n            <image:loc>" . sitemap_xml($image['loc']) . "</image:loc>";
            if (!empty($image['title'])) {
                echo "\n            <image:title>" . sitemap_xml($image['title']) . "</image:title>";
            }
            echo "\n        </image:image>";
        }
        echo "\n    </url>";
    }
}

function sitemap_is_placeholder($name): bool
{
    if (function_exists('is_placeholder_name')) {
        return is_placeholder_name($name);
    }
    return trim((string)$name) === '';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

sitemap_url($baseUrl . '/', date('Y-m-d'), 'daily', '1.0');
sitemap_url($baseUrl . '/products.php', date('Y-m-d'), 'daily', '0.9');
sitemap_url($baseUrl . '/shipping.php', null, 'monthly', '0.5');
sitemap_url($baseUrl . '/about.php', null, 'monthly', '0.4');
sitemap_url($baseUrl . '/contact.php', null, 'monthly', '0.4');

if ($db !== null) {
    try {
        $categories = $db->query("SELECT id, name_en FROM categories WHERE status = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categories as $category) {
            if (sitemap_is_placeholder($category['name_en'] ?? '')) {
                continue;
            }
            sitemap_url($baseUrl . '/products.php?category=' . (int)$category['id'], null, 'weekly', '0.8');
        }
    } catch (Throwable $e) {
    }

    try {
        $brands = $db->query("SELECT id, name_en FROM brands WHERE status = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($brands as $brand) {
            if (sitemap_is_placeholder($brand['name_en'] ?? '')) {
                continue;
            }
            sitemap_url($baseUrl . '/products.php?brand=' . (int)$brand['id'], null, 'weekly', '0.6');
        }
    } catch (Throwable $e) {
    }

    try {
        $products = $db->query("
            SELECT p.id, p.name_en, p.created_at,
                (SELECT pi.image_path FROM product_images pi
                 WHERE pi.product_id = p.id
                 ORDER BY pi.is_main DESC, pi.display_order ASC, pi.id ASC
                 LIMIT 1) AS image_path
            FROM products p
            WHERE p.status = 1
              AND (p.listing_status IS NULL OR p.listing_status = '' OR p.listing_status = 'published')
            ORDER BY p.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as $product) {
            $lastmod = !empty($product['created_at']) ? date('Y-m-d', strtotime($product['created_at'])) : null;
            $images = [];
            if (!empty($product['image_path'])) {
                $images[] = [
                    'loc' => $baseUrl . '/' . ltrim($product['image_path'], '/'),
                    'title' => (string)($product['name_en'] ?? ''),
                ];
            }
            sitemap_url($baseUrl . '/product.php?id=' . (int)$product['id'], $lastmod, 'weekly', '0.8', $images);
        }
    } catch (Throwable $e) {
    }
}

echo "\n</urlset>\n";
