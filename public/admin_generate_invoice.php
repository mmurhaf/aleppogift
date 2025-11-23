<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Invoice - AleppoGift Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; margin-top: 50px; }
        .result { margin-top: 20px; padding: 20px; border-radius: 8px; }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">🔧 Invoice Generator - Admin Tool</h2>
        
        <div class="card">
            <div class="card-header">
                <h5>Generate Missing Invoice</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="order_id" class="form-label">Order ID</label>
                        <input type="number" class="form-control" id="order_id" name="order_id" value="97" required>
                        <div class="form-text">Enter the order ID for which you want to generate an invoice</div>
                    </div>
                    <button type="submit" name="generate" class="btn btn-primary w-100">Generate Invoice PDF</button>
                </form>
            </div>
        </div>

        <?php
        if (isset($_POST['generate'])) {
            $order_id = (int)$_POST['order_id'];
            
            echo "<div class='result info'>";
            echo "<h5>📄 Processing Order #$order_id...</h5>";
            
            try {
                require_once('../includes/generate_invoice_pdf.php');
                
                $generator = new PDFInvoiceGenerator();
                $result = $generator->generateInvoicePDF($order_id);
                
                if ($result['success']) {
                    echo "</div><div class='result success'>";
                    echo "<h5>✅ SUCCESS!</h5>";
                    echo "<p><strong>Invoice generated successfully!</strong></p>";
                    echo "<ul>";
                    echo "<li><strong>Order ID:</strong> #$order_id</li>";
                    echo "<li><strong>Invoice Number:</strong> " . $result['invoice_number'] . "</li>";
                    echo "<li><strong>File Path:</strong> " . $result['file_path'] . "</li>";
                    
                    // Check if file exists
                    if (file_exists($result['file_path'])) {
                        $filesize = filesize($result['file_path']);
                        echo "<li><strong>File Size:</strong> " . number_format($filesize) . " bytes</li>";
                        echo "<li><strong>Status:</strong> ✅ File exists and is readable</li>";
                        echo "</ul>";
                        
                        // Provide download link
                        $filename = basename($result['file_path']);
                        echo "<div class='mt-3'>";
                        echo "<a href='../invoice/$filename' target='_blank' class='btn btn-success me-2'>";
                        echo "<i class='fas fa-download'></i> Download Invoice";
                        echo "</a>";
                        
                        echo "<a href='download_invoice.php?id=$order_id' target='_blank' class='btn btn-outline-primary'>";
                        echo "<i class='fas fa-eye'></i> View via Download System";
                        echo "</a>";
                        echo "</div>";
                    } else {
                        echo "<li><strong>Status:</strong> ❌ File was not created properly</li>";
                        echo "</ul>";
                    }
                } else {
                    echo "</div><div class='result error'>";
                    echo "<h5>❌ FAILED</h5>";
                    echo "<p><strong>Error:</strong> " . ($result['error'] ?? 'Unknown error') . "</p>";
                }
                
            } catch (Exception $e) {
                echo "</div><div class='result error'>";
                echo "<h5>❌ EXCEPTION</h5>";
                echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
                echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
            }
            
            echo "</div>";
        }
        ?>

        <div class="mt-4">
            <div class="card">
                <div class="card-header">
                    <h6>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="thankyou.php?order=97" class="btn btn-outline-secondary me-2">
                        View Thank You Page
                    </a>
                    <a href="../invoice/" class="btn btn-outline-info" target="_blank">
                        Browse Invoice Directory
                    </a>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <small class="text-muted">AleppoGift Admin Tools</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>