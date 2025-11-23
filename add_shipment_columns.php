<?php
// File: add_shipment_columns.php
// Script to safely add shipment tracking columns to orders table

require_once(__DIR__ . '/config/config.php');
require_once(__DIR__ . '/includes/Database.php');

try {
    $db = new Database();
    
    echo "Starting database update...\n";
    
    // Get current table structure
    $columns = $db->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    $existing_columns = array_column($columns, 'Field');
    
    echo "Existing columns: " . implode(', ', $existing_columns) . "\n";
    
    // Define new columns to add
    $new_columns = [
        'tracking_number' => "VARCHAR(255) DEFAULT NULL COMMENT 'Package tracking number'",
        'carrier_name' => "VARCHAR(100) DEFAULT NULL COMMENT 'Shipping carrier (DHL, FedEx, etc.)'",
        'shipping_method' => "VARCHAR(100) DEFAULT NULL COMMENT 'Shipping method (Express, Standard, etc.)'",
        'shipment_status' => "ENUM('pending', 'processing', 'shipped', 'delivered') DEFAULT 'pending' COMMENT 'Shipment status'",
        'shipped_date' => "DATETIME DEFAULT NULL COMMENT 'Date when order was shipped'",
        'note' => "TEXT DEFAULT NULL COMMENT 'Customer visible notes'",
        'remarks' => "TEXT DEFAULT NULL COMMENT 'Internal admin remarks'"
    ];
    
    $added_count = 0;
    
    foreach ($new_columns as $column_name => $definition) {
        if (!in_array($column_name, $existing_columns)) {
            try {
                $sql = "ALTER TABLE orders ADD COLUMN $column_name $definition";
                $db->query($sql);
                echo "✓ Added column: $column_name\n";
                $added_count++;
            } catch (Exception $e) {
                echo "✗ Failed to add column $column_name: " . $e->getMessage() . "\n";
            }
        } else {
            echo "- Column $column_name already exists\n";
        }
    }
    
    if ($added_count > 0) {
        echo "\n✓ Successfully added $added_count new columns to orders table.\n";
    } else {
        echo "\n- All columns already exist, no changes needed.\n";
    }
    
    echo "Database update completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>