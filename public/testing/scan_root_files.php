<?php
/**
 * Root Test Files Scanner
 * Scans the root directory and shows which test files exist
 * Helps identify which files should be added to the proxy whitelist
 */

// Get root path
$rootPath = dirname(dirname(__DIR__));

// Patterns to search for
$patterns = [
    'test_*.php',
    'test_*.html',
    'debug_*.php',
    'diagnostic_*.php',
    'check_*.php',
    '*_test.php',
    '*_test.html',
];

// Scan for files
$foundFiles = [];
foreach ($patterns as $pattern) {
    $files = glob($rootPath . DIRECTORY_SEPARATOR . $pattern);
    foreach ($files as $file) {
        $basename = basename($file);
        if (!in_array($basename, $foundFiles)) {
            $foundFiles[] = $basename;
        }
    }
}

// Sort files
sort($foundFiles);

// Separate by type
$phpFiles = array_filter($foundFiles, function($f) { return substr($f, -4) === '.php'; });
$htmlFiles = array_filter($foundFiles, function($f) { return substr($f, -5) === '.html'; });

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Root Files Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #3f51b5 0%, #2196f3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        
        .stat {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3f51b5;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #3f51b5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .file-item {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            border-left: 4px solid #3f51b5;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .file-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .file-item i {
            color: #3f51b5;
        }
        
        .copy-btn {
            background: #3f51b5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: #303f9f;
        }
        
        .copy-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .copy-section h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .code-box {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            margin-top: 10px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            color: #1976d2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .nav-buttons {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .nav-btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            background: #3f51b5;
            color: white;
            text-decoration: none;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .nav-btn:hover {
            background: #303f9f;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-search"></i> Root Files Scanner</h1>
            <p>Scanning for test files in root directory</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> This scanner searches the root directory for test files using common patterns (test_*.php, debug_*.php, etc.). 
                Use this list to update the whitelist in <code>root_proxy.php</code> if needed.
            </div>
            
            <div class="stats">
                <div class="stat">
                    <div class="stat-number"><?php echo count($foundFiles); ?></div>
                    <div class="stat-label">Total Files Found</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo count($phpFiles); ?></div>
                    <div class="stat-label">PHP Files</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo count($htmlFiles); ?></div>
                    <div class="stat-label">HTML Files</div>
                </div>
            </div>
            
            <?php if (count($phpFiles) > 0): ?>
            <div class="section">
                <h2><i class="fas fa-file-code"></i> PHP Test Files (<?php echo count($phpFiles); ?>)</h2>
                <div class="file-list">
                    <?php foreach ($phpFiles as $file): ?>
                        <div class="file-item">
                            <i class="fas fa-code"></i>
                            <?php echo htmlspecialchars($file); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($htmlFiles) > 0): ?>
            <div class="section">
                <h2><i class="fas fa-file-alt"></i> HTML Test Files (<?php echo count($htmlFiles); ?>)</h2>
                <div class="file-list">
                    <?php foreach ($htmlFiles as $file): ?>
                        <div class="file-item">
                            <i class="fas fa-file-code"></i>
                            <?php echo htmlspecialchars($file); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="copy-section">
                <h3><i class="fas fa-copy"></i> Whitelist Array for root_proxy.php</h3>
                <p>Copy this array and paste it into <code>root_proxy.php</code> to update the whitelist:</p>
                <button class="copy-btn" onclick="copyToClipboard()">
                    <i class="fas fa-clipboard"></i> Copy to Clipboard
                </button>
                <div class="code-box" id="codeBox">
$allowedFiles = [
<?php 
foreach ($foundFiles as $file): 
    echo "    '" . $file . "',\n";
endforeach;
?>
];
                </div>
            </div>
            
            <div class="nav-buttons">
                <a href="root_proxy.php" class="nav-btn">
                    <i class="fas fa-folder-open"></i> Root Files Browser
                </a>
                <a href="index.php" class="nav-btn">
                    <i class="fas fa-flask"></i> Testing Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard() {
            const codeBox = document.getElementById('codeBox');
            const text = codeBox.textContent;
            
            navigator.clipboard.writeText(text).then(function() {
                const btn = document.querySelector('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.style.background = '#4caf50';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '#3f51b5';
                }, 2000);
            }, function(err) {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>
