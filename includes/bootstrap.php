<?php
/**
 * Bootstrap File - Core Application Initialization
 * Loads all essential components and security features
 * 
 * @author AleppoGift Development Team
 * @version 2.0 - Enhanced Security Edition
 * @date August 12, 2025
 */

// Load environment variables first
require_once(__DIR__ . '/env_loader.php');
EnvLoader::load();

// Load core configuration (this sets session settings)
require_once(__DIR__ . '/../config/config.php');

// Configure session security BEFORE starting session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', IS_PRODUCTION ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    // Lax keeps the cart on a top-level return from the card gateway.
    // Strict withholds the cookie on that navigation, so checkout.php
    // sees an empty cart and sends the customer back to cart.php.
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_start();

    $cookieParams = session_get_cookie_params();
    $cookieOptions = [
        'expires' => 0,
        'path' => $cookieParams['path'] !== '' ? $cookieParams['path'] : '/',
        'secure' => (bool)$cookieParams['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (!empty($cookieParams['domain'])) {
        $cookieOptions['domain'] = $cookieParams['domain'];
    }
    setcookie(session_name(), session_id(), $cookieOptions);
}

// Load security components
require_once(__DIR__ . '/security.php');
require_once(__DIR__ . '/functions.php');

// Load database and core components
require_once(__DIR__ . '/Database.php');

// Load optional components (if they exist)
$optional_includes = [
    __DIR__ . '/../vendor/fpdf/fpdf.php',
    __DIR__ . '/send_email_simple.php',
    __DIR__ . '/ZiinaPayment.php',
    __DIR__ . '/whatsapp_notify.php',
    __DIR__ . '/shipping.php',
    __DIR__ . '/store_features.php',
    __DIR__ . '/helpers/cart.php',
    __DIR__ . '/helpers/bundles.php',
    __DIR__ . '/cart_helpers.php',
    __DIR__ . '/shared_cart.php',
    __DIR__ . '/uae_symbol_utils.php'
];

foreach ($optional_includes as $file) {
    if (file_exists($file)) {
        require_once($file);
    }
}

if (function_exists('sharedCartWatch')) {
    sharedCartWatch();
}

// Application constants
define('PAYMENT_METHOD_COD', 'COD');
define('PAYMENT_METHOD_ZIINA', 'ZIINA');
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PAID', 'paid');
define('PAYMENT_STATUS_FAILED', 'failed');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPED', 'shipped');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// Security initialization
if (!isset($_SESSION['csrf_tokens'])) {
    $_SESSION['csrf_tokens'] = [];
}

// Session security validation
if (!Security::validateSession()) {
    // Invalid session - regenerate
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
    $_SESSION['user_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

// Rate limiting. Counts live in the session, so they apply to browsers that keep
// the session cookie. Page views, cart preview, and cart-count AJAX all share
// one bucket with PDF export. 100/hour was used up during normal shopping, and
// the next hit to ajax/export_cart_pdf.php returned 429.
$client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
$script_name = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
$auth_post = $request_method === 'POST'
    && (bool)preg_match('#/(login|register|forgot_password|reset_password)\.php$#', $script_name);

if ($auth_post) {
    $within_limit = Security::checkRateLimit($client_ip . '|auth', 30, 3600);
    $limit_scope = 'auth';
} else {
    $within_limit = Security::checkRateLimit($client_ip, 2000, 3600);
    $limit_scope = 'storefront';
}

if (!$within_limit) {
    Security::logSecurityEvent('Rate limit exceeded', 'warning', [
        'ip' => $client_ip,
        'scope' => $limit_scope,
        'script' => $script_name,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
    ]);

    if (!headers_sent()) {
        header('Retry-After: 60');
        http_response_code(429);
    }
    $limitMessage = 'Too many requests. Please try again later.';
    if (preg_match('#/ajax/#', $script_name)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => $limitMessage]);
    } else {
        echo $limitMessage;
    }
    exit;
}

// Log security event for monitoring
Security::logSecurityEvent('Application bootstrap completed', 'info', [
    'session_id' => session_id(),
    'user_ip' => isset($_SESSION['user_ip']) ? $_SESSION['user_ip'] : $client_ip
]);

// =====================================================
// VISITOR TRACKING
// =====================================================
// Load and initialize visitor tracking (for analytics)
if (file_exists(__DIR__ . '/visitor_tracker.php')) {
    require_once(__DIR__ . '/visitor_tracker.php');
    
    // Track visitor after database is available
    // Note: Tracking is deferred until a database connection is made
    // to avoid initializing DB just for tracking
    register_shutdown_function(function() {
        // Only track if a database connection was established during the request
        global $db;
        if (isset($db) && $db instanceof Database) {
            trackVisitor($db);
        }
    });
}
