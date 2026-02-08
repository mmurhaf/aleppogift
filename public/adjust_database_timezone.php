<?php
/**
 * Adjust Database Timestamps to Dubai Timezone
 */

date_default_timezone_set('Asia/Dubai'); // Set default timezone to Dubai

// Include website configuration file
require_once __DIR__ . '/../config/constants.php'; // Adjusted path for public folder execution

// Database connection
$host = DB_HOST; // Assuming constants.php defines DB_HOST
$username = DB_USER; // Assuming constants.php defines DB_USER
$password = DB_PASS; // Assuming constants.php defines DB_PASS
$database = DB_NAME; // Assuming constants.php defines DB_NAME

// Prevent direct access
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    http_response_code(403);
    exit('Direct access to this file is not permitted.');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to the database successfully.\n";

    // Fetch all tables with datetime columns
    $tables = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE DATA_TYPE IN ('datetime', 'timestamp') AND TABLE_SCHEMA = '$database'")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tables as $table) {
        $tableName = $table['TABLE_NAME'];
        $columnName = $table['COLUMN_NAME'];

        echo "Adjusting timestamps for table: $tableName, column: $columnName\n";

        // Update the column to adjust time difference (+4 hours for Dubai)
        $updateQuery = "UPDATE `$tableName` SET `$columnName` = DATE_ADD(`$columnName`, INTERVAL 4 HOUR)";
        $pdo->exec($updateQuery);

        echo "Timestamps adjusted for $tableName.$columnName\n";
    }

    echo "All timestamps adjusted successfully.\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>