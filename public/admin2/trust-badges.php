<?php
/**
 * Admin Trust Badges Management
 * Dubai Gift - Master Copy Brands E-commerce
 */

$pageTitle = "Trust Badges Management";
include 'header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_badge'])) {
        $badgeName = trim($_POST['badge_name']);
        $badgeDescription = trim($_POST['badge_description']);
        $badgeIcon = trim($_POST['badge_icon']);
        $badgeType = $_POST['badge_type'];
        $displayLocation = $_POST['display_location'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $displayOrder = (int)$_POST['display_order'];
        
        $db->execute(
            "INSERT INTO trust_badges (badge_name, badge_description, badge_icon, badge_type, display_location, is_active, display_order) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$badgeName, $badgeDescription, $badgeIcon, $badgeType, $displayLocation, $isActive, $displayOrder]
        );
        
        setFlashMessage('success', 'Trust badge added successfully.');
        redirect('trust-badges.php');
    }
    
    if (isset($_POST['edit_badge'])) {
        $id = (int)$_POST['badge_id'];
        $badgeName = trim($_POST['badge_name']);
        $badgeDescription = trim($_POST['badge_description']);
        $badgeIcon = trim($_POST['badge_icon']);
        $badgeType = $_POST['badge_type'];
        $displayLocation = $_POST['display_location'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $displayOrder = (int)$_POST['display_order'];
        
        $db->execute(
            "UPDATE trust_badges SET badge_name = ?, badge_description = ?, badge_icon = ?, badge_type = ?, display_location = ?, is_active = ?, display_order = ? WHERE id = ?",
            [$badgeName, $badgeDescription, $badgeIcon, $badgeType, $displayLocation, $isActive, $displayOrder, $id]
        );
        
        setFlashMessage('success', 'Trust badge updated successfully.');
        redirect('trust-badges.php');
    }
    
    if (isset($_POST['delete_badge'])) {
        $id = (int)$_POST['badge_id'];
        $db->execute("DELETE FROM trust_badges WHERE id = ?", [$id]);
        setFlashMessage('success', 'Trust badge deleted successfully.');
        redirect('trust-badges.php');
    }
    
    if (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['badge_id'];
        $db->execute("UPDATE trust_badges SET is_active = NOT is_active WHERE id = ?", [$id]);
        setFlashMessage('success', 'Badge status updated successfully.');
        redirect('trust-badges.php');
    }
}

// Get all trust badges
$badges = $db->fetchAll("SELECT * FROM trust_badges ORDER BY display_order ASC, id DESC");

// Get statistics
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM trust_badges
");

// Badge type options
$badgeTypes = [
    'security' => 'Security',
    'payment' => 'Payment',
    'shipping' => 'Shipping',
    'guarantee' => 'Guarantee',
    'certification' => 'Certification'
];

// Display location options
$displayLocations = [
    'checkout' => 'Checkout Page',
    'product' => 'Product Pages',
    'footer' => 'Footer',
    'all' => 'All Pages'
];

// Popular icon classes (FontAwesome)
$iconOptions = [
    'fas fa-lock' => 'Lock (Security)',
    'fas fa-shield-alt' => 'Shield',
    'fas fa-certificate' => 'Certificate',
    'fas fa-check-circle' => 'Check Circle',
    'fas fa-star' => 'Star',
    'fas fa-shipping-fast' => 'Fast Shipping',
    'fas fa-credit-card' => 'Credit Card',
    'fas fa-undo' => 'Return/Undo',
    'fas fa-truck' => 'Truck',
    'fas fa-users' => 'Users/Customers',
    'fas fa-thumbs-up' => 'Thumbs Up',
    'fas fa-heart' => 'Heart',
    'fas fa-award' => 'Award',
    'fas fa-money-check-alt' => 'Money Back',
    'fab fa-cc-visa' => 'Visa Card',
    'fab fa-cc-mastercard' => 'Mastercard',
    'fab fa-cc-paypal' => 'PayPal'
];
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <div>Total Badges</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['active']); ?></h4>
                    <div>Active Badges</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-secondary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['inactive']); ?></h4>
                    <div>Inactive Badges</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Trust Badges -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-award"></i> Trust Badges</span>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Add New Badge
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($badges)): ?>
                <div class="alert alert-info">No trust badges found. Add your first badge to build customer trust!</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($badges as $badge): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 <?php echo $badge['is_active'] ? 'border-success' : 'border-secondary'; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="badge-icon" style="font-size: 2rem; color: #0d6efd;">
                                        <i class="<?php echo htmlspecialchars($badge['badge_icon']); ?>"></i>
                                    </div>
                                    <div>
                                        <?php if ($badge['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <h5 class="card-title"><?php echo htmlspecialchars($badge['badge_name']); ?></h5>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars($badge['badge_description']); ?></p>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <strong>Type:</strong> 
                                        <span class="badge bg-info"><?php echo ucfirst($badge['badge_type']); ?></span>
                                    </small>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <strong>Display:</strong> 
                                        <span class="badge bg-secondary"><?php echo ucfirst($badge['display_location']); ?></span>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <strong>Order:</strong> <?php echo $badge['display_order']; ?>
                                    </small>
                                </div>
                                
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $badge['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" style="flex: 1;">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $badge['is_active'] ? 'warning' : 'success'; ?> w-100">
                                            <i class="fas fa-power-off"></i> <?php echo $badge['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this badge?');">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                        <button type="submit" name="delete_badge" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?php echo $badge['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Trust Badge</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Badge Name *</label>
                                            <input type="text" name="badge_name" class="form-control" value="<?php echo htmlspecialchars($badge['badge_name']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="badge_description" class="form-control" rows="2"><?php echo htmlspecialchars($badge['badge_description']); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Icon Class (FontAwesome) *</label>
                                            <select name="badge_icon" class="form-select" required>
                                                <?php foreach ($iconOptions as $iconClass => $iconLabel): ?>
                                                    <option value="<?php echo $iconClass; ?>" <?php echo $badge['badge_icon'] === $iconClass ? 'selected' : ''; ?>>
                                                        <?php echo $iconLabel; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Or enter custom FontAwesome class above</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Badge Type *</label>
                                            <select name="badge_type" class="form-select" required>
                                                <?php foreach ($badgeTypes as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $badge['badge_type'] === $key ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Display Location *</label>
                                            <select name="display_location" class="form-select" required>
                                                <?php foreach ($displayLocations as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $badge['display_location'] === $key ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" value="<?php echo $badge['display_order']; ?>" min="0">
                                            <small class="text-muted">Lower numbers appear first</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" class="form-check-input" id="active<?php echo $badge['id']; ?>" <?php echo $badge['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="active<?php echo $badge['id']; ?>">
                                                    Active (Display on site)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="edit_badge" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Info Card -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <h5><i class="fas fa-info-circle"></i> About Trust Badges</h5>
            <p class="mb-2">Trust badges are visual indicators that build credibility and confidence with your customers. They help:</p>
            <ul class="mb-0">
                <li>Reduce cart abandonment by up to 18%</li>
                <li>Increase conversion rates by 15-30%</li>
                <li>Build customer confidence in security and quality</li>
                <li>Highlight guarantees, certifications, and payment options</li>
            </ul>
            <hr>
            <p class="mb-0"><strong>Display Locations:</strong></p>
            <ul class="mb-0">
                <li><strong>Checkout:</strong> Appears only on checkout/cart pages (highest impact)</li>
                <li><strong>Product:</strong> Shown on individual product pages</li>
                <li><strong>Footer:</strong> Displayed in website footer on all pages</li>
                <li><strong>All Pages:</strong> Appears across the entire website</li>
            </ul>
        </div>
    </div>
</div>

<!-- Add New Badge Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Trust Badge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Badge Name *</label>
                        <input type="text" name="badge_name" class="form-control" placeholder="e.g., Secure Payment" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="badge_description" class="form-control" rows="2" placeholder="e.g., 256-bit SSL Encrypted"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Icon Class (FontAwesome) *</label>
                        <select name="badge_icon" class="form-select" required>
                            <?php foreach ($iconOptions as $iconClass => $iconLabel): ?>
                                <option value="<?php echo $iconClass; ?>"><?php echo $iconLabel; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Visit <a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a> for more icons</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Badge Type *</label>
                        <select name="badge_type" class="form-select" required>
                            <?php foreach ($badgeTypes as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Display Location *</label>
                        <select name="display_location" class="form-select" required>
                            <?php foreach ($displayLocations as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="999" min="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="activeNew" checked>
                            <label class="form-check-label" for="activeNew">
                                Active (Display on site)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_badge" class="btn btn-success">Add Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
