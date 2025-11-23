<?php
/**
 * Session Helper - Handles safe session initialization
 * Prevents "session already started" errors
 */

/**
 * Start session safely - only if not already started
 * @return bool Success status
 */
function start_session_safely() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session configuration before starting
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        
        if (session_start()) {
            error_log("✅ Session started safely: " . session_id());
            return true;
        } else {
            error_log("❌ Failed to start session");
            return false;
        }
    } else {
        error_log("✅ Session already active: " . session_id());
        return true;
    }
}

/**
 * Regenerate session ID for security
 */
function regenerate_session_id() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        error_log("🔄 Session ID regenerated: " . session_id());
    }
}

/**
 * Check if admin is logged in and redirect if not
 */
function require_admin_login() {
    start_session_safely();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Determine correct path to login.php based on current script location
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $login_path = 'login.php';
        
        // If we're in a subdirectory like /admin/, adjust the path
        if (strpos($script_dir, '/admin') !== false) {
            $login_path = 'login.php';
        } else {
            $login_path = 'admin/login.php';
        }
        
        header("Location: " . $login_path);
        exit;
    }
    
    // Check session timeout if set
    if (isset($_SESSION['admin_last_activity'])) {
        $timeout = 30 * 60; // 30 minutes
        if (time() - $_SESSION['admin_last_activity'] > $timeout) {
            session_destroy();
            
            // Same path logic for timeout redirect
            $script_dir = dirname($_SERVER['SCRIPT_NAME']);
            $login_path = 'login.php?timeout=1';
            
            if (strpos($script_dir, '/admin') !== false) {
                $login_path = 'login.php?timeout=1';
            } else {
                $login_path = 'admin/login.php?timeout=1';
            }
            
            header("Location: " . $login_path);
            exit;
        }
    }
    $_SESSION['admin_last_activity'] = time();
}

/**
 * Check if user is logged in and redirect if not
 */
function require_user_login($redirect_url = 'login.php') {
    start_session_safely();
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: " . $redirect_url);
        exit;
    }
    
    // Update last activity
    $_SESSION['user_last_activity'] = time();
}

/**
 * Safely destroy session and clear all data
 */
function destroy_session_safely() {
    start_session_safely();
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    error_log("🗑️ Session destroyed safely");
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    start_session_safely();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Get current user ID
 */
function get_current_user_id() {
    start_session_safely();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Set flash message for next page load
 */
function set_flash_message($message, $type = 'info') {
    start_session_safely();
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    start_session_safely();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Generate CSRF token for form protection
 */
function generate_csrf_token() {
    start_session_safely();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    start_session_safely();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
