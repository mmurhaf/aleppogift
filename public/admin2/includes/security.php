<?php
/**
 * Admin Security Helper Functions
 * Additional security functions for admin panel
 */

/**
 * Validate and sanitize numeric ID
 */
function validate_id($id) {
    $id = (int)$id;
    return ($id > 0) ? $id : false;
}

/**
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'], $max_size = 5242880) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file extension
    if (!in_array($file_ext, $allowed_types)) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg', 'image/jpg', 'image/png', 
        'image/gif', 'image/webp'
    ];
    
    if (!in_array($mime_type, $allowed_mimes)) {
        return false;
    }
    
    return true;
}

/**
 * Generate secure filename
 */
function generate_secure_filename($original_name) {
    $ext = pathinfo($original_name, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . strtolower($ext);
}

/**
 * Log admin actions
 */
function log_admin_action($action, $details = '') {
    if (!isset($_SESSION['admin_id'])) return;
    
    $log_entry = sprintf(
        "[%s] Admin ID %d: %s %s\n",
        date('Y-m-d H:i:s'),
        $_SESSION['admin_id'],
        $action,
        $details
    );
    
    error_log($log_entry, 3, '../../logs/admin_actions.log');
}

/**
 * Prevent CSRF attacks
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if admin has specific permission (for future role-based access)
 */
function has_permission($permission) {
    // For now, all admins have all permissions
    // This can be expanded for role-based access control
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}
?>