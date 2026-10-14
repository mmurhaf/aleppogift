<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_admin_login();
$db = new Database();
if (isset($_GET['delete'])) {
    $db->query('DELETE FROM contact_messages WHERE id = ?', [(int)$_GET['delete']]);
    header('Location: contact_messages.php');
    exit;
}
if (isset($_GET['id'])) {
    $db->query('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [(int)$_GET['id']]);
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * 30;
$unread = (int)$db->fetch('SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = 0')['c'];
$rows = $db->fetchAll('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 30 OFFSET ' . (int)$offset);
$open = isset($_GET['id']) ? $db->fetch('SELECT * FROM contact_messages WHERE id = ?', [(int)$_GET['id']]) : null;
$page_title = 'Contact messages';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <p>Unread: <?= $unread ?></p>
    <?php if ($open): ?>
        <div class="card card-body mb-3">
            <h2><?= htmlspecialchars($open['subject']) ?></h2>
            <p><?= htmlspecialchars($open['name']) ?> · <?= htmlspecialchars($open['email']) ?> · <?= htmlspecialchars((string)$open['phone']) ?></p>
            <p><?= nl2br(htmlspecialchars($open['message'])) ?></p>
            <p>IP <?= htmlspecialchars((string)$open['ip']) ?> · emailed <?= (int)$open['emailed'] ? 'yes' : 'no' ?> · <?= htmlspecialchars($open['created_at']) ?></p>
            <a class="btn btn-outline-danger" href="?delete=<?= (int)$open['id'] ?>">Delete</a>
        </div>
    <?php endif; ?>
    <table class="table"><thead><tr><th>From</th><th>Subject</th><th>When</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><a href="?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a> <?= (int)$row['is_read'] ? '' : '(unread)' ?></td>
            <td><?= htmlspecialchars((string)$row['subject']) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
</div>
<?php require __DIR__ . '/includes/footer.php';