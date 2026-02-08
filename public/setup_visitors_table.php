<?php
/**
 * Setup Visitors Table for Analytics
 * Run this once to create or update the visitors table
 * 
 * @author AleppoGift Development Team
 * @date January 27, 2026
 */

// Use bootstrap for secure initialization
require_once(__DIR__ . '/../includes/bootstrap.php');

// Initialize database
$db = new Database();

echo "<h2>Visitors Table Setup</h2>";

try {
    // Check if table exists
    $result = $db->query("SHOW TABLES LIKE 'visitors'");
    $tableExists = $result->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p>✅ Visitors table already exists.</p>";
        
        // Check current structure
        $columns = $db->fetchAll("DESCRIBE visitors");
        echo "<p>Current columns:</p><ul>";
        foreach ($columns as $col) {
            echo "<li><strong>{$col['Field']}</strong>: {$col['Type']}</li>";
        }
        echo "</ul>";
        
        // Add missing columns if needed
        $existingCols = array_column($columns, 'Field');
        
        $requiredColumns = [
            'page_url' => "ALTER TABLE visitors ADD COLUMN page_url VARCHAR(500) DEFAULT NULL AFTER visit_time",
            'user_agent' => "ALTER TABLE visitors ADD COLUMN user_agent VARCHAR(500) DEFAULT NULL AFTER page_url",
            'referer' => "ALTER TABLE visitors ADD COLUMN referer VARCHAR(500) DEFAULT NULL AFTER user_agent",
            'country' => "ALTER TABLE visitors ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER referer",
            'country_code' => "ALTER TABLE visitors ADD COLUMN country_code VARCHAR(5) DEFAULT NULL AFTER country"
        ];
        
        foreach ($requiredColumns as $colName => $alterSql) {
            if (!in_array($colName, $existingCols)) {
                echo "<p>Adding missing column: <strong>$colName</strong>... ";
                try {
                    $db->query($alterSql);
                    echo "✅ Added</p>";
                } catch (Exception $e) {
                    echo "❌ Failed: " . $e->getMessage() . "</p>";
                }
            }
        }
        
        // Check and add indexes
        $indexes = $db->fetchAll("SHOW INDEX FROM visitors");
        $indexNames = array_unique(array_column($indexes, 'Key_name'));
        
        if (!in_array('idx_visit_time', $indexNames)) {
            echo "<p>Adding index on visit_time... ";
            try {
                $db->query("CREATE INDEX idx_visit_time ON visitors(visit_time)");
                echo "✅ Added</p>";
            } catch (Exception $e) {
                echo "❌ " . $e->getMessage() . "</p>";
            }
        }
        
        if (!in_array('idx_ip_address', $indexNames)) {
            echo "<p>Adding index on ip_address... ";
            try {
                $db->query("CREATE INDEX idx_ip_address ON visitors(ip_address)");
                echo "✅ Added</p>";
            } catch (Exception $e) {
                echo "❌ " . $e->getMessage() . "</p>";
            }
        }
        
    } else {
        // Create the table from scratch
        echo "<p>Creating visitors table... ";
        
        $sql = "CREATE TABLE visitors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            visit_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            page_url VARCHAR(500) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            referer VARCHAR(500) DEFAULT NULL,
            country VARCHAR(100) DEFAULT NULL,
            country_code VARCHAR(5) DEFAULT NULL,
            INDEX idx_visit_time (visit_time),
            INDEX idx_ip_address (ip_address),
            INDEX idx_ip_time (ip_address, visit_time),
            INDEX idx_country_code (country_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->query($sql);
        echo "✅ Created successfully!</p>";
    }
    
    // Show current record count
    $count = $db->fetch("SELECT COUNT(*) as total FROM visitors");
    echo "<p>📊 Current visitor records: <strong>" . number_format($count['total']) . "</strong></p>";
    
    // Test insert
    echo "<h3>Testing Visitor Tracking</h3>";
    echo "<p>Inserting test record... ";
    
    $testIp = '192.168.1.1';
    $db->query(
        "INSERT INTO visitors (ip_address, visit_time, page_url, user_agent, referer) VALUES (?, NOW(), ?, ?, ?)",
        [$testIp, '/setup_visitors_table.php', 'Test Browser', 'Direct']
    );
    echo "✅ Test record inserted</p>";
    
    // Cleanup test record
    $db->query("DELETE FROM visitors WHERE ip_address = ? AND page_url = ?", [$testIp, '/setup_visitors_table.php']);
    echo "<p>🧹 Test record cleaned up</p>";
    
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<p>Visitor tracking is now active. Visit the <a href='admin/analytics.php'>Analytics Dashboard</a> to view statistics.</p>";
    
    echo "<h3>What's Being Tracked?</h3>";
    echo "<ul>";
    echo "<li>✅ IP Address (for unique visitor counting)</li>";
    echo "<li>✅ Visit Time (timestamp of each visit)</li>";
    echo "<li>✅ Page URL (which pages are visited)</li>";
    echo "<li>✅ User Agent (browser/device info)</li>";
    echo "<li>✅ Referer (where visitors come from)</li>";
    echo "</ul>";
    
    echo "<h3>What's NOT Tracked?</h3>";
    echo "<ul>";
    echo "<li>❌ Admin pages</li>";
    echo "<li>❌ API/Ajax requests</li>";
    echo "<li>❌ Bot/crawler traffic</li>";
    echo "<li>❌ Static assets (CSS, JS, images)</li>";
    echo "<li>❌ Test/debug pages</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Visitors Table - AleppoGift</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h3 { color: #555; margin-top: 30px; }
        p { line-height: 1.6; }
        ul { background: #f5f5f5; padding: 20px 40px; border-radius: 8px; }
        li { margin: 8px 0; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
</body>
</html>
