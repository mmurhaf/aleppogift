<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Payment Link - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .container {
            max-width: 800px;
            margin-top: 3rem;
            margin-bottom: 3rem;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.3);
        }
        
        .card-header {
            background: linear-gradient(135deg, #ff7f00 0%, #ff9f40 100%);
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 2rem;
            text-align: center;
        }
        
        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .card-body {
            padding: 2.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 2px solid #e0e0e0;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 0.5rem;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(102, 126, 234, 0.4);
        }
        
        .btn-generate:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .result-section {
            display: none;
            margin-top: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            color: white;
        }
        
        .result-section.show {
            display: block;
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .payment-link-display {
            background: rgba(255, 255, 255, 0.15);
            border: 2px dashed rgba(255, 255, 255, 0.4);
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin: 1.5rem 0;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
        }
        
        .share-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .share-btn {
            padding: 1rem;
            border-radius: 0.5rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .share-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            color: white;
        }
        
        .share-btn i {
            font-size: 1.2rem;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .loading.show {
            display: block;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .amount-input {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
        }
        
        .currency-addon {
            background: #667eea;
            color: white;
            border: 2px solid #667eea;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .qr-code-section {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px dashed rgba(255, 255, 255, 0.3);
        }
        
        #qrCode {
            display: inline-block;
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        .form-text {
            color: #666;
            font-size: 0.875rem;
        }
        
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-bolt me-2"></i> Quick Payment Link</h2>
                <p class="mb-0 mt-2">Generate instant payment links for your customers</p>
            </div>
            <div class="card-body">
                <form id="quickPaymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Amount -->
                    <div class="mb-4">
                        <label for="amount" class="form-label">
                            <i class="fas fa-money-bill-wave me-2"></i> Amount <span class="required">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text currency-addon">AED</span>
                            <input type="number" 
                                   class="form-control amount-input" 
                                   id="amount" 
                                   name="amount" 
                                   placeholder="0.00" 
                                   step="0.01" 
                                   min="1" 
                                   required>
                        </div>
                        <div class="form-text">Enter the payment amount in AED</div>
                    </div>

                    <!-- Customer Name -->
                    <div class="mb-4">
                        <label for="customerName" class="form-label">
                            <i class="fas fa-user me-2"></i> Customer Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="customerName" 
                               name="customer_name" 
                               placeholder="Enter customer's full name" 
                               required>
                    </div>

                    <!-- Customer Phone -->
                    <div class="mb-4">
                        <label for="customerPhone" class="form-label">
                            <i class="fas fa-phone me-2"></i> Phone Number <span class="required">*</span>
                        </label>
                        <input type="tel" 
                               class="form-control" 
                               id="customerPhone" 
                               name="customer_phone" 
                               placeholder="+971 50 123 4567" 
                               required>
                        <div class="form-text">Include country code for WhatsApp sharing</div>
                    </div>

                    <!-- Customer Email (Optional) -->
                    <div class="mb-4">
                        <label for="customerEmail" class="form-label">
                            <i class="fas fa-envelope me-2"></i> Email Address (Optional)
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="customerEmail" 
                               name="customer_email" 
                               placeholder="customer@example.com">
                        <div class="form-text">Email address for sending payment link</div>
                    </div>

                    <!-- Description (Optional) -->
                    <div class="mb-4">
                        <label for="description" class="form-label">
                            <i class="fas fa-sticky-note me-2"></i> Payment Description (Optional)
                        </label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3" 
                                  placeholder="e.g., Invoice #1234, Custom Order, etc."></textarea>
                        <div class="form-text">Add a note about this payment</div>
                    </div>

                    <!-- Generate Button -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-generate btn-lg">
                            <i class="fas fa-magic me-2"></i> Generate Payment Link
                        </button>
                    </div>
                </form>

                <!-- Loading Spinner -->
                <div class="loading" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Creating payment link...</p>
                </div>

                <!-- Result Section -->
                <div class="result-section" id="resultSection">
                    <h4 class="mb-3"><i class="fas fa-check-circle me-2"></i> Payment Link Created!</h4>
                    
                    <div class="payment-link-display" id="paymentLinkDisplay"></div>
                    
                    <div class="share-buttons">
                        <button class="share-btn" onclick="copyToClipboard()">
                            <i class="fas fa-copy"></i> Copy Link
                        </button>
                        <a href="#" class="share-btn" id="whatsappBtn" target="_blank">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="#" class="share-btn" id="emailBtn">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                    </div>

                    <div class="qr-code-section">
                        <h5><i class="fas fa-qrcode me-2"></i> Scan QR Code</h5>
                        <div id="qrCode"></div>
                    </div>

                    <div class="text-center mt-4">
                        <button class="btn btn-light" onclick="resetForm()">
                            <i class="fas fa-plus me-2"></i> Create Another Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        let generatedPaymentLink = '';
        let customerData = {};

        // Form submission
        document.getElementById('quickPaymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = {
                amount: document.getElementById('amount').value,
                customer_name: document.getElementById('customerName').value,
                customer_phone: document.getElementById('customerPhone').value,
                customer_email: document.getElementById('customerEmail').value,
                description: document.getElementById('description').value,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            };

            // Validate amount
            if (parseFloat(formData.amount) < 1) {
                alert('Please enter a valid amount (minimum 1 AED)');
                return;
            }

            // Store customer data for sharing
            customerData = formData;

            // Show loading
            document.getElementById('loadingSpinner').classList.add('show');
            document.getElementById('quickPaymentForm').style.display = 'none';

            // Send request
            fetch('ajax/create_quick_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                // Log the raw response for debugging
                console.log('Raw response:', text);
                
                // Try to parse JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
                
                document.getElementById('loadingSpinner').classList.remove('show');

                if (data.success) {
                    generatedPaymentLink = data.payment_link;
                    displayResult(data);
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate payment link'));
                    document.getElementById('quickPaymentForm').style.display = 'block';
                }
            })
            .catch(error => {
                document.getElementById('loadingSpinner').classList.remove('show');
                document.getElementById('quickPaymentForm').style.display = 'block';
                console.error('Error:', error);
                alert('An error occurred: ' + error.message + '\n\nPlease check the browser console for more details.');
            });
        });

        // Display result
        function displayResult(data) {
            // Show payment link
            document.getElementById('paymentLinkDisplay').textContent = data.payment_link;

            // Update WhatsApp link
            const whatsappMessage = encodeURIComponent(
                `Hello ${customerData.customer_name},\n\n` +
                `Please complete your payment of AED ${parseFloat(customerData.amount).toFixed(2)}\n` +
                (customerData.description ? `For: ${customerData.description}\n\n` : '\n') +
                `Click here to pay:\n${data.payment_link}\n\n` +
                `Thank you!\nAleppoGift`
            );

            const whatsappPhone = customerData.customer_phone.replace(/[^0-9]/g, '');
            const whatsappUrl = `https://wa.me/${whatsappPhone}?text=${whatsappMessage}`;
            document.getElementById('whatsappBtn').href = whatsappUrl;

            // Update Email link
            const emailSubject = encodeURIComponent(`Payment Request - AED ${parseFloat(customerData.amount).toFixed(2)}`);
            const emailBody = encodeURIComponent(
                `Dear ${customerData.customer_name},\n\n` +
                `This is a payment request for AED ${parseFloat(customerData.amount).toFixed(2)}\n` +
                (customerData.description ? `Description: ${customerData.description}\n\n` : '\n') +
                `Please click the link below to complete your payment:\n${data.payment_link}\n\n` +
                `If you have any questions, please contact us.\n\n` +
                `Best regards,\nAleppoGift Team`
            );

            const emailTo = customerData.customer_email || '';
            document.getElementById('emailBtn').href = `mailto:${emailTo}?subject=${emailSubject}&body=${emailBody}`;

            // Generate QR Code
            document.getElementById('qrCode').innerHTML = '';
            new QRCode(document.getElementById('qrCode'), {
                text: data.payment_link,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });

            // Show result section
            document.getElementById('resultSection').classList.add('show');
            document.getElementById('resultSection').scrollIntoView({ behavior: 'smooth' });
        }

        // Copy to clipboard
        function copyToClipboard() {
            navigator.clipboard.writeText(generatedPaymentLink).then(() => {
                const btn = event.target.closest('.share-btn');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-2"></i> Copied!';
                btn.style.background = 'rgba(40, 167, 69, 0.3)';

                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy link. Please copy manually.');
            });
        }

        // Reset form
        function resetForm() {
            document.getElementById('quickPaymentForm').reset();
            document.getElementById('quickPaymentForm').style.display = 'block';
            document.getElementById('resultSection').classList.remove('show');
            generatedPaymentLink = '';
            customerData = {};
        }

        // Auto-focus on amount field
        document.getElementById('amount').focus();
    </script>
</body>
</html>
