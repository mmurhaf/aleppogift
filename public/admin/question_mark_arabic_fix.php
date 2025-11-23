<?php
/**
 * Question Mark Arabic Fix Script
 * Specifically designed to handle question marks (?) appearing instead of Arabic text
 * 
 * Production Database Configuration:
 * Host: mmurhaf50350.ipagemysql.com
 * Database: aleppogift
 * User: mmurhaf
 * Password: Salem1972#i
 */

// Database configuration
$db_host = 'mmurhaf50350.ipagemysql.com';
$db_name = 'aleppogift';
$db_user = 'mmurhaf';
$db_pass = 'Salem1972#i';

// Set proper headers for UTF-8
header('Content-Type: text/html; charset=utf-8');

$action = $_GET['action'] ?? 'analyze';
$run_fix = isset($_GET['run']) && $_GET['run'] === 'yes';

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Mark Arabic Fix</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .content {
            padding: 30px;
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
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .step-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .question-sample {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 16px;
            margin: 10px 0;
            border-left: 4px solid #e74c3c;
        }
        
        .arabic-sample {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            font-size: 18px;
            margin: 10px 0;
            direction: rtl;
            border-left: 4px solid #27ae60;
        }
        
        .nav {
            background: #34495e;
            padding: 0;
            display: flex;
        }
        
        .nav-item {
            flex: 1;
        }
        
        .nav-item a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            text-align: center;
            transition: background 0.3s;
        }
        
        .nav-item a:hover, .nav-item a.active {
            background: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Question Mark Arabic Fix</h1>
            <p>Specialized tool for fixing question marks (?) in Arabic text</p>
        </div>
        
        <div class="nav">
            <div class="nav-item">
                <a href="?action=analyze" class="<?= $action === 'analyze' ? 'active' : '' ?>">🔍 Analyze</a>
            </div>
            <div class="nav-item">
                <a href="?action=understand" class="<?= $action === 'understand' ? 'active' : '' ?>">📖 Understand</a>
            </div>
            <div class="nav-item">
                <a href="?action=fix" class="<?= $action === 'fix' ? 'active' : '' ?>">🛠️ Fix</a>
            </div>
        </div>
        
        <div class="content">
            <?php
            
            switch ($action) {
                case 'understand':
                    include_understand();
                    break;
                case 'fix':
                    include_fix();
                    break;
                default:
                    include_analyze();
            }
            
            function include_analyze() {
                global $db_host, $db_name, $db_user, $db_pass;
                ?>
                <h2>🔍 Question Mark Analysis</h2>
                
                <div class="step-card">
                    <h3>What You're Seeing:</h3>
                    <div class="question-sample">
                        Swarovski big serving tray 16 inch<br>
                        ????? ????????? ???? 16 ????
                    </div>
                    <p>Question marks appearing instead of Arabic characters indicate a serious encoding issue.</p>
                </div>
                
                <?php
                if (isset($_GET['run_analysis'])) {
                    try {
                        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        echo '<div class="step-card">';
                        echo '<h3>📊 Analysis Results</h3>';
                        
                        // Count total products
                        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
                        $total = $stmt->fetchColumn();
                        echo '<p><strong>Total Products:</strong> ' . number_format($total) . '</p>';
                        
                        // Count products with question marks
                        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE name_ar LIKE '%?%' OR description_ar LIKE '%?%'");
                        $question_count = $stmt->fetchColumn();
                        echo '<p><strong>Products with Question Marks:</strong> ' . number_format($question_count) . '</p>';
                        
                        // Count products with any Arabic text
                        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE name_ar REGEXP '[ا-ي]' OR description_ar REGEXP '[ا-ي]'");
                        $arabic_count = $stmt->fetchColumn();
                        echo '<p><strong>Products with Valid Arabic:</strong> ' . number_format($arabic_count) . '</p>';
                        
                        if ($question_count > 0) {
                            echo '<div class="danger">🚨 Found ' . number_format($question_count) . ' products with question mark corruption</div>';
                            
                            // Show examples
                            $stmt = $pdo->query("SELECT id, name_ar, description_ar FROM products WHERE name_ar LIKE '%?%' OR description_ar LIKE '%?%' LIMIT 5");
                            echo '<h4>Examples of Affected Products:</h4>';
                            while ($row = $stmt->fetch()) {
                                echo '<div class="question-sample">';
                                echo '<strong>Product ID ' . $row['id'] . ':</strong><br>';
                                echo 'Name: ' . htmlspecialchars($row['name_ar']) . '<br>';
                                if ($row['description_ar'] && strpos($row['description_ar'], '?') !== false) {
                                    echo 'Description: ' . htmlspecialchars(substr($row['description_ar'], 0, 100)) . '...';
                                }
                                echo '</div>';
                            }
                            
                            // Check database charset
                            $stmt = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
                            $db_charset = $stmt->fetchColumn();
                            echo '<p><strong>Database Charset:</strong> ' . htmlspecialchars($db_charset) . '</p>';
                            
                            // Check products table charset
                            $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'products'");
                            $table_info = $stmt->fetch();
                            echo '<p><strong>Products Table Collation:</strong> ' . htmlspecialchars($table_info['Collation']) . '</p>';
                            
                        } else {
                            echo '<div class="success">✅ No question mark corruption found</div>';
                        }
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="danger">❌ Analysis Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                } else {
                    ?>
                    <div class="step-card">
                        <h3>🔍 Run Question Mark Analysis</h3>
                        <p>This will scan your database for question mark corruption without making any changes.</p>
                        <a href="?action=analyze&run_analysis=true" class="btn btn-primary">Start Analysis</a>
                    </div>
                    <?php
                }
            }
            
            function include_understand() {
                ?>
                <h2>📖 Understanding Question Mark Corruption</h2>
                
                <div class="step-card">
                    <h3>🚨 What Causes Question Marks?</h3>
                    <p>Question marks in place of Arabic text usually indicate:</p>
                    <ul>
                        <li><strong>Data Loss:</strong> Original Arabic characters were lost during encoding conversion</li>
                        <li><strong>Charset Mismatch:</strong> Database connection charset doesn't match stored data</li>
                        <li><strong>Invalid UTF-8:</strong> Data stored in wrong encoding and converted incorrectly</li>
                        <li><strong>Display Issues:</strong> Browser/server not handling UTF-8 properly</li>
                    </ul>
                </div>
                
                <div class="step-card">
                    <h3>📊 Severity Assessment</h3>
                    <div class="danger">
                        <strong>High Severity Issue</strong><br>
                        Question marks typically mean the original Arabic data is LOST and cannot be automatically recovered.
                    </div>
                    
                    <h4>Recovery Options (in order of preference):</h4>
                    <ol>
                        <li><strong>Restore from Backup:</strong> Best option if you have a backup with correct Arabic text</li>
                        <li><strong>Manual Data Entry:</strong> Re-enter Arabic text for affected products</li>
                        <li><strong>Clean Removal:</strong> Remove question marks and leave English text only</li>
                        <li><strong>Pattern Guessing:</strong> Attempt to guess common Arabic words (unreliable)</li>
                    </ol>
                </div>
                
                <div class="step-card">
                    <h3>🔧 What This Fix Tool Can Do</h3>
                    <div class="warning">
                        <strong>Limited Recovery Capabilities:</strong>
                        <ul>
                            <li>✅ Clean up question marks to make text readable</li>
                            <li>✅ Fix database charset settings</li>
                            <li>✅ Attempt common word pattern matching</li>
                            <li>❌ Cannot restore lost Arabic characters completely</li>
                            <li>❌ Cannot guess specific product names accurately</li>
                        </ul>
                    </div>
                </div>
                
                <div class="step-card">
                    <h3>📝 Recommended Action Plan</h3>
                    <ol>
                        <li><strong>Check for Backups:</strong> Look for database backups with correct Arabic text</li>
                        <li><strong>Run Analysis:</strong> Understand the scope of the problem</li>
                        <li><strong>Clean Question Marks:</strong> Remove ? characters to improve readability</li>
                        <li><strong>Manual Review:</strong> Identify most important products for manual fixing</li>
                        <li><strong>Prevent Future Issues:</strong> Fix charset settings to prevent recurrence</li>
                    </ol>
                </div>
                
                <div class="step-card">
                    <h3>🛡️ Prevention for Future</h3>
                    <div class="success">
                        <strong>Database Settings:</strong>
                        <div style="background: #2c3e50; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace;">
ALTER DATABASE aleppogift CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;<br>
ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                        </div>
                        
                        <strong>PHP Connection:</strong>
                        <div style="background: #2c3e50; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace;">
mysqli_set_charset($connection, 'utf8mb4');<br>
// Or in PDO:<br>
new PDO("mysql:host=host;dbname=db;charset=utf8mb4", $user, $pass);
                        </div>
                    </div>
                </div>
                <?php
            }
            
            function include_fix() {
                global $db_host, $db_name, $db_user, $db_pass, $run_fix;
                ?>
                <h2>🛠️ Question Mark Fix Operations</h2>
                
                <div class="danger">
                    <strong>🚨 CRITICAL WARNING:</strong><br>
                    These operations will modify your database. Create a backup first!<br>
                    Question mark fixes have limited success - the original data may be permanently lost.
                </div>
                
                <div class="step-card">
                    <h3>🔧 Available Fix Methods</h3>
                    
                    <h4>1. Clean Question Marks (Recommended)</h4>
                    <p>Removes question marks to make text readable, keeps English text intact.</p>
                    <a href="?action=fix&method=clean&run=yes" class="btn btn-success">Clean Question Marks</a>
                    
                    <h4>2. Pattern-Based Recovery (Limited Success)</h4>
                    <p>Attempts to replace common question mark patterns with likely Arabic words.</p>
                    <a href="?action=fix&method=pattern&run=yes" class="btn btn-primary">Try Pattern Recovery</a>
                    
                    <h4>3. Database Charset Fix</h4>
                    <p>Updates database and table charsets to prevent future issues.</p>
                    <a href="?action=fix&method=charset&run=yes" class="btn btn-primary">Fix Charset Settings</a>
                </div>
                
                <?php
                $method = $_GET['method'] ?? '';
                
                if ($method && $run_fix) {
                    echo '<div class="step-card">';
                    echo '<h3>🔧 Running ' . ucfirst($method) . ' Fix...</h3>';
                    
                    try {
                        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $results = runQuestionMarkFix($pdo, $method);
                        
                        echo '<div class="success">✅ Fix completed!</div>';
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
                }
                ?>
                <?php
            }
            
            function runQuestionMarkFix($pdo, $method) {
                $results = [];
                
                switch ($method) {
                    case 'clean':
                        // Clean question marks
                        $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, '?', '') WHERE name_ar LIKE '%?%'");
                        $stmt->execute();
                        $cleaned_names = $stmt->rowCount();
                        
                        $stmt = $pdo->prepare("UPDATE products SET description_ar = REPLACE(description_ar, '?', '') WHERE description_ar LIKE '%?%'");
                        $stmt->execute();
                        $cleaned_desc = $stmt->rowCount();
                        
                        $results[] = "Cleaned question marks from $cleaned_names product names";
                        $results[] = "Cleaned question marks from $cleaned_desc product descriptions";
                        break;
                        
                    case 'pattern':
                        // Attempt pattern-based recovery
                        $patterns = [
                            // Common Arabic words that might appear as question marks
                            '???' => 'دار',
                            '????' => 'هدايا', 
                            '?????' => 'الهدايا',
                            '??? ' => 'ذهب',
                            '????? ' => 'فضة',
                            '?????? ' => 'كريستال',
                            '???????' => 'سواروفسكي'
                        ];
                        
                        foreach ($patterns as $question_pattern => $arabic_word) {
                            $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, ?, ?) WHERE name_ar LIKE ?");
                            $stmt->execute([$question_pattern, $arabic_word, "%$question_pattern%"]);
                            if ($stmt->rowCount() > 0) {
                                $results[] = "Replaced pattern '$question_pattern' with '$arabic_word' in " . $stmt->rowCount() . " names";
                            }
                        }
                        
                        // Clean remaining question marks
                        $stmt = $pdo->prepare("UPDATE products SET name_ar = REPLACE(name_ar, '?', '') WHERE name_ar LIKE '%?%'");
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $results[] = "Cleaned remaining question marks from " . $stmt->rowCount() . " names";
                        }
                        break;
                        
                    case 'charset':
                        // Fix database and table charsets
                        try {
                            $pdo->exec("ALTER DATABASE `aleppogift` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $results[] = "Updated database charset to utf8mb4";
                        } catch (Exception $e) {
                            $results[] = "Database charset update failed: " . $e->getMessage();
                        }
                        
                        try {
                            $pdo->exec("ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $results[] = "Updated products table charset to utf8mb4";
                        } catch (Exception $e) {
                            $results[] = "Products table charset update failed: " . $e->getMessage();
                        }
                        
                        try {
                            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $results[] = "Set connection charset to utf8mb4";
                        } catch (Exception $e) {
                            $results[] = "Connection charset update failed: " . $e->getMessage();
                        }
                        break;
                }
                
                return $results;
            }
            
            ?>
        </div>
    </div>
</body>
</html>




