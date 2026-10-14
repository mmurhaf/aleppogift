<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_customer();
$db = new Database();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$id = current_customer_id();
$total = (int)$db->fetch('SELECT COUNT(*) AS c FROM orders WHERE customer_id = ?', [$id])['c'];
$orders = $db->fetchAll('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset, [$id]);
storefront_page_open(t('account.your_orders'));
?>
<h1><?= te('account.your_orders') ?></h1>
<table class="table"><thead><tr><th><?= te('account.col_order') ?></th><th><?= te('account.col_date') ?></th><th><?= te('account.col_total') ?></th><th><?= te('account.col_payment') ?></th></tr></thead><tbody>
<?php foreach ($orders as $order): ?>
<tr>
    <td><a href="account_order.php?id=<?= (int)$order['id'] ?>">#<?= (int)$order['id'] ?></a></td>
    <td><?= htmlspecialchars((string)$order['order_date']) ?></td>
    <td>AED <?= number_format((float)$order['total_amount'], 2) ?></td>
    <td><?= htmlspecialchars(t_or('order.status.' . $order['payment_status'], (string)$order['payment_status'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>"><?= te('account.previous') ?></a><?php endif; ?>
<?php if ($offset + $perPage < $total): ?><a href="?page=<?= $page + 1 ?>"><?= te('account.next') ?></a><?php endif; ?>
<?php storefront_page_close();