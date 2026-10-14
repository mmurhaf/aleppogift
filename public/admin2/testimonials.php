<?php
/**
 * Admin Testimonials Management
 * Dubai Gift - Master Copy Brands E-commerce
 */

$pageTitle = "Testimonials Management";
include 'header.php';

function testimonialTableColumns($db) {
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    foreach ($db->fetchAll("SHOW COLUMNS FROM testimonials") as $column) {
        $columns[$column['Field']] = true;
    }

    return $columns;
}

function testimonialColumnExists($columns, $name) {
    return isset($columns[$name]);
}

function uploadTestimonialAsset($file, $prefix) {
    if (!isset($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = '../uploads/testimonials/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return null;
    }

    $fileName = $prefix . '_' . time() . '_' . random_int(1000, 9999) . '.' . $extension;
    $uploadPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return null;
    }

    return 'uploads/testimonials/' . $fileName;
}

$testimonialColumns = testimonialTableColumns($db);
$hasWhatsappProofFields = testimonialColumnExists($testimonialColumns, 'whatsapp_screenshot_path')
    && testimonialColumnExists($testimonialColumns, 'consent_status')
    && testimonialColumnExists($testimonialColumns, 'privacy_blurred');

// Get filter parameters
$status = $_GET['status'] ?? '';
$featured = $_GET['featured'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if ($status === 'approved') {
    $conditions[] = "is_approved = 1";
} elseif ($status === 'pending') {
    $conditions[] = "is_approved = 0";
}

if ($featured === 'yes') {
    $conditions[] = "is_featured = 1";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_testimonial'])) {
        $customerName = trim($_POST['customer_name']);
        $customerLocation = trim($_POST['customer_location']);
        $testimonialText = trim($_POST['testimonial_text']);
        $rating = (int)$_POST['rating'];
        $productPurchased = trim($_POST['product_purchased']);
        $whatsappConversation = isset($_POST['whatsapp_conversation']) ? 1 : 0;
        $videoUrl = trim($_POST['video_url']);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;
        $customerPhoto = uploadTestimonialAsset($_FILES['customer_photo'] ?? null, 'testimonial');
        $whatsAppProof = uploadTestimonialAsset($_FILES['whatsapp_screenshot'] ?? null, 'whatsapp');
        $consentStatus = trim($_POST['consent_status'] ?? 'pending');
        $privacyBlurred = isset($_POST['privacy_blurred']) ? 1 : 0;

        $insertFields = [
            'customer_name',
            'customer_location',
            'customer_photo',
            'testimonial_text',
            'rating',
            'product_purchased',
            'whatsapp_conversation',
            'video_url',
            'is_featured',
            'is_approved'
        ];
        $insertParams = [
            'customer_name' => $customerName,
            'customer_location' => $customerLocation,
            'customer_photo' => $customerPhoto,
            'testimonial_text' => $testimonialText,
            'rating' => $rating,
            'product_purchased' => $productPurchased,
            'whatsapp_conversation' => $whatsappConversation,
            'video_url' => $videoUrl,
            'is_featured' => $isFeatured,
            'is_approved' => $isApproved,
        ];

        if ($hasWhatsappProofFields) {
            $insertFields[] = 'whatsapp_screenshot_path';
            $insertFields[] = 'consent_status';
            $insertFields[] = 'privacy_blurred';
            $insertFields[] = 'proof_source';
            $insertParams['whatsapp_screenshot_path'] = $whatsAppProof;
            $insertParams['consent_status'] = in_array($consentStatus, ['pending', 'granted', 'declined', 'internal_only'], true) ? $consentStatus : 'pending';
            $insertParams['privacy_blurred'] = $privacyBlurred;
            $insertParams['proof_source'] = $whatsappConversation ? 'whatsapp' : null;
        }

        $db->query(
            'INSERT INTO testimonials (' . implode(', ', $insertFields) . ') VALUES (:' . implode(', :', $insertFields) . ')',
            $insertParams
        );
        
        setFlashMessage('success', 'Testimonial added successfully.');
        redirect('testimonials.php');
    }
    
    if (isset($_POST['edit_testimonial'])) {
        $id = (int)$_POST['testimonial_id'];
        $customerName = trim($_POST['customer_name']);
        $customerLocation = trim($_POST['customer_location']);
        $testimonialText = trim($_POST['testimonial_text']);
        $rating = (int)$_POST['rating'];
        $productPurchased = trim($_POST['product_purchased']);
        $whatsappConversation = isset($_POST['whatsapp_conversation']) ? 1 : 0;
        $videoUrl = trim($_POST['video_url']);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;
        $displayOrder = (int)$_POST['display_order'];
        $customerPhoto = uploadTestimonialAsset($_FILES['customer_photo'] ?? null, 'testimonial');
        $whatsAppProof = uploadTestimonialAsset($_FILES['whatsapp_screenshot'] ?? null, 'whatsapp');
        $consentStatus = trim($_POST['consent_status'] ?? 'pending');
        $privacyBlurred = isset($_POST['privacy_blurred']) ? 1 : 0;

        $updateParts = [
            'customer_name = :customer_name',
            'customer_location = :customer_location',
            'testimonial_text = :testimonial_text',
            'rating = :rating',
            'product_purchased = :product_purchased',
            'whatsapp_conversation = :whatsapp_conversation',
            'video_url = :video_url',
            'is_featured = :is_featured',
            'is_approved = :is_approved',
            'display_order = :display_order'
        ];
        $queryParams = [
            'customer_name' => $customerName,
            'customer_location' => $customerLocation,
            'testimonial_text' => $testimonialText,
            'rating' => $rating,
            'product_purchased' => $productPurchased,
            'whatsapp_conversation' => $whatsappConversation,
            'video_url' => $videoUrl,
            'is_featured' => $isFeatured,
            'is_approved' => $isApproved,
            'display_order' => $displayOrder,
            'id' => $id,
        ];

        if ($customerPhoto) {
            $updateParts[] = 'customer_photo = :customer_photo';
            $queryParams['customer_photo'] = $customerPhoto;
        }

        if ($hasWhatsappProofFields) {
            $updateParts[] = 'consent_status = :consent_status';
            $updateParts[] = 'privacy_blurred = :privacy_blurred';
            $updateParts[] = 'proof_source = :proof_source';
            $queryParams['consent_status'] = in_array($consentStatus, ['pending', 'granted', 'declined', 'internal_only'], true) ? $consentStatus : 'pending';
            $queryParams['privacy_blurred'] = $privacyBlurred;
            $queryParams['proof_source'] = $whatsappConversation ? 'whatsapp' : null;

            if ($whatsAppProof) {
                $updateParts[] = 'whatsapp_screenshot_path = :whatsapp_screenshot_path';
                $queryParams['whatsapp_screenshot_path'] = $whatsAppProof;
            }
        }

        $db->query(
            'UPDATE testimonials SET ' . implode(', ', $updateParts) . ' WHERE id = :id',
            $queryParams
        );
        
        setFlashMessage('success', 'Testimonial updated successfully.');
        redirect('testimonials.php');
    }
    
    if (isset($_POST['approve_testimonial'])) {
        $id = (int)$_POST['testimonial_id'];
        $db->query("UPDATE testimonials SET is_approved = 1 WHERE id = :id", ['id' => $id]);
        setFlashMessage('success', 'Testimonial approved successfully.');
        redirect('testimonials.php');
    }
    
    if (isset($_POST['feature_testimonial'])) {
        $id = (int)$_POST['testimonial_id'];
        $db->query("UPDATE testimonials SET is_featured = 1 WHERE id = :id", ['id' => $id]);
        setFlashMessage('success', 'Testimonial featured successfully.');
        redirect('testimonials.php');
    }
    
    if (isset($_POST['delete_testimonial'])) {
        $id = (int)$_POST['testimonial_id'];
        $db->query("DELETE FROM testimonials WHERE id = :id", ['id' => $id]);
        setFlashMessage('success', 'Testimonial deleted successfully.');
        redirect('testimonials.php');
    }
    
    if (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selectedTestimonials = $_POST['selected_testimonials'] ?? [];
        
        if (!empty($selectedTestimonials)) {
            $placeholders = str_repeat('?,', count($selectedTestimonials) - 1) . '?';
            
            switch ($action) {
                case 'approve':
                    $db->query("UPDATE testimonials SET is_approved = 1 WHERE id IN ($placeholders)", $selectedTestimonials);
                    setFlashMessage('success', 'Selected testimonials approved successfully.');
                    break;
                    
                case 'feature':
                    $db->query("UPDATE testimonials SET is_featured = 1 WHERE id IN ($placeholders)", $selectedTestimonials);
                    setFlashMessage('success', 'Selected testimonials featured successfully.');
                    break;
                    
                case 'unfeature':
                    $db->query("UPDATE testimonials SET is_featured = 0 WHERE id IN ($placeholders)", $selectedTestimonials);
                    setFlashMessage('success', 'Selected testimonials unfeatured.');
                    break;
                    
                case 'delete':
                    $db->query("DELETE FROM testimonials WHERE id IN ($placeholders)", $selectedTestimonials);
                    setFlashMessage('success', 'Selected testimonials deleted successfully.');
                    break;
            }
            redirect('testimonials.php');
        }
    }
}

// Get total testimonials count
$totalQuery = "SELECT COUNT(*) as total FROM testimonials $whereClause";
$result = $db->fetch($totalQuery, $params);
$totalTestimonials = $result ? $result['total'] : 0;
$totalPages = ceil($totalTestimonials / $limit);

// Get testimonials
$testimonialsQuery = "
    SELECT * FROM testimonials 
    $whereClause
    ORDER BY display_order ASC, created_at DESC 
    LIMIT $limit OFFSET $offset
";
$testimonials = $db->fetchAll($testimonialsQuery, $params);

// Get statistics
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured,
        AVG(rating) as avg_rating
    FROM testimonials
");
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>

    <?php if (!$hasWhatsappProofFields): ?>
        <div class="alert alert-warning">
            WhatsApp screenshot fields are not available yet. Run public_html/database/add_whatsapp_proof_fields.sql to enable screenshot proof uploads and privacy tracking.
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <div>Total Testimonials</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['approved']); ?></h4>
                    <div>Approved</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['pending']); ?></h4>
                    <div>Pending Approval</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['featured']); ?></h4>
                    <div>Featured</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add New Button & Filters -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-filter"></i> Filters</span>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Add New Testimonial
            </button>
        </div>
        <div class="card-body">
            <form method="GET" action="testimonials.php" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Featured</label>
                    <select name="featured" class="form-select">
                        <option value="">All</option>
                        <option value="yes" <?php echo $featured === 'yes' ? 'selected' : ''; ?>>Featured Only</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Testimonials Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-comments"></i> Testimonials (<?php echo number_format($totalTestimonials); ?>)
        </div>
        <div class="card-body">
            <?php if (empty($testimonials)): ?>
                <div class="alert alert-info">No testimonials found.</div>
            <?php else: ?>
                <form method="POST" id="bulkForm">
                    <!-- Bulk Actions -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <select name="bulk_action" class="form-select" required>
                                    <option value="">Bulk Actions</option>
                                    <option value="approve">Approve</option>
                                    <option value="feature">Feature</option>
                                    <option value="unfeature">Unfeature</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Customer</th>
                                    <th>Testimonial</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($testimonials as $testimonial): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_testimonials[]" value="<?php echo $testimonial['id']; ?>" class="testimonial-checkbox"></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($testimonial['customer_photo']): ?>
                                                <img src="<?php echo  '../' . $testimonial['customer_photo']; ?>" 
                                                     class="rounded-circle me-2" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($testimonial['customer_name']); ?></strong>
                                                <?php if ($testimonial['customer_location']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($testimonial['customer_location']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($testimonial['testimonial_text'], 0, 100)); ?>...</small>
                                        <?php if ($testimonial['product_purchased']): ?>
                                            <br><span class="badge bg-secondary"><?php echo htmlspecialchars($testimonial['product_purchased']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($testimonial['whatsapp_conversation']): ?>
                                            <span class="badge bg-success"><i class="fab fa-whatsapp"></i> WhatsApp</span>
                                        <?php endif; ?>
                                        <?php if ($hasWhatsappProofFields && !empty($testimonial['whatsapp_screenshot_path'])): ?>
                                            <span class="badge bg-primary"><i class="fas fa-image"></i> Screenshot</span>
                                        <?php endif; ?>
                                        <?php if ($testimonial['video_url']): ?>
                                            <span class="badge bg-danger"><i class="fas fa-video"></i> Video</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $testimonial['rating'] ? '⭐' : '☆';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($testimonial['is_approved']): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                        <?php if ($testimonial['is_featured']): ?>
                                            <span class="badge bg-info">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $testimonial['display_order']; ?></td>
                                    <td><small><?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></small></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $testimonial['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?php echo $testimonial['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Testimonial</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Customer Name *</label>
                                                            <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($testimonial['customer_name']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Location</label>
                                                            <input type="text" name="customer_location" class="form-control" value="<?php echo htmlspecialchars($testimonial['customer_location']); ?>" placeholder="City, Country">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Customer Photo</label>
                                                        <?php if ($testimonial['customer_photo']): ?>
                                                            <div class="mb-2">
                                                                <img src="<?php echo '../' . $testimonial['customer_photo']; ?>" style="width: 100px; height: 100px; object-fit: cover;" class="rounded">
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" name="customer_photo" class="form-control" accept="image/*">
                                                    </div>

                                                    <?php if ($hasWhatsappProofFields): ?>
                                                    <div class="mb-3">
                                                        <label class="form-label">WhatsApp Screenshot Proof</label>
                                                        <?php if (!empty($testimonial['whatsapp_screenshot_path'])): ?>
                                                            <div class="mb-2">
                                                                <img src="<?php echo '../' . $testimonial['whatsapp_screenshot_path']; ?>" style="max-width: 220px; width: 100%; object-fit: cover;" class="rounded border">
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" name="whatsapp_screenshot" class="form-control" accept="image/*">
                                                        <div class="form-text">Upload a privacy-blurred WhatsApp screenshot only.</div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Testimonial Text *</label>
                                                        <textarea name="testimonial_text" class="form-control" rows="4" required><?php echo htmlspecialchars($testimonial['testimonial_text']); ?></textarea>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Rating *</label>
                                                            <select name="rating" class="form-select" required>
                                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                                    <option value="<?php echo $i; ?>" <?php echo $testimonial['rating'] == $i ? 'selected' : ''; ?>>
                                                                        <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Display Order</label>
                                                            <input type="number" name="display_order" class="form-control" value="<?php echo $testimonial['display_order']; ?>">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Product Purchased</label>
                                                        <input type="text" name="product_purchased" class="form-control" value="<?php echo htmlspecialchars($testimonial['product_purchased']); ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Video URL (YouTube/Vimeo)</label>
                                                        <input type="url" name="video_url" class="form-control" value="<?php echo htmlspecialchars($testimonial['video_url']); ?>">
                                                    </div>

                                                    <?php if ($hasWhatsappProofFields): ?>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Consent Status</label>
                                                            <select name="consent_status" class="form-select">
                                                                <?php $currentConsent = $testimonial['consent_status'] ?? 'pending'; ?>
                                                                <option value="pending" <?php echo $currentConsent === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="granted" <?php echo $currentConsent === 'granted' ? 'selected' : ''; ?>>Granted</option>
                                                                <option value="internal_only" <?php echo $currentConsent === 'internal_only' ? 'selected' : ''; ?>>Internal Only</option>
                                                                <option value="declined" <?php echo $currentConsent === 'declined' ? 'selected' : ''; ?>>Declined</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3 d-flex align-items-end">
                                                            <div class="form-check mb-2">
                                                                <input type="checkbox" name="privacy_blurred" class="form-check-input" id="privacy<?php echo $testimonial['id']; ?>" <?php echo !empty($testimonial['privacy_blurred']) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="privacy<?php echo $testimonial['id']; ?>">
                                                                    Sensitive details blurred
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input type="checkbox" name="whatsapp_conversation" class="form-check-input" id="whatsapp<?php echo $testimonial['id']; ?>" <?php echo $testimonial['whatsapp_conversation'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="whatsapp<?php echo $testimonial['id']; ?>">
                                                                From WhatsApp Conversation
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input type="checkbox" name="is_featured" class="form-check-input" id="featured<?php echo $testimonial['id']; ?>" <?php echo $testimonial['is_featured'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="featured<?php echo $testimonial['id']; ?>">
                                                                Feature on Homepage
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input type="checkbox" name="is_approved" class="form-check-input" id="approved<?php echo $testimonial['id']; ?>" <?php echo $testimonial['is_approved'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="approved<?php echo $testimonial['id']; ?>">
                                                                Approved
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="edit_testimonial" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&featured=<?php echo $featured; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add New Testimonial Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Testimonial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="customer_location" class="form-control" placeholder="City, Country">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Photo</label>
                        <input type="file" name="customer_photo" class="form-control" accept="image/*">
                    </div>

                    <?php if ($hasWhatsappProofFields): ?>
                    <div class="mb-3">
                        <label class="form-label">WhatsApp Screenshot Proof</label>
                        <input type="file" name="whatsapp_screenshot" class="form-control" accept="image/*">
                        <div class="form-text">Upload only blurred screenshots that are safe to publish.</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Testimonial Text *</label>
                        <textarea name="testimonial_text" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rating *</label>
                            <select name="rating" class="form-select" required>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Purchased</label>
                            <input type="text" name="product_purchased" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Video URL (YouTube/Vimeo)</label>
                        <input type="url" name="video_url" class="form-control">
                    </div>

                    <?php if ($hasWhatsappProofFields): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Consent Status</label>
                            <select name="consent_status" class="form-select">
                                <option value="pending">Pending</option>
                                <option value="granted">Granted</option>
                                <option value="internal_only">Internal Only</option>
                                <option value="declined">Declined</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="privacy_blurred" class="form-check-input" id="privacyNew">
                                <label class="form-check-label" for="privacyNew">
                                    Sensitive details blurred
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="whatsapp_conversation" class="form-check-input" id="whatsappNew">
                            <label class="form-check-label" for="whatsappNew">
                                From WhatsApp Conversation
                            </label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_featured" class="form-check-input" id="featuredNew">
                            <label class="form-check-label" for="featuredNew">
                                Feature on Homepage
                            </label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_approved" class="form-check-input" id="approvedNew" checked>
                            <label class="form-check-label" for="approvedNew">
                                Approved
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_testimonial" class="btn btn-success">Add Testimonial</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Select all checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.testimonial-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>

<?php include 'footer.php'; ?>
