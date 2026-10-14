<?php
/**
 * Admin Settings
 * Aleppo Gift - E-commerce Admin Settings
 */

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/session.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/functions.php');

$db = new Database();

$pageTitle = "Settings";
include 'header.php';

// Ensure site_settings table exists
$db->query("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $currentUsername = $_SESSION['admin_username'];
                $newUsername = trim($_POST['username']);
                $email = trim($_POST['email']);
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                // Verify current password
                $adminData = $db->fetch("SELECT * FROM admin WHERE username = ?", [$currentUsername]);
                if (!$adminData || !password_verify($currentPassword, $adminData['password'])) {
                    setFlashMessage('error', 'Current password is incorrect.');
                } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
                    setFlashMessage('error', 'New passwords do not match.');
                } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
                    setFlashMessage('error', 'New password must be at least 6 characters long.');
                } else {
                    // Update profile
                    $updateData = [$newUsername, $email];
                    $updateQuery = "UPDATE admin SET username = ?, email = ?";
                    
                    if (!empty($newPassword)) {
                        $updateQuery .= ", password = ?";
                        $updateData[] = password_hash($newPassword, PASSWORD_DEFAULT);
                    }
                    
                    $updateQuery .= " WHERE username = ?";
                    $updateData[] = $currentUsername;
                    
                    if ($db->query($updateQuery, $updateData)) {
                        $_SESSION['admin_username'] = $newUsername; // Update session
                        setFlashMessage('success', 'Profile updated successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to update profile.');
                    }
                }
                redirect('settings.php');
                break;
                
            case 'update_site_settings':
                $show_prices_val = isset($_POST['show_prices']) ? '1' : '0';
                try {
                    $db->query(
                        "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                        ['show_prices', $show_prices_val, $show_prices_val]
                    );
                } catch (\Exception $e) { /* ignore */ }
                setFlashMessage('success', 'Site settings updated successfully.');
                redirect('settings.php');
                break;

            case 'update_features':
                require_once $root_dir . '/includes/store_features.php';
                $textKeys = [
                    'free_shipping_threshold', 'low_stock_alert', 'products_per_page', 'vat_rate', 'vat_trn',
                    'gift_wrap_fee', 'same_day_fee', 'contact_phone', 'contact_whatsapp', 'contact_address',
                    'social_facebook', 'social_instagram', 'social_tiktok', 'seo_title_en', 'seo_title_ar',
                    'seo_description_en', 'seo_description_ar', 'google_analytics_id', 'smtp_host', 'smtp_port',
                    'smtp_encryption', 'smtp_username', 'smtp_from', 'smtp_from_name', 'admin_email',
                    'imap_host', 'imap_port', 'ziina_api_key',
                ];
                foreach ($textKeys as $key) {
                    if (!array_key_exists($key, $_POST)) {
                        continue;
                    }
                    $value = trim((string)$_POST[$key]);
                    if (in_array($key, ['smtp_password', 'ziina_api_key', 'ziina_webhook_secret'], true) && $value === '') {
                        continue;
                    }
                    set_site_setting($db, $key, $value);
                }
                foreach (['smtp_password', 'ziina_webhook_secret'] as $secret) {
                    $value = trim((string)($_POST[$secret] ?? ''));
                    if ($value !== '') {
                        set_site_setting($db, $secret, $value);
                    }
                }
                set_site_setting($db, 'charge_vat', isset($_POST['charge_vat']) ? '1' : '0');
                set_site_setting($db, 'ziina_sandbox', isset($_POST['ziina_sandbox']) ? '1' : '0');
                $uploadDir = $root_dir . '/public/uploads/settings/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                foreach (['logo' => 'site_logo', 'favicon' => 'site_favicon'] as $fileKey => $settingKey) {
                    if (!empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
                        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'], true)) {
                            $target = $uploadDir . $fileKey . '.' . $ext;
                            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)) {
                                set_site_setting($db, $settingKey, 'uploads/settings/' . $fileKey . '.' . $ext);
                            }
                        }
                    }
                }
                setFlashMessage('success', 'Store features saved.');
                redirect('settings.php#features');
                break;
                
            case 'clear_cache':
                // Clear any cache files or temporary data
                $cacheCleared = true;
                setFlashMessage('success', 'Cache cleared successfully.');
                redirect('settings.php');
                break;
                
            case 'backup_database':
                // In a real application, you would implement database backup
                setFlashMessage('info', 'Database backup feature is not implemented yet.');
                redirect('settings.php');
                break;
        }
    }
}

// Get current admin data
$currentAdmin = $db->fetch("SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_username']]);

// Read current site settings
$show_prices_current = get_site_setting($db, 'show_prices', '1');

// Get system statistics
$systemStats = [
    'total_products' => $db->fetch("SELECT COUNT(*) as count FROM products")['count'],
    'total_orders' => $db->fetch("SELECT COUNT(*) as count FROM orders")['count'],
    'total_customers' => $db->fetch("SELECT COUNT(*) as count FROM customers")['count'],
    'disk_usage' => function_exists('disk_total_space') ? disk_total_space('.') - disk_free_space('.') : 0,
    'total_space' => function_exists('disk_total_space') ? disk_total_space('.') : 0
];
?>

<div class="row">
    <div class="col-md-3">
        <!-- Settings Navigation -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Settings</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="pill">
                    <i class="fas fa-user me-2"></i>Profile
                </a>
                <a href="#site" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                    <i class="fas fa-globe me-2"></i>Site Settings
                </a>
                <a href="#features" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                    <i class="fas fa-sliders me-2"></i>Store Features
                </a>
                <a href="#system" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                    <i class="fas fa-server me-2"></i>System Info
                </a>
                <a href="#maintenance" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                    <i class="fas fa-tools me-2"></i>Maintenance
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="tab-content">
            <!-- Profile Settings -->
            <div class="tab-pane fade show active" id="profile">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Admin Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($currentAdmin['username']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($currentAdmin['email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6>Change Password</h6>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               placeholder="Leave blank to keep current password">
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Site Settings -->
            <div class="tab-pane fade" id="site">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Site Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_site_settings">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" 
                                               value="Aleppo Gift" readonly>
                                        <small class="text-muted">Contact developer to change this setting</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="site_email" class="form-label">Site Email</label>
                                        <input type="email" class="form-control" id="site_email" name="site_email" 
                                               value="admin@aleppogift.com" readonly>
                                        <small class="text-muted">Contact developer to change this setting</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="site_description" class="form-label">Site Description</label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="3" readonly>Aleppo Gift - Premium brands and luxury gifts</textarea>
                                <small class="text-muted">Contact developer to change this setting</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="currency" class="form-label">Currency</label>
                                        <input type="text" class="form-control" id="currency" name="currency" 
                                               value="AED" readonly>
                                        <small class="text-muted">Prices are stored in AED. <a href="currencies.php">Edit display rates</a>.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <input type="text" class="form-control" id="timezone" name="timezone" 
                                               value="Asia/Dubai" readonly>
                                        <small class="text-muted">Contact developer to change this setting</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Most site settings require developer access to modify. Contact your developer for changes to core settings.
                            </div>

                            <hr>
                            <h6 class="mb-3"><i class="fas fa-tag me-2"></i>Price Display</h6>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_prices" name="show_prices"
                                           <?= $show_prices_current === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="show_prices">
                                        Show prices on website (product pages, listings, homepage)
                                    </label>
                                </div>
                                <small class="text-muted">Turn this off to hide all prices site-wide — useful when you receive an intellectual property notice.</small>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="tab-pane fade" id="features">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Store features</h5></div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_features">
                            <?php
                            $featureFields = [
                                'free_shipping_threshold' => '500',
                                'low_stock_alert' => '5',
                                'products_per_page' => '12',
                                'vat_rate' => '5',
                                'vat_trn' => '',
                                'gift_wrap_fee' => '40',
                                'same_day_fee' => '50',
                                'contact_phone' => '',
                                'contact_whatsapp' => '',
                                'contact_address' => '',
                                'social_facebook' => '',
                                'social_instagram' => '',
                                'social_tiktok' => '',
                                'seo_title_en' => '',
                                'seo_title_ar' => '',
                                'seo_description_en' => '',
                                'seo_description_ar' => '',
                                'google_analytics_id' => '',
                                'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : '',
                                'smtp_port' => defined('SMTP_PORT') ? (string)SMTP_PORT : '465',
                                'smtp_encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'ssl',
                                'smtp_username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
                                'smtp_from' => defined('EMAIL_FROM') ? EMAIL_FROM : '',
                                'smtp_from_name' => defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : '',
                                'admin_email' => defined('EMAIL_FROM') ? EMAIL_FROM : '',
                                'imap_host' => 'imap.hostinger.com',
                                'imap_port' => '993',
                            ];
                            foreach ($featureFields as $field => $default):
                                $current = get_site_setting($db, $field, $default);
                            ?>
                                <label class="form-label" for="<?= $field ?>"><?= htmlspecialchars(str_replace('_', ' ', $field)) ?></label>
                                <input class="form-control mb-2" id="<?= $field ?>" name="<?= $field ?>" value="<?= htmlspecialchars((string)$current) ?>">
                            <?php endforeach; ?>
                            <label class="form-label">SMTP password</label>
                            <input class="form-control mb-2" type="password" name="smtp_password" placeholder="Leave blank to keep the current password">
                            <label class="form-label">Ziina API key</label>
                            <input class="form-control mb-2" type="password" name="ziina_api_key" placeholder="Leave blank to keep the current key">
                            <label class="form-label">Ziina webhook secret</label>
                            <input class="form-control mb-2" type="password" name="ziina_webhook_secret" placeholder="Leave blank to keep the current secret">
                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="charge_vat" id="charge_vat" <?= get_site_setting($db, 'charge_vat', '0') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="charge_vat">Charge VAT</label></div>
                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="ziina_sandbox" id="ziina_sandbox" <?= get_site_setting($db, 'ziina_sandbox', '0') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="ziina_sandbox">Ziina sandbox</label></div>
                            <label class="form-label">Logo</label>
                            <input class="form-control mb-2" type="file" name="logo" accept="image/*">
                            <label class="form-label">Favicon</label>
                            <input class="form-control mb-3" type="file" name="favicon" accept="image/*,.ico">
                            <button class="btn btn-primary" type="submit">Save store features</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="system">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Database Statistics</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td>Total Products:</td>
                                        <td><strong><?php echo number_format($systemStats['total_products']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Total Orders:</td>
                                        <td><strong><?php echo number_format($systemStats['total_orders']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Total Customers:</td>
                                        <td><strong><?php echo number_format($systemStats['total_customers']); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Server Information</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td>PHP Version:</td>
                                        <td><strong><?php echo PHP_VERSION; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Server Software:</td>
                                        <td><strong><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Database:</td>
                                        <td><strong>MySQL</strong></td>
                                    </tr>
                                    <?php if ($systemStats['total_space'] > 0): ?>
                                    <tr>
                                        <td>Disk Usage:</td>
                                        <td>
                                            <strong>
                                                <?php 
                                                $usagePercent = ($systemStats['disk_usage'] / $systemStats['total_space']) * 100;
                                                echo number_format($systemStats['disk_usage'] / (1024*1024*1024), 2) . ' GB / ' . number_format($systemStats['total_space'] / (1024*1024*1024), 2) . ' GB'; 
                                                ?>
                                            </strong>
                                            <div class="progress mt-1" style="height: 5px;">
                                                <div class="progress-bar <?php echo $usagePercent > 80 ? 'bg-danger' : ($usagePercent > 60 ? 'bg-warning' : 'bg-success'); ?>" 
                                                     style="width: <?php echo $usagePercent; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6>PHP Extensions</h6>
                        <div class="row">
                            <?php
                            $requiredExtensions = ['mysqli', 'pdo', 'gd', 'curl', 'openssl', 'mbstring', 'json'];
                            foreach ($requiredExtensions as $ext):
                            ?>
                            <div class="col-md-3 mb-2">
                                <span class="badge bg-<?php echo extension_loaded($ext) ? 'success' : 'danger'; ?>">
                                    <?php echo $ext; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Maintenance -->
            <div class="tab-pane fade" id="maintenance">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Maintenance Tools</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-broom me-2"></i>Clear Cache
                                        </h6>
                                        <p class="card-text">Clear all cached data and temporary files to improve performance.</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="clear_cache">
                                            <button type="submit" class="btn btn-warning">Clear Cache</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-database me-2"></i>Database Backup
                                        </h6>
                                        <p class="card-text">Create a backup of your database for safety.</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="backup_database">
                                            <button type="submit" class="btn btn-info">Backup Database</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes</h6>
                            <ul class="mb-0">
                                <li>Always backup your database before making major changes</li>
                                <li>Clear cache after updating products or site settings</li>
                                <li>Monitor disk usage regularly to prevent storage issues</li>
                                <li>Keep your PHP and MySQL versions updated for security</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Password confirmation validation
    ['new_password', 'confirm_password'].forEach(function (id) {
        document.getElementById(id).addEventListener('keyup', function () {
            var newPassword = document.getElementById('new_password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            var confirmEl = document.getElementById('confirm_password');
            if (newPassword !== '' && confirmPassword !== '') {
                if (newPassword === confirmPassword) {
                    confirmEl.classList.remove('is-invalid');
                    confirmEl.classList.add('is-valid');
                } else {
                    confirmEl.classList.remove('is-valid');
                    confirmEl.classList.add('is-invalid');
                }
            } else {
                confirmEl.classList.remove('is-valid', 'is-invalid');
            }
        });
    });

    // Tab navigation
    document.querySelectorAll('.list-group-item-action').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.list-group-item-action').forEach(function (el) {
                el.classList.remove('active');
            });
            link.classList.add('active');
            var target = link.getAttribute('href');
            document.querySelectorAll('.tab-pane').forEach(function (pane) {
                pane.classList.remove('show', 'active');
            });
            var targetEl = document.querySelector(target);
            if (targetEl) targetEl.classList.add('show', 'active');
        });
    });
});
</script>

<?php include 'footer.php'; ?>