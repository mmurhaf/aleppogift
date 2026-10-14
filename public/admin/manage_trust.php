<?php
/**
 * Admin Panel - Manage Reviews and Testimonials
 * Purpose: Approve/reject customer reviews, manage testimonials, trust badges
 */

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();
$conn = $db->getPdo();

function testimonialTableColumns(PDO $conn): array
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $statement = $conn->query("SHOW COLUMNS FROM testimonials");

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = true;
    }

    return $columns;
}

function testimonialColumnExists(array $columns, string $name): bool
{
    return isset($columns[$name]);
}

function uploadTestimonialAsset(?array $file, string $prefix): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads/testimonials/';
    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    $fileName = $prefix . '_' . time() . '_' . random_int(1000, 9999) . '.' . $extension;
    $targetPath = $uploadDirectory . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return 'uploads/testimonials/' . $fileName;
}

$testimonialColumns = testimonialTableColumns($conn);
$hasWhatsappProofFields = testimonialColumnExists($testimonialColumns, 'whatsapp_screenshot_path')
    && testimonialColumnExists($testimonialColumns, 'consent_status')
    && testimonialColumnExists($testimonialColumns, 'privacy_blurred')
    && testimonialColumnExists($testimonialColumns, 'proof_source');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_review':
            $review_id = (int)$_POST['review_id'];
            $stmt = $conn->prepare("UPDATE product_reviews SET is_approved = 1 WHERE id = :id");
            $stmt->execute(['id' => $review_id]);
            $success_message = "Review approved successfully!";
            break;
            
        case 'reject_review':
            $review_id = (int)$_POST['review_id'];
            $stmt = $conn->prepare("DELETE FROM product_reviews WHERE id = :id");
            $stmt->execute(['id' => $review_id]);
            $success_message = "Review rejected and deleted!";
            break;
            
        case 'respond_review':
            $review_id = (int)$_POST['review_id'];
            $response = htmlspecialchars($_POST['admin_response'], ENT_QUOTES, 'UTF-8');
            $stmt = $conn->prepare("UPDATE product_reviews SET admin_response = :response, admin_response_date = NOW() WHERE id = :id");
            $stmt->execute(['response' => $response, 'id' => $review_id]);
            $success_message = "Response added successfully!";
            break;
            
        case 'approve_testimonial':
            $testimonial_id = (int)$_POST['testimonial_id'];
            $stmt = $conn->prepare("UPDATE testimonials SET is_approved = 1 WHERE id = :id");
            $stmt->execute(['id' => $testimonial_id]);
            $success_message = "Testimonial approved!";
            break;
            
        case 'feature_testimonial':
            $testimonial_id = (int)$_POST['testimonial_id'];
            $is_featured = (int)$_POST['is_featured'];
            $stmt = $conn->prepare("UPDATE testimonials SET is_featured = :is_featured WHERE id = :id");
            $stmt->execute(['is_featured' => $is_featured, 'id' => $testimonial_id]);
            $success_message = $is_featured ? "Testimonial featured!" : "Removed from featured!";
            break;
            
        case 'delete_testimonial':
            $testimonial_id = (int)$_POST['testimonial_id'];
            $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = :id");
            $stmt->execute(['id' => $testimonial_id]);
            $success_message = "Testimonial deleted!";
            break;

        case 'add_testimonial':
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_location = trim($_POST['customer_location'] ?? '');
            $testimonial_text = trim($_POST['testimonial_text'] ?? '');
            $rating = (int)($_POST['rating'] ?? 5);
            $product_purchased = trim($_POST['product_purchased'] ?? '');
            $whatsapp_conversation = isset($_POST['whatsapp_conversation']) ? 1 : 0;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $display_order = isset($_POST['display_order']) && $_POST['display_order'] !== '' ? (int)$_POST['display_order'] : 999;

            if ($customer_name === '' || $testimonial_text === '') {
                $success_message = null;
                break;
            }

            $insertFields = [
                'customer_name',
                'customer_location',
                'testimonial_text',
                'rating',
                'product_purchased',
                'whatsapp_conversation',
                'is_featured',
                'is_approved',
                'display_order'
            ];

            $insertValues = [
                'customer_name' => $customer_name,
                'customer_location' => $customer_location,
                'testimonial_text' => $testimonial_text,
                'rating' => max(1, min(5, $rating)),
                'product_purchased' => $product_purchased !== '' ? $product_purchased : null,
                'whatsapp_conversation' => $whatsapp_conversation,
                'is_featured' => $is_featured,
                'is_approved' => 1,
                'display_order' => $display_order,
            ];

            if ($hasWhatsappProofFields) {
                $screenshotPath = uploadTestimonialAsset($_FILES['whatsapp_screenshot'] ?? null, 'whatsapp');
                $consent_status = $_POST['consent_status'] ?? 'granted';
                $allowedConsent = ['pending', 'granted', 'declined', 'internal_only'];

                $insertFields[] = 'whatsapp_screenshot_path';
                $insertFields[] = 'consent_status';
                $insertFields[] = 'privacy_blurred';
                $insertFields[] = 'proof_source';

                $insertValues['whatsapp_screenshot_path'] = $screenshotPath;
                $insertValues['consent_status'] = in_array($consent_status, $allowedConsent, true) ? $consent_status : 'granted';
                $insertValues['privacy_blurred'] = $screenshotPath ? 1 : (isset($_POST['privacy_blurred']) ? 1 : 0);
                $insertValues['proof_source'] = $whatsapp_conversation ? 'whatsapp' : null;
            }

            $sql = 'INSERT INTO testimonials (' . implode(', ', $insertFields) . ') VALUES (:' . implode(', :', $insertFields) . ')';
            $stmt = $conn->prepare($sql);
            $stmt->execute($insertValues);

            $success_message = 'Testimonial added successfully!';
            break;

        case 'edit_testimonial':
            $testimonial_id = (int)($_POST['testimonial_id'] ?? 0);
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_location = trim($_POST['customer_location'] ?? '');
            $testimonial_text = trim($_POST['testimonial_text'] ?? '');
            $rating = (int)($_POST['rating'] ?? 5);
            $product_purchased = trim($_POST['product_purchased'] ?? '');
            $display_order = isset($_POST['display_order']) && $_POST['display_order'] !== '' ? (int)$_POST['display_order'] : 999;
            $whatsapp_conversation = isset($_POST['whatsapp_conversation']) ? 1 : 0;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_approved = isset($_POST['is_approved']) ? 1 : 0;

            if ($testimonial_id <= 0 || $customer_name === '' || $testimonial_text === '') {
                break;
            }

            $updateParts = [
                'customer_name = :customer_name',
                'customer_location = :customer_location',
                'testimonial_text = :testimonial_text',
                'rating = :rating',
                'product_purchased = :product_purchased',
                'display_order = :display_order',
                'whatsapp_conversation = :whatsapp_conversation',
                'is_featured = :is_featured',
                'is_approved = :is_approved'
            ];

            $queryParams = [
                'customer_name' => $customer_name,
                'customer_location' => $customer_location,
                'testimonial_text' => $testimonial_text,
                'rating' => max(1, min(5, $rating)),
                'product_purchased' => $product_purchased !== '' ? $product_purchased : null,
                'display_order' => $display_order,
                'whatsapp_conversation' => $whatsapp_conversation,
                'is_featured' => $is_featured,
                'is_approved' => $is_approved,
                'id' => $testimonial_id,
            ];

            if ($hasWhatsappProofFields) {
                $newScreenshotPath = uploadTestimonialAsset($_FILES['whatsapp_screenshot'] ?? null, 'whatsapp');
                $consent_status = $_POST['consent_status'] ?? 'granted';
                $allowedConsent = ['pending', 'granted', 'declined', 'internal_only'];

                $updateParts[] = 'consent_status = :consent_status';
                $updateParts[] = 'privacy_blurred = :privacy_blurred';
                $updateParts[] = 'proof_source = :proof_source';

                $queryParams['consent_status'] = in_array($consent_status, $allowedConsent, true) ? $consent_status : 'granted';
                $queryParams['privacy_blurred'] = $newScreenshotPath ? 1 : (isset($_POST['privacy_blurred']) ? 1 : 0);
                $queryParams['proof_source'] = $whatsapp_conversation ? 'whatsapp' : null;

                if ($newScreenshotPath) {
                    $updateParts[] = 'whatsapp_screenshot_path = :whatsapp_screenshot_path';
                    $queryParams['whatsapp_screenshot_path'] = $newScreenshotPath;
                }
            }

            $sql = 'UPDATE testimonials SET ' . implode(', ', $updateParts) . ' WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->execute($queryParams);

            $success_message = 'Testimonial updated successfully!';
            break;

        case 'approve_all_publish':
            $approvedReviews = $conn->exec("UPDATE product_reviews SET is_approved = 1 WHERE is_approved = 0");
            $approvedTestimonials = $conn->exec("UPDATE testimonials SET is_approved = 1 WHERE is_approved = 0");

            if ($hasWhatsappProofFields) {
                $conn->exec("UPDATE testimonials
                    SET consent_status = 'granted',
                        privacy_blurred = CASE
                            WHEN whatsapp_screenshot_path IS NOT NULL AND whatsapp_screenshot_path <> '' THEN 1
                            ELSE privacy_blurred
                        END,
                        proof_source = CASE
                            WHEN whatsapp_conversation = 1 THEN 'whatsapp'
                            ELSE proof_source
                        END
                    WHERE is_approved = 1");
            }

            $success_message = 'Published successfully: ' . (int)$approvedReviews . ' reviews and ' . (int)$approvedTestimonials . ' testimonials approved.';
            break;
    }
}

// Get pending reviews
$pending_reviews = $conn->query("
    SELECT pr.*, p.name_en as product_name, p.price,
           CASE WHEN o.id IS NOT NULL THEN 1 ELSE 0 END as has_order
    FROM product_reviews pr
    LEFT JOIN products p ON pr.product_id = p.id
    LEFT JOIN orders o ON pr.order_id = o.id
    WHERE pr.is_approved = 0
    ORDER BY pr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get approved reviews
$approved_reviews = $conn->query("
    SELECT pr.*, p.name_en as product_name
    FROM product_reviews pr
    LEFT JOIN products p ON pr.product_id = p.id
    WHERE pr.is_approved = 1
    ORDER BY pr.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Get all testimonials
$testimonials = $conn->query("
    SELECT * FROM testimonials 
    ORDER BY is_featured DESC, is_approved DESC, created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$review_stats = $conn->query("
    SELECT
        COUNT(*) as total_reviews,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending_reviews,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_reviews,
        SUM(CASE WHEN order_id IS NOT NULL THEN 1 ELSE 0 END) as verified_reviews
    FROM product_reviews
")->fetch(PDO::FETCH_ASSOC) ?: [];

$testimonial_stats = $conn->query("
    SELECT
        COUNT(*) as total_testimonials,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending_testimonials,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_testimonials,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_testimonials,
        SUM(CASE WHEN whatsapp_conversation = 1 THEN 1 ELSE 0 END) as whatsapp_testimonials
    FROM testimonials
")->fetch(PDO::FETCH_ASSOC) ?: [];

$stats = [
    'total_reviews' => (int)($review_stats['total_reviews'] ?? 0),
    'pending_reviews' => (int)($review_stats['pending_reviews'] ?? 0),
    'approved_reviews' => (int)($review_stats['approved_reviews'] ?? 0),
    'verified_reviews' => (int)($review_stats['verified_reviews'] ?? 0),
    'total_testimonials' => (int)($testimonial_stats['total_testimonials'] ?? 0),
    'pending_testimonials' => (int)($testimonial_stats['pending_testimonials'] ?? 0),
    'approved_testimonials' => (int)($testimonial_stats['approved_testimonials'] ?? 0),
    'featured_testimonials' => (int)($testimonial_stats['featured_testimonials'] ?? 0),
    'whatsapp_testimonials' => (int)($testimonial_stats['whatsapp_testimonials'] ?? 0),
    'attention_items' => (int)($review_stats['pending_reviews'] ?? 0) + (int)($testimonial_stats['pending_testimonials'] ?? 0),
];
?>
<?php
$page_title = 'Trust Management';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .manage-trust-page {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .trust-page-header {
        position: relative;
        overflow: hidden;
        padding: 1.75rem;
        border-radius: 28px;
        border: 1px solid rgba(195, 130, 55, 0.18);
        background:
            radial-gradient(circle at top left, rgba(255, 190, 92, 0.24), transparent 32%),
            radial-gradient(circle at bottom right, rgba(225, 117, 39, 0.14), transparent 30%),
            linear-gradient(135deg, #fff8ef 0%, #ffffff 55%, #fff1de 100%);
        box-shadow: 0 24px 50px rgba(31, 27, 19, 0.08);
    }

    .trust-page-header > * {
        position: relative;
        z-index: 1;
    }

    .trust-page-header::after {
        content: '';
        position: absolute;
        width: 240px;
        height: 240px;
        right: -70px;
        top: -90px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(210, 120, 32, 0.12) 0%, rgba(210, 120, 32, 0) 72%);
        pointer-events: none;
    }

    .trust-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        border-radius: 999px;
        background: rgba(118, 75, 162, 0.1);
        color: #7a4ca2;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .trust-header-copy {
        max-width: 760px;
        margin-top: 1rem;
    }

    .trust-header-copy h2 {
        margin-bottom: 0.55rem;
        font-size: clamp(1.9rem, 2.8vw, 2.5rem);
        font-weight: 800;
    }

    .trust-header-copy p {
        margin: 0;
        color: #5f6b7a;
        font-size: 1rem;
    }

    .trust-header-highlights {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 1.15rem;
    }

    .trust-highlight {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(201, 145, 84, 0.16);
        background: rgba(255, 255, 255, 0.72);
        color: #324150;
        font-weight: 600;
    }

    .trust-highlight strong {
        color: #121926;
    }

    .trust-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }

    .trust-stat-card {
        position: relative;
        overflow: hidden;
        padding: 1.2rem 1.25rem;
        border-radius: 22px;
        border: 1px solid rgba(221, 227, 235, 0.9);
        background: #ffffff;
        box-shadow: 0 16px 36px rgba(15, 23, 42, 0.06);
    }

    .trust-stat-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: var(--card-accent, linear-gradient(90deg, #ea580c, #f59e0b));
    }

    .trust-stat-card.reviews { --card-accent: linear-gradient(90deg, #2563eb, #38bdf8); }
    .trust-stat-card.pending { --card-accent: linear-gradient(90deg, #f59e0b, #f97316); }
    .trust-stat-card.approved { --card-accent: linear-gradient(90deg, #10b981, #22c55e); }
    .trust-stat-card.testimonials { --card-accent: linear-gradient(90deg, #8b5cf6, #c084fc); }
    .trust-stat-card.needs-action { --card-accent: linear-gradient(90deg, #ef4444, #fb7185); }
    .trust-stat-card.featured { --card-accent: linear-gradient(90deg, #d97706, #facc15); }

    .trust-stat-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .trust-stat-label {
        color: #6a7685;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .trust-stat-value {
        margin-top: 0.55rem;
        color: #0f172a;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }

    .trust-stat-meta {
        margin-top: 0.75rem;
        color: #6a7685;
        font-size: 0.92rem;
    }

    .trust-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: var(--card-accent, linear-gradient(90deg, #ea580c, #f59e0b));
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.15);
    }

    .trust-panel {
        padding: 1.25rem;
        border-radius: 24px;
        border: 1px solid rgba(221, 227, 235, 0.9);
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 20px 44px rgba(15, 23, 42, 0.05);
    }

    .trust-tabs {
        gap: 0.75rem;
        border-bottom: 0;
    }

    .trust-tabs .nav-link {
        border: 1px solid rgba(219, 226, 234, 0.9);
        border-radius: 16px;
        color: #415163;
        font-weight: 700;
        padding: 0.85rem 1rem;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }

    .trust-tabs .nav-link.active {
        color: #fff;
        border-color: transparent;
        background: linear-gradient(135deg, #1d4ed8 0%, #7c3aed 100%);
        box-shadow: 0 14px 30px rgba(76, 29, 149, 0.24);
    }

    .trust-section {
        margin-top: 1.5rem;
        padding: 1.25rem;
        border-radius: 24px;
        border: 1px solid rgba(221, 227, 235, 0.9);
        background: #ffffff;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
    }

    .trust-section-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .trust-section-header h3 {
        margin-bottom: 0.35rem;
        font-size: 1.15rem;
        font-weight: 800;
    }

    .trust-section-header p {
        margin: 0;
        color: #6a7685;
    }

    .trust-count-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
        font-weight: 800;
    }

    .review-card,
    .testimonial-admin-card {
        border: 1px solid rgba(221, 227, 235, 0.9);
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
    }

    .review-card {
        border-left: 4px solid #f59e0b;
    }

    .review-card.verified {
        border-left-color: #10b981;
    }

    .review-meta,
    .testimonial-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        background: #f7f9fc;
        color: #415163;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .star-rating {
        display: inline-flex;
        gap: 0.12rem;
    }

    .star-rating .filled {
        color: #f4b400;
    }

    .star-rating .empty {
        color: #cbd5e1;
    }

    .actions-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1rem;
    }

    .actions-row form {
        margin: 0;
    }

    .response-box {
        border-radius: 16px;
        border: 1px solid rgba(191, 219, 254, 0.9);
        background: #eff6ff;
        color: #1e3a8a;
    }

    .empty-state {
        padding: 2rem;
        border: 1px dashed rgba(203, 213, 225, 0.95);
        border-radius: 20px;
        background: linear-gradient(180deg, #fbfcfe 0%, #ffffff 100%);
        color: #5f6b7a;
        text-align: center;
    }

    .trust-table-wrap {
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid rgba(221, 227, 235, 0.9);
    }

    .trust-table-wrap .table {
        margin-bottom: 0;
    }

    .trust-table-wrap thead th {
        background: #f8fafc;
        border-bottom-width: 1px;
        color: #526173;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .trust-table-wrap tbody tr:hover {
        background: rgba(59, 130, 246, 0.04);
    }

    .table-snippet {
        color: #5f6b7a;
        max-width: 320px;
    }

    .proof-thumb-wrap {
        margin-top: 0.9rem;
        display: inline-block;
    }

    .proof-thumb {
        display: block;
        width: 150px;
        max-width: 100%;
        border-radius: 12px;
        border: 1px solid rgba(221, 227, 235, 0.9);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .proof-thumb:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 24px rgba(15, 23, 42, 0.12);
    }

    .trust-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .trust-toolbar .input-group {
        max-width: 360px;
    }

    @media (max-width: 767.98px) {
        .trust-page-header,
        .trust-panel,
        .trust-section {
            padding: 1rem;
            border-radius: 20px;
        }

        .trust-section-header,
        .trust-stat-top,
        .trust-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .trust-tabs .nav-link {
            width: 100%;
        }
    }
</style>

<div class="container-fluid manage-trust-page py-2">
    <section class="trust-page-header">
        <span class="trust-eyebrow"><i class="fas fa-shield-heart"></i> Trust Operations</span>
        <div class="trust-header-copy">
            <h2>Manage reviews, testimonials, and approval flow</h2>
            <p>Use this workspace to approve customer feedback quickly, spot pending items, and keep featured testimonials aligned with what appears on the storefront.</p>
        </div>
        <div class="trust-header-highlights">
            <div class="trust-highlight"><i class="fas fa-hourglass-half text-warning"></i> <span><strong><?= number_format($stats['attention_items']) ?></strong> items need action</span></div>
            <div class="trust-highlight"><i class="fas fa-circle-check text-success"></i> <span><strong><?= number_format($stats['approved_reviews']) ?></strong> approved reviews</span></div>
            <div class="trust-highlight"><i class="fab fa-whatsapp text-success"></i> <span><strong><?= number_format($stats['whatsapp_testimonials']) ?></strong> WhatsApp testimonials</span></div>
        </div>
    </section>

    <section class="trust-stats-grid">
        <article class="trust-stat-card reviews">
            <div class="trust-stat-top">
                <div>
                    <div class="trust-stat-label">Product Reviews</div>
                    <div class="trust-stat-value"><?= number_format($stats['total_reviews']) ?></div>
                </div>
                <span class="trust-stat-icon"><i class="fas fa-star"></i></span>
            </div>
            <div class="trust-stat-meta"><?= number_format($stats['verified_reviews']) ?> linked to orders</div>
        </article>

        <article class="trust-stat-card pending">
            <div class="trust-stat-top">
                <div>
                    <div class="trust-stat-label">Pending Reviews</div>
                    <div class="trust-stat-value"><?= number_format($stats['pending_reviews']) ?></div>
                </div>
                <span class="trust-stat-icon"><i class="fas fa-clock"></i></span>
            </div>
            <div class="trust-stat-meta">Awaiting moderation before display</div>
        </article>

        <article class="trust-stat-card approved">
            <div class="trust-stat-top">
                <div>
                    <div class="trust-stat-label">Approved Reviews</div>
                    <div class="trust-stat-value"><?= number_format($stats['approved_reviews']) ?></div>
                </div>
                <span class="trust-stat-icon"><i class="fas fa-circle-check"></i></span>
            </div>
            <div class="trust-stat-meta">Visible on product pages and aggregates</div>
        </article>

        <article class="trust-stat-card testimonials">
            <div class="trust-stat-top">
                <div>
                    <div class="trust-stat-label">Testimonials</div>
                    <div class="trust-stat-value"><?= number_format($stats['total_testimonials']) ?></div>
                </div>
                <span class="trust-stat-icon"><i class="fas fa-comments"></i></span>
            </div>
            <div class="trust-stat-meta"><?= number_format($stats['approved_testimonials']) ?> approved for display</div>
        </article>

        <article class="trust-stat-card needs-action">
            <div class="trust-stat-top">
                <div>
                    <div class="trust-stat-label">Pending Testimonials</div>
                    <div class="trust-stat-value"><?= number_format($stats['pending_testimonials']) ?></div>
                </div>
                <span class="trust-stat-icon"><i class="fas fa-triangle-exclamation"></i></span>
            </div>
            <div class="trust-stat-meta">Customer stories still awaiting approval</div>
        </article>

        <article class="trust-stat-card featured">
            <div class="trust-stat-top">
                <div>
                    <div class="trust-stat-label">Featured Testimonials</div>
                    <div class="trust-stat-value"><?= number_format($stats['featured_testimonials']) ?></div>
                </div>
                <span class="trust-stat-icon"><i class="fas fa-crown"></i></span>
            </div>
            <div class="trust-stat-meta">Pinned to the public testimonials page</div>
        </article>
    </section>

    <section class="trust-panel">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$hasWhatsappProofFields): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-triangle-exclamation"></i> WhatsApp screenshot fields are not available yet. Run public_html/database/add_whatsapp_proof_fields.sql first.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <ul class="nav trust-tabs mb-0" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#pending-reviews">
                    <i class="fas fa-clock"></i> Pending Reviews (<?= $stats['pending_reviews'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#approved-reviews">
                    <i class="fas fa-check"></i> Approved Reviews (<?= $stats['approved_reviews'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#testimonials">
                    <i class="fas fa-comments"></i> Testimonials (<?= $stats['total_testimonials'] ?>)
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div id="pending-reviews" class="tab-pane fade show active">
                <section class="trust-section">
                    <div class="trust-section-header">
                        <div>
                            <h3>Pending product reviews</h3>
                            <p>Review submissions waiting for moderation before they contribute to storefront trust signals.</p>
                        </div>
                        <span class="trust-count-pill"><?= number_format($stats['pending_reviews']) ?></span>
                    </div>
                <?php if (empty($pending_reviews)): ?>
                    <div class="empty-state">
                        <i class="fas fa-circle-check mb-2"></i>
                        <div>No pending reviews at the moment.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_reviews as $review): ?>
                        <div class="card review-card <?= $review['has_order'] ? 'verified' : '' ?> mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-3 flex-wrap">
                                    <div>
                                        <h5 class="mb-1">
                                            <?= htmlspecialchars($review['customer_name']) ?>
                                        </h5>
                                        <div class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?= $i <= $review['rating'] ? 'fa-solid filled' : 'fa-regular empty' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-meta">
                                            <?php if ($review['has_order']): ?>
                                                <span class="meta-chip"><i class="fas fa-circle-check text-success"></i> Verified purchase</span>
                                            <?php endif; ?>
                                            <span class="meta-chip"><i class="fas fa-box-open text-primary"></i> <?= htmlspecialchars($review['product_name']) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                                    </div>
                                </div>

                                <?php if ($review['review_title']): ?>
                                    <h6 class="mt-3 mb-2"><?= htmlspecialchars($review['review_title']) ?></h6>
                                <?php endif; ?>

                                <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>

                                <div class="actions-row">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve_review">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this review?')">
                                        <input type="hidden" name="action" value="reject_review">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                    
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#respond-<?= $review['id'] ?>">
                                        <i class="fas fa-reply"></i> Respond
                                    </button>
                                </div>

                                <div class="collapse mt-3" id="respond-<?= $review['id'] ?>">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="respond_review">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <textarea name="admin_response" class="form-control" rows="3" placeholder="Your response..." required></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm mt-2">Submit Response</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </section>
            </div>

            <div id="approved-reviews" class="tab-pane fade">
                <section class="trust-section">
                    <div class="trust-section-header">
                        <div>
                            <h3>Approved product reviews</h3>
                            <p>The latest approved reviews, including any admin response already shown to customers.</p>
                        </div>
                        <span class="trust-count-pill"><?= number_format($stats['approved_reviews']) ?></span>
                    </div>
                <?php if (empty($approved_reviews)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments mb-2"></i>
                        <div>No approved reviews found yet.</div>
                    </div>
                <?php else: ?>
                <?php foreach ($approved_reviews as $review): ?>
                    <div class="card review-card verified mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($review['customer_name']) ?> <span class="badge bg-success">Approved</span></h5>
                                    <div class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?= $i <= $review['rating'] ? 'fa-solid filled' : 'fa-regular empty' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="review-meta">
                                        <span class="meta-chip"><i class="fas fa-box-open text-primary"></i> <?= htmlspecialchars($review['product_name']) ?></span>
                                    </div>
                                </div>
                                <small class="text-muted"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                            </div>
                            <p class="mt-2"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                            <?php if ($review['admin_response']): ?>
                                <div class="alert response-box mt-2 mb-0">
                                    <strong>Your Response:</strong><br>
                                    <?= nl2br(htmlspecialchars($review['admin_response'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
                </section>
            </div>

            <div id="testimonials" class="tab-pane fade">
                <section class="trust-section">
                    <div class="trust-section-header">
                        <div>
                            <h3>Testimonials library</h3>
                            <p>Manage public-facing stories, feature priority, approval state, and WhatsApp-origin feedback.</p>
                        </div>
                        <span class="trust-count-pill"><?= number_format($stats['total_testimonials']) ?></span>
                    </div>

                    <div class="trust-toolbar">
                        <div class="testimonial-meta">
                            <span class="meta-chip"><i class="fas fa-hourglass-half text-warning"></i> Pending: <?= number_format($stats['pending_testimonials']) ?></span>
                            <span class="meta-chip"><i class="fas fa-circle-check text-success"></i> Approved: <?= number_format($stats['approved_testimonials']) ?></span>
                            <span class="meta-chip"><i class="fas fa-crown text-warning"></i> Featured: <?= number_format($stats['featured_testimonials']) ?></span>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <form method="POST" onsubmit="return confirm('Approve all pending reviews and testimonials and publish now?')">
                                <input type="hidden" name="action" value="approve_all_publish">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-bolt"></i> Approve All & Publish
                                </button>
                            </form>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTestimonialModal">
                                <i class="fas fa-plus"></i> Add New Testimonial
                            </button>
                        </div>
                    </div>

                    <?php if (empty($testimonials)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments mb-2"></i>
                            <div>No testimonials available yet.</div>
                        </div>
                    <?php else: ?>
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="card testimonial-admin-card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <h5 class="mb-1">
                                        <?= htmlspecialchars($testimonial['customer_name']) ?>
                                        <?php if ($testimonial['is_featured']): ?>
                                            <span class="badge bg-warning">Featured</span>
                                        <?php endif; ?>
                                        <?php if ($testimonial['is_approved']): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="mb-0 text-muted"><?= htmlspecialchars($testimonial['customer_location']) ?></p>
                                    <div class="testimonial-meta">
                                        <span class="meta-chip"><i class="fas fa-arrow-down-1-9 text-primary"></i> Display order: <?= (int)$testimonial['display_order'] ?></span>
                                        <?php if (!empty($testimonial['product_purchased'])): ?>
                                            <span class="meta-chip"><i class="fas fa-bag-shopping text-primary"></i> <?= htmlspecialchars($testimonial['product_purchased']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($testimonial['whatsapp_conversation']): ?>
                                            <span class="meta-chip"><i class="fab fa-whatsapp text-success"></i> WhatsApp source</span>
                                        <?php endif; ?>
                                        <?php if ($hasWhatsappProofFields && !empty($testimonial['whatsapp_screenshot_path'])): ?>
                                            <span class="meta-chip"><i class="fas fa-image text-success"></i> Proof attached</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="star-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?= $i <= $testimonial['rating'] ? 'fa-solid filled' : 'fa-regular empty' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <p class="mt-3"><?= nl2br(htmlspecialchars($testimonial['testimonial_text'])) ?></p>

                            <?php if ($hasWhatsappProofFields && !empty($testimonial['whatsapp_screenshot_path'])): ?>
                                <div class="proof-thumb-wrap">
                                    <a href="<?= '../' . htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>" target="_blank" rel="noopener noreferrer" title="Open proof screenshot">
                                        <img src="<?= '../' . htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>" alt="WhatsApp proof screenshot" class="proof-thumb">
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="actions-row">
                                <?php if (!$testimonial['is_approved']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve_testimonial">
                                        <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editTestimonialModal<?= (int)$testimonial['id'] ?>">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                                
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="feature_testimonial">
                                    <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                    <input type="hidden" name="is_featured" value="<?= $testimonial['is_featured'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-star"></i> <?= $testimonial['is_featured'] ? 'Unfeature' : 'Feature' ?>
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this testimonial?')">
                                    <input type="hidden" name="action" value="delete_testimonial">
                                    <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="editTestimonialModal<?= (int)$testimonial['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Testimonial</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="edit_testimonial">
                                    <input type="hidden" name="testimonial_id" value="<?= (int)$testimonial['id'] ?>">
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Customer Name *</label>
                                                <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($testimonial['customer_name']) ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Location</label>
                                                <input type="text" name="customer_location" class="form-control" value="<?= htmlspecialchars($testimonial['customer_location']) ?>" placeholder="Dubai, UAE">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Testimonial Text *</label>
                                            <textarea name="testimonial_text" class="form-control" rows="4" required><?= htmlspecialchars($testimonial['testimonial_text']) ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Rating *</label>
                                                <select name="rating" class="form-select" required>
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                        <option value="<?= $i ?>" <?= (int)$testimonial['rating'] === $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Display Order</label>
                                                <input type="number" name="display_order" class="form-control" value="<?= (int)$testimonial['display_order'] ?>" min="1">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Product Purchased</label>
                                                <input type="text" name="product_purchased" class="form-control" value="<?= htmlspecialchars($testimonial['product_purchased'] ?? '') ?>" placeholder="Serving tray, coffee cups, vase...">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" name="whatsapp_conversation" class="form-check-input" id="editWhatsappCheck<?= (int)$testimonial['id'] ?>" value="1" <?= !empty($testimonial['whatsapp_conversation']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="editWhatsappCheck<?= (int)$testimonial['id'] ?>">From WhatsApp</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" name="is_featured" class="form-check-input" id="editFeaturedCheck<?= (int)$testimonial['id'] ?>" value="1" <?= !empty($testimonial['is_featured']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="editFeaturedCheck<?= (int)$testimonial['id'] ?>">Featured</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" name="is_approved" class="form-check-input" id="editApprovedCheck<?= (int)$testimonial['id'] ?>" value="1" <?= !empty($testimonial['is_approved']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="editApprovedCheck<?= (int)$testimonial['id'] ?>">Approved</label>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($hasWhatsappProofFields): ?>
                                            <div class="mb-3">
                                                <label class="form-label">Replace WhatsApp Screenshot</label>
                                                <?php if (!empty($testimonial['whatsapp_screenshot_path'])): ?>
                                                    <div class="mb-2">
                                                        <img src="<?= '../' . htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>" alt="Current WhatsApp screenshot" class="img-fluid rounded border" style="max-height: 180px;">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" name="whatsapp_screenshot" class="form-control" accept="image/*" capture="environment">
                                                <div class="form-text">Leave empty to keep current screenshot.</div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Consent Status</label>
                                                    <select name="consent_status" class="form-select">
                                                        <?php $currentConsent = $testimonial['consent_status'] ?? 'granted'; ?>
                                                        <option value="granted" <?= $currentConsent === 'granted' ? 'selected' : '' ?>>Granted</option>
                                                        <option value="pending" <?= $currentConsent === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="internal_only" <?= $currentConsent === 'internal_only' ? 'selected' : '' ?>>Internal Only</option>
                                                        <option value="declined" <?= $currentConsent === 'declined' ? 'selected' : '' ?>>Declined</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3 d-flex align-items-end">
                                                    <div class="form-check mb-2">
                                                        <input type="checkbox" name="privacy_blurred" class="form-check-input" id="editPrivacyBlurredCheck<?= (int)$testimonial['id'] ?>" value="1" <?= !empty($testimonial['privacy_blurred']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="editPrivacyBlurredCheck<?= (int)$testimonial['id'] ?>">Sensitive details blurred</label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </section>
    
    <!-- Add Testimonial Modal -->
    <div class="modal fade" id="addTestimonialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Testimonial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_testimonial">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="customer_location" class="form-control" placeholder="Dubai, UAE">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating *</label>
                            <select name="rating" class="form-select" required>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Testimonial Text *</label>
                            <textarea name="testimonial_text" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Purchased</label>
                            <input type="text" name="product_purchased" class="form-control" placeholder="Serving tray, coffee cups, vase...">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="999" min="1">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="is_featured" class="form-check-input" id="featuredCheck" value="1">
                                    <label class="form-check-label" for="featuredCheck">Feature on testimonials page</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="whatsapp_conversation" class="form-check-input" id="whatsappCheck" value="1">
                            <label class="form-check-label" for="whatsappCheck">From WhatsApp conversation</label>
                        </div>

                        <?php if ($hasWhatsappProofFields): ?>
                            <div class="mb-3">
                                <label class="form-label">WhatsApp Screenshot</label>
                                <input type="file" name="whatsapp_screenshot" class="form-control" accept="image/*" capture="environment">
                                <div class="form-text">Upload from desktop or phone. If uploaded from mobile, camera/gallery picker opens automatically on supported browsers.</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Consent Status</label>
                                    <select name="consent_status" class="form-select">
                                        <option value="granted" selected>Granted</option>
                                        <option value="pending">Pending</option>
                                        <option value="internal_only">Internal Only</option>
                                        <option value="declined">Declined</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="privacy_blurred" class="form-check-input" id="privacyBlurredCheck" value="1" checked>
                                        <label class="form-check-label" for="privacyBlurredCheck">Sensitive details blurred</label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Testimonial</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
