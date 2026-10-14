<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_customer();
$db = new Database();
$customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [current_customer_id()]);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $db->query('UPDATE customers SET fullname = ?, phone = ? WHERE id = ?', [$name, $phone, $customer['id']]);
    $_SESSION['customer_name'] = $name;
    if (($_POST['new_password'] ?? '') !== '') {
        $current = (string)($_POST['current_password'] ?? '');
        $next = (string)$_POST['new_password'];
        if (!password_verify($current, (string)$customer['password_hash']) || strlen($next) < 8) {
            $message = t('account.password_change_failed');
        } else {
            $db->query('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($next, PASSWORD_DEFAULT), $customer['id']]);
            $message = t('account.password_updated');
        }
    } else {
        $message = t('account.profile_saved');
    }
    $customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [current_customer_id()]);
}
storefront_page_open(t('account.profile'));
?>
<h1><?= te('account.profile') ?></h1>
<?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<form method="post" class="card card-body">
    <label class="form-label"><?= te('account.name') ?></label>
    <input class="form-control mb-3" name="name" value="<?= htmlspecialchars($customer['fullname'] ?? '') ?>">
    <label class="form-label"><?= te('account.phone') ?></label>
    <input class="form-control mb-3" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
    <label class="form-label"><?= te('account.current_password') ?></label>
    <input class="form-control mb-3" type="password" name="current_password">
    <label class="form-label"><?= te('account.new_password') ?></label>
    <input class="form-control mb-3" type="password" name="new_password">
    <button class="btn btn-primary" type="submit"><?= te('save') ?></button>
</form>
<?php storefront_page_close();