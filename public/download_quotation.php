<?php
ob_start(); // Start output buffering
session_start();
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

define('PAYMENT_STATUS_QUOTATION', 'quotation');

$db = new Database();


// --- GET ORDER ITEMS FROM SESSION (or DB if saved) ---
$order_items = $_SESSION['cart'] ?? [];

// --- SHIPPING INFO ---
$country = $_SESSION['shipping_country'] ?? 'Not specified';
$city = $_SESSION['shipping_city'] ?? 'Not specified';
$totalWeight = $_SESSION['total_weight'] ?? 0;

// --- OTHER INFO ---
$note = $_SESSION['customer_note'] ?? '';
$shippingAED = 0.00; // Placeholder or calculate dynamically

// --- GRAND TOTAL ---

$quotationDir = 'quotations';
if (!is_dir($quotationDir)) mkdir($quotationDir, 0755, true);

$uniqueId = time() . '_' . substr(session_id(), 0, 8); 
$quotationPath = "$quotationDir/quotation_$uniqueId.pdf";
$publicQuotationPath = "$quotationDir/quotation_$uniqueId.pdf";



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
$pdf->SetTitle('Invoice #' . $uniqueId);
$pdf->SetAuthor('AleppoGift.com');

// Header Section
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Image(__DIR__ . '/../public/assets/images/logo.png', 15, 15, 45);
$pdf->Cell(0, 10, 'QUOTATION', 0, 1, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100);
$pdf->Cell(0, 5, 'AleppoGift.com | Your Trusted Gift Shop', 0, 1, 'R');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'Date: ' . date('Y-m-d'), 0, 1, 'R');
$pdf->Cell(0, 5, 'Quotation #' . $uniqueId, 0, 1, 'R');
$pdf->Cell(0, 5, 'Valid until: ' . date('Y-m-d', strtotime('+7 days')), 0, 1, 'R');
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
$pdf->Cell(95, 6, 'customer_name', 0, 1);
$pdf->Cell(95, 6, 'Dubai, United Arab Emirates', 0, 0);
$pdf->Cell(95, 6, 'customer_address', 0, 1);
$pdf->Cell(95, 6, 'Phone: +971 56 112 5320', 0, 0);
$pdf->Cell(95, 6, 'Phone: ' . 'customer_phone', 0, 1);
$pdf->Cell(95, 6, 'Email: info@aleppogift.com', 0, 0);
$pdf->Cell(95, 6, 'Email: ' . 'customer_email', 0, 1);
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
$grand_Total = 0;
foreach ($order_items as $item) {
        $product = $db->query("SELECT name_en, price FROM products WHERE id = :id", ['id' => $item['product_id']])->fetch();
    $price = $product['price'];
    $variationName = '';

    if (!empty($item['variation_id'])) {
        $variation = $db->query("SELECT additional_price FROM product_variations WHERE id = :id", ['id' => $item['variation_id']])->fetch();
        $price += $variation['additional_price'] ?? 0;
    }

    $line_total = $price * $item['quantity'];
    $grand_Total = $grand_Total + $line_total ;
    // Alternate row color
    $pdf->SetFillColor($index % 2 ? 255 : $lightColor[0], $index % 2 ? 255 : $lightColor[1], $index % 2 ? 255 : $lightColor[2]);
    
    // Calculate needed height
    $productName = $product['name_en'];
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
    $pdf->Cell(30, $currentRowHeight, number_format($price, 2), 1, 0, 'R', true);
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

// Summary Rows
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(60);
$pdf->SetFillColor(255);

// Shipping
$pdf->Cell(140, 8, 'Shipping:', 0, 0, 'R');
$pdf->Cell(40, 8, number_format($shippingAED, 2), 0, 1, 'R');

// VAT
$vat = 0;
// $vat = $grand_Total * 0.05;
// $pdf->Cell(140, 8, 'VAT (5%):', 0, 0, 'R');
// $pdf->Cell(40, 8, number_format($vat, 2), 0, 1, 'R');

// Grand Total
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(140, 10, 'GRAND TOTAL:', 0, 0, 'R');
$pdf->Cell(40, 10, number_format($grand_Total + $vat, 2), 0, 1, 'R');
$pdf->Ln(15);

// Footer Notes
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100);
$pdf->MultiCell(0, 5, "Payment Terms: Due upon receipt\nAll amounts are in UAE Dirhams (AED)", 0, 'C');

$pdf->MultiCell(0, 5, "Note: Shipping cost is not included in this quotation. It will be calculated at checkout after selecting your country and city.\nYou can review our shipping rates at: https://www.aleppogift.com/shipping.php", 0, 'L');

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100);
$pdf->MultiCell(0, 5, "This is a preliminary quotation, not an invoice.\nPrices may change until order confirmation.", 0, 'C');
$pdf->Cell(0, 5, 'Contact us at: sales@aleppogift.com', 0, 1, 'C');


// Legal text
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(150);
$pdf->Cell(0, 4, 'This is a computer-generated document. No signature is required.', 0, 1, 'C');
$pdf->Ln(5);

// Output PDF
// Add after PDF creation:
$pdf->Output('F', $quotationPath);

// Redirect to download
header("Location: $publicQuotationPath");
exit;