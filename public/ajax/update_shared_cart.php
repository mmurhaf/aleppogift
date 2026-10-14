<?php
/**
 * update_shared_cart.php
 * Public AJAX endpoint – forces a sync of the visitor's session cart back to the
 * shared cart they opened. Cart edits are normally synced automatically by
 * sharedCartWatch(); this endpoint is kept for explicit/manual syncs.
 */

require_once('../../includes/bootstrap.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$result = sharedCartSync();
if (!$result['success'] && $result['message'] === 'Database error. Please try again.') {
    http_response_code(500);
}
echo json_encode($result);
