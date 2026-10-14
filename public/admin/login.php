<?php
// Use absolute paths to avoid relative path issues
$root_dir = dirname(dirname(__DIR__));
// Load configuration and session helper first
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

// Start the session using the safe helper function
start_session_safely();

$db = new Database();

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM admin WHERE username = :username";
    $stmt = $db->query($sql, ['username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password']) && (int)($admin['active'] ?? 1) === 1) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'] ?? 'super_admin';
        $_SESSION['admin_last_activity'] = time(); // Set initial activity time
        header("Location: dashboard.php");
        exit;
    } else {
        $message = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - AleppoGift</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        :root {
            --primary-color: #e67e22;
            --secondary-color: #d35400;
            --accent-color: #f39c12;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(230, 126, 34, 0.18), transparent 32%),
                radial-gradient(circle at bottom right, rgba(211, 84, 0, 0.14), transparent 28%),
                linear-gradient(135deg, #fff8f1 0%, #f6efe7 48%, #f3f4f6 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }
        
        .login-container {
            background: white;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(230, 126, 34, 0.16);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(230, 126, 34, 0.14);
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 26px 60px rgba(211, 84, 0, 0.22);
        }
        
        .login-header {
            background: var(--gradient-primary);
            color: white;
            padding: 28px;
            text-align: center;
        }
        
        .login-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-header h2 i {
            margin-right: 10px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .login-form {
            padding: 32px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 13px 15px 13px 42px;
            border: 1px solid #ddd7cf;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: #fffdfa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.14);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: #9a6b3f;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 13px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            box-shadow: 0 14px 28px rgba(230, 126, 34, 0.22);
        }
        
        .btn:hover {
            opacity: 0.96;
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(211, 84, 0, 0.26);
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 0;
            }
            
            .login-header {
                padding: 22px;
            }
            
            .login-form {
                padding: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-gifts"></i> AleppoGift</h2>
            <p>Admin Dashboard Login</p>
        </div>
        
        <div class="login-form">
            <?php if($message): ?>
                <div class="alert alert-danger">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                
                <div class="forgot-password">
                    <a href="mailto:sales@aleppogift.com?subject=Admin%20password%20reset">Forgot password?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>