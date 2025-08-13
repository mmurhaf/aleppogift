<?php
// Add missing coupon columns to orders table if they don't exist
header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/../../includes/bootstrap.php');
    
    $db = new Database();
    
    // Get current orders table structure
    $structure_query = "DESCRIBE orders";
    $structure = $db->query($structure_query)->fetchAll(PDO::FETCH_ASSOC);
    $existing_columns = array_column($structure, 'Field');
    
    // Define required coupon columns
    $required_columns = [
        'coupon_code' => 'varchar(50) DEFAULT NULL',
        'discount_type' => "enum('fixed','percent') DEFAULT NULL", 
        'discount_value' => 'decimal(10,2) DEFAULT NULL',
        'discount_amount' => 'decimal(10,2) DEFAULT NULL'
    ];
    
    $added_columns = [];
    $errors = [];
    
    // Add missing columns
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $alter_query = "ALTER TABLE orders ADD COLUMN $column $definition";
                $db->query($alter_query);
                $added_columns[] = $column;
            } catch (Exception $e) {
                $errors[] = [
                    'column' => $column,
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    // Get updated structure
    $updated_structure = $db->query($structure_query)->fetchAll(PDO::FETCH_ASSOC);
    $updated_columns = array_column($updated_structure, 'Field');
    
    echo json_encode([
        'success' => true,
        'original_columns' => $existing_columns,
        'required_columns' => array_keys($required_columns),
        'added_columns' => $added_columns,
        'errors' => $errors,
        'updated_columns' => $updated_columns,
        'all_coupon_columns_present' => empty(array_diff(array_keys($required_columns), $updated_columns))
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
