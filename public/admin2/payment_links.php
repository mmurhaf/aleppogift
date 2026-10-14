<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Payment Links';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .form-section {
        background: white;
        padding: 2rem;
        margin-bottom: 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .section-title {
        color: #333;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #ff7f00;
    }

    .link-display {
        display: none;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        border-radius: 0.5rem;
        color: white;
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .link-display.show {
        display: block;
    }

    .payment-link-box {
        background: rgba(255, 255, 255, 0.1);
        border: 2px dashed rgba(255, 255, 255, 0.3);
        border-radius: 0.5rem;
        padding: 1rem;
        margin: 1rem 0;
        word-break: break-all;
    }

    .share-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .share-btn {
        flex: 1;
        min-width: 150px;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        border: none;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .share-btn.whatsapp { background: #25D366; }
    .share-btn.whatsapp:hover { background: #1ea952; color: white; }
    .share-btn.email { background: #EA4335; }
    .share-btn.email:hover { background: #c5362e; color: white; }
    .share-btn.copy {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.5);
    }
    .share-btn.copy:hover { background: rgba(255, 255, 255, 0.3); color: white; }

    .order-search { position: relative; }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 0.25rem;
        max-height: 300px;
        overflow-y: auto;
        display: none;
        z-index: 1000;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .search-results.show { display: block; }

    .search-result-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }

    .search-result-item:hover { background: #f8f9fa; }
    .search-result-item:last-child { border-bottom: none; }

    .order-id { font-weight: 600; color: #ff7f00; }
    .customer-name { color: #333; font-weight: 500; }
    .order-amount { color: #667eea; font-weight: 600; }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 0.75rem 2rem;
        font-weight: 600;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }

    .loading { display: none; text-align: center; padding: 1rem; }
    .loading.show { display: block; }
    .spinner-border { width: 3rem; height: 3rem; }

    .qr-code-section { text-align: center; margin-top: 1.5rem; }

    #qrCode {
        margin: 1rem auto;
        display: inline-block;
        padding: 1rem;
        background: white;
        border-radius: 0.5rem;
    }
</style>

<div class="form-section">
    <h2 class="section-title"><i class="fas fa-search me-2"></i> Find Order</h2>
    <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <div class="row">
        <div class="col-md-8">
            <div class="mb-3 order-search">
                <label for="orderSearch" class="form-label">Search by Order ID, Customer Name, Email, or Phone</label>
                <input type="text"
                       class="form-control form-control-lg"
                       id="orderSearch"
                       placeholder="Type to search orders..."
                       autocomplete="off">
                <div class="search-results" id="searchResults"></div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label d-block">&nbsp;</label>
            <button type="button" class="btn btn-secondary w-100" id="clearBtn">
                <i class="fas fa-times me-2"></i> Clear
            </button>
        </div>
    </div>

    <div id="selectedOrderInfo" class="alert alert-info" style="display: none;">
        <h5><i class="fas fa-info-circle me-2"></i> Selected Order</h5>
        <div id="orderDetails"></div>
    </div>

    <div class="text-center mt-4">
        <button type="button" class="btn btn-primary btn-lg" id="generateBtn" disabled>
            <i class="fas fa-magic me-2"></i> Generate Payment Link
        </button>
    </div>
</div>

<div class="loading" id="loadingSpinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Generating payment link...</p>
</div>

<div class="link-display" id="linkDisplay">
    <h3 class="mb-4"><i class="fas fa-check-circle me-2"></i> Payment Link Generated!</h3>

    <div class="payment-link-box">
        <small class="d-block mb-2 opacity-75">Payment Link:</small>
        <div id="generatedLink" class="fs-6"></div>
    </div>

    <div class="share-buttons">
        <button class="share-btn copy" onclick="copyToClipboard()">
            <i class="fas fa-copy"></i> Copy Link
        </button>
        <a href="#" class="share-btn whatsapp" id="whatsappBtn" target="_blank">
            <i class="fab fa-whatsapp"></i> Share on WhatsApp
        </a>
        <a href="#" class="share-btn email" id="emailBtn">
            <i class="fas fa-envelope"></i> Send via Email
        </a>
    </div>

    <div class="qr-code-section">
        <h5><i class="fas fa-qrcode me-2"></i> QR Code</h5>
        <div id="qrCode"></div>
        <small class="opacity-75">Scan to open payment page</small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    let selectedOrderId = null;
    let selectedOrderData = null;
    let searchTimeout = null;
    let generatedPaymentLink = '';

    document.getElementById('orderSearch').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        if (query.length < 2) {
            document.getElementById('searchResults').classList.remove('show');
            return;
        }
        searchTimeout = setTimeout(() => searchOrders(query), 300);
    });

    function searchOrders(query) {
        fetch('ajax/search_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query, csrf_token: document.getElementById('csrfToken').value })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displaySearchResults(data.orders);
            } else {
                const rd = document.getElementById('searchResults');
                rd.innerHTML = '<div class="search-result-item text-danger">Error: ' + (data.message || 'Search failed') + '</div>';
                rd.classList.add('show');
            }
        })
        .catch(() => {
            const rd = document.getElementById('searchResults');
            rd.innerHTML = '<div class="search-result-item text-danger">Error connecting to server.</div>';
            rd.classList.add('show');
        });
    }

    function displaySearchResults(orders) {
        const rd = document.getElementById('searchResults');
        if (orders.length === 0) {
            rd.innerHTML = '<div class="search-result-item">No orders found</div>';
        } else {
            rd.innerHTML = orders.map(order => {
                const safeOrder = JSON.stringify(order).replace(/"/g, '&quot;');
                const statusClass = order.payment_status === 'paid' ? 'success' : order.payment_status === 'pending' ? 'warning' : 'danger';
                return '<div class="search-result-item" onclick="selectOrder(' + order.id + ', JSON.parse(this.dataset.order))" data-order="' + safeOrder + '">' +
                    '<div class="order-info">' +
                    '<span class="order-id">Order #' + order.id + '</span> - ' +
                    '<span class="customer-name">' + (order.customer_name || 'N/A') + '</span><br>' +
                    '<small class="text-muted">' + (order.email || '') + ' ' + (order.phone || '') + '</small><br>' +
                    '<span class="order-amount">AED ' + parseFloat(order.total_amount).toFixed(2) + '</span>' +
                    '<span class="badge bg-' + statusClass + ' ms-2">' + order.payment_status + '</span>' +
                    '</div></div>';
            }).join('');
        }
        rd.classList.add('show');
    }

    function selectOrder(orderId, orderData) {
        selectedOrderId = orderId;
        selectedOrderData = orderData;

        document.getElementById('searchResults').classList.remove('show');
        document.getElementById('orderSearch').value = 'Order #' + orderId + ' - ' + orderData.customer_name;

        document.getElementById('orderDetails').innerHTML =
            '<div class="row">' +
            '<div class="col-md-6"><strong>Order ID:</strong> #' + orderData.id + '<br>' +
            '<strong>Customer:</strong> ' + (orderData.customer_name || 'N/A') + '<br>' +
            '<strong>Email:</strong> ' + (orderData.email || 'N/A') + '</div>' +
            '<div class="col-md-6"><strong>Phone:</strong> ' + (orderData.phone || 'N/A') + '<br>' +
            '<strong>Total:</strong> AED ' + parseFloat(orderData.total_amount).toFixed(2) + '<br>' +
            '<strong>Status:</strong> <span class="badge bg-' + (orderData.payment_status === 'paid' ? 'success' : 'warning') + '">' + orderData.payment_status + '</span>' +
            '</div></div>';

        document.getElementById('selectedOrderInfo').style.display = 'block';
        document.getElementById('generateBtn').disabled = false;
        document.getElementById('linkDisplay').classList.remove('show');
    }

    document.getElementById('clearBtn').addEventListener('click', function() {
        selectedOrderId = null;
        selectedOrderData = null;
        document.getElementById('orderSearch').value = '';
        document.getElementById('searchResults').classList.remove('show');
        document.getElementById('selectedOrderInfo').style.display = 'none';
        document.getElementById('generateBtn').disabled = true;
        document.getElementById('linkDisplay').classList.remove('show');
    });

    document.getElementById('generateBtn').addEventListener('click', function() {
        if (!selectedOrderId) return;
        document.getElementById('loadingSpinner').classList.add('show');

        fetch('ajax/generate_payment_link.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: selectedOrderId, csrf_token: document.getElementById('csrfToken').value })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
            return response.json();
        })
        .then(data => {
            document.getElementById('loadingSpinner').classList.remove('show');
            if (data.success) {
                generatedPaymentLink = data.payment_link;
                displayPaymentLink(data);
            } else {
                alert('Error: ' + (data.message || 'Failed to generate payment link'));
            }
        })
        .catch(error => {
            document.getElementById('loadingSpinner').classList.remove('show');
            alert('An error occurred: ' + error.message);
        });
    });

    function displayPaymentLink(data) {
        document.getElementById('generatedLink').textContent = data.payment_link;

        const whatsappMessage = encodeURIComponent(
            'Hello ' + selectedOrderData.customer_name + ',\n\n' +
            'Your order #' + selectedOrderId + ' is ready for payment.\n' +
            'Total Amount: AED ' + parseFloat(selectedOrderData.total_amount).toFixed(2) + '\n\n' +
            'Click here to complete your payment:\n' + data.payment_link +
            '\n\nThank you for shopping with AleppoGift!'
        );

        const whatsappPhone = selectedOrderData.phone ? selectedOrderData.phone.replace(/[^0-9]/g, '') : '';
        document.getElementById('whatsappBtn').href = whatsappPhone
            ? 'https://wa.me/' + whatsappPhone + '?text=' + whatsappMessage
            : 'https://wa.me/?text=' + whatsappMessage;

        const emailSubject = encodeURIComponent('Payment for Order #' + selectedOrderId);
        const emailBody = encodeURIComponent(
            'Dear ' + selectedOrderData.customer_name + ',\n\n' +
            'Your order #' + selectedOrderId + ' is ready for payment.\n' +
            'Total Amount: AED ' + parseFloat(selectedOrderData.total_amount).toFixed(2) + '\n\n' +
            'Please click the link below to complete your payment:\n' + data.payment_link +
            '\n\nBest regards,\nAleppoGift Team'
        );

        document.getElementById('emailBtn').href = 'mailto:' + (selectedOrderData.email || '') +
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

        document.getElementById('linkDisplay').classList.add('show');
        document.getElementById('linkDisplay').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function copyToClipboard() {
        navigator.clipboard.writeText(generatedPaymentLink).then(() => {
            const btn = event.target.closest('.share-btn');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-2"></i> Copied!';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
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

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.order-search')) {
            document.getElementById('searchResults').classList.remove('show');
        }
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>