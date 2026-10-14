<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_customer();
$db = new Database();
$customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [current_customer_id()]);
$order = $db->fetch('SELECT * FROM orders WHERE id = ? AND customer_id = ?', [(int)($_GET['id'] ?? 0), current_customer_id()]);
if (!$order) {
    http_response_code(404);
    storefront_page_open(t('account.col_order'));
    echo '<p>' . te('account.order_not_found') . '</p>';
    storefront_page_close();
    exit;
}
$items = $db->fetchAll('SELECT oi.*, p.name_en, p.name_ar FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$order['id']]);
$paymentStatus = (string)$order['payment_status'];
$shipmentStatus = (string)($order['shipment_status'] ?? '');
storefront_page_open(t('account.order_number', ['id' => (int)$order['id']]));
?>
<h1><?= te('account.order_number', ['id' => (int)$order['id']]) ?></h1>
<p><?= te('account.order_statuses', ['payment' => t_or('order.status.' . $paymentStatus, $paymentStatus), 'shipment' => $shipmentStatus === '' ? '' : t_or('order.status.' . $shipmentStatus, $shipmentStatus)]) ?></p>
<?php if (!empty($order['tracking_number'])): ?><p><?= te('account.tracking', ['number' => $order['tracking_number']]) ?></p><?php endif; ?>
<table class="table"><tbody>
<?php foreach ($items as $item): ?>
<tr><td><?= htmlspecialchars(loc_field($item, 'name')) ?></td><td><?= (int)$item['quantity'] ?></td><td>AED <?= number_format((float)$item['price'], 2) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><?= te('account.order_total', ['amount' => 'AED ' . number_format((float)$order['total_amount'], 2)]) ?></p>
<?php if (!ag_order_invoice_allowed($order)): ?>
    <p><?= te('order.cod_invoice_note') ?></p>
    <?php if (($_GET['invoice'] ?? '') === 'sent'): ?>
        <p><?= te('order.invoice_requested') ?></p>
    <?php else: ?>
        <?php if (($_GET['invoice'] ?? '') === 'failed'): ?>
            <p><?= te('order.request_invoice_error') ?></p>
        <?php endif; ?>
        <form method="post" action="request_invoice.php">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <button type="submit" class="btn btn-primary"><?= te('order.request_invoice') ?></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php storefront_page_close();