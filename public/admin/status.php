<?php
// Testing Dashboard Status Check
$startTime = microtime(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing Dashboard - Status Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #ff7f00; text-align: center; }
        .status-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
            background: #f9f9f9;
        }
        .status-ok { border-left-color: #4CAF50; }
        .status-warning { border-left-color: #ff9800; background: #fff3cd; }
        .status-error { border-left-color: #f44336; background: #f8d7da; }
        .nav-links {
            text-align: center;
            margin-top: 30px;
        }
        .nav-links a {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background: #ff7f00;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover {
            background: #e56b00;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Testing Dashboard - Status Check</h1>
        
        <div class="status-item status-ok">
            <strong>✅ Testing Dashboard:</strong> Operational and accessible
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ Test Files Location:</strong> <?php echo __DIR__; ?>
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ File Count:</strong> <?php 
            $files = glob('*.php');
            echo count($files) . ' PHP test files found';
            ?>
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ PHP Version:</strong> <?php echo PHP_VERSION; ?>
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?>
        </div>
        
        <div class="status-item status-ok">
            <strong>✅ Load Time:</strong> <?php 
            $endTime = microtime(true);
            echo round(($endTime - $startTime) * 1000, 2) . ' ms';
            ?>
        </div>

        <h3>Available Test Categories:</h3>
        <ul>
            <li>🗄️ Database Tests (7 files)</li>
            <li>📧 Email Tests (10 files)</li>
            <li>🛒 Cart & Checkout Tests (10 files)</li>
            <li>💳 Payment Tests (4 files)</li>
            <li>🚚 Shipping Tests (6 files)</li>
            <li>🔧 System Diagnostics (13 files)</li>
            <li>⚡ AJAX Tests (2 files)</li>
        </ul>

        <div class="nav-links">
            <a href="index.php">📊 Full Testing Dashboard</a>
            <a href="../admin/dashboard.php">🛡️ Admin Dashboard</a>
            <a href="../index.php">🏠 Home</a>
        </div>
        
        <p style="text-align: center; color: #666; margin-top: 30px;">
            <small>AleppoGift Testing Dashboard - Production Ready</small>
        </p>
    </div>
</body>
</html>
