<?php

require_once __DIR__ . '/i18n.php';

function loc_field(array $row, string $base): string
{
    $lang = store_lang();
    $keys = [$base . '_' . $lang, $base . '_en', $base . '_ar'];
    $seen = [];
    foreach ($keys as $key) {
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function visit_mix_seed(): string
{
    $now = time();
    $idle = $now - (int)($_SESSION['visit_mix_at'] ?? 0);
    if (empty($_SESSION['visit_mix_seed']) || $idle > 1800) {
        $_SESSION['visit_mix_seed'] = bin2hex(random_bytes(4));
    }
    $_SESSION['visit_mix_at'] = $now;
    return preg_replace('/[^a-f0-9]/', '', (string)$_SESSION['visit_mix_seed']);
}

function visit_order_sql(string $alias = 'p'): string
{
    $seed = visit_mix_seed();
    return "CRC32(CONCAT($alias.id, '$seed'))";
}

function ag_has_column($db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $row = $db->fetch(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $cache[$key] = ((int)($row['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function published_product_sql(string $alias = 'p'): string
{
    return "($alias.status = 1 AND ($alias.listing_status IS NULL OR $alias.listing_status = '' OR $alias.listing_status = 'published'))";
}

function store_setting($db, string $key, string $default = ''): string
{
    $value = get_site_setting($db, $key, $default);
    return ($value === null || $value === '') ? $default : (string)$value;
}

function free_shipping_threshold($db): float
{
    return max(0, (float)store_setting($db, 'free_shipping_threshold', '500'));
}

function gift_wrap_fee($db): float
{
    $raw = get_site_setting($db, 'gift_wrap_fee', '');
    return $raw === '' || $raw === null ? 40.0 : max(0, (float)$raw);
}

function same_day_fee($db): float
{
    $raw = get_site_setting($db, 'same_day_fee', '');
    return $raw === '' || $raw === null ? 50.0 : max(0, (float)$raw);
}

function vat_settings($db): array
{
    return [
        'enabled' => store_setting($db, 'charge_vat', '0') === '1',
        'rate' => (float)store_setting($db, 'vat_rate', '5'),
        'trn' => store_setting($db, 'vat_trn', ''),
    ];
}

function same_day_city(string $city): bool
{
    $city = strtolower(trim($city));
    $city = str_replace(['-', '_'], ' ', $city);
    return in_array($city, ['dubai', 'abu dhabi', 'abu dhabi city', 'abudhabi', 'al ain', 'alain', 'al-ain'], true);
}

function is_uae_country(string $country): bool
{
    $country = strtolower(trim($country));
    return in_array($country, ['united arab emirates', 'uae', 'u.a.e', 'ae'], true);
}

function current_customer_id(): int
{
    return (int)($_SESSION['customer_id'] ?? 0);
}

function require_customer(): void
{
    if (current_customer_id() <= 0) {
        header('Location: ' . store_url('login.php'));
        exit;
    }
}

function customer_login_row(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$customer['id'];
    $_SESSION['customer_email'] = $customer['email'] ?? '';
    $_SESSION['customer_name'] = $customer['fullname'] ?? '';
}

function wishlist_ids($db): array
{
    $customerId = current_customer_id();
    if ($customerId > 0 && ag_has_column($db, 'wishlists', 'product_id')) {
        $rows = $db->fetchAll('SELECT product_id FROM wishlists WHERE customer_id = ?', [$customerId]);
        return array_map('intval', array_column($rows, 'product_id'));
    }
    $guest = $_SESSION['guest_wishlist'] ?? [];
    return array_values(array_unique(array_map('intval', is_array($guest) ? $guest : [])));
}

function wishlist_toggle($db, int $productId): bool
{
    $customerId = current_customer_id();
    if ($customerId > 0) {
        $existing = $db->fetch('SELECT id FROM wishlists WHERE customer_id = ? AND product_id = ?', [$customerId, $productId]);
        if ($existing) {
            $db->query('DELETE FROM wishlists WHERE id = ?', [$existing['id']]);
            return false;
        }
        $db->query('INSERT INTO wishlists (customer_id, product_id) VALUES (?, ?)', [$customerId, $productId]);
        return true;
    }
    $ids = wishlist_ids($db);
    if (in_array($productId, $ids, true)) {
        $_SESSION['guest_wishlist'] = array_values(array_diff($ids, [$productId]));
        return false;
    }
    $ids[] = $productId;
    $_SESSION['guest_wishlist'] = $ids;
    return true;
}

function adopt_guest_wishlist($db, int $customerId): void
{
    $guest = $_SESSION['guest_wishlist'] ?? [];
    if (!is_array($guest)) {
        $guest = [];
    }
    foreach (array_unique(array_map('intval', $guest)) as $productId) {
        if ($productId <= 0) {
            continue;
        }
        $db->query(
            'INSERT IGNORE INTO wishlists (customer_id, product_id) VALUES (?, ?)',
            [$customerId, $productId]
        );
    }
    unset($_SESSION['guest_wishlist']);
}

function refresh_product_rating($db, int $productId): void
{
    if ($productId <= 0) {
        return;
    }
    $row = $db->fetch(
        'SELECT ROUND(AVG(rating), 2) AS avg_rating, COUNT(*) AS review_count FROM product_reviews WHERE product_id = ? AND is_approved = 1',
        [$productId]
    );
    if (ag_has_column($db, 'products', 'avg_rating')) {
        $db->query(
            'UPDATE products SET avg_rating = ?, review_count = ? WHERE id = ?',
            [$row['avg_rating'], (int)$row['review_count'], $productId]
        );
    }
}

function send_store_mail(string $to, string $subject, string $html, ?string $replyTo = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = defined('EMAIL_FROM') ? EMAIL_FROM : 'sales@aleppogift.com';
    $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'Aleppo Gift';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . $fromName . ' <' . $from . ">\r\n";
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
    }
    return (bool)@mail($to, $subject, $html, $headers);
}

function maybe_send_shipment_email($db, int $orderId, string $oldStatus, string $oldTracking, string $newStatus, string $newTracking): void
{
    $becameShipped = $newStatus === 'shipped' && $oldStatus !== 'shipped';
    $trackingChanged = $newStatus === 'shipped' && $oldStatus === 'shipped' && trim($newTracking) !== trim($oldTracking);
    if (!$becameShipped && !$trackingChanged) {
        return;
    }
    $order = $db->fetch(
        'SELECT o.*, c.email, c.fullname FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = ?',
        [$orderId]
    );
    if (!$order || empty($order['email'])) {
        return;
    }
    $link = store_url('track_order.php');
    $html = '<p>Hello ' . htmlspecialchars((string)$order['fullname']) . ',</p>'
        . '<p>Your Aleppo Gift order #' . (int)$orderId . ' has shipped.</p>'
        . '<p>Courier: ' . htmlspecialchars((string)($order['carrier_name'] ?? '')) . '<br>'
        . 'Tracking: ' . htmlspecialchars($newTracking) . '</p>'
        . '<p><a href="' . htmlspecialchars($link) . '">Track your order</a></p>';
    send_store_mail($order['email'], 'Your Aleppo Gift order has shipped', $html);
}

function reserve_order_stock($db, int $orderId): bool
{
    if (!ag_has_column($db, 'orders', 'stock_reserved')) {
        return true;
    }
    $order = $db->fetch('SELECT stock_reserved FROM orders WHERE id = ?', [$orderId]);
    if (!$order || (int)$order['stock_reserved'] === 1) {
        return true;
    }
    $items = $db->fetchAll('SELECT product_id, variation_id, quantity FROM order_items WHERE order_id = ?', [$orderId]);
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        if (!empty($item['variation_id'])) {
            $row = $db->fetch('SELECT stock FROM product_variations WHERE id = ?', [(int)$item['variation_id']]);
            if ($row && $row['stock'] !== null && (int)$row['stock'] < $qty) {
                return false;
            }
        } else {
            $row = $db->fetch('SELECT stock FROM products WHERE id = ?', [(int)$item['product_id']]);
            if ($row && $row['stock'] !== null && (int)$row['stock'] < $qty) {
                return false;
            }
        }
    }
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        if (!empty($item['variation_id'])) {
            $db->query('UPDATE product_variations SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL', [$qty, (int)$item['variation_id']]);
        }
        $db->query('UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ? AND stock IS NOT NULL', [$qty, (int)$item['product_id']]);
    }
    $db->query('UPDATE orders SET stock_reserved = 1 WHERE id = ?', [$orderId]);
    return true;
}

function release_order_stock($db, int $orderId): void
{
    if (!ag_has_column($db, 'orders', 'stock_reserved')) {
        return;
    }
    $order = $db->fetch('SELECT stock_reserved FROM orders WHERE id = ?', [$orderId]);
    if (!$order || (int)$order['stock_reserved'] !== 1) {
        return;
    }
    $items = $db->fetchAll('SELECT product_id, variation_id, quantity FROM order_items WHERE order_id = ?', [$orderId]);
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        if (!empty($item['variation_id'])) {
            $db->query('UPDATE product_variations SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$qty, (int)$item['variation_id']]);
        }
        $db->query('UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$qty, (int)$item['product_id']]);
    }
    $db->query('UPDATE orders SET stock_reserved = 0 WHERE id = ?', [$orderId]);
}

function apply_order_stock_for_status($db, int $orderId, string $status): ?string
{
    if (in_array($status, ['cancelled', 'refunded', 'failed'], true)) {
        release_order_stock($db, $orderId);
        return null;
    }
    if (in_array($status, ['pending', 'paid'], true)) {
        if (!reserve_order_stock($db, $orderId)) {
            return 'Not enough stock to move this order back to an active status.';
        }
    }
    return null;
}

function product_card_badges(array $product): string
{
    $html = '';
    if ((float)($product['discount'] ?? 0) > 0 || !empty($product['on_sale'])) {
        $html .= '<span class="product-badge">' . htmlspecialchars(t('sale')) . '</span>';
    }
    if ((int)($product['stock'] ?? 0) <= 0) {
        $html .= '<span class="product-badge">' . htmlspecialchars(t('out_of_stock')) . '</span>';
    }
    $count = (int)($product['review_count'] ?? 0);
    if ($count > 0) {
        $html .= '<span class="product-badge">' . htmlspecialchars(number_format((float)$product['avg_rating'], 1)) . ' ★ (' . $count . ')</span>';
    }
    return $html;
}

function review_stats_select(string $alias = 'p'): string
{
    return "(SELECT ROUND(AVG(rating), 1) FROM product_reviews pr WHERE pr.product_id = $alias.id AND pr.is_approved = 1) AS avg_rating,
            (SELECT COUNT(*) FROM product_reviews pr2 WHERE pr2.product_id = $alias.id AND pr2.is_approved = 1) AS review_count";
}

function catalog_order_sql(string $sort, string $alias = 'p'): string
{
    switch ($sort) {
        case 'newest':
            return "$alias.created_at DESC, $alias.id DESC";
        case 'price_asc':
            return "$alias.price ASC, $alias.id DESC";
        case 'price_desc':
            return "$alias.price DESC, $alias.id DESC";
        case 'rating':
            return "avg_rating DESC, $alias.id DESC";
        case 'bestseller':
            return "units_sold DESC, $alias.id DESC";
        default:
            return visit_order_sql($alias);
    }
}

function units_sold_select(string $alias = 'p'): string
{
    return "(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = $alias.id AND o.payment_status NOT IN ('failed', 'cancelled', 'refunded')) AS units_sold";
}

function convert_amount_to_aed(float $amount, string $currency): float
{
    $currency = strtoupper(trim($currency));
    if ($currency === '' || $currency === 'AED') {
        return $amount;
    }
    if (function_exists('currency_rate')) {
        $rate = (float)currency_rate($currency);
        if ($rate > 0) {
            return round($amount / $rate, 2);
        }
    }
    return $amount;
}

function admin_is_super($db): bool
{
    $id = (int)($_SESSION['admin_id'] ?? 0);
    if ($id <= 0 || !ag_has_column($db, 'admin', 'role')) {
        return true;
    }
    $row = $db->fetch('SELECT role FROM admin WHERE id = ?', [$id]);
    $role = $row['role'] ?? 'super_admin';
    return $role === '' || $role === 'super_admin';
}

function storefront_page_open(string $title): void
{
    echo '<!DOCTYPE html><html lang="' . store_lang_html() . '" dir="' . store_lang_dir() . '"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">';
    echo '</head><body>';
    require __DIR__ . '/header.php';
    echo '<main class="container store-main my-4">';
}

function storefront_page_close(): void
{
    echo '</main>';
    require __DIR__ . '/footer.php';
    echo '</body></html>';
}
