<?php
// File: includes/generate_invoice_pdf.php
// This file creates actual PDF invoice files using FPDF

require_once(__DIR__ . '/../vendor/fpdf/fpdf.php');
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/Database.php');

class PDFInvoiceGenerator {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function generateInvoicePDF($order_id) {
        // Validate order_id
        if (!$order_id || !is_numeric($order_id)) {
            throw new Exception("Invalid order ID provided");
        }
        
        $order_id_int = (int)$order_id;

        // Get order details with customer info in one query
        try {
            $order = $this->db->query("
                SELECT 
                    o.*, 
                    c.fullname AS customer_name,
                    c.email AS customer_email,
                    c.phone AS customer_phone,
                    c.address AS customer_address,
                    c.city AS customer_city,
                    c.country AS customer_country
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.id = ?
            ", [$order_id_int])->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Database error fetching order: " . $e->getMessage());
        }
        
        if (!$order) {
            throw new Exception("Order #$order_id not found");
        }
        
        // Get order items with product details
        try {
            $order_items = $this->db->query("
                SELECT 
                    oi.*,
                    p.name_en,
                    p.name_ar
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ", [$order_id_int])->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Database error fetching order items: " . $e->getMessage());
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

        // Invoice number and date
        $invoice_number = 'INV-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
        $invoice_date = date('F j, Y', strtotime($order['created_at'] ?? $order['order_date'] ?? 'now'));

        // Set document properties
        $pdf->SetTitle('Invoice #' . $invoice_number);
        $pdf->SetAuthor('AleppoGift.com');

        // Header Section
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        
        // Add logo if exists
        $logo_path = __DIR__ . '/../public/assets/images/logo.png';
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, 15, 15, 45);
        }
        
        $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(100);
        $pdf->Cell(0, 5, 'AleppoGift.com | Premium Syrian Products', 0, 1, 'R');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Date: ' . $invoice_date, 0, 1, 'R');
        $pdf->Cell(0, 5, 'Invoice #: ' . $invoice_number, 0, 1, 'R');
        $pdf->Cell(0, 5, 'Order ID: #' . $order_id, 0, 1, 'R');
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
        $pdf->Cell(95, 6, $order['customer_name'] ?: 'N/A', 0, 1);
        $pdf->Cell(95, 6, 'Dubai, United Arab Emirates', 0, 0);
        $pdf->Cell(95, 6, $order['customer_address'] ?: '', 0, 1);
        $pdf->Cell(95, 6, 'Phone: +971 56 112 5320', 0, 0);
        $pdf->Cell(95, 6, 'Phone: ' . ($order['customer_phone'] ?: ''), 0, 1);
        $pdf->Cell(95, 6, 'Email: info@aleppogift.com', 0, 0);
        $pdf->Cell(95, 6, 'Email: ' . ($order['customer_email'] ?: ''), 0, 1);
        $pdf->Ln(15);

        // Payment Information
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
        $pdf->Cell(0, 6, 'PAYMENT INFORMATION', 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60);
        $pdf->Cell(0, 5, 'Payment Method: ' . ($order['payment_method'] ?: 'N/A'), 0, 1);
        $pdf->Cell(0, 5, 'Payment Status: ' . ucfirst($order['payment_status'] ?: 'pending'), 0, 1);
        if (!empty($order['shipping_method'])) {
            $pdf->Cell(0, 5, 'Shipping Method: ' . $order['shipping_method'], 0, 1);
        }
        $pdf->Ln(10);

        // Items Table Header
        $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
        $pdf->SetTextColor(255);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(15, 10, '#', 1, 0, 'C', true);
        $pdf->Cell(85, 10, 'PRODUCT DESCRIPTION', 1, 0, 'L', true);
        $pdf->Cell(20, 10, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'UNIT PRICE', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'TOTAL', 1, 0, 'C', true);
        $pdf->Ln();

        // Items Table Body
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60);
        $subtotal = 0;
        $item_number = 1;

        foreach ($order_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $subtotal += $item_total;
            
            // Product name (prefer English, fall back to Arabic)
            $product_name = $item['name_en'] ?: $item['name_ar'] ?: $item['product_name'] ?: 'Product';
            // SKU field not available in this database, using product ID instead
            if (!empty($item['product_id'])) {
                $product_name .= ' (ID: ' . $item['product_id'] . ')';
            }
            
            // Check if we need a new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Re-add table header
                $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
                $pdf->SetTextColor(255);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(15, 10, '#', 1, 0, 'C', true);
                $pdf->Cell(85, 10, 'PRODUCT DESCRIPTION', 1, 0, 'L', true);
                $pdf->Cell(20, 10, 'QTY', 1, 0, 'C', true);
                $pdf->Cell(25, 10, 'UNIT PRICE', 1, 0, 'C', true);
                $pdf->Cell(25, 10, 'TOTAL', 1, 0, 'C', true);
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(60);
            }
            
            $pdf->Cell(15, 8, $item_number, 1, 0, 'C');
            $pdf->Cell(85, 8, $product_name, 1, 0, 'L');
            $pdf->Cell(20, 8, $item['quantity'], 1, 0, 'C');
            $pdf->Cell(25, 8, 'AED ' . number_format($item['price'], 2), 1, 0, 'C');
            $pdf->Cell(25, 8, 'AED ' . number_format($item_total, 2), 1, 0, 'C');
            $pdf->Ln();
            
            $item_number++;
        }

        // Totals Section
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);

        // Subtotal
        $pdf->Cell(145, 8, 'Subtotal:', 0, 0, 'R');
        $pdf->Cell(25, 8, 'AED ' . number_format($subtotal, 2), 1, 1, 'C');

        // Shipping
        $shipping_amount = $order['shipping_amount'] ?? 0;
        if ($shipping_amount > 0) {
            $pdf->Cell(145, 8, 'Shipping:', 0, 0, 'R');
            $pdf->Cell(25, 8, 'AED ' . number_format($shipping_amount, 2), 1, 1, 'C');
        }

        // Discount
        $discount_amount = $order['discount_amount'] ?? 0;
        if ($discount_amount > 0) {
            $pdf->SetTextColor(220, 53, 69); // Red color for discount
            $pdf->Cell(145, 8, 'Discount:', 0, 0, 'R');
            $pdf->Cell(25, 8, '-AED ' . number_format($discount_amount, 2), 1, 1, 'C');
            $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
        }

        // Total
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255);
        $pdf->Cell(145, 10, 'TOTAL:', 0, 0, 'R');
        $pdf->Cell(25, 10, 'AED ' . number_format($order['total_amount'], 2), 1, 1, 'C', true);

        // Notes section
        if (!empty($order['notes'])) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
            $pdf->Cell(0, 6, 'NOTES:', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(60);
            $pdf->MultiCell(0, 5, $order['notes']);
        }

        // Footer
        $pdf->Ln(15);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100);
        $pdf->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Visit us at www.aleppogift.com | Email: info@aleppogift.com', 0, 1, 'C');
        $pdf->Cell(0, 5, 'This is a computer-generated invoice.', 0, 1, 'C');

        // Save PDF to file
        $invoice_dir = __DIR__ . '/../invoice';
        if (!is_dir($invoice_dir)) {
            mkdir($invoice_dir, 0755, true);
        }
        
        $pdf_path = $invoice_dir . '/invoice_' . $order_id . '.pdf';
        $pdf->Output('F', $pdf_path);
        
        return [
            'success' => true,
            'file_path' => $pdf_path,
            'full_path' => $pdf_path,
            'invoice_number' => $invoice_number
        ];
    }
}

// If called directly, generate invoice for the provided order ID
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__) && (isset($_GET['order_id']) || isset($_GET['id']))) {
    $order_id = intval($_GET['order_id'] ?? $_GET['id'] ?? 0);
    
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID provided']);
        exit;
    }
    
    try {
        $generator = new PDFInvoiceGenerator();
        $result = $generator->generateInvoicePDF($order_id);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>