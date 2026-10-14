<?php
/**
 * Admin Product Reviews Management
 * Dubai Gift - Master Copy Brands E-commerce
 */

$pageTitle = "Product Reviews Management";
include 'header.php';

// Get filter parameters
$productId = $_GET['product'] ?? 0;
$status = $_GET['status'] ?? '';
$rating = $_GET['rating'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if ($productId > 0) {
    $conditions[] = "pr.product_id = ?";
    $params[] = $productId;
}

if ($status === 'approved') {
    $conditions[] = "pr.is_approved = 1";
} elseif ($status === 'pending') {
    $conditions[] = "pr.is_approved = 0";
}

if ($rating > 0) {
    $conditions[] = "pr.rating = ?";
    $params[] = $rating;
}

if (!empty($search)) {
    $conditions[] = "(pr.customer_name LIKE ? OR pr.review_text LIKE ? OR p.name_en LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_review'])) {
        $reviewId = (int)$_POST['review_id'];
        $db->execute("UPDATE product_reviews SET is_approved = 1 WHERE id = ?", [$reviewId]);
        setFlashMessage('success', 'Review approved successfully.');
        redirect('reviews.php');
    }
    
    if (isset($_POST['reject_review'])) {
        $reviewId = (int)$_POST['review_id'];
        $db->execute("UPDATE product_reviews SET is_approved = 0 WHERE id = ?", [$reviewId]);
        setFlashMessage('success', 'Review rejected.');
        redirect('reviews.php');
    }
    
    if (isset($_POST['delete_review'])) {
        $reviewId = (int)$_POST['review_id'];
        $db->execute("DELETE FROM product_reviews WHERE id = ?", [$reviewId]);
        setFlashMessage('success', 'Review deleted successfully.');
        redirect('reviews.php');
    }
    
    if (isset($_POST['add_admin_response'])) {
        $reviewId = (int)$_POST['review_id'];
        $response = trim($_POST['admin_response']);
        
        if (!empty($response)) {
            $db->execute(
                "UPDATE product_reviews SET admin_response = ?, admin_response_date = NOW() WHERE id = ?",
                [$response, $reviewId]
            );
            setFlashMessage('success', 'Admin response added successfully.');
        }
        redirect('reviews.php');
    }
    
    if (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selectedReviews = $_POST['selected_reviews'] ?? [];
        
        if (!empty($selectedReviews)) {
            $placeholders = str_repeat('?,', count($selectedReviews) - 1) . '?';
            
            switch ($action) {
                case 'approve':
                    $db->execute("UPDATE product_reviews SET is_approved = 1 WHERE id IN ($placeholders)", $selectedReviews);
                    setFlashMessage('success', 'Selected reviews approved successfully.');
                    break;
                    
                case 'reject':
                    $db->execute("UPDATE product_reviews SET is_approved = 0 WHERE id IN ($placeholders)", $selectedReviews);
                    setFlashMessage('success', 'Selected reviews rejected.');
                    break;
                    
                case 'delete':
                    $db->execute("DELETE FROM product_reviews WHERE id IN ($placeholders)", $selectedReviews);
                    setFlashMessage('success', 'Selected reviews deleted successfully.');
                    break;
            }
            redirect('reviews.php');
        }
    }
}

// Get total reviews count
$totalQuery = "SELECT COUNT(*) as total FROM product_reviews pr LEFT JOIN products p ON pr.product_id = p.id $whereClause";
$result = $db->fetch($totalQuery, $params);
$totalReviews = $result ? $result['total'] : 0;
$totalPages = ceil($totalReviews / $limit);

// Get reviews
$reviewsQuery = "
    SELECT pr.*, p.name_en as product_name, pi.image_path as product_image
    FROM product_reviews pr 
    LEFT JOIN products p ON pr.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
    $whereClause
    ORDER BY pr.created_at DESC 
    LIMIT $limit OFFSET $offset
";
$reviews = $db->fetchAll($reviewsQuery, $params);

// Get statistics
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN is_verified_purchase = 1 THEN 1 ELSE 0 END) as verified
    FROM product_reviews
");

// Get products for filter
$products = $db->fetchAll("SELECT id, name_en FROM products ORDER BY name_en LIMIT 100");
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <div>Total Reviews</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['approved']); ?></h4>
                    <div>Approved Reviews</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['pending']); ?></h4>
                    <div>Pending Reviews</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format($stats['avg_rating'], 1); ?> ⭐</h4>
                    <div>Average Rating</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter"></i> Filters
        </div>
        <div class="card-body">
            <form method="GET" action="reviews.php" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select name="product" class="form-select">
                        <option value="0">All Products</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $productId == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name_en']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Rating</label>
                    <select name="rating" class="form-select">
                        <option value="">All Ratings</option>
                        <option value="5" <?php echo $rating == '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating == '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating == '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating == '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating == '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Customer name or review text" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reviews Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-star"></i> Reviews (<?php echo number_format($totalReviews); ?>)</span>
        </div>
        <div class="card-body">
            <?php if (empty($reviews)): ?>
                <div class="alert alert-info">No reviews found.</div>
            <?php else: ?>
                <form method="POST" id="bulkForm">
                    <!-- Bulk Actions -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <select name="bulk_action" class="form-select" required>
                                    <option value="">Bulk Actions</option>
                                    <option value="approve">Approve</option>
                                    <option value="reject">Reject</option>
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
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th>Rating</th>
                                    <th>Review</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_reviews[]" value="<?php echo $review['id']; ?>" class="review-checkbox"></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($review['product_image']): ?>
                                                <img src="<?php echo getImageUrl($review['product_image']); ?>" 
                                                     class="img-thumbnail me-2" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php endif; ?>
                                            <small><?php echo htmlspecialchars($review['product_name']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($review['customer_name']); ?></strong>
                                        <?php if ($review['is_verified_purchase']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $review['rating'] ? '⭐' : '☆';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($review['review_title']): ?>
                                            <strong><?php echo htmlspecialchars($review['review_title']); ?></strong><br>
                                        <?php endif; ?>
                                        <small><?php echo htmlspecialchars(substr($review['review_text'], 0, 100)); ?>...</small>
                                        <?php if ($review['helpful_count'] > 0): ?>
                                            <br><small class="text-muted"><?php echo $review['helpful_count']; ?> found helpful</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($review['is_approved']): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $review['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Review Detail Modal -->
                                <div class="modal fade" id="reviewModal<?php echo $review['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Review Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Product:</strong> <?php echo htmlspecialchars($review['product_name']); ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Customer:</strong> <?php echo htmlspecialchars($review['customer_name']); ?>
                                                        <?php if ($review['is_verified_purchase']): ?>
                                                            <span class="badge bg-success">Verified Purchase</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Email:</strong> <?php echo htmlspecialchars($review['customer_email'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Phone:</strong> <?php echo htmlspecialchars($review['customer_phone'] ?? 'N/A'); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <strong>Rating:</strong>
                                                    <?php 
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $review['rating'] ? '⭐' : '☆';
                                                    }
                                                    ?>
                                                </div>
                                                
                                                <?php if ($review['review_title']): ?>
                                                <div class="mb-3">
                                                    <strong>Title:</strong><br>
                                                    <?php echo htmlspecialchars($review['review_title']); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="mb-3">
                                                    <strong>Review:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                                                </div>
                                                
                                                <?php if ($review['admin_response']): ?>
                                                <div class="alert alert-info mb-3">
                                                    <strong>Admin Response:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($review['admin_response'])); ?>
                                                    <br><small class="text-muted">Responded on: <?php echo date('M d, Y', strtotime($review['admin_response_date'])); ?></small>
                                                </div>
                                                <?php else: ?>
                                                <form method="POST" class="mb-3">
                                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                    <label class="form-label"><strong>Add Admin Response:</strong></label>
                                                    <textarea name="admin_response" class="form-control mb-2" rows="3" placeholder="Type your response..."></textarea>
                                                    <button type="submit" name="add_admin_response" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-reply"></i> Add Response
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">Submitted: <?php echo date('F j, Y, g:i a', strtotime($review['created_at'])); ?></small>
                                                </div>
                                                
                                                <div class="btn-group" role="group">
                                                    <?php if (!$review['is_approved']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                        <button type="submit" name="approve_review" class="btn btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                        <button type="submit" name="reject_review" class="btn btn-warning">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this review?');">
                                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                        <button type="submit" name="delete_review" class="btn btn-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
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
                                <a class="page-link" href="?page=<?php echo $i; ?>&product=<?php echo $productId; ?>&status=<?php echo $status; ?>&rating=<?php echo $rating; ?>&search=<?php echo urlencode($search); ?>">
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

<script>
// Select all checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.review-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>

<?php include 'footer.php'; ?>
