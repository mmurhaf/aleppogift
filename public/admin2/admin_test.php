<?php
// Test admin login and session functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/session_helper.php');

echo "<h2>Admin System Test</h2>";

try {
    // Test database connection
    $db = new Database();
    echo "✅ Database connection successful<br>";
    
    // Check if admin table exists
    $result = $db->query("SHOW TABLES LIKE 'admin'")->fetchAll();
    if (empty($result)) {
        echo "❌ Admin table does not exist<br>";
        
        // Create admin table
        $createTable = "
        CREATE TABLE IF NOT EXISTS admin (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->query($createTable);
        echo "✅ Admin table created<br>";
        
        // Create default admin user
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $db->query("INSERT INTO admin (username, password, email) VALUES (:username, :password, :email)", [
            'username' => 'admin',
            'password' => $defaultPassword,
            'email' => 'admin@aleppogift.com'
        ]);
        echo "✅ Default admin user created (username: admin, password: admin123)<br>";
        
    } else {
        echo "✅ Admin table exists<br>";
        
        // Check if there are any admin users
        $adminCount = $db->query("SELECT COUNT(*) as count FROM admin")->fetch();
        echo "📊 Admin users count: " . $adminCount['count'] . "<br>";
        
        if ($adminCount['count'] == 0) {
            // Create default admin user
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $db->query("INSERT INTO admin (username, password, email) VALUES (:username, :password, :email)", [
                'username' => 'admin',
                'password' => $defaultPassword,
                'email' => 'admin@aleppogift.com'
            ]);
            echo "✅ Default admin user created (username: admin, password: admin123)<br>";
        }
    }
    
    // Test session functionality
    start_session_safely();
    echo "✅ Session started successfully<br>";
    echo "Session ID: " . session_id() . "<br>";
    
    // Check current session state
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        echo "✅ Admin is currently logged in<br>";
        echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'Not set') . "<br>";
    } else {
        echo "ℹ️ Admin is not logged in<br>";
        echo '<a href="login.php">Go to Login Page</a><br>';
    }
    
    echo "<br><h3>Session Data:</h3>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>