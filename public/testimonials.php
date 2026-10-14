<?php
/**
 * Customer Testimonials & Success Stories Page
 */

require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();

$order_status_column = 'status';
if ($db->fetch("SHOW COLUMNS FROM orders LIKE 'shipment_status'")) {
    $order_status_column = 'shipment_status';
} elseif ($db->fetch("SHOW COLUMNS FROM orders LIKE 'order_status'")) {
    $order_status_column = 'order_status';
}

// Get featured testimonials
$featured = $db->fetchAll(" 
    SELECT * FROM testimonials 
    WHERE is_approved = 1 AND is_featured = 1
    ORDER BY display_order ASC, created_at DESC
");

// Get all approved testimonials
$all_testimonials = $db->fetchAll(" 
    SELECT * FROM testimonials 
    WHERE is_approved = 1
    ORDER BY created_at DESC
");

// Get statistics from the same source used by social proof widgets.
$social_proof_rows = $db->fetchAll(" 
    SELECT stat_type, stat_value
    FROM social_proof_stats
    WHERE is_active = 1
      AND stat_type IN ('total_orders', 'happy_customers')
");

$social_proof_stats = [];
foreach ($social_proof_rows as $row) {
    $social_proof_stats[$row['stat_type']] = (int)($row['stat_value'] ?? 0);
}

if (isset($social_proof_stats['total_orders']) || isset($social_proof_stats['happy_customers'])) {
    $order_stats = [
        'total_orders' => (int)($social_proof_stats['total_orders'] ?? 0),
        'happy_customers' => (int)($social_proof_stats['happy_customers'] ?? 0),
    ];
} else {
    // Fallback for environments where social_proof_stats is not seeded yet.
    $order_stats = $db->fetch(" 
        SELECT 
            COUNT(*) as total_orders,
            COUNT(DISTINCT customer_id) as happy_customers
        FROM orders
        WHERE {$order_status_column} IN ('completed', 'delivered')
          AND (customer_id IS NULL OR customer_id >= 0)
    ") ?: ['total_orders' => 0, 'happy_customers' => 0];
}

$testimonial_review_stats = $db->fetch(" 
    SELECT 
        COUNT(*) as total_reviews,
        COALESCE(AVG(rating), 0) as avg_rating
    FROM testimonials
    WHERE is_approved = 1
") ?: ['total_reviews' => 0, 'avg_rating' => 0];

$product_review_stats = $db->fetch(" 
    SELECT 
        COUNT(*) as total_reviews,
        COALESCE(AVG(rating), 0) as avg_rating
    FROM product_reviews
    WHERE is_approved = 1
") ?: ['total_reviews' => 0, 'avg_rating' => 0];

$testimonial_review_count = (int)($testimonial_review_stats['total_reviews'] ?? 0);
$product_review_count = (int)($product_review_stats['total_reviews'] ?? 0);
$combined_review_count = $testimonial_review_count + $product_review_count;

$combined_rating_total =
    ((float)($testimonial_review_stats['avg_rating'] ?? 0) * $testimonial_review_count) +
    ((float)($product_review_stats['avg_rating'] ?? 0) * $product_review_count);

$stats = [
    'total_orders' => (int)($order_stats['total_orders'] ?? 0),
    'happy_customers' => (int)($order_stats['happy_customers'] ?? 0),
    'total_reviews' => $combined_review_count,
    'avg_rating' => $combined_review_count > 0 ? $combined_rating_total / $combined_review_count : 0,
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('pages.testimonials.meta_title') ?></title>
    <meta name="description" content="<?= te('pages.testimonials.meta_description') ?>">
    
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/reviews.css')) ?>?v=20261006a">
    
    <style>
        :root {
            --sp-gold: #f5c842;
            --sp-gold-dim: rgba(245, 200, 66, 0.55);
            --sp-gold-faint: rgba(245, 200, 66, 0.12);
            --sp-ink: #fff8ee;
            --sp-muted: rgba(255, 228, 175, 0.72);
            --sp-card-bg: rgba(255, 210, 100, 0.07);
            --sp-card-border: rgba(245, 200, 66, 0.30);
            --sp-shadow-deep: rgba(12, 5, 0, 0.55);
            --sp-shadow-card: rgba(20, 8, 0, 0.45);
        }

        .testimonials-page {
            background: linear-gradient(180deg, #fdf8ee 0%, #fffefb 50%, #fff8ef 100%);
        }

        .stats-row {
            border-radius: 20px;
            padding: 2rem;
            margin-top: -2rem;
            border: 1px solid var(--sp-card-border);
            background:
                linear-gradient(145deg, rgba(35, 16, 8, 0.96), rgba(20, 10, 5, 0.94));
            box-shadow:
                0 2px 0 rgba(245, 200, 66, 0.22),
                0 18px 36px var(--sp-shadow-card),
                inset 0 1px 0 rgba(255, 230, 140, 0.15);
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: "Playfair Display", Georgia, serif;
            color: #ffffff;
            display: block;
            margin-bottom: 0.5rem;
            text-shadow:
                0 0 22px rgba(245, 195, 60, 0.40),
                0 3px 14px rgba(0, 0, 0, 0.30);
        }

        .stat-label {
            color: rgba(255, 228, 170, 0.88);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .testimonial-card-large {
            background: #fffdf8;
            border: 1px solid rgba(190, 120, 30, 0.20);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .testimonial-card-large:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 34px rgba(45, 20, 5, 0.16);
        }

        .testimonial-card-large.featured {
            border: 2px solid var(--sp-gold-dim);
            background: linear-gradient(145deg, #fff6df 0%, #fffdf6 100%);
        }

        .customer-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(245, 195, 60, 0.85), rgba(180, 90, 20, 0.88));
            border: 1px solid rgba(245, 200, 66, 0.48);
            color: #fff8e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            box-shadow:
                inset 0 1px 0 rgba(255, 240, 160, 0.45),
                0 8px 20px rgba(15, 6, 0, 0.28);
        }

        .testimonial-quote {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #342012;
            font-style: italic;
            position: relative;
            padding: 1.5rem;
            background: #fff8ee;
            border: 1px solid rgba(245, 200, 66, 0.22);
            border-radius: 12px;
            margin: 1.5rem 0;
        }

        .testimonial-quote::before {
            content: '"';
            font-size: 4rem;
            color: rgba(196, 122, 24, 0.38);
            position: absolute;
            top: -10px;
            left: 10px;
        }

        .whatsapp-badge {
            background: #25d366;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 8px 18px rgba(37, 211, 102, 0.24);
        }

        .video-testimonial {
            border-radius: 15px;
            overflow: hidden;
            margin-top: 1rem;
            border: 1px solid rgba(190, 120, 30, 0.18);
        }

        .proof-screenshot-wrap {
            margin-top: 1rem;
        }

        .proof-screenshot {
            width: 100%;
            border-radius: 16px;
            border: 1px solid rgba(245, 200, 66, 0.22);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
            display: block;
        }

        .proof-meta {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .proof-meta .badge {
            font-weight: 500;
        }

        .testimonials-page .bg-light {
            background: linear-gradient(180deg, #fff9ef 0%, #fffefb 100%) !important;
        }
    </style>
</head>
<body class="testimonials-page">
    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="container store-main my-4">
        <header class="page-hero">
            <p class="page-kicker"><?= te('pages.testimonials.kicker') ?></p>
            <h1><?= te('pages.testimonials.heading') ?></h1>
            <p class="page-lead"><?= te('pages.testimonials.lead') ?></p>
        </header>
        <div class="stats-row">
            <div class="row">
                <div class="col-md-3 stat-item">
                    <span class="stat-number"><?= number_format($stats['total_orders']) ?>+</span>
                    <span class="stat-label"><?= te('pages.testimonials.stat_orders') ?></span>
                </div>
                <div class="col-md-3 stat-item">
                    <span class="stat-number"><?= number_format($stats['happy_customers']) ?>+</span>
                    <span class="stat-label"><?= te('pages.testimonials.stat_customers') ?></span>
                </div>
                <div class="col-md-3 stat-item">
                    <span class="stat-number"><?= number_format($stats['total_reviews']) ?></span>
                    <span class="stat-label"><?= te('pages.testimonials.stat_reviews') ?></span>
                </div>
                <div class="col-md-3 stat-item">
                    <span class="stat-number"><?= number_format($stats['avg_rating'], 1) ?> <i class="fas fa-star text-warning"></i></span>
                    <span class="stat-label"><?= te('pages.testimonials.stat_rating') ?></span>
                </div>
            </div>
        </div>
    
    <!-- Featured Testimonials -->
    <?php if (!empty($featured)): ?>
    <section class="py-5 mt-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">
                    <i class="fas fa-star text-warning"></i> <?= te('pages.testimonials.featured_heading') ?>
                </h2>
                <p class="text-muted"><?= te('pages.testimonials.featured_lead') ?></p>
            </div>
            
            <div class="row">
                <?php foreach ($featured as $testimonial): ?>
                    <div class="col-lg-12">
                        <div class="testimonial-card-large featured">
                            <div class="d-flex align-items-start">
                                <div class="customer-avatar-large">
                                    <?= strtoupper(substr($testimonial['customer_name'], 0, 1)) ?>
                                </div>
                                <div class="flex-grow-1 ms-4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4 class="mb-1"><?= htmlspecialchars($testimonial['customer_name']) ?></h4>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($testimonial['customer_location']) ?>
                                            </p>
                                            <div class="star-rating mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i > $testimonial['rating'] ? ' text-muted' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <?php if ($testimonial['whatsapp_conversation']): ?>
                                                <span class="whatsapp-badge">
                                                    <i class="fab fa-whatsapp"></i> <?= te('pages.testimonials.whatsapp_customer') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-crown"></i> <?= te('pages.testimonials.featured_badge') ?>
                                        </span>
                                    </div>
                                    
                                    <div class="testimonial-quote">
                                        <?= nl2br(htmlspecialchars($testimonial['testimonial_text'])) ?>
                                    </div>

                                    <?php if (!empty($testimonial['whatsapp_screenshot_path']) && !empty($testimonial['privacy_blurred'])): ?>
                                        <div class="proof-screenshot-wrap">
                                            <a href="<?= htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>" target="_blank" rel="noopener noreferrer">
                                                <img src="<?= htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>"
                                                     alt="<?= te('pages.testimonials.proof_alt', ['name' => $testimonial['customer_name']]) ?>"
                                                     class="proof-screenshot">
                                            </a>
                                            <div class="proof-meta">
                                                <span class="badge text-bg-success"><i class="fab fa-whatsapp me-1"></i><?= te('pages.testimonials.screenshot_proof') ?></span>
                                                <span class="badge text-bg-light"><?= te('pages.testimonials.privacy_blurred') ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($testimonial['product_purchased'])): ?>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-shopping-bag"></i> <?= te('pages.testimonials.purchased', ['product' => $testimonial['product_purchased']]) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($testimonial['video_url'])): ?>
                                        <div class="video-testimonial">
                                            <iframe width="100%" height="315" src="<?= htmlspecialchars($testimonial['video_url']) ?>" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- All Testimonials -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold"><?= te('pages.testimonials.more_heading') ?></h2>
                <p class="text-muted"><?= te('pages.testimonials.more_lead') ?></p>
            </div>
            
            <div class="row">
                <?php foreach ($all_testimonials as $testimonial): ?>
                    <?php if ($testimonial['is_featured']) continue; // Skip featured ones ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="review-card h-100">
                            <div class="review-header">
                                <div class="review-author">
                                    <div class="review-author-avatar">
                                        <?= strtoupper(substr($testimonial['customer_name'], 0, 1)) ?>
                                    </div>
                                    <div class="review-author-info">
                                        <h6><?= htmlspecialchars($testimonial['customer_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($testimonial['customer_location']) ?></small>
                                        <div class="star-rating star-rating-small mt-1">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i > $testimonial['rating'] ? ' text-muted' : '' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <p class="review-text"><?= nl2br(htmlspecialchars($testimonial['testimonial_text'])) ?></p>

                            <?php if (!empty($testimonial['whatsapp_screenshot_path']) && !empty($testimonial['privacy_blurred'])): ?>
                                <a href="<?= htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>" target="_blank" rel="noopener noreferrer" class="d-block mb-3">
                                    <img src="<?= htmlspecialchars($testimonial['whatsapp_screenshot_path']) ?>"
                                         alt="<?= te('pages.testimonials.proof_alt', ['name' => $testimonial['customer_name']]) ?>"
                                         class="proof-screenshot">
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($testimonial['whatsapp_conversation']): ?>
                                <span class="whatsapp-badge">
                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Call to Action -->
    <section class="py-5">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-4"><?= te('pages.testimonials.cta_heading') ?></h2>
            <p class="lead mb-4"><?= te('pages.testimonials.cta_text') ?></p>
            <div class="d-flex justify-content-center gap-3">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> <?= te('pages.testimonials.start_shopping') ?>
                </a>
                <a href="https://wa.me/971561125320" class="btn btn-success btn-lg" target="_blank">
                    <i class="fab fa-whatsapp"></i> <?= te('pages.testimonials.chat_whatsapp') ?>
                </a>
            </div>
        </div>
    </section>
    </main>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=20261012i18n"></script>
    <script src="assets/js/enhanced-main.js?v=20261012i18n"></script>
</body>
</html>
