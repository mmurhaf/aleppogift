<?php
$root_dir = dirname(dirname(__DIR__));
if (!file_exists($root_dir . '/includes/session_helper.php')) {
    $root_dir = dirname(dirname(dirname(__DIR__)));
}
require_once $root_dir . '/includes/session_helper.php';

require_admin_login();

$page_title = $page_title ?? ($pageTitle ?? 'Admin Panel');
$admin_name = $_SESSION['admin_username'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$admin_root = dirname(__DIR__);

$admin_navigation = [
    'Overview' => [
        ['file' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt'],
        ['file' => 'analytics.php', 'label' => 'Analytics', 'icon' => 'fa-chart-line'],
        ['file' => 'reports.php', 'label' => 'Reports', 'icon' => 'fa-chart-bar'],
    ],
    'Catalog' => [
        ['file' => 'products.php', 'label' => 'Products', 'icon' => 'fa-box-open'],
        ['file' => 'add_product.php', 'label' => 'Add Product', 'icon' => 'fa-plus-circle'],
        ['file' => 'bulk-upload-images.php', 'label' => 'Bulk Upload Images', 'icon' => 'fa-images'],
        ['file' => 'bulk_edit_prices.php', 'label' => 'Bulk Edit Products', 'icon' => 'fa-table-cells'],
        ['file' => 'product-consolidate.php', 'label' => 'Merge Products', 'icon' => 'fa-object-group'],
        ['file' => 'categories.php', 'label' => 'Categories', 'icon' => 'fa-tags'],
        ['file' => 'brands.php', 'label' => 'Brands', 'icon' => 'fa-copyright'],
    ],
    'Sales' => [
        ['file' => 'orders.php', 'label' => 'Orders', 'icon' => 'fa-shopping-cart'],
        ['file' => 'create_order.php', 'label' => 'Create Order', 'icon' => 'fa-cart-plus'],
        ['file' => 'shared_carts.php', 'label' => 'Shared Carts', 'icon' => 'fa-share-alt'],
        ['file' => '../cart.php', 'label' => 'Public Cart', 'icon' => 'fa-cart-arrow-down', 'href' => '../cart.php', 'target' => '_blank'],
        ['file' => 'customers.php', 'label' => 'Customers', 'icon' => 'fa-users'],
        ['file' => 'coupons.php', 'label' => 'Coupons', 'icon' => 'fa-ticket-alt'],
        ['file' => 'payment_links.php', 'label' => 'Payment Links', 'icon' => 'fa-link'],
        ['file' => 'quick_payment_link.php', 'label' => 'Quick Payment', 'icon' => 'fa-bolt'],
    ],
    'Content' => [
        ['file' => 'hero-slider.php', 'label' => 'Hero Slider', 'icon' => 'fa-images'],
        ['file' => 'reviews.php', 'label' => 'Product Reviews', 'icon' => 'fa-star'],
        ['file' => 'testimonials.php', 'label' => 'Testimonials', 'icon' => 'fa-quote-right'],
    ],
    'Tools' => [
        ['file' => 'change_product_id.php', 'label' => 'Change Product ID', 'icon' => 'fa-hashtag'],        ['file' => 'remove_brand_from_names.php',  'label' => 'Remove Brand from Names',   'icon' => 'fa-eraser'],        ['file' => 'email_client.php', 'label' => 'Email Client', 'icon' => 'fa-envelope'],
        ['file' => 'manage_trust.php', 'label' => 'Trust Management', 'icon' => 'fa-shield-heart'],
        ['file' => 'trust-badges.php', 'label' => 'Trust Badges', 'icon' => 'fa-award'],
        ['file' => 'query_runner.php', 'label' => 'Query Runner', 'icon' => 'fa-database'],
        ['file' => 'view-logs.php', 'label' => 'Server Logs', 'icon' => 'fa-file-lines'],
        ['file' => 'system_status.php', 'label' => 'System Status', 'icon' => 'fa-heart-pulse'],
        ['file' => 'settings.php', 'label' => 'Settings', 'icon' => 'fa-cog'],
    ],
];

$filtered_navigation = $admin_navigation;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <?php if (isset($additional_css)) echo $additional_css; ?>
</head>
<body class="admin-layout">
    <div class="sidebar-overlay" data-sidebar-overlay></div>

    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-gift"></i> <span>AleppoGift</span></h2>
            <p class="sidebar-subtitle">Admin workspace</p>
        </div>

        <div class="sidebar-scroll">
            <?php foreach ($filtered_navigation as $section => $items): ?>
                <div class="nav-section">
                    <div class="nav-section-title"><?php echo htmlspecialchars($section); ?></div>
                    <ul class="sidebar-menu">
                        <?php foreach ($items as $item): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($item['href'] ?? $item['file']); ?>"
                                   class="<?php echo $current_page === $item['file'] ? 'active' : ''; ?>"
                                   <?php if (!empty($item['target'])): ?>target="<?php echo htmlspecialchars($item['target']); ?>"<?php endif; ?>>
                                    <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                                    <?php if (!empty($item['target'])): ?><i class="fas fa-external-link-alt ms-auto" style="font-size:.65rem;opacity:.6"></i><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer">
            <?php if (file_exists($admin_root . '/profile.php')): ?>
            <a href="profile.php" style="display:flex;align-items:center;gap:8px;padding:10px 15px;color:inherit;text-decoration:none;border-radius:6px;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <?php endif; ?>
            <a class="logout-btn" href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <button type="button" class="admin-menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="admin-topbar__eyebrow">Admin Panel</div>
                    <h1 class="admin-topbar__title"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
            </div>
            <div class="admin-topbar__right">
                <div class="admin-user-chip">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($admin_name); ?></span>
                </div>
            </div>
        </header>

        <main class="main-content">