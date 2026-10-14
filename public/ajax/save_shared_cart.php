<?php
/**
 * save_shared_cart.php
 * Admin-only AJAX endpoint.
 * Saves the current session cart into the shared_carts table and returns a
 * one-click share URL that can be sent to the customer (e.g. via WhatsApp).
 *
 * POST params (all optional):
 *   customer_name  – customer display name
 *   customer_phone – customer phone / WhatsApp number
 *   note           – internal note for admin
 *   expires_days   – link lifetime in days (default 7, 0 = never)
 */

ob_start();
require_once __DIR__ . '/../../includes/bootstrap.php';

function sc_json(array $payload, int $code = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    echo $json === false
        ? '{"success":false,"message":"Could not encode the response."}'
        : $json;
    exit;
}

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sc_json(['success' => false, 'message' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sc_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$cart = $_SESSION['cart'] ?? [];
$items = function_exists('sharedCartSanitizeItems')
    ? sharedCartSanitizeItems($cart)
    : array_values(is_array($cart) ? $cart : []);
if ($items === []) {
    sc_json(['success' => false, 'message' => 'Cart is empty – add items first.']);
}

$customer_name  = trim(strip_tags($_POST['customer_name']  ?? ''));
$customer_phone = trim(strip_tags($_POST['customer_phone'] ?? ''));
$note           = trim(strip_tags($_POST['note']           ?? ''));
$expires_days   = max(0, (int)($_POST['expires_days']      ?? 7));
$expires_at     = $expires_days > 0
    ? date('Y-m-d H:i:s', strtotime("+{$expires_days} days"))
    : null;

$cartJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($cartJson === false || $cartJson === '' || $cartJson === '[]') {
    sc_json(['success' => false, 'message' => 'Cart could not be saved. Remove any invalid items and try again.']);
}

try {
    $token = bin2hex(random_bytes(32));
    $db = new Database();
    if (function_exists('sharedCartEnsureTable')) {
        sharedCartEnsureTable($db);
    }
    $db->query(
        "INSERT INTO shared_carts
            (token, cart_data, customer_name, customer_phone, note, status, expires_at)
         VALUES
            (:token, :cart_data, :customer_name, :customer_phone, :note, 'active', :expires_at)",
        [
            'token'          => $token,
            'cart_data'      => $cartJson,
            'customer_name'  => $customer_name !== '' ? $customer_name : null,
            'customer_phone' => $customer_phone !== '' ? $customer_phone : null,
            'note'           => $note !== '' ? $note : null,
            'expires_at'     => $expires_at,
        ]
    );
} catch (Throwable $e) {
    error_log('Save shared cart failed: ' . $e->getMessage());
    sc_json(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}

$base_url = rtrim(defined('SITE_URL') ? SITE_URL : 'https://aleppogift.com', '/');
$base_url = preg_replace('#^(https://)www\.#i', '$1', $base_url);
$share_url = $base_url . '/shared-cart.php?token=' . $token;

$wa_number = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '971561125320';
$wa_message = urlencode(
    'Hi' . ($customer_name !== '' ? ' ' . $customer_name : '')
    . "! Here is your cart link. You can review and edit the items, then proceed to checkout:\n"
    . $share_url
);
$wa_link = 'https://wa.me/' . preg_replace('/\D/', '', (string)$wa_number) . '?text=' . $wa_message;

sc_json([
    'success'    => true,
    'token'      => $token,
    'share_url'  => $share_url,
    'wa_link'    => $wa_link,
    'expires_at' => $expires_at,
    'message'    => 'Cart saved! Share the link with your customer.',
]);
