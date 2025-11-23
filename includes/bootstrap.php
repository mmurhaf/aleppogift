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
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_start();
}

// Load security components
require_once(__DIR__ . '/security.php');

// Load database and core components
require_once(__DIR__ . '/Database.php');

// Load optional components (if they exist)
$optional_includes = [
    __DIR__ . '/../vendor/fpdf/fpdf.php',
    __DIR__ . '/send_email_simple.php',
    __DIR__ . '/ZiinaPayment.php',
    __DIR__ . '/whatsapp_notify.php',
    __DIR__ . '/shipping.php',
    __DIR__ . '/helpers/cart.php',
    __DIR__ . '/cart_helpers.php',
    __DIR__ . '/uae_symbol_utils.php'
];

foreach ($optional_includes as $file) {
    if (file_exists($file)) {
        require_once($file);
    }
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

// Rate limiting for security
$client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
if (!Security::checkRateLimit($client_ip, 100, 3600)) {
    Security::logSecurityEvent('Rate limit exceeded', 'warning', [
        'ip' => $client_ip,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
    ]);
    
    http_response_code(429);
    die('Too many requests. Please try again later.');
}

// Log security event for monitoring
Security::logSecurityEvent('Application bootstrap completed', 'info', [
    'session_id' => session_id(),
    'user_ip' => isset($_SESSION['user_ip']) ? $_SESSION['user_ip'] : $client_ip
]);
