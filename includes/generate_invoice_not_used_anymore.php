<?php
// File: public/generate_invoice.php
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = new Database();


// Check if order_id is set
if (!isset($order_id)) {
    throw new Exception("Order ID not provided");
}else {
    $order_id = intval($order_id); // Ensure order_id is an integer
}

try {
    // Fetch order data with validation
        $order = $db->query("
            SELECT 
                o.*, 
                c.fullname AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.address AS customer_address,
                c.city AS customer_city,
                c.country AS customer_country,
                DATE_FORMAT(o.order_date, '%d %b %Y') AS formatted_order_date
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = :id
        ", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);

        // ✅ Now define the variables
        $country = $order['customer_country'] ?? 'N/A';
        $city = $order['customer_city'] ?? 'N/A';

        // Get order details
        $totalWeight = $order['total_weight'] ?? 0;   
        $shippingAED = $order['shipping_aed'] ?? 0;   
        $discountAmount = $order['discount_amount'] ?? 0;
        $grand_Total = $order['total_amount'] ?? 0; 
        $note = $order['note'] ?? ''; 

    if (!$order) {
        throw new Exception("Order not found");
    }

    $order_items = $db->query("
        SELECT oi.*, p.name_en 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE order_id = :id
    ", ['id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order_items)) {
        throw new Exception("No items found in order");
    }
    
    // Calculate subtotal from order items
    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Colors
$primaryColor = array(230, 123, 46); // Aleppo Gift orange
$headerColor = array(44, 62, 80); // Dark blue
$lightColor = array(245, 245, 245); // Light gray
$borderColor = array(220, 220, 220); // Border gray

// Set document properties
$pdf->SetTitle('Invoice #' . $order_id);
$pdf->SetAuthor('AleppoGift.com');

// Header Section
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Image(__DIR__ . '/../public/assets/images/logo.png', 15, 15, 45);
$pdf->Cell(0, 15, 'INVOICE', 0, 1, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100);
$pdf->Cell(0, 5, 'AleppoGift.com | Your Trusted Gift Shop', 0, 1, 'R');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'Date: ' . $order['formatted_order_date'], 0, 1, 'R');
$pdf->Cell(0, 5, 'Invoice #' . $order_id, 0, 1, 'R');
$pdf->Ln(12);

// Company and Customer Info
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(95, 7, 'FROM:', 0, 0);
$pdf->Cell(95, 7, 'BILL TO:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(60);

// Company info
$pdf->Cell(95, 6, 'Aleppo Gift Trading LLC', 0, 0);
$pdf->Cell(95, 6, $order['customer_name'], 0, 1);
$pdf->Cell(95, 6, 'Dubai, United Arab Emirates', 0, 0);
$pdf->Cell(95, 6, $order['customer_address'], 0, 1);
$pdf->Cell(95, 6, 'Phone: +971 56 112 5320', 0, 0);
$pdf->Cell(95, 6, 'Phone: ' . $order['customer_phone'], 0, 1);
$pdf->Cell(95, 6, 'Email: info@aleppogift.com', 0, 0);
$pdf->Cell(95, 6, 'Email: ' . $order['customer_email'], 0, 1);
$pdf->Ln(15);

// Shipping Information
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(0, 6, 'SHIPPING INFORMATION', 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(60);
$pdf->Cell(0, 5, 'Country: ' . htmlspecialchars($country), 0, 1);
$pdf->Cell(0, 5, 'City: ' . htmlspecialchars($city), 0, 1);
$pdf->Cell(0, 5, 'Total Weight: ' . number_format($totalWeight, 2) . ' kg', 0, 1);
$pdf->Ln(10);

// Items Table Header
$pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(15, 10, '#', 1, 0, 'C', true);
$pdf->Cell(85, 10, 'PRODUCT DESCRIPTION', 1, 0, 'L', true);
$pdf->Cell(20, 10, 'QTY', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'UNIT PRICE', 1, 0, 'R', true);
$pdf->Cell(30, 10, 'TOTAL', 1, 1, 'R', true);

// Items Table Rows
$pdf->SetTextColor(60);
$pdf->SetFont('Arial', '', 9);
$index = 1;
$rowHeight = 7;

foreach ($order_items as $item) {
    $line_total = $item['price'] * $item['quantity'];
    
    // Alternate row color
    $pdf->SetFillColor($index % 2 ? 255 : $lightColor[0], $index % 2 ? 255 : $lightColor[1], $index % 2 ? 255 : $lightColor[2]);
    
    // Calculate needed height
    $productName = $item['name_en'];
    $nameWidth = 85;
    $nameLength = $pdf->GetStringWidth($productName);
    $lines = ceil($nameLength / $nameWidth);
    $currentRowHeight = max($rowHeight, $rowHeight * $lines);
    
    // Row cells
    $pdf->Cell(15, $currentRowHeight, $index++, 1, 0, 'C', true);
    
    // Product name with multi-line support
    $pdf->MultiCell(85, $rowHeight, $productName, 1, 'L', true);
    $pdf->SetXY($pdf->GetX() + 100, $pdf->GetY() - $currentRowHeight);
    
    $pdf->Cell(20, $currentRowHeight, $item['quantity'], 1, 0, 'C', true);
    $pdf->Cell(30, $currentRowHeight, number_format($item['price'], 2), 1, 0, 'R', true);
    $pdf->Cell(30, $currentRowHeight, number_format($line_total, 2), 1, 1, 'R', true);
}

// Summary Section
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->SetTextColor(255);
$pdf->Cell(140, 10, 'ORDER SUMMARY', 0, 0, 'L', true);
$pdf->Cell(40, 10, 'AED', 0, 1, 'R', true);

// Add Note
if (!empty($note)) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Customer Note:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, $note);
}

// Add Remarks
if (!empty($order['remarks'])) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Remarks:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, $order['remarks']);
}

// Summary Rows
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(60);
$pdf->SetFillColor(255);

// Subtotal
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(140, 8, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(40, 8, number_format($subtotal, 2), 0, 1, 'R');

// Shipping
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(140, 8, 'Shipping:', 0, 0, 'R');
$pdf->Cell(40, 8, number_format($shippingAED, 2), 0, 1, 'R');

// Discount (if applicable)
if ($discountAmount > 0) {
    $pdf->SetTextColor(220, 53, 69); // Red color for discount
    $pdf->Cell(140, 8, 'Discount:', 0, 0, 'R');
    $pdf->Cell(40, 8, '- ' . number_format($discountAmount, 2), 0, 1, 'R');
    $pdf->SetTextColor(60); // Reset to default color
}

// VAT (currently disabled)
$vat = 0;
// $vat = $grand_Total * 0.05;
// $pdf->Cell(140, 8, 'VAT (5%):', 0, 0, 'R');
// $pdf->Cell(40, 8, number_format($vat, 2), 0, 1, 'R');

// Grand Total
$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(140, 10, 'GRAND TOTAL:', 0, 0, 'R');
$pdf->Cell(40, 10, number_format($grand_Total, 2), 0, 1, 'R');
$pdf->Ln(15);

// Footer Notes
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100);
$pdf->MultiCell(0, 5, "Payment Terms: Due upon receipt\nAll amounts are in UAE Dirhams (AED)", 0, 'C');
$pdf->Ln(5);
$pdf->Cell(0, 5, 'For any inquiries, please contact: sales@aleppogift.com', 0, 1, 'C');
$pdf->Cell(0, 5, 'Thank you for shopping with AleppoGift.com!', 0, 1, 'C');

// Legal text
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(150);
$pdf->Cell(0, 4, 'This is a computer-generated document. No signature is required.', 0, 1, 'C');
$pdf->Ln(5);

    // Save PDF with proper paths
    $invoiceDir = __DIR__ . '/../invoice';
    if (!file_exists($invoiceDir)) {
        if (!mkdir($invoiceDir, 0755, true)) {
            throw new Exception("Failed to create invoice directory");
        }
    }
    
    $invoiceFile = "invoice_$order_id.pdf";
    $fullPath = "$invoiceDir/$invoiceFile";
    
    $pdf->Output('F', $fullPath);

    // Return paths
    return [
        'full_path' => $fullPath,
        'public_path' => "invoice/$invoiceFile",
        'web_url' => SITE_URL . "/invoice/$invoiceFile"
    ];

} catch (Exception $e) {
    error_log("Invoice generation failed: " . $e->getMessage());
    throw $e; // Re-throw for calling code to handle
}