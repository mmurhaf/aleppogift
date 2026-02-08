<?php
/**
 * Core Functions Library
 * Common helper functions for the application
 * 
 * @author AleppoGift Development Team
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    die('Direct access not permitted');
}

/**
 * Sanitize string for output
 * @param string $string Input string
 * @return string Sanitized string
 */
function sanitize($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize string for database input
 * @param string $string Input string
 * @return string Cleaned string
 */
function cleanInput($string) {
    $string = trim($string);
    $string = stripslashes($string);
    return $string;
}

/**
 * Format price with currency
 * @param float $price Price value
 * @param bool $includeSymbol Whether to include currency symbol
 * @return string Formatted price
 */
function formatPriceDisplay($price, $includeSymbol = true) {
    $formatted = number_format((float)$price, 2);
    if ($includeSymbol) {
        $symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'AED';
        return $symbol . ' ' . $formatted;
    }
    return $formatted;
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : $date;
}

/**
 * Format datetime for display
 * @param string $datetime Datetime string
 * @param string $format Output format
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = 'd M Y H:i') {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return $timestamp ? date($format, $timestamp) : $datetime;
}

/**
 * Generate a random string
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address
 * @return string IP address
 */
function getClientIP() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if ($header === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Truncate text to specified length
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to append
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic)
 * @param string $phone Phone number
 * @return bool
 */
function isValidPhone($phone) {
    // Remove common separators
    $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
    // Check for valid phone format (7-15 digits, optionally starting with +)
    return preg_match('/^\+?[0-9]{7,15}$/', $phone);
}

/**
 * Generate slug from string
 * @param string $string Input string
 * @return string URL-friendly slug
 */
function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Get file extension
 * @param string $filename Filename
 * @return string Extension (lowercase)
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file is an allowed image type
 * @param string $filename Filename to check
 * @return bool
 */
function isAllowedImage($filename) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    return in_array(getFileExtension($filename), $allowed);
}

/**
 * Format file size for display
 * @param int $bytes File size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get order status badge HTML
 * @param string $status Order status
 * @return string HTML badge
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'secondary'
    ];
    
    $class = $badges[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . ucfirst($status) . '</span>';
}

/**
 * Log application event
 * @param string $message Log message
 * @param string $type Log type (info, error, warning, debug)
 * @param array $context Additional context data
 */
function logEvent($message, $type = 'info', $context = []) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    
    $logEntry = "[{$timestamp}] [{$type}] {$message}{$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Debug output (only in development)
 * @param mixed $data Data to debug
 * @param bool $die Whether to stop execution
 */
function debug($data, $die = false) {
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        return; // Don't output in production
    }
    
    echo '<pre style="background:#f5f5f5;padding:15px;border:1px solid #ddd;margin:10px;overflow:auto;">';
    print_r($data);
    echo '</pre>';
    
    if ($die) {
        die();
    }
}

/**
 * Check if current request is secure (HTTPS)
 * @return bool
 */
function isSecure() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Get base URL of the application
 * @return string Base URL
 */
function getBaseUrl() {
    if (defined('SITE_URL')) {
        return rtrim(SITE_URL, '/');
    }
    
    $protocol = isSecure() ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool
 */
function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token is expired (1 hour)
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF token as hidden form field
 */
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}
