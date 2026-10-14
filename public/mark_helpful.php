<?php
/**
 * Mark Review as Helpful Handler
 */

session_start();
require_once(__DIR__ . '/../includes/bootstrap.php');
require_once(__DIR__ . '/../includes/ProductReviews.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => t('product.invalid_request')]);
    exit;
}

$review_id = (int)$_POST['review_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$_SESSION['helpful_votes'] = $_SESSION['helpful_votes'] ?? [];
if (isset($_SESSION['helpful_votes'][$review_id])) {
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Location: product.php?id=' . (int)($_POST['product_id'] ?? 0) . '#reviews');
        exit;
    }
    echo json_encode(['success' => false, 'message' => t('product.already_voted')]);
    exit;
}

$reviews = new ProductReviews();
$result = $reviews->markHelpful($review_id, $ip_address);
if ($result) {
    $_SESSION['helpful_votes'][$review_id] = 1;
}
if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Location: product.php?id=' . (int)($_POST['product_id'] ?? 0) . '#reviews');
    exit;
}

echo json_encode([
    'success' => $result,
    'message' => $result ? t('product.marked_helpful') : t('product.already_voted')
]);
?>
