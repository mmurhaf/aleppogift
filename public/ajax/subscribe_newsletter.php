<?php
require_once(__DIR__ . '/../../includes/bootstrap.php');

$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

function subscribe_response(bool $ok, string $message, int $status = 200): void {
    global $wantsJson;
    http_response_code($status);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => $ok, 'message' => $message]);
        exit;
    }

    $target = store_url() . '?' . ($ok ? 'subscribed=1' : 'subscribed=0');
    header('Location: ' . $target);
    exit;
}

if (!$email) {
    subscribe_response(false, t('cart.newsletter_invalid_email'), 422);
}

try {
    $db = new Database();
    $db->getPdo()->exec(
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email_unique (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $existing = $db->fetch('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
    if ($existing && ($existing['status'] ?? 'active') === 'active' && !empty($existing['token'])) {
        subscribe_response(true, t('cart.newsletter_already'));
    }
    $token = $existing['token'] ?? bin2hex(random_bytes(16));
    if ($existing) {
        $db->query("UPDATE newsletter_subscribers SET status = 'active', token = ?, subscribed_at = NOW() WHERE id = ?", [$token, $existing['id']]);
        subscribe_response(true, ($existing['status'] ?? '') === 'unsubscribed' ? t('cart.newsletter_again') : t('footer.subscribed'));
    }
    $db->query(
        "INSERT INTO newsletter_subscribers (email, status, token, subscribed_at) VALUES (?, 'active', ?, NOW())",
        [$email, $token]
    );
    subscribe_response(true, t('footer.subscribed'));
} catch (Throwable $e) {
    error_log('Newsletter subscribe failed: ' . $e->getMessage());
    subscribe_response(false, t('cart.newsletter_failed'), 500);
}
