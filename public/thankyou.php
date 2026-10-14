<?php
require_once('../includes/bootstrap.php');

// Bootstrap already loads all necessary components
$db = new Database();
$order_id = isset($_GET['order']) ? (int)$_GET['order'] : (int)($_GET['order_id'] ?? 0);

if (!$order_id) {
    header("Location: index.php");
    exit;
}

// Fetch the order
$order = $db->query("
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
    WHERE o.id = :id
", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit;
}

// Card checkout lands here after Ziina. COD is still unpaid until it is collected.
$thankyouMethod = strtolower(trim((string)($order['payment_method'] ?? '')));
$isCardPayment = in_array($thankyouMethod, ['ziina', 'zina', 'online', 'stripe', 'credit_card', 'card'], true);
$isUnpaidCod = function_exists('ag_order_invoice_allowed') ? !ag_order_invoice_allowed($order) : ($thankyouMethod === 'cod');
if ($isCardPayment && ($order['payment_status'] ?? '') !== 'paid') {
    $db->query("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE id = :id", ['id' => $order_id]);
    $order['payment_status'] = 'paid';
    $isUnpaidCod = false;
}
if ($isCardPayment && ($order['payment_status'] ?? '') === 'paid' && function_exists('reserve_order_stock')) {
    reserve_order_stock($db, $order_id);
}

$_SESSION['confirmed_order_' . $order_id] = true;

// Paid orders get an invoice. Unpaid cash on delivery does not, until the customer asks.
if (!$isUnpaidCod) {
    try {
        require_once '../includes/generate_invoice_pdf.php';
        $generator = new PDFInvoiceGenerator();
        $invoice_result = $generator->generateInvoicePDF($order_id);

        if ($invoice_result['success']) {
            $invoicePath = $invoice_result['file_path'];
            $_SESSION['valid_invoice_' . $order_id] = true;
            error_log("✅ Invoice PDF generated successfully for Order #$order_id at: $invoicePath");
        } else {
            error_log("❌ Failed to generate invoice PDF for Order #$order_id: " . ($invoice_result['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log("❌ Invoice generation error for Order #$order_id: " . $e->getMessage());
    }
}





// In your POST handler, after creating the real order:
if (isset($_SESSION['temp_order_id'])) {
    // Delete the temporary quotation order
    $db->query("DELETE FROM orders WHERE id = :id", ['id' => $_SESSION['temp_order_id']]);
    unset($_SESSION['temp_order_id']);
    
    // Delete the temporary quotation file
    if (file_exists($publicQuotationPath)) {
        unlink($publicQuotationPath);
    }
}

if (!$isUnpaidCod) {
    $_SESSION['valid_invoice_' . $order_id] = true;
}

// --- Confirmation email ---
$payment_method = $order['payment_method'] ?? '';
$grandTotal = $order['total_amount'] ?? 0;
$fullname = $order['customer_name'] ?? '';
$email = $order['customer_email'] ?? '';

$customer_data = [
    'fullname' => $order['customer_name'] ?? '',
    'phone' => $order['customer_phone'] ?? '',
    'address' => $order['customer_address'] ?? '',
    'city' => $order['customer_city'] ?? '',
    'country' => $order['customer_country'] ?? ''
];

if ($email !== '' && !ag_confirmation_already_sent($db, $order_id)) {
    if ($payment_method === 'Ziina') {
        sendAdminWhatsApp($order_id, $fullname, $grandTotal, $payment_method);
    }
    sendConfirmedOrderEmail($email, $order_id, $fullname, $customer_data);
}

// Clear session data
if (!empty($_SESSION['cart'])) {
    sharedCartMarkUsed();
}
unset($_SESSION['cart']);
unset($_SESSION['temp_order_id']);
unset($_SESSION['discount_amount']);
unset($_SESSION['payment_method']);
unset($_SESSION['order_id']); 
unset($_SESSION['grandTotal']); 
unset($_SESSION['fullname']);
unset($_SESSION['email']); 

// Clear any temporary order session data
if (isset($_SESSION['temp_order'])) {
    unset($_SESSION['temp_order']);
}

$_SESSION['cart'] = [];
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('order.thankyou_title') ?></title>
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>

</head>
<body>

    <!-- Main Content -->
    <style>
        :root {
            --primary-color: #d4840e;
            --secondary-color: #fff8ee;
            --success-color: #28a745;
            --light-text: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .thank-you-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
            padding: 3rem 2rem;
            margin: 2rem auto;
            max-width: 800px;
        }
        
        .thank-you-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #d4840e, #f5c842);
        }
        
        .confirmation-icon {
            font-size: 5rem;
            color: var(--success-color);
            margin-bottom: 1.5rem;
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }
        
        .thank-you-header {
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }
        
        .order-summary {
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin: 2.5rem 0;
            border-left: 4px solid var(--primary-color);
        }
        
        .order-summary h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .order-summary h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        }
        
        .order-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-detail:last-child {
            border-bottom: none;
        }
        
        .order-detail-label {
            font-weight: 500;
            color: var(--light-text);
        }
        
        .order-detail-value {
            font-weight: 600;
        }
        
        .btn-download {
            background: #dc3545;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
            margin: 1rem 0;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }
        
        .btn-download:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
            text-decoration: none;
        }
        
        .btn-resend {
            background: #28a745;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
            margin: 1rem 0.5rem;
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
            cursor: pointer;
        }
        
        .btn-resend:hover {
            background: #218838;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            text-decoration: none;
        }
        
        .btn-resend:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d6;
            color: white;
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .whats-next {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid #eee;
        }
        
        .whats-next h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .steps {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            position: relative;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-weight: bold;
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .thank-you-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .steps {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include('../includes/header.php'); ?>

    <main class="thank-you-container">
        <div class="text-center py-4">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="thank-you-header"><?= te('order.thankyou_heading') ?></h1>
            <p class="lead"><?= str_replace('{email}', '<strong>' . htmlspecialchars($order['customer_email']) . '</strong>', te($isUnpaidCod ? 'order.thankyou_lead_cod' : 'order.thankyou_lead')) ?></p>
            <p class="text-muted"><?= te('order.order_id', ['id' => $order['id']]) ?></p>
        </div>

        <div class="order-summary">
            <h3 class="h5"><?= te('order.summary') ?></h3>
            <div class="order-detail">
                <span class="order-detail-label"><?= te('order.order_number_label') ?></span>
                <span class="order-detail-value">#<?= $order['id']; ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label"><?= te('order.date') ?></span>
                <span class="order-detail-value"><?= htmlspecialchars(store_date($order['order_date'])); ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label"><?= te('order.customer_name') ?></span>
                <span class="order-detail-value"><?= htmlspecialchars($order['customer_name']); ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label"><?= te('order.payment_method') ?></span>
                <span class="order-detail-value"><?php
                    $shownPayment = (string)($order['payment_method'] ?? '');
                    if (strcasecmp($shownPayment, 'Ziina') === 0) {
                        $shownPayment = t('order.method_card');
                    } elseif (strcasecmp($shownPayment, 'COD') === 0) {
                        $shownPayment = t('order.method_cod');
                    }
                    echo htmlspecialchars($shownPayment);
                ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label"><?= te('order.total_amount') ?></span>
                <?php 
                $aed_to_eur = 0.23;
                $total_eur = $order['total_amount'] * $aed_to_eur;
                ?>
                <span class="order-detail-value text-success fw-bold">AED <?= number_format($order['total_amount'], 2); ?> (€<?= number_format($total_eur, 2); ?>)</span>
            </div>
        </div>

        <div class="whats-next">
            <h4 class="h5"><?= te('order.whats_next') ?></h4>
            <p><?= te('order.whats_next_text') ?></p>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-title"><?= te('order.step_processing') ?></div>
                    <div class="step-desc"><?= te('order.step_processing_desc') ?></div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-title"><?= te('shipping') ?></div>
                    <div class="step-desc"><?= te('order.step_shipping_desc') ?></div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-title"><?= te('order.step_delivery') ?></div>
                    <div class="step-desc"><?= te('order.step_delivery_desc') ?></div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <?php
            $invoiceFileReady = file_exists("../invoice/invoice_{$order_id}.pdf");
            $codInvoiceRequested = !empty($_SESSION['invoice_requested_' . $order_id]);
            ?>
            <?php if ($isUnpaidCod): ?>
                <div class="alert alert-warning text-start" id="invoiceGeneratingAlert">
                    <?= te('order.cod_invoice_note') ?>
                </div>
                <?php if ($codInvoiceRequested): ?>
                    <div class="alert alert-success"><?= te('order.invoice_requested') ?></div>
                <?php else: ?>
                    <div class="mt-3">
                        <button id="generateInvoiceBtn" class="btn btn-primary" onclick="generateInvoiceNow(<?= $order_id ?>, true)">
                            <i class="fas fa-file-pdf me-2"></i> <?= te('order.request_invoice') ?>
                        </button>
                    </div>
                    <div id="invoiceGenerationStatus" class="mt-3"></div>
                <?php endif; ?>
            <?php elseif ($invoiceFileReady): ?>
                <a href="download_invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-download">
                    <i class="fas fa-file-pdf me-2"></i> <?= te('order.download_invoice') ?>
                </a>
            <?php else: ?>
                <div class="alert alert-warning" id="invoiceGeneratingAlert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= te('order.invoice_generating') ?>
                </div>
                <div class="mt-3">
                    <button id="generateInvoiceBtn" class="btn btn-primary" onclick="generateInvoiceNow(<?= $order_id ?>)">
                        <i class="fas fa-file-pdf me-2"></i> <?= te('order.generate_invoice_now') ?>
                    </button>
                </div>
                <div id="invoiceGenerationStatus" class="mt-3"></div>
            <?php endif; ?>
            <button id="resendEmailBtn" class="btn btn-resend" onclick="resendEmail(<?= $order_id ?>)">
                <i class="fas fa-envelope me-2"></i> <?= te('order.resend_email') ?>
            </button>
            
            <!-- Status message container -->
            <div id="resendStatus" class="mt-3"></div>
            
            <div class="mt-4">
                <p class="mb-3"><?= te('order.need_help') ?></p>
                <a href="contact.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-headset me-2"></i> <?= te('order.contact_support') ?>
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i> <?= te('order.back_home') ?>
                </a>
            </div>
        </div>
    </main>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js?v=20261012i18n"></script>
	<script src="assets/js/enhanced-main.js?v=20261012i18n"></script>
	
	<script>
        const orderI18n = <?= json_encode([
            'sending' => te('order.sending'),
            'resendEmail' => te('order.resend_email'),
            'resendError' => te('order.resend_error'),
            'generating' => te('order.generating_invoice'),
            'generated' => te('order.invoice_generated'),
            'invoiceNumber' => te('order.invoice_number'),
            'downloadInvoice' => te('order.download_invoice'),
            'generationFailed' => te('order.generation_failed'),
            'generateError' => te('order.generate_error'),
            'generateNow' => te('order.generate_invoice_now'),
            'requestInvoice' => te('order.request_invoice'),
            'requestingInvoice' => te('order.requesting_invoice'),
            'invoiceRequested' => te('order.invoice_requested'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

        function resendEmail(orderId) {
            const btn = document.getElementById('resendEmailBtn');
            const statusDiv = document.getElementById('resendStatus');
            
            // Disable button and show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> ' + orderI18n.sending;
            
            // Clear previous status
            statusDiv.innerHTML = '';
            
            // Send AJAX request
            fetch('resend_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.message}
                        </div>
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${orderI18n.resendError}
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable button and restore original text
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-envelope me-2"></i> ' + orderI18n.resendEmail;
                
                // Auto-hide success message after 5 seconds
                setTimeout(() => {
                    const alerts = statusDiv.querySelectorAll('.alert-success');
                    alerts.forEach(alert => {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 500);
                    });
                }, 5000);
            });
        }
        
        function generateInvoiceNow(orderId, requested) {
            const btn = document.getElementById('generateInvoiceBtn');
            const statusDiv = document.getElementById('invoiceGenerationStatus');
            const alertDiv = document.getElementById('invoiceGeneratingAlert');
            
            // Disable button and show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> ' + (requested ? orderI18n.requestingInvoice : orderI18n.generating);
            
            // Clear previous status
            statusDiv.innerHTML = '';
            
            // Send AJAX request to generate invoice
            fetch('api_generate_invoice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && (requested || data.admin_only)) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ${orderI18n.invoiceRequested}
                        </div>
                    `;
                    btn.style.display = 'none';
                } else if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>${orderI18n.generated}</strong><br>
                            ${orderI18n.invoiceNumber} ${data.invoice_number}<br>
                            <a href="${data.download_url}" target="_blank" class="btn btn-sm btn-success mt-2">
                                <i class="fas fa-download me-1"></i> ${orderI18n.downloadInvoice}
                            </a>
                        </div>
                    `;
                    
                    if (alertDiv) {
                        alertDiv.style.display = 'none';
                    }
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                    
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>${orderI18n.generationFailed}</strong> ${data.error}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${orderI18n.generateError}
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable button and restore original text
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-pdf me-2"></i> ' + (requested ? orderI18n.requestInvoice : orderI18n.generateNow);
            });
        }
    </script>
</body>
</html>