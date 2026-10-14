<?php
/**
 * Add Testimonial Handler
 */

session_start();
require_once(__DIR__ . '/../../includes/bootstrap.php');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $customer_name = sanitize($_POST['customer_name'], 'string');
    $customer_location = sanitize($_POST['customer_location'] ?? '', 'string');
    $rating = (int)$_POST['rating'];
    $testimonial_text = sanitize($_POST['testimonial_text'], 'string');
    $from_whatsapp = isset($_POST['from_whatsapp']) ? 1 : 0;
    
    $stmt = $conn->prepare("
        INSERT INTO testimonials 
        (customer_name, customer_location, testimonial_text, rating, whatsapp_conversation, is_approved, is_featured)
        VALUES (?, ?, ?, ?, ?, 1, 0)
    ");
    
    $stmt->bind_param("sssii", $customer_name, $customer_location, $testimonial_text, $rating, $from_whatsapp);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Testimonial added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding testimonial: " . $stmt->error;
    }
    
    $stmt->close();
}

header('Location: manage_trust.php');
exit;
?>
