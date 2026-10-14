<?php
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

$db = new Database();

$username = 'mmurhaf';
$password_plain = '57108855';
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

// Check if user exists
$sql_check = "SELECT * FROM admin WHERE username = :username";
$admin = $db->query($sql_check, ['username' => $username])->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    // Update existing user
    $sql_update = "UPDATE admin SET password = :password WHERE username = :username";
    $db->query($sql_update, [
        'password' => $password_hashed,
        'username' => $username
    ]);
    echo "✅ Admin user updated successfully.";
} else {
    // Insert new user
    $sql_insert = "INSERT INTO admin (username, password) VALUES (:username, :password)";
    $db->query($sql_insert, [
        'username' => $username,
        'password' => $password_hashed
    ]);
    echo "✅ Admin user created successfully.";
}
?>
