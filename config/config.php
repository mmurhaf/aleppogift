<?php
/**
 * AleppoGift Configuration File
 * Environment-based configuration for local and production
 */

// Load environment variables
require_once(__DIR__ . '/../includes/env_loader.php');

// Environment Detection (more secure)
$is_local = (env('ENVIRONMENT', 'production') === 'development');

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'aleppogift'));
define('ENVIRONMENT', env('ENVIRONMENT', 'production'));
define('SITE_URL', env('SITE_URL', 'https://aleppogift.com/'));

// Application Settings
define('CURRENCY', 'AED');
define('CURRENCY_SYMBOL', 'د.إ');
define('APP_NAME', 'AleppoGift');

// Email Configuration
define('EMAIL_FROM', env('EMAIL_FROM', 'sales@aleppogift.com'));
define('EMAIL_FROM_NAME', env('EMAIL_FROM_NAME', 'AleppoGift Sales'));
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));

// Ziina Payment Configuration
define('ZIINA_SECRET_KEY', env('ZIINA_SECRET_KEY'));
define('ZIINA_TEST_MODE', env_bool('ZIINA_TEST_MODE', $is_local));

// Security Settings
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY'));
define('CSRF_SECRET', env('CSRF_SECRET'));

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Session Configuration (secure settings)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', !$is_local);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Error Reporting
if ($is_local) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Set timezone
date_default_timezone_set('Asia/Dubai');
?>
