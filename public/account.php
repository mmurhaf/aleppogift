<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_customer();
$db = new Database();
$id = current_customer_id();
$customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [$id]);
$orders = (int)$db->fetch('SELECT COUNT(*) AS c FROM orders WHERE customer_id = ?', [$id])['c'];
$spend = (float)$db->fetch("SELECT COALESCE(SUM(total_amount),0) AS c FROM orders WHERE customer_id = ? AND payment_status = 'paid'", [$id])['c'];
$wishes = (int)$db->fetch('SELECT COUNT(*) AS c FROM wishlists WHERE customer_id = ?', [$id])['c'];
$reviews = (int)$db->fetch('SELECT COUNT(*) AS c FROM product_reviews WHERE customer_email = ?', [$customer['email'] ?? ''])['c'];
$recent = $db->fetchAll('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 5', [$id]);
storefront_page_open(t('account'));
?>
<h1><?= htmlspecialchars(t('account')) ?></h1>
<p><?= htmlspecialchars($customer['fullname'] ?? '') ?></p>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-body"><strong><?= $orders ?></strong><div><?= te('account.orders') ?></div></div></div>
    <div class="col-md-3"><div class="card card-body"><strong>AED <?= number_format($spend, 2) ?></strong><div><?= te('account.paid_spend') ?></div></div></div>
    <div class="col-md-3"><div class="card card-body"><strong><?= $wishes ?></strong><div><a href="wishlist.php"><?= htmlspecialchars(t('wishlist')) ?></a></div></div></div>
    <div class="col-md-3"><div class="card card-body"><strong><?= $reviews ?></strong><div><?= te('account.reviews') ?></div></div></div>
</div>
<p><a href="account_orders.php"><?= te('account.orders') ?></a> · <a href="account_profile.php"><?= te('account.profile') ?></a> · <a href="account_addresses.php"><?= te('account.addresses') ?></a> · <a href="logout.php"><?= htmlspecialchars(t('logout')) ?></a></p>
<h2><?= te('account.recent_orders') ?></h2>
<ul><?php foreach ($recent as $order): ?><li><a href="account_order.php?id=<?= (int)$order['id'] ?>">#<?= (int)$order['id'] ?></a> — AED <?= number_format((float)$order['total_amount'], 2) ?></li><?php endforeach; ?></ul>
<?php storefront_page_close();