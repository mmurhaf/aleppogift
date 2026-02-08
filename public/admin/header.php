<?php
/**
 * Admin Header Include
 * Dubai Gift - Master Copy Brands E-commerce
 * Enhanced with error handling and debugging capabilities
 */

// Enhanced error reporting and debugging
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/error.log');
}

// Comprehensive file includes with error handling
// Note: Admin is in public_html/public/admin/, so we need to go up TWO levels
$requiredFiles = [
    '../../config/config.php',
    '../../includes/Database.php', 
    '../../includes/functions.php',
    '../../includes/session.php'
];

foreach ($requiredFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (!file_exists($filePath)) {
        error_log("Critical: Required file missing - " . $filePath);
        die("System error: Configuration files missing. Please contact administrator.");
    }
    require_once $filePath;
}

// Start session with error handling
try {
    startSession();
} catch (Exception $e) {
    error_log("Session start failed: " . $e->getMessage());
    die("Session initialization failed. Please try again.");
}

// Check if admin is logged in with enhanced security
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    // Clear any potentially corrupted session data
    session_unset();
    setFlashMessage('error', 'Please login to access admin panel.');
    redirect('index.php');
}

// Initialize database with comprehensive error handling
try {
    $db = new Database();
    
    // Test database connection
    $testResult = $db->query("SELECT 1");
    if ($testResult === false) {
        throw new Exception("Database connection test failed");
    }
    
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    
    // In production, show generic error. In development, show specific error
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Database Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact administrator.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Admin Panel'; ?> - Aleppo Gift</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flag Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        /* Mobile specific adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 1040;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
            }
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-gift"></i> Aleppo Gift</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <li>
                <a href="#productsMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-box"></i> Products
                </a>
                <ul class="collapse list-unstyled ps-3" id="productsMenu">
                    <li>
                        <a href="products.php" class="text-white-50 ms-3">
                            <i class="fas fa-list me-2"></i> All Products
                        </a>
                    </li>
                    <li>
                        <a href="add-product.php" class="text-white-50 ms-3">
                            <i class="fas fa-plus me-2"></i> Add Product
                        </a>
                    </li>
                </ul>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'brands.php' ? 'active' : ''; ?>" href="brands.php">
                    <i class="fas fa-trademark"></i> Brands
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-users"></i> Customers
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : ''; ?>" href="coupons.php">
                    <i class="fas fa-ticket-alt"></i> Coupons
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
            </li>
            
            <li>
                <a class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content Wrapper -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="header mb-4">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars fa-lg text-dark"></i>
                </button>
                <h1 class="h3 mb-0"><?php echo $pageTitle ?? 'Admin Panel'; ?></h1>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <?php if (function_exists('getFlashMessages')): ?>
            <?php $flashMessages = getFlashMessages(); ?>
            <?php if (!empty($flashMessages)): ?>
                <?php foreach ($flashMessages as $message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?> alert-dismissible fade show shadow-sm" role="alert">
                        <?php echo htmlspecialchars($message['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Error Debug Info (only show in development) -->
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug'])): ?>
            <div class="alert alert-debug alert-dismissible fade show" role="alert">
                <strong>Debug Info:</strong><br>
                File: <?php echo basename($_SERVER['PHP_SELF']); ?><br>
                Query String: <?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ?? ''); ?><br>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <script>
            function toggleSidebar() {
                document.getElementById('sidebar').classList.toggle('active');
                document.querySelector('.sidebar-overlay').classList.toggle('active');
            }
        </script>
        
        <!-- Main Content Area -->