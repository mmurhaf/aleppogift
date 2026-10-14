<?php
/**
 * Product Reviews Display Component
 * Include this file in product pages to show reviews
 * Required variable: $product_id
 * Optional variable: $db (Database instance) - will reuse if available
 */

require_once(__DIR__ . '/../includes/ProductReviews.php');

// Ensure database connection exists - create only if not already available
if (!isset($db) || !($db instanceof Database)) {
    $db = new Database();
}

$reviewsObj = new ProductReviews($db);
$rating_summary = $reviewsObj->getProductRatingSummary($product_id);
$reviews = $reviewsObj->getProductReviews($product_id, 10);

$avg_rating = round($rating_summary['average_rating'], 1);
$total_reviews = $rating_summary['total_reviews'];
$verified_count = $rating_summary['verified_count'];
?>

<!-- Reviews Section -->
<section class="reviews-section mt-5" id="reviews">
    <div class="container">
        <h2 class="section-title mb-4">
            <i class="fas fa-star text-warning"></i> <?= te('product.reviews_title') ?>
        </h2>
        
        <?php if ($total_reviews > 0): ?>
            <!-- Rating Summary -->
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="rating-summary text-center">
                        <div class="rating-number"><?= number_format($avg_rating, 1) ?></div>
                        <div class="star-rating star-rating-large mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i > $avg_rating ? '-half-alt' : '' ?><?= $i > ceil($avg_rating) ? ' text-muted' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mb-2"><?= $total_reviews == 1 ? te('product.review_count_one') : te('product.review_count_many', ['count' => $total_reviews]) ?></p>
                        <?php if ($verified_count > 0): ?>
                            <p class="text-success mb-0">
                                <i class="fas fa-check-circle"></i> <?= $verified_count == 1 ? te('product.verified_one') : te('product.verified_many', ['count' => $verified_count]) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <h5 class="mb-4"><?= te('product.rating_distribution') ?></h5>
                    <div class="rating-bars">
                        <?php 
                        $stars = [5, 4, 3, 2, 1];
                        foreach ($stars as $star):
                            $count = $rating_summary[$star == 5 ? 'five_star' : ($star == 4 ? 'four_star' : ($star == 3 ? 'three_star' : ($star == 2 ? 'two_star' : 'one_star')))];
                            $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
                        ?>
                            <div class="rating-bar-item">
                                <span class="rating-bar-label"><?= te('product.stars.' . $star) ?></span>
                                <div class="rating-bar-container">
                                    <div class="rating-bar-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <span class="rating-bar-count"><?= $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= te('product.no_reviews_first') ?>
            </div>
        <?php endif; ?>
        
        <!-- Write a Review Form -->
        <div class="review-form-container mb-5">
            <h3 class="review-form-title"><?= te('product.write_review') ?></h3>
            <p class="review-form-subtitle"><?= te('product.share_experience') ?></p>
            
            <form id="reviewForm">
                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= te('product.your_name') ?> *</label>
                        <input type="text" name="customer_name" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= te('product.email') ?> *</label>
                        <input type="email" name="customer_email" class="form-control" required>
                        <small class="text-muted"><?= te('product.email_hint') ?></small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?= te('product.rating') ?> *</label>
                    <div class="star-rating-input" id="starRatingInput">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?= te('product.review_title_optional') ?></label>
                    <input type="text" name="review_title" class="form-control" placeholder="<?= te('product.review_title_ph') ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?= te('product.your_review') ?> *</label>
                    <textarea name="review_text" class="form-control" rows="5" required placeholder="<?= te('product.review_text_ph') ?>"></textarea>
                </div>
                
                <div class="review-form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> <?= te('product.submit_review') ?>
                    </button>
                </div>
            </form>
            
            <div id="reviewFormMessage" class="mt-3" style="display:none;"></div>
        </div>
        
        <!-- Display Reviews -->
        <?php if (!empty($reviews)): ?>
            <div class="review-controls">
                <h4 class="mb-0"><?= $total_reviews == 1 ? te('product.review_count_one') : te('product.review_count_many', ['count' => $total_reviews]) ?></h4>
                <div class="review-sort">
                    <button class="sort-btn active" data-sort="recent"><?= te('product.sort_recent') ?></button>
                    <button class="sort-btn" data-sort="helpful"><?= te('product.sort_helpful') ?></button>
                    <button class="sort-btn" data-sort="highest"><?= te('product.sort_highest') ?></button>
                    <button class="sort-btn" data-sort="lowest"><?= te('product.sort_lowest') ?></button>
                </div>
            </div>
            
            <div id="reviewsList">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card <?= $review['verified'] ? 'verified' : '' ?>">
                        <div class="review-header">
                            <div class="review-author">
                                <div class="review-author-avatar">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr($review['customer_name'], 0, 1))) ?>
                                </div>
                                <div class="review-author-info">
                                    <h6>
                                        <?= htmlspecialchars($review['customer_name']) ?>
                                        <?php if ($review['verified']): ?>
                                            <span class="verified-badge">
                                                <i class="fas fa-check-circle"></i> <?= te('product.verified_purchase') ?>
                                            </span>
                                        <?php endif; ?>
                                    </h6>
                                    <div class="star-rating star-rating-small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i > $review['rating'] ? ' text-muted' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <span class="review-date"><?= htmlspecialchars(store_date($review['created_at'], 'short')) ?></span>
                        </div>
                        
                        <?php if (!empty($review['review_title'])): ?>
                            <h5 class="review-title"><?= htmlspecialchars($review['review_title']) ?></h5>
                        <?php endif; ?>
                        
                        <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                        
                        <div class="review-footer">
                            <button class="review-helpful" data-review-id="<?= $review['id'] ?>">
                                <i class="fas fa-thumbs-up"></i>
                                <?= te('product.helpful_count', ['count' => (int)$review['helpful_count']]) ?>
                            </button>
                        </div>
                        
                        <?php if (!empty($review['admin_response'])): ?>
                            <div class="admin-response">
                                <div class="admin-response-header">
                                    <i class="fas fa-reply"></i> <?= te('product.response_from') ?>
                                </div>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($review['admin_response'])) ?></p>
                                <small class="text-muted"><?= htmlspecialchars(store_date($review['admin_response_date'], 'short')) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- JavaScript for Review Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewI18n = <?= json_encode([
        'selectRating' => t('product.select_rating'),
        'submitError' => t('product.submit_error'),
        'helpfulCount' => t('product.helpful_count'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Star rating input
    const stars = document.querySelectorAll('.star-rating-input i');
    const ratingValue = document.getElementById('ratingValue');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingValue.value = rating;
            
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('fas');
                    s.classList.remove('far');
                }
            });
        });
    });
    
    document.querySelector('.star-rating-input').addEventListener('mouseleave', function() {
        const currentRating = ratingValue.value;
        stars.forEach((s, index) => {
            if (index < currentRating) {
                s.classList.add('fas', 'active');
                s.classList.remove('far');
            } else {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            }
        });
    });
    
    // Submit review form
    const reviewForm = document.getElementById('reviewForm');
    reviewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!ratingValue.value) {
            alert(reviewI18n.selectRating);
            return;
        }
        
        const formData = new FormData(reviewForm);
        const messageDiv = document.getElementById('reviewFormMessage');
        
        fetch('submit_review.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            messageDiv.style.display = 'block';
            if (data.success) {
                messageDiv.className = 'alert alert-success';
                messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                reviewForm.reset();
                ratingValue.value = '';
                stars.forEach(s => {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                });
                
                // Reload page after 2 seconds
                setTimeout(() => window.location.reload(), 2000);
            } else {
                messageDiv.className = 'alert alert-danger';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
            }
        })
        .catch(error => {
            messageDiv.style.display = 'block';
            messageDiv.className = 'alert alert-danger';
            messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + reviewI18n.submitError;
        });
    });
    
    // Mark review as helpful
    document.querySelectorAll('.review-helpful').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            
            fetch('mark_helpful.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'review_id=' + reviewId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const count = this.querySelector('.fas.fa-thumbs-up').nextSibling.textContent.match(/\d+/)[0];
                    this.querySelector('.fas.fa-thumbs-up').nextSibling.textContent = ' ' + reviewI18n.helpfulCount.replace('{count}', parseInt(count) + 1);
                    this.classList.add('voted');
                    this.disabled = true;
                }
            });
        });
    });
});
</script>
