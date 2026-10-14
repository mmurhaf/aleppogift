<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$email = trim((string)($_GET['email'] ?? ''));
$token = (string)($_GET['token'] ?? '');
$row = $db->fetch('SELECT * FROM newsletter_subscribers WHERE email = ? AND token = ?', [$email, $token]);
if ($row) {
    $db->query("UPDATE newsletter_subscribers SET status = 'unsubscribed' WHERE id = ?", [$row['id']]);
    $message = t('account.unsubscribed');
} else {
    $message = t('account.unsubscribe_invalid');
}
storefront_page_open(t('account.unsubscribe'));
echo '<h1>' . te('account.newsletter') . '</h1><p>' . htmlspecialchars($message) . '</p>';
storefront_page_close();