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

$page_title = 'Quick Payment Link';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .qpl-wrapper {
        max-width: 800px;
        margin: 0 auto;
    }

    .qpl-wrapper .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.15);
    }

    .qpl-wrapper .card-header {
        background: linear-gradient(135deg, #ff7f00 0%, #ff9f40 100%);
        color: white;
        border-radius: 1rem 1rem 0 0 !important;
        padding: 2rem;
        text-align: center;
    }

    .qpl-wrapper .card-header h2 {
        margin: 0;
        font-weight: 700;
        font-size: 1.8rem;
    }

    .qpl-wrapper .card-body {
        padding: 2.5rem;
    }

    .qpl-wrapper .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.5rem;
    }

    .qpl-wrapper .form-control,
    .qpl-wrapper .form-select {
        border-radius: 0.5rem;
        border: 2px solid #e0e0e0;
        padding: 0.75rem 1rem;
        transition: all 0.3s;
    }

    .qpl-wrapper .form-control:focus,
    .qpl-wrapper .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .qpl-wrapper .btn-generate {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 1rem 3rem;
        font-weight: 600;
        font-size: 1.1rem;
        border-radius: 0.5rem;
        color: white;
        transition: all 0.3s;
    }

    .qpl-wrapper .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(102, 126, 234, 0.4);
        color: white;
    }

    .qpl-wrapper .btn-generate:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .qpl-wrapper .result-section {
        display: none;
        margin-top: 2rem;
        padding: 2rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 1rem;
        color: white;
    }

    .qpl-wrapper .result-section.show {
        display: block;
        animation: slideDown 0.4s ease-out;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .qpl-wrapper .payment-link-display {
        background: rgba(255, 255, 255, 0.15);
        border: 2px dashed rgba(255, 255, 255, 0.4);
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin: 1.5rem 0;
        word-break: break-all;
        font-family: 'Courier New', monospace;
        font-size: 0.95rem;
    }

    .qpl-wrapper .share-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .qpl-wrapper .share-btn {
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

    .qpl-wrapper .share-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        color: white;
    }

    .qpl-wrapper .loading {
        display: none;
        text-align: center;
        padding: 2rem;
    }

    .qpl-wrapper .loading.show {
        display: block;
    }

    .qpl-wrapper .amount-input {
        font-size: 1.5rem;
        font-weight: 600;
        text-align: center;
    }

    .qpl-wrapper .currency-addon {
        background: #667eea;
        color: white;
        border: 2px solid #667eea;
        font-weight: 600;
        font-size: 1.2rem;
    }

    .qpl-wrapper .qr-code-section {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px dashed rgba(255, 255, 255, 0.3);
    }

    .qpl-wrapper #qrCode {
        display: inline-block;
        padding: 1rem;
        background: white;
        border-radius: 0.5rem;
        margin-top: 1rem;
    }

    .qpl-wrapper .required {
        color: #dc3545;
    }
</style>

<div class="qpl-wrapper">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-bolt me-2"></i> Quick Payment Link</h2>
            <p class="mb-0 mt-2">Generate instant payment links for your customers</p>
        </div>
        <div class="card-body">
            <form id="quickPaymentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

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

                <div class="mb-4">
                    <label for="customerName" class="form-label">
                        <i class="fas fa-user me-2"></i> Customer Name <span class="required">*</span>
                    </label>
                    <input type="text"
                           class="form-control"
                           id="customerName"
                           name="customer_name"
                           placeholder="Enter customer full name"
                           required>
                </div>

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

                <div class="text-center">
                    <button type="submit" class="btn btn-generate btn-lg">
                        <i class="fas fa-magic me-2"></i> Generate Payment Link
                    </button>
                </div>
            </form>

            <div class="loading" id="loadingSpinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Creating payment link...</p>
            </div>

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

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    let generatedPaymentLink = '';
    let customerData = {};

    document.getElementById('quickPaymentForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = {
            amount: document.getElementById('amount').value,
            customer_name: document.getElementById('customerName').value,
            customer_phone: document.getElementById('customerPhone').value,
            customer_email: document.getElementById('customerEmail').value,
            description: document.getElementById('description').value,
            csrf_token: document.querySelector('[name="csrf_token"]').value
        };

        if (parseFloat(formData.amount) < 1) {
            alert('Please enter a valid amount (minimum 1 AED)');
            return;
        }

        customerData = formData;

        document.getElementById('loadingSpinner').classList.add('show');
        document.getElementById('quickPaymentForm').style.display = 'none';

        fetch('ajax/create_quick_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
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
            alert('An error occurred: ' + error.message);
        });
    });

    function displayResult(data) {
        document.getElementById('paymentLinkDisplay').textContent = data.payment_link;

        const whatsappMessage = encodeURIComponent(
            'Hello ' + customerData.customer_name + ',\n\n' +
            'Please complete your payment of AED ' + parseFloat(customerData.amount).toFixed(2) + '\n' +
            (customerData.description ? 'For: ' + customerData.description + '\n\n' : '\n') +
            'Click here to pay:\n' + data.payment_link + '\n\nThank you!\nAleppoGift'
        );

        const whatsappPhone = customerData.customer_phone.replace(/[^0-9]/g, '');
        document.getElementById('whatsappBtn').href = 'https://wa.me/' + whatsappPhone + '?text=' + whatsappMessage;

        const emailSubject = encodeURIComponent('Payment Request - AED ' + parseFloat(customerData.amount).toFixed(2));
        const emailBody = encodeURIComponent(
            'Dear ' + customerData.customer_name + ',\n\nThis is a payment request for AED ' +
            parseFloat(customerData.amount).toFixed(2) + '\n' +
            (customerData.description ? 'Description: ' + customerData.description + '\n\n' : '\n') +
            'Please click the link below to complete your payment:\n' + data.payment_link +
            '\n\nBest regards,\nAleppoGift Team'
        );

        document.getElementById('emailBtn').href = 'mailto:' + (customerData.customer_email || '') +
            '?subject=' + emailSubject + '&body=' + emailBody;

        document.getElementById('qrCode').innerHTML = '';
        new QRCode(document.getElementById('qrCode'), {
            text: data.payment_link,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        document.getElementById('resultSection').classList.add('show');
        document.getElementById('resultSection').scrollIntoView({ behavior: 'smooth' });
    }

    function copyToClipboard() {
        navigator.clipboard.writeText(generatedPaymentLink).then(() => {
            alert('Link copied to clipboard!');
        }).catch(() => {
            const ta = document.createElement('textarea');
            ta.value = generatedPaymentLink;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            alert('Link copied to clipboard!');
        });
    }

    function resetForm() {
        document.getElementById('quickPaymentForm').reset();
        document.getElementById('quickPaymentForm').style.display = 'block';
        document.getElementById('resultSection').classList.remove('show');
        generatedPaymentLink = '';
        customerData = {};
    }

    document.getElementById('amount').focus();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>