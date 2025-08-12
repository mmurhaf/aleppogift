<?php
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once(__DIR__ . '/../../includes/bootstrap.php');


$db = new Database();
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    echo "<p class='text-muted'>Your cart is empty.</p>";
    return;
}



$code = trim($_POST['code'] ?? '');

if (!$code) {
    echo json_encode(['success' => false, 'message' => 'No coupon provided.']);
    exit;
}

// Fetch coupon
$sql = "SELECT * FROM coupons WHERE code = :code AND status = 'active' LIMIT 1";
$stmt = $db->query($sql, ['code' => $code]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive coupon.']);
    exit;
}

// Validate dates
$today = date('Y-m-d');
if ($coupon['start_date'] > $today || $coupon['end_date'] < $today) {
    echo json_encode(['success' => false, 'message' => 'Coupon expired or not yet valid.']);
    exit;
}

// Validate usage
if ($coupon['used_count'] >= $coupon['max_usage']) {
    echo json_encode(['success' => false, 'message' => 'Coupon usage limit reached.']);
    exit;
}

// Calculate item total (without shipping)
$total = 0;
$itemTotal = 0;
foreach ($cart as $item):
    $product = $db->query("SELECT name_en, price FROM products WHERE id = :id", ['id' => $item['product_id']])->fetch(PDO::FETCH_ASSOC);
    if (!$product) continue;

    $lineTotal = $item['quantity'] * $product['price'];
    $itemTotal += $lineTotal;
    
endforeach;


// if (!empty($_SESSION['cart_item'])) {
//     foreach ($_SESSION['cart_item'] as $item) {
//         $itemTotal += $item['price'] * $item['quantity'];
//     }
// }

// Calculate discount
$discountAmount = 0;
if ($coupon['discount_type'] === 'fixed') {
    $discountAmount = min($coupon['discount_value'], $itemTotal);
} elseif ($coupon['discount_type'] === 'percent') {
    $discountAmount = ($coupon['discount_value'] / 100) * $itemTotal;
}

$discount_type = $coupon['discount_type'];
$discount_value = $coupon['discount_value'];

// Save coupon in session
$_SESSION['applied_coupon'] = [
    'code' => $coupon['code'],
    'discount' => $discountAmount,
    'itemTotal' => $itemTotal,
    'discount_type' => $coupon['discount_type'],
    'discount_value' => $coupon['discount_value']
];

$_SESSION['discount_amount'] = $discountAmount;
$_SESSION['coupon_code'] = $coupon['code'];

// Update usage count
$db->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = :id", ['id' => $coupon['id']]);

echo json_encode([
    'success' => true, 
    'discountAmount' => $discountAmount, 
    'itemTotal' => $itemTotal,
    'discount_type' => $discount_type,
    'discount_value' => $discount_value
        ]);
exit;
