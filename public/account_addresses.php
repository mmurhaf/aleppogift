<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_customer();
$db = new Database();
$id = current_customer_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->query('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [(int)$_POST['delete_id'], $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    if (!empty($_POST['is_default'])) {
        $db->query('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$id]);
    }
    $db->query(
        'INSERT INTO customer_addresses (customer_id, label, fullname, phone, address_line1, address_line2, city, state, country, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $_POST['label'] ?? '', $_POST['fullname'] ?? '', $_POST['phone'] ?? '', $_POST['address_line1'] ?? '', $_POST['address_line2'] ?? '', $_POST['city'] ?? '', $_POST['state'] ?? '', $_POST['country'] ?? '', $_POST['postal_code'] ?? '', !empty($_POST['is_default']) ? 1 : 0]
    );
}
$addresses = $db->fetchAll('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$id]);
storefront_page_open(t('account.addresses'));
?>
<h1><?= te('account.addresses') ?></h1>
<?php foreach ($addresses as $address): ?>
<div class="card card-body mb-2">
    <strong><?= htmlspecialchars($address['label'] ?: $address['fullname']) ?></strong>
    <div><?= htmlspecialchars($address['address_line1']) ?> <?= htmlspecialchars($address['address_line2']) ?>, <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['country']) ?></div>
    <form method="post"><input type="hidden" name="delete_id" value="<?= (int)$address['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><?= te('account.delete') ?></button></form>
</div>
<?php endforeach; ?>
<form method="post" class="card card-body mt-3">
    <input type="hidden" name="add_address" value="1">
    <div class="row g-2">
        <?php foreach (['label','fullname','phone','address_line1','address_line2','city','state','country','postal_code'] as $field): ?>
        <div class="col-md-6"><input class="form-control" name="<?= $field ?>" placeholder="<?= te('account.field.' . $field) ?>"></div>
        <?php endforeach; ?>
        <div class="col-12"><label><input type="checkbox" name="is_default" value="1"> <?= te('account.default_address') ?></label></div>
    </div>
    <button class="btn btn-primary mt-3" type="submit"><?= te('account.add_address') ?></button>
</form>
<?php storefront_page_close();