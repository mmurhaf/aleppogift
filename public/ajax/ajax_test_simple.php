<?php
// Simple AJAX test without any includes
header('Content-Type: application/json');

// Start output buffering to catch any unwanted output
ob_start();

try {
    // Test simple response
    $response = [
        'success' => true,
        'message' => 'AJAX endpoint is working',
        'timestamp' => date('Y-m-d H:i:s'),
        'test' => 'simple'
    ];
    
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_clean();
exit;
?>