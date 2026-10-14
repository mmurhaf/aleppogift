<?php
/**
 * Submit Product Review Handler
 */

session_start();
require_once(__DIR__ . '/../includes/bootstrap.php');
require_once(__DIR__ . '/../includes/ProductReviews.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => t('product.invalid_request_method')]);
    exit;
}

$reviews = new ProductReviews();

// Sanitize input
$product_id = (int)$_POST['product_id'];
$customer_name = sanitize($_POST['customer_name'], 'string');
$customer_email = sanitize($_POST['customer_email'] ?? '', 'email');
$customer_phone = sanitize($_POST['customer_phone'] ?? '', 'string');
$rating = (int)$_POST['rating'];
$review_title = sanitize($_POST['review_title'] ?? '', 'string');
$review_text = sanitize($_POST['review_text'], 'string');

// Validation
$errors = [];

if (empty($customer_name)) {
    $errors[] = t('product.name_required');
}

if (empty($customer_email) && empty($customer_phone)) {
    $errors[] = t('product.contact_required');
}

if ($rating < 1 || $rating > 5) {
    $errors[] = t('product.rating_range');
}

if (empty($review_text)) {
    $errors[] = t('product.review_text_required');
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Check if customer purchased the product (verified purchase)
$order_id = null;
$check_email_phone = $customer_email ?: $customer_phone;

if (!empty($check_email_phone)) {
    $order_id = $reviews->canCustomerReview($product_id, $check_email_phone);
}

// Prepare review data
$review_data = [
    'product_id' => $product_id,
    'customer_name' => $customer_name,
    'customer_email' => $customer_email,
    'customer_phone' => $customer_phone,
    'rating' => $rating,
    'review_title' => $review_title,
    'review_text' => $review_text,
    'order_id' => $order_id
];

// Submit review
$result = $reviews->submitReview($review_data);

if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (!$result) {
        $_SESSION['review_error'] = t('product.submit_failed');
    }
    header('Location: product.php?id=' . $product_id . '#reviews');
    exit;
}

if ($result) {
    $message = $order_id 
        ? t('product.review_submitted_verified') 
        : t('product.review_submitted');
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'verified' => !empty($order_id)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => t('product.submit_failed')
    ]);
}
?>
