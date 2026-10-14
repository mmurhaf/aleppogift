<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/store_features.php';
require_admin_login();
$db = new Database();
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'subscribed_at']);
    foreach ($db->fetchAll("SELECT email, subscribed_at FROM newsletter_subscribers WHERE status = 'active'") as $row) {
        fputcsv($out, [$row['email'], $row['subscribed_at']]);
    }
    exit;
}
if (isset($_GET['delete'])) {
    $db->query('DELETE FROM newsletter_subscribers WHERE id = ?', [(int)$_GET['delete']]);
    header('Location: newsletter.php');
    exit;
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $sendAt = trim((string)($_POST['send_at'] ?? ''));
    $db->query(
        'INSERT INTO newsletter_campaigns (name, subject, html_body, send_at, status) VALUES (?, ?, ?, ?, ?)',
        [$_POST['name'] ?? '', $_POST['subject'] ?? '', $_POST['html_body'] ?? '', $sendAt !== '' ? $sendAt : null, $sendAt !== '' ? 'scheduled' : 'draft']
    );
    $message = 'Campaign saved.';
}
if (isset($_POST['send_id'])) {
    $campaign = $db->fetch('SELECT * FROM newsletter_campaigns WHERE id = ?', [(int)$_POST['send_id']]);
    $sent = 0;
    if ($campaign) {
        $subscribers = $db->fetchAll("SELECT * FROM newsletter_subscribers WHERE status = 'active'");
        foreach ($subscribers as $subscriber) {
            $link = store_url('newsletter_unsubscribe.php?email=' . urlencode($subscriber['email']) . '&token=' . urlencode((string)$subscriber['token']));
            $html = $campaign['html_body'] . '<p><a href="' . htmlspecialchars($link) . '">Unsubscribe</a></p>';
            if (send_store_mail($subscriber['email'], $campaign['subject'], $html)) {
                $sent++;
            }
        }
        if ($sent === 0) {
            $message = 'The campaign reached nobody. Check the mail settings.';
        } else {
            $db->query('UPDATE newsletter_campaigns SET status = ?, sent_at = NOW(), sent_count = ? WHERE id = ?', ['sent', $sent, $campaign['id']]);
            $message = "Sent to $sent subscribers.";
        }
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
$subscribers = $db->fetchAll('SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30));
$campaigns = $db->fetchAll('SELECT * FROM newsletter_campaigns ORDER BY id DESC');
$page_title = 'Newsletter';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <p><a class="btn btn-outline-primary" href="?export=1">Export active subscribers</a></p>
    <table class="table"><thead><tr><th>Email</th><th>Status</th><th>Subscribed</th><th></th></tr></thead><tbody>
    <?php foreach ($subscribers as $subscriber): ?>
        <tr><td><?= htmlspecialchars($subscriber['email']) ?></td><td><?= htmlspecialchars($subscriber['status']) ?></td><td><?= htmlspecialchars((string)$subscriber['subscribed_at']) ?></td><td><a href="?delete=<?= (int)$subscriber['id'] ?>">Delete</a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <h2>Campaign</h2>
    <form method="post" class="card card-body mb-3">
        <input type="hidden" name="create_campaign" value="1">
        <input class="form-control mb-2" name="name" placeholder="Name" required>
        <input class="form-control mb-2" name="subject" placeholder="Subject" required>
        <textarea class="form-control mb-2" name="html_body" placeholder="HTML content" required></textarea>
        <input class="form-control mb-2" type="datetime-local" name="send_at">
        <button class="btn btn-primary" type="submit">Save campaign</button>
    </form>
    <?php foreach ($campaigns as $campaign): ?>
        <form method="post" class="mb-2"><?= htmlspecialchars($campaign['name']) ?> (<?= htmlspecialchars($campaign['status']) ?>, sent <?= (int)$campaign['sent_count'] ?>)
            <input type="hidden" name="send_id" value="<?= (int)$campaign['id'] ?>">
            <button class="btn btn-sm btn-success" type="submit">Send</button>
        </form>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';