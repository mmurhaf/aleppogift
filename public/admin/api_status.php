<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/store_features.php';
require_admin_login();
$db = new Database();
$checks = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $to = store_setting($db, 'admin_email', defined('EMAIL_FROM') ? EMAIL_FROM : '');
    $ok = send_store_mail($to, 'Aleppo Gift mail test', '<p>This is a test message from the admin status page.</p>');
    $checks[] = ['name' => 'Test message', 'ok' => $ok, 'error' => $ok ? '' : 'mail() returned false'];
}
$key = store_setting($db, 'ziina_api_key', defined('ZIINA_API_KEY') ? ZIINA_API_KEY : '');
$ziinaOk = false;
$ziinaError = '';
if ($key === '') {
    $ziinaError = 'No Ziina key is configured.';
} elseif (function_exists('curl_init')) {
    $ch = curl_init('https://api-v2.ziina.com/api/payment_intent');
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 12, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key]]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ziinaError = curl_error($ch);
    curl_close($ch);
    $ziinaOk = $code > 0 && $code < 500;
    if (!$ziinaOk && $ziinaError === '') {
        $ziinaError = 'HTTP ' . $code;
    }
} else {
    $ziinaError = 'cURL is not available.';
}
$checks[] = ['name' => 'Ziina', 'ok' => $ziinaOk, 'error' => $ziinaOk ? '' : $ziinaError];
$smtpHost = store_setting($db, 'smtp_host', defined('SMTP_HOST') ? SMTP_HOST : '');
$smtpPort = (int)store_setting($db, 'smtp_port', defined('SMTP_PORT') ? (string)SMTP_PORT : '465');
$errno = 0;
$errstr = '';
$socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 8);
$smtpOk = is_resource($socket);
if ($smtpOk) {
    fclose($socket);
}
$checks[] = ['name' => 'SMTP ' . $smtpHost . ':' . $smtpPort, 'ok' => $smtpOk, 'error' => $smtpOk ? '' : ($errstr ?: 'Connection failed')];
$page_title = 'API status';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <?php foreach ($checks as $check): ?>
        <div class="alert alert-<?= $check['ok'] ? 'success' : 'danger' ?>">
            <?= htmlspecialchars($check['name']) ?>: <?= $check['ok'] ? 'reachable' : 'not reachable' ?>
            <?php if ($check['error']): ?><div><?= htmlspecialchars($check['error']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <form method="post"><button class="btn btn-primary" name="test_email" value="1" type="submit">Send test message</button></form>
</div>
<?php require __DIR__ . '/includes/footer.php';