<?php
/**
 * Export Cart to PDF
 * Generates a PDF with cart items with option to include or exclude prices
 */

session_start();
require_once(__DIR__ . '/../../includes/bootstrap.php');
require_once(__DIR__ . '/../../vendor/fpdf/fpdf.php');

$db = new Database();

// Check if cart exists
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    http_response_code(400);
    die('Cart is empty');
}

// Get option to include prices (default: yes)
$includePrices = isset($_GET['prices']) && $_GET['prices'] === 'no' ? false : true;

// Create PDF
class CartPDF extends FPDF {
    private $includePrices;
    function __construct($includePrices = true) {
        parent::__construct();
        $this->includePrices = $includePrices;
    }
    // Header unchanged
    function Header() {
        // Logo or header image (if exists)
        $logoPath = __DIR__ . '/../../assets/images/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 30);
        }
        
        // Title
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(230, 123, 46); // Orange color
        $this->Cell(0, 10, 'AleppoGift - Shopping Cart', 0, 0, 'C');
        $this->Ln(15);
        
        // Date
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Generated: ' . date('F d, Y H:i'), 0, 0, 'R');
        $this->Ln(10);
        
        // Horizontal line
        $this->SetDrawColor(230, 123, 46);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | AleppoGift - Your Premium Shopping Destination', 0, 0, 'C');
    }
    
    function CartTable($header, $data) {
        // Colors and fonts
        $this->SetFillColor(230, 123, 46); // Orange
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 11);
        // Add image column
        if ($this->includePrices) {
            $w = array(15, 25, 50, 40, 20, 25, 30); // #, Img, Name, Variation, Qty, Price, Total
        } else {
            $w = array(15, 25, 70, 60, 25); // #, Img, Name, Variation, Qty
        }
        // Header
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        // Data
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 10);
        $fill = false;
        $itemNumber = 1;
        foreach ($data as $row) {
            $startY = $this->GetY();
            $maxHeight = 18; // Height for image row
            if ($startY > 250) {
                $this->AddPage();
                $startY = $this->GetY();
            }
            if ($this->includePrices) {
                $this->Cell($w[0], $maxHeight, $itemNumber++, 'LR', 0, 'C', $fill);
                // Image
                if (!empty($row[1]) && file_exists($row[1])) {
                    $this->Image($row[1], $this->GetX(), $this->GetY(), 16, 16);
                }
                $this->Cell($w[1], $maxHeight, '', 'LR', 0, 'C', $fill);
                $x = $this->GetX();
                $this->MultiCell($w[2], 8, $row[2], 'LR', 'L', $fill);
                $height1 = $this->GetY() - $startY;
                $maxHeight = max($maxHeight, $height1);
                $this->SetXY($x + $w[2], $startY);
                $this->MultiCell($w[3], 8, $row[3], 'LR', 'L', $fill);
                $height2 = $this->GetY() - $startY;
                $maxHeight = max($maxHeight, $height2);
                $this->SetXY($x + $w[2] + $w[3], $startY);
                $this->Cell($w[4], $maxHeight, $row[4], 'LR', 0, 'C', $fill);
                $this->Cell($w[5], $maxHeight, $row[5], 'LR', 0, 'R', $fill);
                $this->Cell($w[6], $maxHeight, $row[6], 'LR', 0, 'R', $fill);
            } else {
                $this->Cell($w[0], $maxHeight, $itemNumber++, 'LR', 0, 'C', $fill);
                if (!empty($row[1]) && file_exists($row[1])) {
                    $this->Image($row[1], $this->GetX(), $this->GetY(), 16, 16);
                }
                $this->Cell($w[1], $maxHeight, '', 'LR', 0, 'C', $fill);
                $x = $this->GetX();
                $this->MultiCell($w[2], 8, $row[2], 'LR', 'L', $fill);
                $height1 = $this->GetY() - $startY;
                $maxHeight = max($maxHeight, $height1);
                $this->SetXY($x + $w[2], $startY);
                $this->MultiCell($w[3], 8, $row[3], 'LR', 'L', $fill);
                $height2 = $this->GetY() - $startY;
                $maxHeight = max($maxHeight, $height2);
                $this->SetXY($x + $w[2] + $w[3], $startY);
                $this->Cell($w[4], $maxHeight, $row[4], 'LR', 0, 'C', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
    
    function SummaryBox($subtotal, $shipping, $total) {
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(245, 245, 245);
        
        // Summary box
        $boxWidth = 70;
        $boxX = $this->GetPageWidth() - $boxWidth - 10;
        $this->SetX($boxX);
        
        $this->SetFont('Arial', '', 11);
        $this->Cell($boxWidth/2, 8, 'Subtotal:', 0, 0, 'L', true);
        $this->Cell($boxWidth/2, 8, 'AED ' . number_format($subtotal, 2), 0, 1, 'R', true);
        
        $this->SetX($boxX);
        $this->Cell($boxWidth/2, 8, 'Shipping:', 0, 0, 'L', true);
        $this->Cell($boxWidth/2, 8, 'AED ' . number_format($shipping, 2), 0, 1, 'R', true);
        
        $this->SetX($boxX);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(230, 123, 46);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($boxWidth/2, 10, 'Total:', 1, 0, 'L', true);
        $this->Cell($boxWidth/2, 10, 'AED ' . number_format($total, 2), 1, 1, 'R', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    function InfoBox() {
        $this->Ln(15);
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(100, 100, 100);
        
        $this->SetFillColor(250, 250, 250);
        $this->MultiCell(0, 5, "Note: This is a cart export for your reference. To complete your purchase, please visit our website and proceed to checkout. Prices and availability are subject to change.", 1, 'L', true);
        
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(230, 123, 46);
        $this->Cell(0, 5, 'Contact Information:', 0, 1);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, 'Website: www.aleppogift.com', 0, 1);
        $this->Cell(0, 5, 'Email: info@aleppogift.com', 0, 1);
    }
}

try {
    // Initialize PDF
    $pdf = new CartPDF($includePrices);
    $pdf->AddPage();
    
    // Prepare table header
    if ($includePrices) {
        $header = array('#', 'Image', 'Product Name', 'Variation', 'Qty', 'Price', 'Total');
    } else {
        $header = array('#', 'Image', 'Product Name', 'Variation', 'Qty');
    }
    // Prepare data
    $data = array();
    $grandTotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $product = $db->query(
            "SELECT * FROM products WHERE id = :id AND status = 1",
            ['id' => $item['product_id']]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            continue;
        }
        // Get main image path
        $main_image = $db->query(
            "SELECT image_path FROM product_images WHERE product_id = :pid AND is_main = 1 LIMIT 1",
            ['pid' => $item['product_id']]
        )->fetchColumn();
        if ($main_image) {
            // Try to resolve the image path
            $img_path = __DIR__ . '/../../' . ltrim($main_image, '/');
            if (!file_exists($img_path)) {
                $img_path = __DIR__ . '/../uploads/products/' . basename($main_image);
            }
        } else {
            $img_path = __DIR__ . '/../uploads/products/default.jpg';
        }
        $price = $product['price'];
        $variationText = "Standard";
        if (!empty($item['variation_id'])) {
            $variation = $db->query(
                "SELECT * FROM product_variations WHERE id = :id",
                ['id' => $item['variation_id']]
            )->fetch(PDO::FETCH_ASSOC);
            if ($variation) {
                $variationText = "Size: {$variation['size']}, Color: {$variation['color']}";
                $price += $variation['additional_price'];
            }
        }
        $total = $price * $item['quantity'];
        $grandTotal += $total;
        if ($includePrices) {
            $data[] = array(
                $product['name_en'], // 0
                $img_path,           // 1
                $product['name_en'], // 2
                $variationText,      // 3
                $item['quantity'],   // 4
                'AED ' . number_format($price, 2), // 5
                'AED ' . number_format($total, 2)  // 6
            );
        } else {
            $data[] = array(
                $product['name_en'], // 0
                $img_path,           // 1
                $product['name_en'], // 2
                $variationText,      // 3
                $item['quantity']    // 4
            );
        }
    }
    // Draw table
    $pdf->CartTable($header, $data);
    
    // Add summary if prices included
    if ($includePrices) {
        $shipping = 30.00;
        $pdf->SummaryBox($grandTotal, $shipping, $grandTotal + $shipping);
    }
    
    // Add info box
    $pdf->InfoBox();
    
    // Output PDF
    $filename = 'AleppoGift_Cart_' . date('Y-m-d') . ($includePrices ? '_with_prices' : '_without_prices') . '.pdf';
    $pdf->Output('D', $filename);
    
} catch (Exception $e) {
    error_log("Cart PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Failed to generate PDF: ' . $e->getMessage());
}
