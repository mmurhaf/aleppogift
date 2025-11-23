<?php
require_once('config/config.php');
require_once('includes/Database.php');

$db = new Database();

echo "<h2>Admin User Check</h2>";

try {
    // Check if admin table exists and has users
    $result = $db->query("SELECT * FROM admin");
    $admins = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Admin users found: " . count($admins) . "<br><br>";
    
    if (count($admins) == 0) {
        echo "No admin users found. Creating default admin user...<br>";
        
        // Create a default admin user
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        
        $db->query("INSERT INTO admin (username, password, created_at) VALUES (:username, :password, NOW())", [
            'username' => $username,
            'password' => $password
        ]);
        
        echo "✅ Default admin user created:<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Existing admin users:<br>";
        foreach ($admins as $admin) {
            echo "- ID: " . $admin['id'] . ", Username: " . $admin['username'] . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>



