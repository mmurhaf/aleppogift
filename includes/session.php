<?php
/**
 * Session Management Functions
 * Provides session handling for admin panel
 * 
 * @author AleppoGift Development Team
 */

// Include the session helper for actual implementation
require_once __DIR__ . '/session_helper.php';

/**
 * Start session with proper configuration
 * Wrapper function for compatibility with admin header
 */
function startSession() {
    return start_session_safely();
}

/**
 * Check if admin is logged in
 * @return bool True if admin is logged in
 */
function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safely();
    }
    
    // Check for admin session
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // Check session timeout
        if (isset($_SESSION['admin_last_activity'])) {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 7200; // 2 hours default
            if (time() - $_SESSION['admin_last_activity'] > $timeout) {
                // Session expired
                session_destroy();
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['admin_last_activity'] = time();
        return true;
    }
    
    return false;
}

/**
 * Set flash message for display
 * @param string $type Message type (success, error, warning, info)
 * @param string $message The message to display
 */
function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safely();
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'time' => time()
    ];
}

/**
 * Get and clear flash messages
 * @param string|null $type Optional type filter
 * @return array Flash messages
 */
function getFlashMessages($type = null) {
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safely();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    $_SESSION['flash_messages'] = []; // Clear after reading
    
    if ($type !== null) {
        $messages = array_filter($messages, function($msg) use ($type) {
            return $msg['type'] === $type;
        });
    }
    
    return $messages;
}

/**
 * Display flash messages as HTML
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    
    foreach ($messages as $msg) {
        $alertClass = match($msg['type']) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($msg['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Redirect to a URL
 * @param string $url URL to redirect to
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    } else {
        echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        exit;
    }
}

/**
 * Admin logout
 */
function adminLogout() {
    if (session_status() === PHP_SESSION_NONE) {
        start_session_safely();
    }
    
    // Clear admin session data
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_last_activity']);
    
    // Destroy the session
    session_destroy();
}
