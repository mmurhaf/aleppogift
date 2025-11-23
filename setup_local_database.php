<?php
/**
 * Local Database Setup Script
 * This script creates the local database for development
 */

echo "=== AleppoGift Local Database Setup ===\n\n";

// Connect to MySQL without database
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to MySQL server\n";
    
    // Create database
    $dbName = 'u933234997_aleppogift';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '$dbName' created/verified\n";
    
    // Select database
    $pdo->exec("USE `$dbName`");
    echo "✓ Database selected\n\n";
    
    echo "=== Setup Complete ===\n";
    echo "Your local database is ready to use.\n";
    echo "Database: $dbName\n";
    echo "Host: localhost\n";
    echo "User: root\n";
    echo "Password: (empty)\n\n";
    
    echo "Next Steps:\n";
    echo "1. Import your production database dump if you have one\n";
    echo "2. Or run your application to let it create the tables\n";
    echo "3. Access your site at: http://localhost/aleppogift/\n";
    
} catch(PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "1. XAMPP MySQL is running\n";
    echo "2. MySQL root user has no password (default XAMPP setup)\n";
    exit(1);
}
?>
