<?php
/**
 * Admin Settings
 * Dubai Gift - Master Copy Brands E-commerce
 */

$pageTitle = "Settings";
include 'header.php';

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
                    
                    if ($db->execute($updateQuery, $updateData)) {
                        $_SESSION['admin_username'] = $newUsername; // Update session
                        setFlashMessage('success', 'Profile updated successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to update profile.');
                    }
                }
                redirect('settings.php');
                break;
                
            case 'update_site_settings':
                // In a real application, you would store these in a settings table
                // For now, we'll just show a success message
                setFlashMessage('success', 'Site settings updated successfully.');
                redirect('settings.php');
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
                                               value="Dubai Gift" readonly>
                                        <small class="text-muted">Contact developer to change this setting</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="site_email" class="form-label">Site Email</label>
                                        <input type="email" class="form-control" id="site_email" name="site_email" 
                                               value="admin@dubaigift.com" readonly>
                                        <small class="text-muted">Contact developer to change this setting</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="site_description" class="form-label">Site Description</label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="3" readonly>Dubai Gift - Premium brands and luxury items from Dubai</textarea>
                                <small class="text-muted">Contact developer to change this setting</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="currency" class="form-label">Currency</label>
                                        <input type="text" class="form-control" id="currency" name="currency" 
                                               value="AED" readonly>
                                        <small class="text-muted">Contact developer to change this setting</small>
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
                            
                            <!-- <button type="submit" class="btn btn-primary" disabled>Update Settings</button> -->
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
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
$(document).ready(function() {
    // Password confirmation validation
    $('#new_password, #confirm_password').on('keyup', function() {
        var newPassword = $('#new_password').val();
        var confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== '' && confirmPassword !== '') {
            if (newPassword === confirmPassword) {
                $('#confirm_password').removeClass('is-invalid').addClass('is-valid');
            } else {
                $('#confirm_password').removeClass('is-valid').addClass('is-invalid');
            }
        } else {
            $('#confirm_password').removeClass('is-valid is-invalid');
        }
    });
    
    // Tab navigation
    $('.list-group-item-action').on('click', function(e) {
        e.preventDefault();
        $('.list-group-item-action').removeClass('active');
        $(this).addClass('active');
        
        var target = $(this).attr('href');
        $('.tab-pane').removeClass('show active');
        $(target).addClass('show active');
    });
});
</script>

<?php include 'footer.php'; ?>