
<?php
// Admin Logout - Use session helper for proper session management
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');

// Safely destroy the session using the session helper
destroy_session_safely();

// Redirect to login page
if (!headers_sent()) {
    header("Location: login.php");
    exit;
} else {
    // Fallback if headers already sent
    echo '<script>window.location.href = "login.php";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=login.php" /></noscript>';
    exit;
}
?>
