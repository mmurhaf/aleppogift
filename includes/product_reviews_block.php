<?php
$reviewRows = $db->fetchAll(
    'SELECT * FROM product_reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC',
    [$product_id]
);
$reviewCount = count($reviewRows);
$reviewAverage = 0;
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviewRows as $reviewRow) {
    $stars = (int)$reviewRow['rating'];
    $reviewAverage += $stars;
    if (isset($distribution[$stars])) {
        $distribution[$stars]++;
    }
}
if ($reviewCount > 0) {
    $reviewAverage = $reviewAverage / $reviewCount;
}
$reviewError = $_SESSION['review_error'] ?? '';
unset($_SESSION['review_error']);
?>
<section class="related-section" id="reviews">
    <h2><?= te('product.reviews_heading') ?></h2>
    <?php if ($reviewCount > 0): ?>
        <p><?= te('product.rating_summary', ['avg' => number_format($reviewAverage, 1), 'count' => $reviewCount]) ?></p>
        <?php foreach ($distribution as $stars => $starCount):
            $percent = $reviewCount ? round($starCount / $reviewCount * 100) : 0; ?>
            <div><?= te('product.stars.' . $stars) ?>
                <div style="background:#eee;height:8px;width:180px;display:inline-block;vertical-align:middle;">
                    <div style="background:#e67e22;height:8px;width:<?= (int)$percent ?>%;"></div>
                </div>
                <?= (int)$percent ?>%
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p><?= te('product.no_approved_reviews') ?></p>
    <?php endif; ?>
    <?php foreach ($reviewRows as $reviewRow): ?>
        <article class="card card-body mb-2">
            <strong><?= htmlspecialchars($reviewRow['customer_name']) ?></strong>
            <span><?= str_repeat('★', (int)$reviewRow['rating']) ?></span>
            <p><?= nl2br(htmlspecialchars($reviewRow['review_text'])) ?></p>
            <small><?= htmlspecialchars((string)$reviewRow['created_at']) ?></small>
            <?php if (!empty($reviewRow['admin_response'])): ?>
                <div class="alert alert-light mt-2"><strong><?= te('product.store_reply') ?></strong> <?= nl2br(htmlspecialchars($reviewRow['admin_response'])) ?></div>
            <?php endif; ?>
            <form method="post" action="mark_helpful.php">
                <input type="hidden" name="review_id" value="<?= (int)$reviewRow['id'] ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= te('product.helpful_count', ['count' => (int)$reviewRow['helpful_count']]) ?></button>
            </form>
        </article>
    <?php endforeach; ?>
    <h3><?= te('product.write_review') ?></h3>
    <?php if ($reviewError): ?><div class="alert alert-danger"><?= htmlspecialchars($reviewError) ?></div><?php endif; ?>
    <form method="post" action="submit_review.php" class="card card-body">
        <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
        <input class="form-control mb-2" name="customer_name" placeholder="<?= te('product.name') ?>" required>
        <input class="form-control mb-2" type="email" name="customer_email" placeholder="<?= te('product.email') ?>" required>
        <select class="form-select mb-2" name="rating" required>
            <option value="5"><?= te('product.stars.5') ?></option>
            <option value="4"><?= te('product.stars.4') ?></option>
            <option value="3"><?= te('product.stars.3') ?></option>
            <option value="2"><?= te('product.stars.2') ?></option>
            <option value="1"><?= te('product.stars.1') ?></option>
        </select>
        <textarea class="form-control mb-2" name="review_text" required placeholder="<?= te('product.your_review') ?>"></textarea>
        <button class="btn btn-primary" type="submit"><?= te('product.submit_review') ?></button>
    </form>
</section>
