<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$db = new Database();
$productId = (int)($_POST['product_id'] ?? 0);
$saved = false;
if ($productId > 0) {
    $saved = wishlist_toggle($db, $productId);
}
$wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
if ($wantsJson) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'saved' => $saved, 'product_id' => $productId]);
    exit;
}
$redirect = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? store_url());
header('Location: ' . $redirect);
exit;