<?php
// admin/index.php - Admin Dashboard Entry Point

// Ensure this is the admin directory
if (!headers_sent()) {
    // Use relative path for better compatibility
    header("Location: dashboard.php");
    exit;
} else {
    // JavaScript fallback
    echo '<script>window.location.href = "dashboard.php";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=dashboard.php"></noscript>';
    echo '<p>If you are not redirected automatically, <a href="dashboard.php">click here</a>.</p>';
    exit;
}
