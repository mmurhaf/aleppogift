<?php
// File: public/admin/ajax/search_orders.php
// Search orders for payment link generation

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

try {
    $root_dir = dirname(dirname(dirname(__DIR__)));
    require_once($root_dir . '/includes/session_helper.php');
    require_once($root_dir . '/config/config.php');
    require_once($root_dir . '/includes/Database.php');

    // Start session and check admin login
    start_session_safely();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access - please login']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Validate query
    if (!isset($input['query']) || strlen(trim($input['query'])) < 2) {
        echo json_encode(['success' => false, 'message' => 'Search query too short']);
        exit;
    }

    $query = trim($input['query']);

    $db = new Database();
    
    // Check if query is numeric (could be order ID)
    $isNumeric = is_numeric($query);
    
    // Build search query
    if ($isNumeric) {
        // Search by order ID or phone
        $sql = "
            SELECT 
                o.id,
                o.total_amount,
                o.payment_status,
                o.order_date,
                c.fullname as customer_name,
                c.email,
                c.phone
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = :query
               OR c.phone LIKE :query_like
            ORDER BY o.id DESC
            LIMIT 20
        ";
        
        $params = [
            'query' => $query,
            'query_like' => '%' . $query . '%'
        ];
    } else {
        // Search by customer name or email
        $sql = "
            SELECT 
                o.id,
                o.total_amount,
                o.payment_status,
                o.order_date,
                c.fullname as customer_name,
                c.email,
                c.phone
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE c.fullname LIKE :query_name
               OR c.email LIKE :query_email
            ORDER BY o.id DESC
            LIMIT 20
        ";
        
        $params = [
            'query_name' => '%' . $query . '%',
            'query_email' => '%' . $query . '%',
        ];
    }
    
    $orders = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
} catch (Exception $e) {
    error_log("Order search error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Search error: ' . $e->getMessage()
    ]);
}
