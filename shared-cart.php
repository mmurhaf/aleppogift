<?php
/**
 * shared-cart.php
 * Public landing page for shared cart links.
 *
 * URL: https://aleppogift.com/shared-cart.php?token=XXXXXXXX
 *
 * - Reads the token from GET
 * - Validates it in the shared_carts table
 * - Loads the saved cart_data into the visitor's session
 * - Stores the token in session so edits are synced back (see includes/shared_cart.php)
 * - Sends the visitor to cart.php with a flash message
 */

require_once('includes/bootstrap.php');

/**
 * These links are opened from WhatsApp and other sites. A 302 would keep
 * the navigation cross-site, so the browser can drop the new session cookie
 * on cart.php and the loaded cart would vanish. Navigating from an HTML
 * page makes the next request same-site.
 */
function sharedCartRedirect(string $url): void {
    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
        . '<meta name="robots" content="noindex">'
        . '<meta http-equiv="refresh" content="0;url=' . $safe . '">'
        . '<title>Loading your cart…</title></head><body>'
        . '<p>Loading your cart… <a href="' . $safe . '">Continue</a></p>'
        . '<script>location.replace(' . json_encode($url) . ');</script>'
        . '</body></html>';
    exit;
}

$cart_url = 'public/cart.php';
$db       = new Database();
$token    = trim($_GET['token'] ?? '');

// ── Validate token ───────────────────────────────────────────────────────────
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $_SESSION['cart_error'] = 'Invalid or missing cart link. Please ask for a new one.';
    sharedCartRedirect($cart_url);
}

try {
    $row = $db->query(
        "SELECT * FROM shared_carts WHERE token = :token LIMIT 1",
        ['token' => $token]
    )->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['cart_error'] = 'Could not load the shared cart. Please try again.';
    sharedCartRedirect($cart_url);
}

if (!$row) {
    $_SESSION['cart_error'] = 'This cart link does not exist.';
    sharedCartRedirect($cart_url);
}

if ($row['status'] === 'used') {
    $_SESSION['cart_error'] = 'An order has already been placed with this cart link. Please contact us if you need a new one.';
    sharedCartRedirect($cart_url);
}

if (!sharedCartIsOpen($row)) {
    if ($row['status'] === 'active') {
        $db->query("UPDATE shared_carts SET status='expired' WHERE token=:token", ['token' => $token]);
    }
    $_SESSION['cart_error'] = 'This cart link has expired. Please contact us for a new one.';
    sharedCartRedirect($cart_url);
}

// ── Load cart into session ───────────────────────────────────────────────────
// Replace (don't merge) the current session cart so the customer sees
// exactly what the admin built.
$_SESSION['cart']              = sharedCartSanitizeItems(json_decode($row['cart_data'], true));
$_SESSION['shared_cart_token'] = $token;

$name_part = $row['customer_name'] ? ', ' . $row['customer_name'] : '';
$_SESSION['cart_success'] = "Welcome{$name_part}! Your cart has been loaded. Feel free to add, remove or change quantities, then proceed to checkout.";

sharedCartRedirect($cart_url);
