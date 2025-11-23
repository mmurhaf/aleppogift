<?php
// Check admin users in database
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

$db = new Database();

echo "<h2>Admin Users Check</h2>";

try {
    $sql = "SELECT id, username, created_at FROM admin ORDER BY id";
    $stmt = $db->query($sql);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo "❌ No admin users found in database<br>";
        echo "<p>You need to create an admin user first.</p>";
        
        // Check if admin table exists
        $sql_check = "SHOW TABLES LIKE 'admin'";
        $stmt_check = $db->query($sql_check);
        $table_exists = $stmt_check->fetch();
        
        if (!$table_exists) {
            echo "❌ Admin table does not exist<br>";
            echo "<p>You need to create the admin table and insert an admin user.</p>";
        } else {
            echo "✅ Admin table exists but is empty<br>";
        }
    } else {
        echo "✅ Found " . count($admins) . " admin user(s):<br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Created At</th></tr>";
        foreach ($admins as $admin) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($admin['id']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Check table structure
try {
    $sql_desc = "DESCRIBE admin";
    $stmt_desc = $db->query($sql_desc);
    $columns = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Admin Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error checking table structure: " . $e->getMessage() . "<br>";
}
?>