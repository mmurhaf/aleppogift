<?php
/**
 * Database Setup Script
 * Run this file once to create the database structure
 * Access: http://localhost/dubgift/aleppogift/setup_database.php
 */

require_once('includes/bootstrap.php');

// Check if database exists and create if not
try {
    $pdo_check = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo_check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo_check->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<h2>✅ Database '" . DB_NAME . "' created/verified successfully!</h2>";
    
    // Now connect to the specific database
    $db = new Database();
    
    // Read and execute the SQL structure file
    $sql_file = __DIR__ . '/database/aleppogift_structure.sql';
    
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        
        // Split SQL into individual statements
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^(--|\#)/', $statement)) {
                try {
                    $db->conn->exec($statement);
                } catch (PDOException $e) {
                    // Skip errors for existing tables
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "<p style='color: orange;'>Warning: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
        
        echo "<h3>✅ Database structure created successfully!</h3>";
        
        // Test data insertion
        echo "<h3>Sample Data:</h3>";
        $products = $db->query("SELECT COUNT(*) as count FROM products")->fetch();
        echo "<p>Products: " . $products['count'] . "</p>";
        
        $categories = $db->query("SELECT COUNT(*) as count FROM categories")->fetch();
        echo "<p>Categories: " . $categories['count'] . "</p>";
        
        echo "<h3>🎉 Database setup completed!</h3>";
        echo "<p><a href='public/'>Visit your website</a> | <a href='public/admin/'>Admin Panel</a></p>";
        echo "<p><strong>Default Admin Login:</strong><br>Username: admin<br>Password: password</p>";
        echo "<p style='color: red;'><strong>Important:</strong> Please change the default admin password!</p>";
        
    } else {
        echo "<h3 style='color: red;'>❌ Error: SQL structure file not found!</h3>";
        echo "<p>Expected file: " . $sql_file . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Database Setup Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<h4>Common Solutions:</h4>";
    echo "<ul>";
    echo "<li>Make sure XAMPP MySQL service is running</li>";
    echo "<li>Check database credentials in config/config.php</li>";
    echo "<li>Ensure MySQL user has CREATE DATABASE privileges</li>";
    echo "</ul>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AleppoGift Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2, h3 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>AleppoGift Database Setup</h1>
    <hr>
</body>
</html>
