<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $customer = $db->fetch(
        "SELECT * FROM customers WHERE email = ? AND password_hash IS NOT NULL AND password_hash != '' ORDER BY id DESC LIMIT 1",
        [$email]
    );
    $message = t('account.reset_sent');
    if ($customer) {
        $token = bin2hex(random_bytes(16));
        $db->query('UPDATE customers SET reset_token_hash = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?', [hash('sha256', $token), $customer['id']]);
        $link = store_url('reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        send_store_mail($email, t('account.reset_email_subject'), '<p><a href="' . htmlspecialchars($link) . '">' . te('account.reset_email_link') . '</a>. ' . te('account.reset_email_expiry') . '</p>');
    }
}
storefront_page_open(t('account.forgot_password'));
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h1><?= te('account.forgot_password') ?></h1>
    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <label class="form-label"><?= te('account.email') ?></label>
        <input class="form-control mb-3" type="email" name="email" required>
        <button class="btn btn-primary" type="submit"><?= te('account.send_reset_link') ?></button>
    </form>
</div></div>
<?php storefront_page_close();