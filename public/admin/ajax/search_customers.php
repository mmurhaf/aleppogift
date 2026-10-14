<?php
// File: admin/ajax/search_customers.php
// Search customers for admin order creation

error_reporting(E_ALL);
ini_set('display_errors', 0);

$root_dir = dirname(dirname(dirname(__DIR__)));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

start_session_safely();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $query = isset($input['query']) ? trim($input['query']) : '';

    if (empty($query) || strlen($query) < 2) {
        echo json_encode(['success' => false, 'error' => 'Query too short']);
        exit;
    }

    $db = new Database();

    // Search customers by name, email, or phone
    $searchTerm = '%' . $query . '%';
    
    $customers = $db->query("
        SELECT 
            id,
            fullname,
            email,
            phone,
            address,
            city,
            country
        FROM customers
        WHERE fullname LIKE :search_name 
           OR email LIKE :search_email 
           OR phone LIKE :search_phone
        ORDER BY fullname ASC
        LIMIT 15
    ", [
        'search_name' => $searchTerm,
        'search_email' => $searchTerm,
        'search_phone' => $searchTerm,
    ])->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'customers' => $customers
    ]);

} catch (Exception $e) {
    error_log("Customer search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
}
?>
