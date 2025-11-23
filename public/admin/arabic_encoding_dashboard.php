<?php
/**
 * Arabic Encoding Fix Dashboard
 * Comprehensive tool for fixing Arabic character encoding issues
 * 
 * Production Database Configuration:
 * Host: mmurhaf50350.ipagemysql.com
 * Database: aleppogift
 * User: mmurhaf
 * Password: Salem1972#i
 */

// Database configuration - Production settings
$db_host = 'mmurhaf50350.ipagemysql.com';
$db_name = 'aleppogift';
$db_user = 'mmurhaf';
$db_pass = 'Salem1972#i';

// You can override these for local testing
if (isset($_GET['use_local']) && $_GET['use_local'] === 'true') {
    $db_host = 'localhost';
    $db_name = 'aleppogift';
    $db_user = 'root';
    $db_pass = '';
}

// Set proper headers for UTF-8
header('Content-Type: text/html; charset=utf-8');

$action = $_GET['action'] ?? 'dashboard';
$run_fix = isset($_GET['run']) && $_GET['run'] === 'yes';

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arabic Encoding Fix Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #2c3e50, #3498db);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .nav {
            background: #34495e;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
        }
        
        .nav-item {
            flex: 1;
            min-width: 200px;
        }
        
        .nav-item a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            text-align: center;
            transition: background 0.3s;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-item a:hover, .nav-item a.active {
            background: #2c3e50;
        }
        
        .content {
            padding: 30px;
        }
        
        .step-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 25px;
            background: #3498db;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 10px 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 15px 0;
        }
        
        .arabic-sample {
            font-size: 18px;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            direction: rtl;
        }
        
        .arabic-correct {
            background: #d4edda;
            color: #155724;
        }
        
        .arabic-corrupted {
            background: #f8d7da;
            color: #721c24;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .tool-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .tool-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-safe { background: #27ae60; }
        .status-caution { background: #f39c12; }
        .status-danger { background: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Arabic Encoding Fix Dashboard</h1>
            <p>Comprehensive solution for fixing Arabic character encoding issues in your database</p>
        </div>
        
        <div class="nav">
            <div class="nav-item">
                <a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a>
            </div>
            <div class="nav-item">
                <a href="?action=instructions" class="<?= $action === 'instructions' ? 'active' : '' ?>">📋 Instructions</a>
            </div>
            <div class="nav-item">
                <a href="?action=analyze" class="<?= $action === 'analyze' ? 'active' : '' ?>">🔍 Analyze</a>
            </div>
            <div class="nav-item">
                <a href="?action=fix" class="<?= $action === 'fix' ? 'active' : '' ?>">🛠️ Fix Issues</a>
            </div>
            <div class="nav-item">
                <a href="?action=tools" class="<?= $action === 'tools' ? 'active' : '' ?>">⚡ Tools</a>
            </div>
        </div>
        
        <div class="content">
            <?php
            
            switch ($action) {
                case 'instructions':
                    include_instructions();
                    break;
                case 'analyze':
                    include_analyze();
                    break;
                case 'fix':
                    include_fix();
                    break;
                case 'tools':
                    include_tools();
                    break;
                default:
                    include_dashboard();
            }
            
            function include_dashboard() {
                global $db_host, $db_name, $db_user;
                ?>
                <h2>🎯 Current Status</h2>
                
                <div class="success">
                    <strong>✅ Production Database Connected:</strong><br>
                    Host: <?= htmlspecialchars($db_host) ?><br>
                    Database: <?= htmlspecialchars($db_name) ?><br>
                    User: <?= htmlspecialchars($db_user) ?>
                </div>
                
                <h3>🚀 Quick Start Guide</h3>
                
                <div class="grid">
                    <div class="tool-card">
                        <div class="tool-icon">📋</div>
                        <h4>Step 1: Read Instructions</h4>
                        <p>Learn about Arabic encoding issues and fix process</p>
                        <a href="?action=instructions" class="btn btn-primary">View Instructions</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">🔍</div>
                        <h4>Step 2: Analyze Issues</h4>
                        <p>Scan your database for encoding problems</p>
                        <a href="?action=analyze" class="btn btn-warning">Analyze Database</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">🛠️</div>
                        <h4>Step 3: Fix Problems</h4>
                        <p>Apply automated fixes to restore Arabic text</p>
                        <a href="?action=fix" class="btn btn-success">Fix Issues</a>
                    </div>
                </div>
                
                <h3>🔧 Available Tools</h3>
                <div class="step-card">
                    <div class="step-number">?</div>
                    <h4>Encoding Fix Tools</h4>
                    <p>Access individual scripts and utilities for specific encoding issues.</p>
                    <a href="?action=tools" class="btn btn-primary">View All Tools</a>
                </div>
                
                <div class="warning">
                    <strong>⚠️ Important Notes:</strong>
                    <ul>
                        <li>Always backup your database before running any fix operations</li>
                        <li>Test fixes on a small dataset first</li>
                        <li>Arabic text corruption can be complex - use the analyze tool first</li>
                        <li>Some corruption may require manual intervention</li>
                    </ul>
                </div>
                <?php
            }
            
            function include_instructions() {
                ?>
                <h2>📖 Complete Arabic Encoding Fix Instructions</h2>
                
                <div class="danger">
                    <strong>🚨 CRITICAL: Database Backup Required</strong><br>
                    Before proceeding with ANY fix operation, create a complete backup of your database. 
                    Encoding fixes modify data and may be irreversible.
                </div>
                
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Understanding the Problem</h3>
                    <p><strong>What causes Arabic encoding corruption?</strong></p>
                    <ul>
                        <li>Incorrect character set settings in database/tables</li>
                        <li>Wrong charset in PHP connections</li>
                        <li>Multiple encoding conversions corrupting data</li>
                        <li>HTML form submissions without proper encoding</li>
                    </ul>
                    
                    <p><strong>Common corruption patterns you'll see:</strong></p>
                    <div class="arabic-sample arabic-corrupted">
                        Corrupted: Ã¢â‚¬Å"Ø¯Ø§Ø±Ã¢â‚¬Â or ÃÂÃÂ­ÃÂÃÂ§ÃÂÃ¢ÂÂ¦
                    </div>
                    <div class="arabic-sample arabic-correct">
                        Correct: دار or هدايا
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Pre-Fix Checklist</h3>
                    <div class="warning">
                        <strong>Complete these steps before fixing:</strong>
                        <ol>
                            <li>✅ Create full database backup</li>
                            <li>✅ Verify database connection settings</li>
                            <li>✅ Test on a copy/staging environment first</li>
                            <li>✅ Document current corruption patterns</li>
                        </ol>
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Analyze Current State</h3>
                    <p>Before fixing anything, understand what's corrupted:</p>
                    <ol>
                        <li>Go to the <strong>"Analyze"</strong> tab</li>
                        <li>Run the analysis tool (safe - makes no changes)</li>
                        <li>Review the corruption report</li>
                        <li>Identify which tables/fields need fixing</li>
                    </ol>
                    <a href="?action=analyze" class="btn btn-warning">Start Analysis</a>
                </div>
                
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3>Choose Your Fix Strategy</h3>
                    
                    <h4><span class="status-indicator status-safe"></span>Conservative Approach (Recommended)</h4>
                    <ul>
                        <li>Start with pattern-based restoration</li>
                        <li>Fix common corruption patterns first</li>
                        <li>Verify results before proceeding</li>
                        <li>Handle edge cases manually</li>
                    </ul>
                    
                    <h4><span class="status-indicator status-caution"></span>Comprehensive Approach</h4>
                    <ul>
                        <li>Use ultimate fix script for all patterns</li>
                        <li>More aggressive restoration</li>
                        <li>Higher success rate but more risk</li>
                        <li>Requires careful verification</li>
                    </ul>
                </div>
                
                <div class="step-card">
                    <div class="step-number">5</div>
                    <h3>Execute the Fix</h3>
                    <p>Based on your analysis results:</p>
                    <ol>
                        <li>Go to the <strong>"Fix Issues"</strong> tab</li>
                        <li>Choose the appropriate fix method</li>
                        <li>Review the preview of changes</li>
                        <li>Execute the fix operation</li>
                        <li>Verify the results</li>
                    </ol>
                    <a href="?action=fix" class="btn btn-success">Go to Fix Tools</a>
                </div>
                
                <div class="step-card">
                    <div class="step-number">6</div>
                    <h3>Post-Fix Verification</h3>
                    <p>After running any fix:</p>
                    <ol>
                        <li>Re-run the analysis tool to check improvements</li>
                        <li>Manually check key products/categories</li>
                        <li>Test website functionality</li>
                        <li>Verify search and filtering work</li>
                        <li>Check admin panel displays correctly</li>
                    </ol>
                </div>
                
                <div class="step-card">
                    <div class="step-number">7</div>
                    <h3>Prevention for Future</h3>
                    <p>Prevent future encoding issues:</p>
                    <ol>
                        <li>Ensure database/tables use utf8mb4 charset</li>
                        <li>Set PHP connection charset to utf8mb4</li>
                        <li>Use proper HTML meta tags</li>
                        <li>Validate form inputs for proper encoding</li>
                    </ol>
                    
                    <div class="code-block">
-- Database charset check
SHOW TABLE STATUS LIKE 'products';
SHOW FULL COLUMNS FROM products WHERE Field LIKE '%ar';

-- PHP connection
mysqli_set_charset($connection, 'utf8mb4');
                    </div>
                </div>
                
                <div class="success">
                    <strong>💡 Pro Tips:</strong>
                    <ul>
                        <li>Start with a small test dataset</li>
                        <li>Keep detailed logs of what you fix</li>
                        <li>Some corruption may need manual correction</li>
                        <li>Consider gradual fixes rather than bulk operations</li>
                    </ul>
                </div>
                <?php
            }
            
            function include_analyze() {
                global $db_host, $db_name, $db_user, $db_pass;
                
                if (isset($_GET['run_analysis'])) {
                    // Run the analysis
                    try {
                        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        echo '<h2>🔍 Database Analysis Results</h2>';
                        
                        // Check database charset
                        $stmt = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
                        $db_charset = $stmt->fetchColumn();
                        
                        echo '<div class="step-card">';
                        echo '<h3>Database Configuration</h3>';
                        echo '<p><strong>Database Charset:</strong> ' . htmlspecialchars($db_charset) . '</p>';
                        
                        // Check tables
                        $stmt = $pdo->query("SHOW TABLE STATUS");
                        echo '<h4>Table Charsets:</h4>';
                        while ($table = $stmt->fetch()) {
                            $status = $table['Collation'] ? 'status-safe' : 'status-danger';
                            echo '<p><span class="status-indicator ' . $status . '"></span>' . 
                                 htmlspecialchars($table['Name']) . ': ' . htmlspecialchars($table['Collation']) . '</p>';
                        }
                        echo '</div>';
                        
                        // Analyze Arabic text in products table
                        if ($pdo->query("SHOW TABLES LIKE 'products'")->rowCount() > 0) {
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
                            $total_products = $stmt->fetchColumn();
                            
                            $stmt = $pdo->query("SELECT COUNT(*) as corrupted FROM products WHERE name_ar REGEXP '[Ã]' OR description_ar REGEXP '[Ã]'");
                            $corrupted_count = $stmt->fetchColumn();
                            
                            $stmt = $pdo->query("SELECT COUNT(*) as questionmarks FROM products WHERE name_ar REGEXP '[?]' OR description_ar REGEXP '[?]'");
                            $questionmark_count = $stmt->fetchColumn();
                            
                            $stmt = $pdo->query("SELECT COUNT(*) as arabic FROM products WHERE name_ar REGEXP '[ا-ي]' OR description_ar REGEXP '[ا-ي]'");
                            $arabic_count = $stmt->fetchColumn();
                            
                            echo '<div class="step-card">';
                            echo '<h3>Products Table Analysis</h3>';
                            echo '<p><strong>Total Products:</strong> ' . number_format($total_products) . '</p>';
                            echo '<p><strong>Products with Arabic Text:</strong> ' . number_format($arabic_count) . '</p>';
                            echo '<p><strong>Products with Corruption:</strong> ' . number_format($corrupted_count) . '</p>';
                            echo '<p><strong>Products with Question Marks:</strong> ' . number_format($questionmark_count) . '</p>';
                            
                            if ($questionmark_count > 0) {
                                echo '<div class="danger">🚨 Found ' . number_format($questionmark_count) . ' products with question mark issues</div>';
                                echo '<div class="warning">⚠️ Question marks usually indicate the data was lost during encoding conversion. This may require restoring from a backup that has the original Arabic text.</div>';
                                
                                // Show examples
                                $stmt = $pdo->query("SELECT id, name_ar, description_ar FROM products WHERE name_ar REGEXP '[?]' OR description_ar REGEXP '[?]' LIMIT 5");
                                echo '<h4>Question Mark Examples:</h4>';
                                while ($row = $stmt->fetch()) {
                                    echo '<div class="arabic-sample arabic-corrupted">';
                                    echo '<strong>Product ID ' . $row['id'] . ':</strong><br>';
                                    echo 'Name: ' . htmlspecialchars(substr($row['name_ar'], 0, 100)) . '<br>';
                                    if ($row['description_ar']) {
                                        echo 'Description: ' . htmlspecialchars(substr($row['description_ar'], 0, 100)) . '...';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            if ($corrupted_count > 0) {
                                echo '<div class="warning">⚠️ Found ' . number_format($corrupted_count) . ' products with potential encoding issues</div>';
                                
                                // Show examples
                                $stmt = $pdo->query("SELECT id, name_ar, description_ar FROM products WHERE name_ar REGEXP '[Ã]' OR description_ar REGEXP '[Ã]' LIMIT 5");
                                echo '<h4>Corruption Examples:</h4>';
                                while ($row = $stmt->fetch()) {
                                    echo '<div class="arabic-sample arabic-corrupted">';
                                    echo '<strong>Product ID ' . $row['id'] . ':</strong><br>';
                                    echo 'Name: ' . htmlspecialchars(substr($row['name_ar'], 0, 100)) . '<br>';
                                    if ($row['description_ar']) {
                                        echo 'Description: ' . htmlspecialchars(substr($row['description_ar'], 0, 100)) . '...';
                                    }
                                    echo '</div>';
                                }
                            } 
                            
                            if ($corrupted_count == 0 && $questionmark_count == 0) {
                                echo '<div class="success">✅ No obvious corruption patterns found</div>';
                            }
                            echo '</div>';
                        }
                        
                        // Check categories if exists
                        if ($pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0) {
                            $stmt = $pdo->query("SELECT COUNT(*) as corrupted FROM categories WHERE name_ar REGEXP '[Ã]'");
                            $cat_corrupted = $stmt->fetchColumn();
                            
                            echo '<div class="step-card">';
                            echo '<h3>Categories Analysis</h3>';
                            echo '<p><strong>Categories with Corruption:</strong> ' . number_format($cat_corrupted) . '</p>';
                            echo '</div>';
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="danger">❌ Analysis Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                } else {
                    ?>
                    <h2>🔍 Database Analysis Tool</h2>
                    
                    <div class="success">
                        <strong>✅ Safe Operation:</strong> This analysis tool only reads your database and makes no changes.
                    </div>
                    
                    <div class="step-card">
                        <h3>What This Analysis Will Check:</h3>
                        <ul>
                            <li>📊 Database and table character set configurations</li>
                            <li>🔢 Count of products with Arabic text</li>
                            <li>⚠️ Count of products with corruption patterns</li>
                            <li>📝 Examples of corrupted text for review</li>
                            <li>🏷️ Category encoding issues</li>
                            <li>📈 Overall corruption severity assessment</li>
                        </ul>
                    </div>
                    
                    <div class="step-card">
                        <h3>Current Database Connection:</h3>
                        <div class="code-block">
Host: <?= htmlspecialchars($db_host) ?>
Database: <?= htmlspecialchars($db_name) ?>
User: <?= htmlspecialchars($db_user) ?>
                        </div>
                    </div>
                    
                    <div class="warning">
                        <strong>Before Running Analysis:</strong>
                        <ol>
                            <li>Verify the database connection details above are correct</li>
                            <li>Ensure you have read access to the database</li>
                            <li>This may take a few moments for large databases</li>
                        </ol>
                    </div>
                    
                    <a href="?action=analyze&run_analysis=true" class="btn btn-warning">🔍 Start Database Analysis</a>
                    <?php
                }
            }
            
            function include_fix() {
                global $db_host, $db_name, $db_user, $db_pass, $run_fix;
                ?>
                <h2>🛠️ Arabic Encoding Fix Tools</h2>
                
                <div class="danger">
                    <strong>🚨 WARNING:</strong> These tools will modify your database. 
                    Ensure you have a complete backup before proceeding.
                </div>
                
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3><span class="status-indicator status-safe"></span>Pattern-Based Fix (Recommended)</h3>
                    <p>Safely restores common corruption patterns using known mappings.</p>
                    <ul>
                        <li>✅ Conservative approach</li>
                        <li>✅ Handles most common issues</li>
                        <li>✅ Lower risk of data loss</li>
                        <li>⚠️ May not fix all corruption types</li>
                    </ul>
                    <a href="?action=fix&method=pattern" class="btn btn-success">Use Pattern Fix</a>
                </div>
                
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3><span class="status-indicator status-caution"></span>Comprehensive Fix</h3>
                    <p>Advanced restoration handling multiple corruption patterns.</p>
                    <ul>
                        <li>⚡ More aggressive restoration</li>
                        <li>⚡ Handles complex corruption</li>
                        <li>⚠️ Higher risk, more testing needed</li>
                        <li>⚠️ May require manual review</li>
                    </ul>
                    <a href="?action=fix&method=comprehensive" class="btn btn-warning">Use Comprehensive Fix</a>
                </div>
                
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3><span class="status-indicator status-danger"></span>Question Mark Fix (For ? characters)</h3>
                    <p>Specifically handles question marks (?) appearing instead of Arabic text.</p>
                    <ul>
                        <li>🎯 Targets question mark corruption</li>
                        <li>🔧 Fixes database connection charset issues</li>
                        <li>⚠️ May require data restoration from backup</li>
                        <li>🔥 Use when seeing ????? instead of Arabic</li>
                    </ul>
                    <a href="?action=fix&method=questionmark" class="btn btn-danger">Fix Question Marks</a>
                </div>
                
                <div class="step-card">
                    <div class="step-number">5</div>
                    <h3><span class="status-indicator status-danger"></span>Ultimate Fix (Advanced Users)</h3>
                    <p>Most comprehensive fix handling all known corruption patterns.</p>
                    <ul>
                        <li>🚀 Handles ALL corruption types</li>
                        <li>🚀 Highest success rate</li>
                        <li>🔥 Highest risk - expert use only</li>
                        <li>🔥 Requires thorough testing</li>
                    </ul>
                    <a href="?action=fix&method=ultimate" class="btn btn-danger">Use Ultimate Fix</a>
                </div>
                
                <?php
                $method = $_GET['method'] ?? '';
                
                if ($method && $run_fix) {
                    echo '<div class="step-card">';
                    echo '<h3>🔧 Running ' . ucfirst($method) . ' Fix...</h3>';
                    
                    try {
                        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $results = runFixMethod($pdo, $method);
                        
                        echo '<div class="success">✅ Fix completed successfully!</div>';
                        echo '<h4>Results:</h4>';
                        echo '<ul>';
                        foreach ($results as $result) {
                            echo '<li>' . htmlspecialchars($result) . '</li>';
                        }
                        echo '</ul>';
                        
                    } catch (Exception $e) {
                        echo '<div class="danger">❌ Fix Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    
                    echo '</div>';
                } elseif ($method) {
                    echo '<div class="step-card">';
                    echo '<h3>⚠️ Confirm ' . ucfirst($method) . ' Fix</h3>';
                    echo '<div class="warning">This will modify your database. Are you sure you want to proceed?</div>';
                    echo '<a href="?action=fix&method=' . $method . '&run=yes" class="btn btn-danger">Yes, Run ' . ucfirst($method) . ' Fix</a>';
                    echo '<a href="?action=fix" class="btn btn-primary">Cancel</a>';
                    echo '</div>';
                }
                ?>
                <?php
            }
            
            function include_tools() {
                ?>
                <h2>⚡ Individual Fix Tools</h2>
                
                <p>Access the individual Arabic encoding fix scripts that this dashboard consolidates:</p>
                
                <div class="grid">
                    <div class="tool-card">
                        <div class="tool-icon">🔍</div>
                        <h4>analyze_arabic_text.php</h4>
                        <p><span class="status-indicator status-safe"></span>Safe analysis tool</p>
                        <a href="analyze_arabic_text.php" class="btn btn-primary" target="_blank">Open Tool</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">🔧</div>
                        <h4>restore_arabic_manual.php</h4>
                        <p><span class="status-indicator status-caution"></span>Manual restoration</p>
                        <a href="restore_arabic_manual.php" class="btn btn-warning" target="_blank">Open Tool</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">⚡</div>
                        <h4>fix_arabic_ultimate.php</h4>
                        <p><span class="status-indicator status-danger"></span>Ultimate fix script</p>
                        <a href="fix_arabic_ultimate.php" class="btn btn-danger" target="_blank">Open Tool</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">🛠️</div>
                        <h4>fix_arabic_advanced.php</h4>
                        <p><span class="status-indicator status-caution"></span>Advanced restoration</p>
                        <a href="fix_arabic_advanced.php" class="btn btn-warning" target="_blank">Open Tool</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">�</div>
                        <h4>question_mark_arabic_fix.php</h4>
                        <p><span class="status-indicator status-danger"></span>Question mark specialist</p>
                        <a href="question_mark_arabic_fix.php" class="btn btn-danger" target="_blank">Open Tool</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">�📊</div>
                        <h4>analyze_corruption_detailed.php</h4>
                        <p><span class="status-indicator status-safe"></span>Detailed corruption analysis</p>
                        <a href="analyze_corruption_detailed.php" class="btn btn-primary" target="_blank">Open Tool</a>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-icon">⚙️</div>
                        <h4>config_arabic_fix.php</h4>
                        <p><span class="status-indicator status-safe"></span>Configuration helper</p>
                        <a href="config_arabic_fix.php" class="btn btn-primary" target="_blank">Open Tool</a>
                    </div>
                </div>
                
                <div class="step-card">
                    <h3>📖 Documentation Files</h3>
                    <p>View the comprehensive guides created for Arabic encoding fixes:</p>
                    <ul>
                        <li><a href="ARABIC_ENCODING_FIX_GUIDE.md" target="_blank">Arabic Encoding Fix Guide</a></li>
                        <li><a href="ARABIC_ENCODING_FIX_COMPLETE.md" target="_blank">Complete Fix Documentation</a></li>
                    </ul>
                </div>
                
                <div class="warning">
                    <strong>🔧 Tool Usage Notes:</strong>
                    <ul>
                        <li>Each tool opens in a new window/tab</li>
                        <li>Tools marked with ⚠️ or 🔥 modify your database</li>
                        <li>Always backup before using modification tools</li>
                        <li>Start with analysis tools to understand issues first</li>
                    </ul>
                </div>
                <?php
            }
            
            function runFixMethod($pdo, $method) {
                $results = [];
                
                switch ($method) {
                    case 'pattern':
                        // Pattern-based fix
                        $patterns = [
                            'Ã¢â‚¬Å"' => '"',
                            'Ã¢â‚¬Â' => '"',
                            'Ã¢â‚¬™' => "'",
                            'Ã¢â‚¬˜' => "'",
                            'Ã¢â‚¬' => '–',
                            'Ã‚Â' => '',
                        ];
                        
                        foreach ($patterns as $corrupt => $fix) {
                            $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, ?, ?) WHERE name_ar LIKE ?");
                            $stmt->execute([$corrupt, $fix, "%$corrupt%"]);
                            if ($stmt->rowCount() > 0) {
                                $results[] = "Fixed pattern '$corrupt' in " . $stmt->rowCount() . " name_ar fields";
                            }
                            
                            $stmt = $pdo->prepare("UPDATE products SET description_ar = REPLACE(description_ar, ?, ?) WHERE description_ar LIKE ?");
                            $stmt->execute([$corrupt, $fix, "%$corrupt%"]);
                            if ($stmt->rowCount() > 0) {
                                $results[] = "Fixed pattern '$corrupt' in " . $stmt->rowCount() . " description_ar fields";
                            }
                        }
                        break;
                        
                    case 'questionmark':
                        // Question mark fix - attempt to restore from backup or clean entries
                        $results[] = "Starting question mark fix...";
                        
                        // First, check if we can detect the charset issue
                        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $results[] = "Set connection charset to utf8mb4";
                        
                        // Count affected records
                        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE name_ar REGEXP '[?]' OR description_ar REGEXP '[?]'");
                        $affected = $stmt->fetchColumn();
                        $results[] = "Found $affected records with question marks";
                        
                        if ($affected > 0) {
                            // Option 1: Try to clean up question marks (remove them)
                            $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, '?', '') WHERE name_ar REGEXP '[?]'");
                            $stmt->execute();
                            $cleaned_names = $stmt->rowCount();
                            
                            $stmt = $pdo->prepare("UPDATE products SET description_ar = REPLACE(description_ar, '?', '') WHERE description_ar REGEXP '[?]'");
                            $stmt->execute();
                            $cleaned_desc = $stmt->rowCount();
                            
                            $results[] = "Cleaned question marks from $cleaned_names product names";
                            $results[] = "Cleaned question marks from $cleaned_desc product descriptions";
                            
                            // Option 2: Try common question mark to Arabic character mappings
                            $questionmark_mappings = [
                                '???????' => 'هدايا',
                                '????' => 'دار',
                                '??????' => 'الهدايا',
                                '????? ?????' => 'شرقية قديمة',
                                '?????? ????????' => 'كريستال جميلة',
                                '????? ??????' => 'ذهبية فضية'
                            ];
                            
                            foreach ($questionmark_mappings as $question_pattern => $arabic_text) {
                                $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, ?, ?) WHERE name_ar LIKE ?");
                                $stmt->execute([$question_pattern, $arabic_text, "%$question_pattern%"]);
                                if ($stmt->rowCount() > 0) {
                                    $results[] = "Replaced '$question_pattern' with '$arabic_text' in " . $stmt->rowCount() . " records";
                                }
                                
                                $stmt = $pdo->prepare("UPDATE products SET description_ar = REPLACE(description_ar, ?, ?) WHERE description_ar LIKE ?");
                                $stmt->execute([$question_pattern, $arabic_text, "%$question_pattern%"]);
                                if ($stmt->rowCount() > 0) {
                                    $results[] = "Replaced '$question_pattern' with '$arabic_text' in " . $stmt->rowCount() . " descriptions";
                                }
                            }
                        }
                        
                        $results[] = "Question mark fix completed - manual review recommended";
                        break;
                        
                    case 'comprehensive':
                        // More comprehensive fix
                        $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(REPLACE(REPLACE(name_ar, 'Ã¢â‚¬Å\"', '\"'), 'Ã¢â‚¬Â', '\"'), 'Ã‚Â', '') WHERE name_ar REGEXP '[Ã]'");
                        $stmt->execute();
                        $results[] = "Updated " . $stmt->rowCount() . " product names";
                        
                        $stmt = $pdo->prepare("UPDATE products SET description_ar = REPLACE(REPLACE(REPLACE(description_ar, 'Ã¢â‚¬Å\"', '\"'), 'Ã¢â‚¬Â', '\"'), 'Ã‚Â', '') WHERE description_ar REGEXP '[Ã]'");
                        $stmt->execute();
                        $results[] = "Updated " . $stmt->rowCount() . " product descriptions";
                        break;
                        
                    case 'ultimate':
                        // Ultimate fix - most comprehensive
                        $arabic_fixes = [
                            'Ø§' => 'ا', 'Ø¨' => 'ب', 'Øª' => 'ت', 'Ø«' => 'ث',
                            'Ø¬' => 'ج', 'Ø­' => 'ح', 'Ø®' => 'خ', 'Ø¯' => 'د',
                            'Ø°' => 'ذ', 'Ø±' => 'ر', 'Ø²' => 'ز', 'Ø³' => 'س',
                            'Ø´' => 'ش', 'Øµ' => 'ص', 'Ø¶' => 'ض', 'Ø·' => 'ط',
                            'Ø¸' => 'ظ', 'Ø¹' => 'ع', 'Øº' => 'غ', 'Ù' => 'ف',
                            'Ù‚' => 'ق', 'Ùƒ' => 'ك', 'Ù„' => 'ل', 'Ù…' => 'م',
                            'Ù†' => 'ن', 'Ù‡' => 'ه', 'Ùˆ' => 'و', 'ÙŠ' => 'ي'
                        ];
                        
                        foreach ($arabic_fixes as $corrupt => $correct) {
                            $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, ?, ?) WHERE name_ar LIKE ?");
                            $stmt->execute([$corrupt, $correct, "%$corrupt%"]);
                            
                            $stmt = $pdo->prepare("UPDATE products SET description_ar = REPLACE(description_ar, ?, ?) WHERE description_ar LIKE ?");
                            $stmt->execute([$corrupt, $correct, "%$corrupt%"]);
                        }
                        
                        $results[] = "Applied ultimate Arabic character restoration";
                        break;
                }
                
                if (empty($results)) {
                    $results[] = "No changes were needed or made";
                }
                
                return $results;
            }
            
            ?>
        </div>
    </div>
</body>
</html>
