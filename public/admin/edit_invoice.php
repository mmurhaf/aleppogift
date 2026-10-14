<?php
// File: admin/edit_invoice.php
// Invoice editor with design that mimics the actual PDF invoice

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/store_features.php');

require_admin_login();

function admin_product_image_src(?string $path): string {
    $path = trim(str_replace('\\', '/', (string)$path));
    if ($path === '') {
        return '';
    }
    $path = preg_replace('#^(\.\./)+#', '', $path);
    $path = ltrim($path, '/');
    if ($path === '') {
        return '';
    }
    $segments = array_map('rawurlencode', explode('/', $path));
    return '../' . implode('/', $segments);
}

function invoice_select_options(array $options, string $selected): array {
    if ($selected !== '' && !array_key_exists($selected, $options)) {
        $options = [$selected => $selected] + $options;
    }
    return $options;
}

function invoice_payment_method_choice(string $stored): string {
    $aliases = [
        'cod' => 'COD',
        'cash_on_delivery' => 'COD',
        'zina' => 'Ziina',
        'ziina' => 'Ziina',
        'online' => 'Ziina',
        'credit_card' => 'Ziina',
        'card' => 'Ziina',
        'stripe' => 'stripe',
        'bank_transfer' => 'bank_transfer',
    ];
    $key = strtolower(trim($stored));
    return $aliases[$key] ?? trim($stored);
}

function invoice_flash_redirect(int $orderId, string $type, string $text): void {
    $_SESSION['invoice_editor_flash'] = ['type' => $type, 'text' => $text];
    header('Location: edit_invoice.php?id=' . $orderId);
    exit;
}

function invoice_save_line_items(Database $db, int $orderId, array $items): void {
    foreach ($items as $itemId => $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = (int)$itemId;
        if ($itemId <= 0) {
            continue;
        }
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $price = max(0, round((float)($item['price'] ?? 0), 2));
        $name = invoice_plain_text((string)($item['product_name'] ?? ''));
        $lineTotal = round($quantity * $price, 2);
        if ($name !== '') {
            $db->query(
                "UPDATE order_items
                 SET quantity = ?, price = ?, product_name = ?, total = ?
                 WHERE id = ? AND order_id = ?",
                [$quantity, $price, $name, $lineTotal, $itemId, $orderId]
            );
        } else {
            $db->query(
                "UPDATE order_items
                 SET quantity = ?, price = ?, total = ?
                 WHERE id = ? AND order_id = ?",
                [$quantity, $price, $lineTotal, $itemId, $orderId]
            );
        }
    }
}

function invoice_order_adjustments(Database $db, int $orderId): array {
    $columns = ['shipping_aed', 'discount_amount'];
    if (ag_has_column($db, 'orders', 'gift_wrap_amount')) {
        $columns[] = 'gift_wrap_amount';
    }
    if (ag_has_column($db, 'orders', 'vat_amount')) {
        $columns[] = 'vat_amount';
    }
    $current = $db->fetch(
        'SELECT ' . implode(', ', $columns) . ' FROM orders WHERE id = ?',
        [$orderId]
    ) ?: [];

    return [
        'shipping' => (float)($current['shipping_aed'] ?? 0),
        'discount' => (float)($current['discount_amount'] ?? 0),
        'wrap' => (float)($current['gift_wrap_amount'] ?? 0),
        'vat' => (float)($current['vat_amount'] ?? 0),
    ];
}

function invoice_order_total(Database $db, int $orderId, float $shipping, float $discount, float $wrap = 0, float $vat = 0): float {
    $rows = $db->query(
        "SELECT quantity, price FROM order_items WHERE order_id = ?",
        [$orderId]
    )->fetchAll(PDO::FETCH_ASSOC);
    $subtotal = 0.0;
    foreach ($rows as $row) {
        $subtotal += (float)$row['quantity'] * (float)$row['price'];
    }
    return round(max(0, $subtotal + $shipping + $wrap + $vat - $discount), 2);
}

function invoice_sync_total(Database $db, int $orderId): void {
    $current = invoice_order_adjustments($db, $orderId);
    $total = invoice_order_total(
        $db,
        $orderId,
        $current['shipping'],
        $current['discount'],
        $current['wrap'],
        $current['vat']
    );
    $db->query("UPDATE orders SET total_amount = ? WHERE id = ?", [$total, $orderId]);
}

function invoice_plain_text(string $value): string {
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(strip_tags($value));
}

function invoice_line_name(array $item): string {
    $stored = invoice_plain_text((string)($item['product_name'] ?? ''));
    if ($stored !== '') {
        return $stored;
    }
    $catalog = invoice_plain_text((string)($item['name_en'] ?? ''));
    if ($catalog === '') {
        $catalog = invoice_plain_text((string)($item['name_ar'] ?? ''));
    }
    return $catalog !== '' ? $catalog : 'Product';
}

function invoice_apply_editor(Database $db, int $orderId, array $post): void {
    if (!empty($post['items']) && is_array($post['items'])) {
        invoice_save_line_items($db, $orderId, $post['items']);
    }

    $previousShipment = $db->fetch(
        'SELECT shipment_status, tracking_number FROM orders WHERE id = ?',
        [$orderId]
    ) ?: [];
    $shipping = max(0, round((float)($post['shipping_aed'] ?? 0), 2));
    $discount = max(0, round((float)($post['discount_amount'] ?? 0), 2));
    $adjustments = invoice_order_adjustments($db, $orderId);
    $total = invoice_order_total($db, $orderId, $shipping, $discount, $adjustments['wrap'], $adjustments['vat']);
    $shippedDate = trim((string)($post['shipped_date'] ?? ''));

    $db->query("UPDATE orders SET
        note = ?,
        remarks = ?,
        tracking_number = ?,
        carrier_name = ?,
        shipping_method = ?,
        shipment_status = ?,
        shipped_date = ?,
        shipping_aed = ?,
        total_amount = ?,
        payment_method = ?,
        payment_status = ?,
        discount_amount = ?,
        total_weight = ?
        WHERE id = ?", [
        $post['note'] ?? '',
        $post['remarks'] ?? '',
        $post['tracking_number'] ?? '',
        $post['carrier_name'] ?? '',
        $post['shipping_method'] ?? '',
        $post['shipment_status'] ?? 'pending',
        $shippedDate !== '' ? $shippedDate : null,
        $shipping,
        $total,
        $post['payment_method'] ?? '',
        $post['payment_status'] ?? 'pending',
        $discount,
        max(0, round((float)($post['total_weight'] ?? 0), 2)),
        $orderId
    ]);

    maybe_send_shipment_email(
        $db,
        $orderId,
        (string)($previousShipment['shipment_status'] ?? ''),
        (string)($previousShipment['tracking_number'] ?? ''),
        (string)($post['shipment_status'] ?? 'pending'),
        (string)($post['tracking_number'] ?? '')
    );

    $customerId = (int)($post['customer_id'] ?? 0);
    if ($customerId > 0) {
        $db->query("UPDATE customers SET
            fullname = ?,
            email = ?,
            phone = ?,
            address = ?,
            city = ?,
            country = ?
            WHERE id = ?", [
            $post['customer_name'] ?? '',
            $post['customer_email'] ?? '',
            $post['customer_phone'] ?? '',
            $post['customer_address'] ?? '',
            $post['customer_city'] ?? '',
            $post['customer_country'] ?? '',
            $customerId
        ]);
    }
}

$db = new Database();
$message = '';
$messageType = '';
if (!empty($_SESSION['invoice_editor_flash']) && is_array($_SESSION['invoice_editor_flash'])) {
    $message = (string)($_SESSION['invoice_editor_flash']['text'] ?? '');
    $messageType = (string)($_SESSION['invoice_editor_flash']['type'] ?? 'success');
    unset($_SESSION['invoice_editor_flash']);
}
$order = null;
$customer = null;
$order_items = [];
$bundle_names = [];
$bundle_discount = 0.0;
$subtotal = 0;
$order_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['order_id']) ? intval($_POST['order_id']) : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $order_id = intval($_POST['order_id']);

    try {
        $product_name = invoice_plain_text((string)($_POST['item_product_name'] ?? ''));
        $quantity = intval($_POST['item_quantity'] ?? 1);
        $price = floatval($_POST['item_price'] ?? 0);
        $product_id = intval($_POST['item_product_id'] ?? 0);

        if ($product_id > 0) {
            $product = $db->query(
                "SELECT id, name_en, name_ar, price FROM products WHERE id = ?",
                [$product_id]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new Exception("Product ID {$product_id} was not found");
            }
            if ($product_name === '') {
                $product_name = invoice_plain_text((string)($product['name_en'] ?: $product['name_ar']));
            }
            if (($_POST['item_price'] ?? '') === '') {
                $price = (float)$product['price'];
            }
        }

        if ($product_name === '' || $quantity <= 0 || $price < 0) {
            throw new Exception("Please provide valid product name, quantity, and price");
        }

        invoice_apply_editor($db, $order_id, $_POST);
        $db->query("INSERT INTO order_items
            (order_id, product_id, product_name, quantity, price, total)
            VALUES (?, ?, ?, ?, ?, ?)", [
            $order_id,
            $product_id > 0 ? $product_id : null,
            $product_name,
            $quantity,
            $price,
            round($quantity * $price, 2)
        ]);

        invoice_sync_total($db, $order_id);
        invoice_flash_redirect($order_id, 'success', 'Item added and the order total was updated.');
    } catch (Exception $e) {
        invoice_flash_redirect($order_id, 'error', 'Error adding item: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $order_id = intval($_POST['order_id']);
    $item_id = intval($_POST['delete_item']);

    try {
        invoice_apply_editor($db, $order_id, $_POST);
        $db->query("DELETE FROM order_items WHERE id = ? AND order_id = ?", [$item_id, $order_id]);
        invoice_sync_total($db, $order_id);
        invoice_flash_redirect($order_id, 'success', 'Item removed and the order total was updated.');
    } catch (Exception $e) {
        invoice_flash_redirect($order_id, 'error', 'Error deleting item: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    $order_id = intval($_POST['order_id']);

    try {
        invoice_apply_editor($db, $order_id, $_POST);
        invoice_flash_redirect($order_id, 'success', 'Invoice saved. The total now matches the printed invoice.');
    } catch (Exception $e) {
        invoice_flash_redirect($order_id, 'error', 'Error updating invoice: ' . $e->getMessage());
    }
}

// Load order data
if ($order_id > 0) {
    $order = $db->query("
        SELECT o.*, c.fullname AS customer_name, c.email AS customer_email,
               c.phone AS customer_phone, c.address AS customer_address,
               c.city AS customer_city, c.country AS customer_country
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ", [$order_id])->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $customer = $db->query("SELECT * FROM customers WHERE id = ?", [$order['customer_id']])->fetch(PDO::FETCH_ASSOC);
        $order_items = $db->query("
            SELECT oi.*, p.name_en, p.name_ar,
                   pv.size AS variation_size, pv.color AS variation_color,
                   COALESCE(
                       (SELECT pi.image_path
                        FROM product_images pi
                        WHERE pi.id = pv.image_id
                        LIMIT 1),
                       (SELECT pi.image_path
                        FROM product_images pi
                        WHERE pi.product_id = oi.product_id
                        ORDER BY pi.is_main DESC, pi.id ASC
                        LIMIT 1)
                   ) AS image_path
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_variations pv ON oi.variation_id = pv.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ", [$order_id])->fetchAll(PDO::FETCH_ASSOC);

        try {
            $orderBundles = $db->query(
                "SELECT bundle_id, bundle_name_en, discount_amount FROM order_bundles WHERE order_id = ?",
                [$order_id]
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($orderBundles as $orderBundle) {
                $bundle_discount += (float)$orderBundle['discount_amount'];
                if (!empty($orderBundle['bundle_id'])) {
                    $bundle_names[(int)$orderBundle['bundle_id']] = (string)$orderBundle['bundle_name_en'];
                }
            }
        } catch (Exception $e) {
            $bundle_names = [];
            $bundle_discount = 0.0;
        }
    }
}

$invoice_number = 'INV-' . str_pad((string)$order_id, 6, '0', STR_PAD_LEFT);
$invoiceStamp = $order ? strtotime((string)($order['order_date'] ?? '')) : false;
$invoice_date = $invoiceStamp ? date('F j, Y', $invoiceStamp) : date('F j, Y');
$aed_to_usd = defined('AED_TO_USD_RATE') ? (float)AED_TO_USD_RATE : 0.2723;
$shippingAmount = $order ? (float)($order['shipping_aed'] ?? 0) : 0.0;
$discountAmount = $order ? (float)($order['discount_amount'] ?? 0) : 0.0;
$wrapAmount = $order ? (float)($order['gift_wrap_amount'] ?? 0) : 0.0;
$vatAmount = $order ? (float)($order['vat_amount'] ?? 0) : 0.0;
$vatRate = $order ? (float)($order['vat_rate'] ?? 0) : 0.0;
$trn = $order ? trim((string)($order['trn'] ?? '')) : '';
$shippedValue = '';
$shippedRaw = $order ? trim((string)($order['shipped_date'] ?? '')) : '';
if ($shippedRaw !== '' && !str_starts_with($shippedRaw, '0000')) {
    $shippedStamp = strtotime($shippedRaw);
    if ($shippedStamp) {
        $shippedValue = date('Y-m-d', $shippedStamp);
    }
}
$shippingLabel = $shippingAmount > 0
    ? 'AED ' . number_format($shippingAmount, 2) . ' (USD ' . number_format($shippingAmount * $aed_to_usd, 2) . ')'
    : 'Free';
$paymentMethodChoice = $order ? invoice_payment_method_choice((string)($order['payment_method'] ?? '')) : '';
$paymentMethodOptions = invoice_select_options([
    'COD' => 'Cash on delivery',
    'Ziina' => 'Card',
    'bank_transfer' => 'Bank transfer',
    'stripe' => 'Stripe',
], $paymentMethodChoice);
$paymentStatusChoice = $order ? strtolower(trim((string)($order['payment_status'] ?? ''))) : '';
$paymentStatusOptions = invoice_select_options([
    'pending' => 'Pending',
    'processing' => 'Processing',
    'paid' => 'Paid',
    'failed' => 'Failed',
], $paymentStatusChoice);
$shipmentStatus = $order ? (string)($order['shipment_status'] ?? 'pending') : 'pending';
?>
<?php
$page_title = 'Edit Invoice';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/edit-invoice.css">
<div class="edit-invoice-page">
    <?php if ($message): ?>
        <div class="sheet-alert is-<?= $messageType === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!$order): ?>
        <div class="empty-order">
            <h3>Order not found</h3>
            <p>Choose an order from the orders list to edit its invoice.</p>
            <a href="orders.php" class="sheet-link">Back to orders</a>
        </div>
    <?php else: ?>
        <form method="POST" action="" autocomplete="off">
            <input type="hidden" name="order_id" value="<?= (int)$order_id ?>">
            <input type="hidden" name="customer_id" value="<?= (int)($order['customer_id'] ?? 0) ?>">
            <button type="submit" name="save_invoice" value="1" hidden formnovalidate></button>

            <div class="invoice-sheet">
                <div class="sheet-head">
                    <div class="sheet-brand">
                        <div class="brand">Aleppo <span>Gift</span></div>
                        <div class="brand-sub">
                            Premium Syrian Products<br>
                            www.aleppogift.com<br>
                            +971 56 112 5320
                        </div>
                    </div>
                    <div class="sheet-doc">
                        <div class="kicker">ALEPPO GIFT</div>
                        <div class="title">INVOICE</div>
                        <div class="meta">
                            Date: <?= htmlspecialchars($invoice_date) ?><br>
                            Invoice #: <?= htmlspecialchars($invoice_number) ?><br>
                            Order ID: #<?= (int)$order_id ?>
                        </div>
                    </div>
                </div>
                <div class="sheet-rule"></div>

                <div class="sheet-parties">
                    <div class="box">
                        <h3>FROM</h3>
                        <p>
                            <strong>Aleppo Gift Trading LLC</strong><br>
                            Dubai, United Arab Emirates<br>
                            Phone: +971 56 112 5320<br>
                            Email: info@aleppogift.com
                        </p>
                    </div>
                    <div class="box">
                        <h3>BILL TO</h3>
                        <input type="text" name="customer_name" class="sheet-input is-strong" value="<?= htmlspecialchars((string)($order['customer_name'] ?? '')) ?>" placeholder="Customer name">
                        <textarea name="customer_address" class="sheet-input sheet-area" rows="2" placeholder="Address"><?= htmlspecialchars((string)($order['customer_address'] ?? '')) ?></textarea>
                        <div class="sheet-place">
                            <input type="text" name="customer_city" class="sheet-input" value="<?= htmlspecialchars((string)($order['customer_city'] ?? '')) ?>" placeholder="City">
                            <input type="text" name="customer_country" class="sheet-input" value="<?= htmlspecialchars((string)($order['customer_country'] ?? '')) ?>" placeholder="Country">
                        </div>
                        <label class="sheet-line"><span>Phone:</span><input type="text" name="customer_phone" class="sheet-input" value="<?= htmlspecialchars((string)($order['customer_phone'] ?? '')) ?>"></label>
                        <label class="sheet-line"><span>Email:</span><input type="text" name="customer_email" class="sheet-input" value="<?= htmlspecialchars((string)($order['customer_email'] ?? '')) ?>"></label>
                    </div>
                </div>

                <div class="sheet-parties">
                    <div class="box">
                        <h3>PAYMENT</h3>
                        <label class="sheet-line">
                            <span>Method:</span>
                            <select name="payment_method" class="sheet-input">
                                <?php if ($paymentMethodChoice === ''): ?>
                                    <option value="" selected>N/A</option>
                                <?php endif; ?>
                                <?php foreach ($paymentMethodOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars((string)$value) ?>" <?= $paymentMethodChoice === (string)$value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sheet-line">
                            <span>Status:</span>
                            <select name="payment_status" class="sheet-input">
                                <?php if ($paymentStatusChoice === ''): ?>
                                    <option value="" selected>Pending</option>
                                <?php endif; ?>
                                <?php foreach ($paymentStatusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars((string)$value) ?>" <?= $paymentStatusChoice === (string)$value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if (trim((string)($order['coupon_code'] ?? '')) !== ''): ?>
                            <div class="muted">Coupon: <?= htmlspecialchars((string)$order['coupon_code']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="box">
                        <h3>SHIPPING</h3>
                        <label class="sheet-line"><span>Method:</span><input type="text" name="shipping_method" class="sheet-input" value="<?= htmlspecialchars((string)($order['shipping_method'] ?? '')) ?>" placeholder="Standard"></label>
                        <div class="sheet-line"><span>Cost:</span><strong id="shippingCostLabel"><?= htmlspecialchars($shippingLabel) ?></strong></div>
                        <label class="sheet-line">
                            <span>Weight:</span>
                            <input type="number" name="total_weight" class="sheet-input sheet-qty" min="0" step="0.01" value="<?= htmlspecialchars(number_format((float)($order['total_weight'] ?? 0), 2, '.', '')) ?>">
                            <span>kg</span>
                        </label>
                        <label class="sheet-line"><span>Carrier:</span><input type="text" name="carrier_name" class="sheet-input" value="<?= htmlspecialchars((string)($order['carrier_name'] ?? '')) ?>" placeholder="Carrier"></label>
                        <label class="sheet-line"><span>Tracking:</span><input type="text" name="tracking_number" class="sheet-input" value="<?= htmlspecialchars((string)($order['tracking_number'] ?? '')) ?>" placeholder="Tracking number"></label>
                        <label class="sheet-line">
                            <span>Status:</span>
                            <select name="shipment_status" class="sheet-input">
                                <?php foreach (['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $shipmentStatus === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sheet-line"><span>Shipped:</span><input type="date" name="shipped_date" class="sheet-input" value="<?= htmlspecialchars($shippedValue) ?>"></label>
                    </div>
                </div>

                <div class="items-wrap">
                    <table class="items">
                        <colgroup>
                            <col style="width:6%">
                            <col style="width:12%">
                            <col style="width:36%">
                            <col style="width:10%">
                            <col style="width:16%">
                            <col style="width:16%">
                            <col style="width:4%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>IMAGE</th>
                                <th>PRODUCT</th>
                                <th>QTY</th>
                                <th>UNIT PRICE</th>
                                <th>TOTAL</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $subtotal = 0;
                            $itemNumber = 1;
                            if (!$order_items):
                            ?>
                                <tr>
                                    <td class="center muted" colspan="7">No items found for this order</td>
                                </tr>
                            <?php
                            endif;
                            foreach ($order_items as $item):
                                $lineTotal = (float)$item['price'] * (int)$item['quantity'];
                                $subtotal += $lineTotal;
                                $productName = invoice_line_name($item);
                                $itemImage = admin_product_image_src($item['image_path'] ?? '');
                                $variantBits = array_values(array_filter([
                                    trim((string)($item['variation_color'] ?? '')),
                                    trim((string)($item['variation_size'] ?? '')),
                                ], static function ($bit) {
                                    return $bit !== '';
                                }));
                                $bundleLabel = '';
                                if (!empty($item['bundle_id']) && !empty($bundle_names[(int)$item['bundle_id']])) {
                                    $bundleLabel = (string)$bundle_names[(int)$item['bundle_id']];
                                }
                            ?>
                                <tr class="<?= $itemNumber % 2 === 0 ? 'alt' : '' ?>" data-line>
                                    <td class="center"><?= $itemNumber++ ?></td>
                                    <td class="center">
                                        <?php if ($itemImage !== ''): ?>
                                            <img src="<?= htmlspecialchars($itemImage) ?>" alt="" class="item-thumb" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                                            <div class="item-thumb-empty" hidden><i class="fas fa-image"></i></div>
                                        <?php else: ?>
                                            <div class="item-thumb-empty"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="text" class="sheet-input pname" name="items[<?= (int)$item['id'] ?>][product_name]" value="<?= htmlspecialchars($productName) ?>">
                                        <?php if ($variantBits): ?>
                                            <div class="muted"><?= htmlspecialchars(implode(' / ', $variantBits)) ?></div>
                                        <?php endif; ?>
                                        <?php if ($bundleLabel !== ''): ?>
                                            <div class="muted">Bundle: <?= htmlspecialchars($bundleLabel) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['product_id'])): ?>
                                            <div class="muted">ID: <?= (int)$item['product_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="center">
                                        <input type="number" data-qty class="sheet-input sheet-qty" min="1" required name="items[<?= (int)$item['id'] ?>][quantity]" value="<?= (int)$item['quantity'] ?>">
                                    </td>
                                    <td class="money">
                                        <div class="aed">AED <input type="number" data-price class="sheet-input sheet-money" min="0" step="0.01" required name="items[<?= (int)$item['id'] ?>][price]" value="<?= htmlspecialchars(number_format((float)$item['price'], 2, '.', '')) ?>"></div>
                                        <div class="usd" data-price-usd>USD <?= number_format((float)$item['price'] * $aed_to_usd, 2) ?></div>
                                    </td>
                                    <td class="money">
                                        <div class="aed">AED <span data-line-total><?= number_format($lineTotal, 2) ?></span></div>
                                        <div class="usd" data-line-usd>USD <?= number_format($lineTotal * $aed_to_usd, 2) ?></div>
                                    </td>
                                    <td class="center">
                                        <button type="submit" name="delete_item" value="<?= (int)$item['id'] ?>" class="sheet-remove" formnovalidate onclick="return confirm('Remove this line from the invoice?');" aria-label="Remove line">&times;</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($bundle_discount > 0): ?>
                    <div class="bundle-note">Bundle savings of AED <?= number_format($bundle_discount, 2) ?> are already included in the line prices.</div>
                <?php endif; ?>

                <div class="sheet-add">
                    <div class="section-title">ADD A LINE</div>
                    <div class="product-search-wrap">
                        <label for="productSearch">Search product</label>
                        <input type="text" id="productSearch" class="sheet-input" placeholder="Name, SKU, or product ID" autocomplete="off">
                        <div id="productSearchResults" class="product-search-results" hidden></div>
                    </div>
                    <div id="selectedProductPreview" class="selected-product" hidden>
                        <img id="selectedProductImage" src="" alt="" class="item-thumb">
                        <div id="selectedProductImageEmpty" class="item-thumb-empty" hidden><i class="fas fa-image"></i></div>
                        <div>
                            <strong id="selectedProductLabel">Product</strong>
                            <div class="muted">This image is used on the printed invoice.</div>
                        </div>
                    </div>
                    <input type="hidden" name="item_product_id" id="itemProductId" value="">
                    <div class="add-grid">
                        <div>
                            <label for="itemProductName">Product</label>
                            <input type="text" name="item_product_name" id="itemProductName" class="sheet-input" placeholder="Product name">
                        </div>
                        <div>
                            <label for="itemQuantity">Qty</label>
                            <input type="number" name="item_quantity" id="itemQuantity" class="sheet-input" value="1" min="1">
                        </div>
                        <div>
                            <label for="itemProductPrice">Unit price</label>
                            <input type="number" name="item_price" id="itemProductPrice" class="sheet-input" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <button type="submit" name="add_item" class="sheet-add-btn">Add line</button>
                    </div>
                </div>

                <?php
                $grand = round(max(0, $subtotal + $shippingAmount + $wrapAmount + $vatAmount - $discountAmount), 2);
                $vatLabel = 'VAT';
                if ($vatRate > 0) {
                    $vatLabel .= ' ' . rtrim(rtrim(number_format($vatRate, 2), '0'), '.') . '%';
                }
                if ($trn !== '') {
                    $vatLabel .= ' (TRN ' . $trn . ')';
                }
                ?>
                <div class="totals-wrap">
                    <table class="totals">
                        <tr>
                            <td class="lbl">Subtotal</td>
                            <td class="money">
                                <div class="aed">AED <span id="subtotalAmount"><?= number_format($subtotal, 2) ?></span></div>
                                <div class="usd" id="subtotalUsd">USD <?= number_format($subtotal * $aed_to_usd, 2) ?></div>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Shipping</td>
                            <td class="money">
                                <div class="aed">AED <input type="number" name="shipping_aed" class="sheet-input sheet-money" min="0" step="0.01" value="<?= htmlspecialchars(number_format($shippingAmount, 2, '.', '')) ?>"></div>
                                <div class="usd" id="shippingUsd">USD <?= number_format($shippingAmount * $aed_to_usd, 2) ?></div>
                            </td>
                        </tr>
                        <tr class="discount<?= $discountAmount > 0 ? ' has-discount' : '' ?>">
                            <td class="lbl">Discount</td>
                            <td class="money">
                                <div class="aed"><span id="discountSign"><?= $discountAmount > 0 ? '- ' : '' ?></span>AED <input type="number" name="discount_amount" class="sheet-input sheet-money" min="0" step="0.01" value="<?= htmlspecialchars(number_format($discountAmount, 2, '.', '')) ?>"></div>
                                <div class="usd" id="discountUsd"><?= $discountAmount > 0 ? '- ' : '' ?>USD <?= number_format($discountAmount * $aed_to_usd, 2) ?></div>
                            </td>
                        </tr>
                        <?php if ($wrapAmount > 0): ?>
                            <tr>
                                <td class="lbl">Gift wrap</td>
                                <td class="money">
                                    <div class="aed">AED <?= number_format($wrapAmount, 2) ?></div>
                                    <div class="usd">USD <?= number_format($wrapAmount * $aed_to_usd, 2) ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($vatAmount > 0): ?>
                            <tr>
                                <td class="lbl"><?= htmlspecialchars($vatLabel) ?></td>
                                <td class="money">
                                    <div class="aed">AED <?= number_format($vatAmount, 2) ?></div>
                                    <div class="usd">USD <?= number_format($vatAmount * $aed_to_usd, 2) ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="grand">
                            <td>GRAND TOTAL</td>
                            <td class="money">
                                <div class="aed">AED <span id="grandAed"><?= number_format($grand, 2) ?></span></div>
                                <div class="usd" id="grandUsd">USD <?= number_format($grand * $aed_to_usd, 2) ?></div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="notes-block">
                    <div class="section-title">NOTES</div>
                    <textarea name="note" class="sheet-input" rows="3" placeholder="Printed on the invoice"><?= htmlspecialchars((string)($order['note'] ?? '')) ?></textarea>
                </div>
                <div class="notes-block is-internal">
                    <div class="section-title">INTERNAL REMARKS</div>
                    <textarea name="remarks" class="sheet-input" rows="3" placeholder="Not printed"><?= htmlspecialchars((string)($order['remarks'] ?? '')) ?></textarea>
                </div>

                <div class="sheet-foot">
                    Thank you for your order.<br>
                    www.aleppogift.com &nbsp;|&nbsp; info@aleppogift.com<br>
                    Amounts are shown in AED with the USD equivalent.
                </div>
            </div>

            <div class="sheet-actions">
                <button type="submit" name="save_invoice" class="sheet-save" formnovalidate>Save invoice</button>
                <a href="generate_invoice.php?id=<?= (int)$order_id ?>" target="_blank" class="sheet-link">View PDF</a>
                <a href="order_detail.php?id=<?= (int)$order_id ?>" class="sheet-link">Back to order</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    const usdRate = <?= json_encode($aed_to_usd) ?>;
    const wrapAmount = <?= json_encode($wrapAmount) ?>;
    const vatAmount = <?= json_encode($vatAmount) ?>;

    function money(amount) {
        return (Math.round((amount + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function calculateTotal() {
        const shippingInput = document.querySelector('[name="shipping_aed"]');
        const discountInput = document.querySelector('[name="discount_amount"]');
        if (!shippingInput || !discountInput) return;

        let subtotal = 0;
        document.querySelectorAll('[data-line]').forEach(function (row) {
            const qtyInput = row.querySelector('[data-qty]');
            const priceInput = row.querySelector('[data-price]');
            if (!qtyInput || !priceInput) return;
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const lineTotal = qty * price;
            subtotal += lineTotal;
            const lineLabel = row.querySelector('[data-line-total]');
            const lineUsd = row.querySelector('[data-line-usd]');
            const priceUsd = row.querySelector('[data-price-usd]');
            if (lineLabel) lineLabel.textContent = money(lineTotal);
            if (lineUsd) lineUsd.textContent = 'USD ' + money(lineTotal * usdRate);
            if (priceUsd) priceUsd.textContent = 'USD ' + money(price * usdRate);
        });

        const shipping = Math.max(0, parseFloat(shippingInput.value) || 0);
        const discount = Math.max(0, parseFloat(discountInput.value) || 0);
        const total = Math.max(0, subtotal + shipping + wrapAmount + vatAmount - discount);
        const subtotalAmount = document.getElementById('subtotalAmount');
        const subtotalUsd = document.getElementById('subtotalUsd');
        const shippingUsd = document.getElementById('shippingUsd');
        const discountUsd = document.getElementById('discountUsd');
        const grandAed = document.getElementById('grandAed');
        const grandUsd = document.getElementById('grandUsd');
        const shippingLabel = document.getElementById('shippingCostLabel');
        if (subtotalAmount) subtotalAmount.textContent = money(subtotal);
        if (subtotalUsd) subtotalUsd.textContent = 'USD ' + money(subtotal * usdRate);
        if (shippingUsd) shippingUsd.textContent = shipping > 0 ? 'USD ' + money(shipping * usdRate) : 'Free';
        const discountSign = document.getElementById('discountSign');
        const discountRow = discountInput.closest('tr');
        if (discountRow) discountRow.classList.toggle('has-discount', discount > 0);
        if (discountSign) discountSign.textContent = discount > 0 ? '- ' : '';
        if (discountUsd) discountUsd.textContent = (discount > 0 ? '- ' : '') + 'USD ' + money(discount * usdRate);
        if (grandAed) grandAed.textContent = money(total);
        if (grandUsd) grandUsd.textContent = 'USD ' + money(total * usdRate);
        if (shippingLabel) {
            shippingLabel.textContent = shipping > 0
                ? 'AED ' + money(shipping) + ' (USD ' + money(shipping * usdRate) + ')'
                : 'Free';
        }
    }

    document.querySelectorAll('[data-qty], [data-price], [name="shipping_aed"], [name="discount_amount"]').forEach(function (input) {
        input.addEventListener('input', calculateTotal);
    });

    const addLineButton = document.querySelector('[name="add_item"]');
    if (addLineButton) {
        addLineButton.addEventListener('click', function (event) {
            const name = document.getElementById('itemProductName');
            const price = document.getElementById('itemProductPrice');
            if (!name || !name.value.trim() || !price || price.value === '') {
                event.preventDefault();
                window.alert('Enter a product name and unit price before adding a line.');
                if (name && !name.value.trim()) {
                    name.focus();
                } else if (price) {
                    price.focus();
                }
            }
        });
    }
    window.addEventListener('load', calculateTotal);

    function productImageSrc(path) {
        if (!path) return '';
        path = String(path).replace(/\\/g, '/').replace(/^(\.\.\/)+/, '').replace(/^\/+/, '');
        if (!path) return '';
        return '../' + path.split('/').map(encodeURIComponent).join('/');
    }

    function selectInvoiceProduct(product) {
        document.getElementById('itemProductId').value = product.id;
        document.getElementById('itemProductName').value = product.name_en || product.name_ar || '';
        document.getElementById('itemProductPrice').value = parseFloat(product.price || 0).toFixed(2);
        document.getElementById('productSearch').value = '';
        document.getElementById('productSearchResults').hidden = true;

        const preview = document.getElementById('selectedProductPreview');
        const image = document.getElementById('selectedProductImage');
        const empty = document.getElementById('selectedProductImageEmpty');
        const src = productImageSrc(product.image_path);
        preview.hidden = false;
        document.getElementById('selectedProductLabel').textContent =
            (product.name_en || 'Product') + ' · ID ' + product.id;
        if (src) {
            image.hidden = false;
            image.onerror = function () {
                image.hidden = true;
                empty.hidden = false;
            };
            image.src = src;
            empty.hidden = true;
        } else {
            image.hidden = true;
            image.removeAttribute('src');
            empty.hidden = false;
        }
    }

    (function () {
        const input = document.getElementById('productSearch');
        const results = document.getElementById('productSearchResults');
        if (!input || !results) return;

        let timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            const query = input.value.trim();
            if (query.length < 2) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch('ajax/search_products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: query })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success || !data.products || !data.products.length) {
                        results.innerHTML = '<div style="padding:10px;color:#8a6d4f;">' +
                            (data.error ? 'Search failed' : 'No products found') + '</div>';
                        results.hidden = false;
                        return;
                    }
                    results.innerHTML = '';
                    data.products.forEach(function (product) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'product-search-item';
                        const src = productImageSrc(product.image_path);
                        const thumb = src
                            ? '<img src="' + src.replace(/"/g, '&quot;') + '" alt="" class="item-thumb">'
                            : '<div class="item-thumb-empty"><i class="fas fa-image"></i></div>';
                        const name = document.createElement('div');
                        button.innerHTML = thumb;
                        button.appendChild(name);
                        const strong = document.createElement('strong');
                        const meta = document.createElement('div');
                        meta.className = 'muted';
                        strong.textContent = product.name_en || product.name_ar || 'Product';
                        meta.textContent = 'ID ' + product.id + ' · AED ' + parseFloat(product.price || 0).toFixed(2);
                        name.appendChild(strong);
                        name.appendChild(meta);
                        button.addEventListener('click', function () {
                            selectInvoiceProduct(product);
                        });
                        results.appendChild(button);
                    });
                    results.hidden = false;
                })
                .catch(function () {
                    results.innerHTML = '<div style="padding:10px;color:#8a6d4f;">Search failed</div>';
                    results.hidden = false;
                });
            }, 250);
        });

        document.addEventListener('click', function (event) {
            if (!input.contains(event.target) && !results.contains(event.target)) {
                results.hidden = true;
            }
        });
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
