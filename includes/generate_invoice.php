<?php
// File: includes/generate_invoice.php

require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/Database.php');

class InvoiceGenerator {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function generateInvoice($order_id, $output = 'html') {
        // Validate order_id
        if (!$order_id || !is_numeric($order_id)) {
            throw new Exception("Invalid order ID provided");
        }
        
        $order_id_int = (int)$order_id;

        // Get order details
        try {
            $order = $this->db->query("SELECT * FROM orders WHERE id = ?", [$order_id_int])->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Database error fetching order: " . $e->getMessage());
        }
        
        if (!$order) {
            throw new Exception("Order #$order_id not found");
        }
        
        // Get customer details
        $customer = null;
        if (!empty($order['customer_id'])) {
            try {
                $cust_id_int = (int)$order['customer_id'];
                $customer = $this->db->query("SELECT * FROM customers WHERE id = ?", [$cust_id_int])->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Continue without customer data if there's an error
                error_log("Error fetching customer: " . $e->getMessage());
            }
        }
        
        // Get order items
        $order_items = [];
        try {
            $order_items = $this->db->query("
                SELECT oi.*, p.name_en, p.name_ar 
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id
            ", [$order_id_int])->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Database error fetching order items: " . $e->getMessage());
        }
        
        if ($output === 'pdf') {
            return $this->generatePDF($order, $customer, $order_items);
        } else {
            return $this->generateHTML($order, $customer, $order_items);
        }
    }
    
    private function generateHTML($order, $customer, $order_items) {
        $invoice_date = date('M j, Y', strtotime($order['order_date'] ?? $order['updated_at'] ?? 'now'));
        $invoice_number = 'INV-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice #<?= $invoice_number ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
                .invoice-container { max-width: 800px; margin: 0 auto; background: white; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e74c3c; padding-bottom: 20px; }
                .logo { font-size: 28px; font-weight: bold; color: #e74c3c; margin-bottom: 10px; }
                .company-info { font-size: 14px; color: #666; }
                .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .bill-to, .invoice-details { width: 48%; }
                .bill-to h3, .invoice-details h3 { color: #e74c3c; margin-bottom: 10px; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                .items-table th { background-color: #f8f9fa; font-weight: bold; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .note { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #e74c3c; }
                .no-print { margin-top: 30px; text-align: center; }
                .btn { padding: 10px 20px; margin: 0 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-primary { background-color: #007bff; color: white; }
                .btn-secondary { background-color: #6c757d; color: white; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <div class="header">
                    <div class="logo">AleppoGift</div>
                    <div class="company-info">
                        Premium Syrian Products<br>
                        Email: info@aleppogift.com | Website: www.aleppogift.com
                    </div>
                </div>
                
                <div class="invoice-info">
                    <div class="bill-to">
                        <h3>Bill To:</h3>
                        <?php if ($customer): ?>
                            <strong><?= htmlspecialchars($customer['fullname'] ?? 'N/A') ?></strong><br>
                            <?= htmlspecialchars($customer['email'] ?? '') ?><br>
                            <?= htmlspecialchars($customer['phone'] ?? '') ?><br>
                            <?= nl2br(htmlspecialchars($customer['address'] ?? '')) ?><br>
                            <?= htmlspecialchars($customer['city'] ?? '') ?><?= !empty($customer['city']) && !empty($customer['country']) ? ', ' : '' ?><?= htmlspecialchars($customer['country'] ?? '') ?>
                        <?php else: ?>
                            <em>Customer information not available</em>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-details">
                        <h3>Invoice Details:</h3>
                        <strong>Invoice #:</strong> <?= $invoice_number ?><br>
                        <strong>Order #:</strong> <?= $order['id'] ?><br>
                        <strong>Date:</strong> <?= $invoice_date ?><br>
                        <strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')) ?><br>
                        <strong>Status:</strong> <?= ucfirst($order['payment_status'] ?? 'Pending') ?>
                    </div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($order_items)): ?>
                            <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name_en'] ?? $item['product_name'] ?? 'Unknown Product') ?></td>
                                <td><?= (int)$item['quantity'] ?></td>
                                <td>AED <?= number_format((float)$item['price'], 2) ?></td>
                                <td>AED <?= number_format((float)$item['quantity'] * (float)$item['price'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #666;">No items found for this order</td>
                            </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td colspan="3"><strong>Total Amount:</strong></td>
                            <td><strong>AED <?= number_format((float)$order['total_amount'], 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!empty($order['note'])): ?>
                <div class="note">
                    <strong>Note:</strong><br>
                    <?= nl2br(htmlspecialchars($order['note'])) ?>
                </div>
                <?php endif; ?>
                
                <div class="footer">
                    <p>Thank you for your business!</p>
                    <p>This is a computer-generated invoice.</p>
                </div>
                
                <div class="no-print">
                    <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
                    <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generatePDF($order, $customer, $order_items) {
        // PDF generation would require a library like TCPDF or FPDF
        // For now, return HTML that can be converted to PDF
        return $this->generateHTML($order, $customer, $order_items);
    }
}

// If called directly (not included), generate invoice
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__) && (isset($_GET['order_id']) || isset($_GET['id']))) {
    $order_id = intval($_GET['order_id'] ?? $_GET['id'] ?? 0);
    
    if ($order_id <= 0) {
        echo "<div style='padding: 20px; text-align: center;'>";
        echo "<h3>Error</h3>";
        echo "<p>Invalid order ID provided</p>";
        echo "<a href='orders.php'>Back to Orders</a>";
        echo "</div>";
        exit;
    }
    
    $generator = new InvoiceGenerator();
    
    try {
        echo $generator->generateInvoice($order_id);
    } catch (Exception $e) {
        echo "<div style='padding: 20px; text-align: center;'>";
        echo "<h3>Error</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<a href='orders.php' style='color: #007bff; text-decoration: none;'>Back to Orders</a>";
        echo "</div>";
    }
}
?>