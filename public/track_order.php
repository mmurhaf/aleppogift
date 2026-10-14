<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$order = null;
$notFound = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = trim((string)($_POST['order_number'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    if ($number === '' || $email === '') {
        $notFound = true;
    } else {
        $order = $db->fetch(
            'SELECT o.* FROM orders o INNER JOIN customers c ON c.id = o.customer_id WHERE o.id = ? AND c.email = ?',
            [(int)$number, $email]
        );
        if (!$order) {
            $notFound = true;
        }
    }
}
storefront_page_open(t('track'));
?>
<h1><?= htmlspecialchars(t('track')) ?></h1>
<form method="post" class="card card-body mb-3">
    <label class="form-label"><?= te('order.track_number') ?></label>
    <input class="form-control mb-3" name="order_number" required>
    <label class="form-label"><?= te('order.track_email') ?></label>
    <input class="form-control mb-3" type="email" name="email" required>
    <button class="btn btn-primary" type="submit"><?= te('order.track_btn') ?></button>
</form>
<?php if ($notFound): ?><div class="alert alert-warning"><?= te('order.err_not_found') ?></div><?php endif; ?>
<?php if ($order): ?>
<?php
$trackPayment = (string)$order['payment_status'];
$trackShipment = (string)($order['shipment_status'] ?? '');
?>
<div class="card card-body">
    <p><?= te('order.track_payment', ['status' => $trackPayment === '' ? '' : t_or('order.status.' . strtolower($trackPayment), $trackPayment)]) ?></p>
    <p><?= te('order.track_shipment', ['status' => $trackShipment === '' ? '' : t_or('order.status.' . strtolower($trackShipment), $trackShipment)]) ?></p>
    <?php if (!empty($order['carrier_name'])): ?><p><?= te('order.track_courier', ['name' => $order['carrier_name']]) ?></p><?php endif; ?>
    <?php if (!empty($order['tracking_number'])): ?><p><?= te('order.track_tracking', ['number' => $order['tracking_number']]) ?></p><?php endif; ?>
    <?php if (!empty($order['estimated_delivery'])): ?><p><?= te('order.track_eta', ['date' => $order['estimated_delivery']]) ?></p><?php endif; ?>
</div>
<?php endif; ?>
<?php storefront_page_close();