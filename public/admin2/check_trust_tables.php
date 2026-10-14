<?php
/**
 * Check Trust System Tables
 * Verify if trust system tables exist in the database
 */

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

$db = new Database();
$conn = $db->getPdo();

$required_tables = [
    'product_reviews',
    'testimonials',
    'trust_badges',
    'customer_photos',
    'success_stories',
    'social_proof_stats',
    'review_helpful_votes'
];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Trust System Table Check</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; }
        .table-check { margin: 20px 0; }
        .exists { color: green; font-weight: bold; }
        .missing { color: red; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0; }
        .warning { background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0; }
        .success { background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #2196f3; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
        .btn:hover { background: #1976d2; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Trust System Database Check</h1>";

$missing_tables = [];
$existing_tables = [];

echo "<table>
<tr>
    <th>Table Name</th>
    <th>Status</th>
    <th>Row Count</th>
</tr>";

foreach ($required_tables as $table) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            $count_stmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<tr>
                <td><strong>$table</strong></td>
                <td><span class='exists'>✓ EXISTS</span></td>
                <td>$count rows</td>
            </tr>";
            $existing_tables[] = $table;
        } else {
            echo "<tr>
                <td><strong>$table</strong></td>
                <td><span class='missing'>✗ MISSING</span></td>
                <td>-</td>
            </tr>";
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        echo "<tr>
            <td><strong>$table</strong></td>
            <td><span class='missing'>✗ ERROR</span></td>
            <td>Error: " . $e->getMessage() . "</td>
        </tr>";
        $missing_tables[] = $table;
    }
}

echo "</table>";

if (empty($missing_tables)) {
    echo "<div class='success'>
        <strong>✓ All trust system tables are installed!</strong><br>
        You can now use the manage_trust.php page.
    </div>";
    echo "<a href='manage_trust.php' class='btn'>Go to Trust Management</a>";
} else {
    echo "<div class='warning'>
        <strong>⚠ Missing Tables Detected</strong><br>
        The following tables are missing: " . implode(', ', $missing_tables) . "
    </div>";
    
    echo "<div class='info'>
        <strong>How to Fix:</strong><br>
        1. Open phpMyAdmin or your database management tool<br>
        2. Select your database: <strong>" . DB_NAME . "</strong><br>
        3. Run the SQL file: <strong>database/setup_trust_system.sql</strong><br>
        <br>
        Or you can run it from command line:<br>
        <code>mysql -u " . DB_USER . " -p " . DB_NAME . " < setup_trust_system.sql</code>
    </div>";
}

echo "<h2>Database Information</h2>";
echo "<table>
<tr><th>Setting</th><th>Value</th></tr>
<tr><td>Database Name</td><td>" . DB_NAME . "</td></tr>
<tr><td>Database Host</td><td>" . DB_HOST . "</td></tr>
<tr><td>Database User</td><td>" . DB_USER . "</td></tr>
</table>";

echo "<div style='margin-top: 30px;'>
    <a href='dashboard.php' class='btn'>← Back to Dashboard</a>
    <a href='check_trust_tables.php' class='btn' onclick='location.reload()'>🔄 Refresh</a>
</div>";

echo "</div>
</body>
</html>";
?>
