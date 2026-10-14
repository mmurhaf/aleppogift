<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$email = trim((string)($_GET['email'] ?? $_POST['email'] ?? ''));
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$customer = $db->fetch('SELECT * FROM customers WHERE email = ? AND reset_expires > NOW() ORDER BY id DESC LIMIT 1', [$email]);
$valid = $customer && hash_equals((string)$customer['reset_token_hash'], hash('sha256', $token));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    if (!$valid) {
        $error = t('account.reset_invalid');
    } elseif (strlen($password) < 8 || $password !== $confirm) {
        $error = t('account.password_rules');
    } else {
        $db->query('UPDATE customers SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $customer['id']]);
        header('Location: ' . store_url('login.php'));
        exit;
    }
}
storefront_page_open(t('account.reset_password'));
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h1><?= te('account.reset_password') ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($valid): ?>
    <form method="post" class="card card-body">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <label class="form-label"><?= te('account.new_password') ?></label>
        <input class="form-control mb-3" type="password" name="password" minlength="8" required>
        <label class="form-label"><?= te('account.confirm_password') ?></label>
        <input class="form-control mb-3" type="password" name="confirm" minlength="8" required>
        <button class="btn btn-primary" type="submit"><?= te('account.save_password') ?></button>
    </form>
    <?php else: ?><div class="alert alert-warning"><?= te('account.reset_invalid') ?></div><?php endif; ?>
</div></div>
<?php storefront_page_close();