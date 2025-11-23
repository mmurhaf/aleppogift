<?php
// Check current orders table structure
header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/../../includes/bootstrap.php');
    
    $db = new Database();
    
    // Get current orders table structure
    $structure_query = "DESCRIBE orders";
    $structure = $db->query($structure_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the actual column names
    $columns = array_column($structure, 'Field');
    
    echo json_encode([
        'success' => true,
        'table_structure' => $structure,
        'column_names' => $columns,
        'has_created_at' => in_array('created_at', $columns),
        'has_order_date' => in_array('order_date', $columns),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
