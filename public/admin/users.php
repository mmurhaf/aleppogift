<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/store_features.php';
require_admin_login();
$db = new Database();
if (!admin_is_super($db)) {
    http_response_code(403);
    echo 'Only a super admin can manage users.';
    exit;
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $role = in_array($_POST['role'] ?? '', ['super_admin', 'admin', 'manager'], true) ? $_POST['role'] : 'admin';
    $active = isset($_POST['active']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');
    $self = (int)($_SESSION['admin_id'] ?? 0);
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $superCount = (int)$db->fetch("SELECT COUNT(*) AS c FROM admin WHERE role = 'super_admin' AND active = 1 AND id != ?", [$deleteId])['c'];
        if ($deleteId === $self) {
            $message = 'You cannot delete your own account.';
        } elseif ($superCount < 1) {
            $message = 'The last active super admin cannot be deleted.';
        } else {
            $db->query('DELETE FROM admin WHERE id = ?', [$deleteId]);
            $message = 'User deleted.';
        }
    } elseif ($id > 0) {
        $target = $db->fetch('SELECT * FROM admin WHERE id = ?', [$id]);
        $superCount = (int)$db->fetch("SELECT COUNT(*) AS c FROM admin WHERE role = 'super_admin' AND active = 1 AND id != ?", [$id])['c'];
        if ($target && $target['role'] === 'super_admin' && ($role !== 'super_admin' || $active !== 1) && $superCount < 1) {
            $message = 'The last active super admin cannot be deactivated.';
        } else {
            $sql = 'UPDATE admin SET username = ?, name = ?, email = ?, role = ?, active = ?';
            $params = [$username, $name, $email, $role, $active];
            if ($password !== '') {
                if (strlen($password) < 8) {
                    $message = 'Password must be at least 8 characters.';
                } else {
                    $sql .= ', password = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
            }
            if ($message === '') {
                $params[] = $id;
                $db->query($sql . ' WHERE id = ?', $params);
                $message = 'User saved.';
            }
        }
    } else {
        if (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters.';
        } else {
            $db->query('INSERT INTO admin (username, password, email, name, role, active) VALUES (?, ?, ?, ?, ?, ?)', [$username, password_hash($password, PASSWORD_DEFAULT), $email, $name, $role, $active]);
            $message = 'User created.';
        }
    }
}
$users = $db->fetchAll('SELECT id, username, name, email, role, active FROM admin ORDER BY id');
$edit = isset($_GET['edit']) ? $db->fetch('SELECT * FROM admin WHERE id = ?', [(int)$_GET['edit']]) : null;
$page_title = 'Admin users';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <table class="table"><thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars((string)$user['name']) ?></td>
            <td><?= htmlspecialchars((string)$user['email']) ?></td>
            <td><?= htmlspecialchars((string)$user['role']) ?></td>
            <td><?= (int)$user['active'] ? 'Yes' : 'No' ?></td>
            <td><a href="?edit=<?= (int)$user['id'] ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?')"><input type="hidden" name="delete_id" value="<?= (int)$user['id'] ?>"><button class="btn btn-sm btn-link" type="submit">Delete</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
    <form method="post" class="card card-body">
        <h2><?= $edit ? 'Edit user' : 'New user' ?></h2>
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <input class="form-control mb-2" name="username" placeholder="Username" value="<?= htmlspecialchars($edit['username'] ?? '') ?>" required>
        <input class="form-control mb-2" name="name" placeholder="Name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
        <input class="form-control mb-2" name="email" placeholder="Email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
        <select class="form-select mb-2" name="role">
            <?php foreach (['super_admin', 'admin', 'manager'] as $role): ?>
                <option value="<?= $role ?>" <?= (($edit['role'] ?? '') === $role) ? 'selected' : '' ?>><?= $role ?></option>
            <?php endforeach; ?>
        </select>
        <label><input type="checkbox" name="active" value="1" <?= !isset($edit['active']) || (int)$edit['active'] === 1 ? 'checked' : '' ?>> Active</label>
        <input class="form-control mb-2" type="password" name="password" placeholder="<?= $edit ? 'Leave blank to keep the current password' : 'Password' ?>">
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php';