<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once(__DIR__ . '/../../includes/bootstrap.php');

try {
    $bundleId = filter_var($_POST['bundle_id'] ?? null, FILTER_VALIDATE_INT);
    if ($bundleId === false || $bundleId < 1) {
        echo json_encode(['success' => false, 'message' => t('cart.bundle_id_required')]);
        exit;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $db = new Database();
    $result = addBundleToSessionCart($db, $_SESSION['cart'], (int)$bundleId);
    if (empty($result['success'])) {
        echo json_encode($result);
        exit;
    }

    $count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'count' => $count,
        'cart_count' => $count,
        'bundle_name' => $result['bundle_name'],
    ]);
} catch (Exception $e) {
    error_log('Add bundle to cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => t('cart.bundle_add_failed')]);
}
