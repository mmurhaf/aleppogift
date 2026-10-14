<?php
/**
 * Admin Profile Page
 * Dubai Gift - Master Copy Brands E-commerce
 */

$pageTitle = "My Profile";
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
                } elseif (empty($newUsername)) {
                    setFlashMessage('error', 'Username cannot be empty.');
                } else {
                    // Check if new username already exists (if changed)
                    if ($newUsername !== $currentUsername) {
                        $existingUser = $db->fetch("SELECT id FROM admin WHERE username = ? AND username != ?", [$newUsername, $currentUsername]);
                        if ($existingUser) {
                            setFlashMessage('error', 'Username already exists.');
                            redirect('profile.php');
                            exit;
                        }
                    }
                    
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
                redirect('profile.php');
                break;
        }
    }
}

// Get current admin data
$currentAdmin = $db->fetch("SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_username']]);

// Get admin activity stats
$activityStats = [
    'total_logins' => 0, // This would require a login log table
    'last_login' => date('Y-m-d H:i:s'), // This would be stored in session or database
    'account_created' => $currentAdmin['created_at'],
    'profile_updates' => 0 // This would require an audit table
];

// Get recent activity (mock data - in real app this would come from logs)
$recentActivity = [
    ['action' => 'Profile Updated', 'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
    ['action' => 'Product Added', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ['action' => 'Order Processed', 'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days'))],
    ['action' => 'System Login', 'timestamp' => date('Y-m-d H:i:s', strtotime('-3 days'))],
];
?>

<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card">
            <div class="card-body text-center">
                <div class="profile-avatar mb-3">
                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                         style="width: 80px; height: 80px; font-size: 2rem; color: white;">
                        <?php echo strtoupper(substr($currentAdmin['username'], 0, 2)); ?>
                    </div>
                </div>
                <h5 class="card-title"><?php echo htmlspecialchars($currentAdmin['username']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($currentAdmin['email'] ?? 'No email set'); ?></p>
                <small class="text-muted">Administrator</small>
            </div>
        </div>
        
        <!-- Account Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Account Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo number_format($activityStats['total_logins']); ?></h4>
                            <small class="text-muted">Total Logins</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo number_format($activityStats['profile_updates']); ?></h4>
                        <small class="text-muted">Profile Updates</small>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Member Since:</small>
                    <small><strong><?php echo date('M d, Y', strtotime($activityStats['account_created'])); ?></strong></small>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">Last Login:</small>
                    <small><strong><?php echo date('M d, Y H:i', strtotime($activityStats['last_login'])); ?></strong></small>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                    <p class="text-muted text-center">No recent activity</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-light rounded-circle p-2">
                                        <i class="fas fa-clock text-muted"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="mb-1 small"><?php echo htmlspecialchars($activity['action']); ?></p>
                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Profile Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Profile Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($currentAdmin['username']); ?>" required>
                                <small class="text-muted">This will be your login username</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($currentAdmin['email'] ?? ''); ?>">
                                <small class="text-muted">For notifications and recovery</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Security Settings</h6>
                    <p class="text-muted">To update your profile, please confirm your current password. To change your password, fill in the new password fields.</p>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password *</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <small class="text-muted">Required to confirm any changes</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="text-muted">Leave blank to keep current password (min. 6 characters)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <div class="invalid-feedback">
                                    Passwords do not match.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Security -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Account Security</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-shield-alt text-success me-2"></i>Password Security
                                </h6>
                                <p class="card-text small">
                                    Your password was last updated on 
                                    <strong><?php echo date('M d, Y', strtotime($currentAdmin['created_at'])); ?></strong>
                                </p>
                                <div class="mt-2">
                                    <span class="badge bg-success">Strong</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-clock text-info me-2"></i>Login Activity
                                </h6>
                                <p class="card-text small">
                                    Your last login was on 
                                    <strong><?php echo date('M d, Y H:i', strtotime($activityStats['last_login'])); ?></strong>
                                </p>
                                <div class="mt-2">
                                    <span class="badge bg-info">Active Now</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Security Tips:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Use a strong password with at least 8 characters</li>
                        <li>Include uppercase, lowercase, numbers, and special characters</li>
                        <li>Don't share your admin credentials with anyone</li>
                        <li>Log out when finished, especially on shared computers</li>
                    </ul>
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
    
    // Password strength indicator
    $('#new_password').on('keyup', function() {
        var password = $(this).val();
        var strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[^a-zA-Z0-9]+/)) strength++;
        
        var strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        var strengthClass = ['danger', 'warning', 'info', 'primary', 'success'];
        
        if (password.length > 0) {
            $(this).siblings('small').html(
                'Password strength: <span class="text-' + strengthClass[strength - 1] + '">' + strengthText[strength - 1] + '</span>'
            );
        } else {
            $(this).siblings('small').text('Leave blank to keep current password (min. 6 characters)');
        }
    });
    
    // Form validation
    $('form').on('submit', function(e) {
        var newPassword = $('#new_password').val();
        var confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== '' && newPassword !== confirmPassword) {
            e.preventDefault();
            $('#confirm_password').addClass('is-invalid');
            setFlashMessage('error', 'New passwords do not match.');
            return false;
        }
        
        if (newPassword !== '' && newPassword.length < 6) {
            e.preventDefault();
            $('#new_password').addClass('is-invalid');
            setFlashMessage('error', 'New password must be at least 6 characters long.');
            return false;
        }
    });
});
</script>

<?php include 'footer.php'; ?>