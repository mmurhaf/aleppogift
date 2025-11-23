<?php
// File: admin/generate_invoice_debug.php - Debug version without auth

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Generate Invoice Debug (No Auth)</h2>";

$root_dir = dirname(dirname(__DIR__));
echo "Root dir: $root_dir<br>";

try {
    require_once($root_dir . '/includes/generate_invoice.php');
    echo "✅ generate_invoice.php included<br>";

    if (!isset($_GET['id'])) {
        echo "No ID provided. <a href='?id=94'>Test with ID 94</a>";
        exit;
    }

    $order_id = intval($_GET['id']);
    echo "Order ID: $order_id<br>";
    
    $generator = new InvoiceGenerator();
    echo "✅ InvoiceGenerator created<br>";

    echo "<hr>";
    echo $generator->generateInvoice($order_id);
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "<h4>Stack trace:</h4>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>