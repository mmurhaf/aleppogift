<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Admin Login Status Check</h2>";

// Check session
session_start();
echo "<h3>Session Status:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Session Data: <pre>" . print_r($_SESSION, true) . "</pre>";

// Check if admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo "<div style='color: green; font-weight: bold;'>✅ Admin is logged in</div>";
    echo "<a href='regenerate_invoice.php?id=94'>Try regenerate_invoice.php?id=94</a><br>";
} else {
    echo "<div style='color: red; font-weight: bold;'>❌ Admin is NOT logged in</div>";
    echo "<a href='login.php'>Go to Admin Login</a><br>";
}

echo "<hr>";
echo "<h3>Try Manual Login (for testing):</h3>";
if (isset($_POST['test_login'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'test_admin';
    echo "<div style='color: green;'>✅ Test login set! <a href='regenerate_invoice.php?id=94'>Try regenerate_invoice.php now</a></div>";
}
?>
<form method="post">
    <button type="submit" name="test_login" style="background: orange; color: white; padding: 10px;">Set Test Admin Login</button>
</form>