<?php
// Use absolute paths to avoid relative path issues
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

echo "<h1>Admin Password Reset</h1>";

try {
    $db = new Database();
    if ($db->conn) {
        echo "<p style='color:green;'>Database connection successful.</p>";
    } else {
        echo "<p style='color:red;'>Database connection failed.</p>";
        exit;
    }

    $username = 'admin';
    // This is the new hash generated for the password 'admin123' from the debug script
    $new_hash = '$2y$10$.hB59uveOThKU3g95uIPPe3YUY.c./RTSSQ.RXW0J2U0PzgHrOdAi';

    $sql = "UPDATE admin SET password = :password WHERE username = :username";
    $stmt = $db->query($sql, ['password' => $new_hash, 'username' => $username]);

    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>Admin password has been successfully reset for username '<strong>$username</strong>'.</p>";
        echo "<p>You should now be able to log in with the password '<strong>admin123</strong>'.</p>";
        echo '<p><a href="login.php">Go to Login Page</a></p>';
    } else {
        echo "<p style='color:red;'>Failed to update the admin password. The user '<strong>$username</strong>' might not exist or the password is already set to this hash.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>An error occurred: " . $e->getMessage() . "</p>";
}

echo "<p><strong>Important:</strong> For security reasons, please delete this file (`reset_admin_password.php`) and `debug_admin_login.php` from your server after you have successfully logged in.</p>";
?>
