<?php
// Create admin user script
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

$db = new Database();

echo "<h2>Create Admin User</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($username) || empty($password)) {
        echo "❌ Username and password are required<br>";
    } elseif ($password !== $confirm_password) {
        echo "❌ Passwords do not match<br>";
    } elseif (strlen($password) < 6) {
        echo "❌ Password must be at least 6 characters long<br>";
    } else {
        try {
            // Check if username already exists
            $sql_check = "SELECT id FROM admin WHERE username = :username";
            $stmt_check = $db->query($sql_check, ['username' => $username]);
            
            if ($stmt_check->fetch()) {
                echo "❌ Username already exists<br>";
            } else {
                // Create new admin user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_insert = "INSERT INTO admin (username, password, created_at) VALUES (:username, :password, NOW())";
                $result = $db->query($sql_insert, [
                    'username' => $username,
                    'password' => $hashed_password
                ]);
                
                if ($result) {
                    echo "✅ Admin user created successfully!<br>";
                    echo "Username: " . htmlspecialchars($username) . "<br>";
                    echo "You can now <a href='login.php'>login here</a>";
                } else {
                    echo "❌ Failed to create admin user<br>";
                }
            }
        } catch (Exception $e) {
            echo "❌ Database error: " . $e->getMessage() . "<br>";
        }
    }
}

// Check if admin table exists and create if needed
try {
    $sql_check_table = "SHOW TABLES LIKE 'admin'";
    $stmt = $db->query($sql_check_table);
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<h3>Creating admin table...</h3>";
        $sql_create = "
        CREATE TABLE admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $db->query($sql_create);
        echo "✅ Admin table created successfully<br>";
    }
} catch (Exception $e) {
    echo "❌ Error with admin table: " . $e->getMessage() . "<br>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { 
            width: 300px; 
            padding: 8px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
        }
        button { 
            background-color: #007bff; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<form method="POST">
    <div class="form-group">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
    </div>
    
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    
    <div class="form-group">
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    
    <button type="submit" name="create_admin">Create Admin User</button>
</form>

<p><a href="check_admin_users.php">Check existing admin users</a></p>
<p><a href="debug_session.php">Debug session status</a></p>
<p><a href="login.php">Go to login</a></p>

</body>
</html>