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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Link Generator - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        body {
            background-color: #f5f7fa;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .page-header {
            background: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
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
        
        .copy-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.3);
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
        
        .share-btn.whatsapp {
            background: #25D366;
        }
        
        .share-btn.whatsapp:hover {
            background: #1ea952;
        }
        
        .share-btn.email {
            background: #EA4335;
        }
        
        .share-btn.email:hover {
            background: #c5362e;
        }
        
        .share-btn.copy {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .share-btn.copy:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .order-search {
            position: relative;
        }
        
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
        
        .search-results.show {
            display: block;
        }
        
        .search-result-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        .search-result-item:hover {
            background: #f8f9fa;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .order-info {
            font-size: 0.9rem;
        }
        
        .order-id {
            font-weight: 600;
            color: #ff7f00;
        }
        
        .customer-name {
            color: #333;
            font-weight: 500;
        }
        
        .order-amount {
            color: #667eea;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 1rem;
        }
        
        .loading.show {
            display: block;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        .qr-code-section {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        #qrCode {
            margin: 1rem auto;
            display: inline-block;
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-gifts"></i> <span>AleppoGift</span></h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-box-open"></i> <span>Products</span></a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
            <li><a href="brands.php"><i class="fas fa-copyright"></i> <span>Brands</span></a></li>
            <li><a href="coupons.php"><i class="fas fa-ticket-alt"></i> <span>Coupons</span></a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
            <li><a href="create_order.php"><i class="fas fa-plus-circle"></i> <span>Create Order</span></a></li>
            <li class="active"><a href="payment_links.php"><i class="fas fa-link"></i> <span>Payment Links</span></a></li>
            <li><a href="quick_payment_link.php"><i class="fas fa-bolt"></i> <span>Quick Payment</span></a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> <span>Customers</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="header-title">
                <button class="header-menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1><i class="fas fa-link me-2"></i> Payment Link Generator</h1>
                    <p class="text-muted mb-0">Generate and share secure payment links for orders</p>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-search me-2"></i> Find Order</h2>
            
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        let selectedOrderId = null;
        let selectedOrderData = null;
        let searchTimeout = null;
        let generatedPaymentLink = '';

        // Order search with debounce
        document.getElementById('orderSearch').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                document.getElementById('searchResults').classList.remove('show');
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchOrders(query);
            }, 300);
        });

        // Search orders
        function searchOrders(query) {
            fetch('ajax/search_orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    query: query,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Search response:', data);
                if (data.success) {
                    displaySearchResults(data.orders);
                } else {
                    console.error('Search failed:', data.message);
                    const resultsDiv = document.getElementById('searchResults');
                    resultsDiv.innerHTML = '<div class="search-result-item text-danger">Error: ' + (data.message || 'Search failed') + '</div>';
                    resultsDiv.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                const resultsDiv = document.getElementById('searchResults');
                resultsDiv.innerHTML = '<div class="search-result-item text-danger">Error connecting to server. Please try again.</div>';
                resultsDiv.classList.add('show');
            });
        }

        // Display search results
        function displaySearchResults(orders) {
            const resultsDiv = document.getElementById('searchResults');
            
            if (orders.length === 0) {
                resultsDiv.innerHTML = '<div class="search-result-item">No orders found</div>';
            } else {
                resultsDiv.innerHTML = orders.map(order => `
                    <div class="search-result-item" onclick="selectOrder(${order.id}, ${JSON.stringify(order).replace(/"/g, '&quot;')})">
                        <div class="order-info">
                            <span class="order-id">Order #${order.id}</span> - 
                            <span class="customer-name">${order.customer_name || 'N/A'}</span>
                            <br>
                            <small class="text-muted">${order.email || ''} ${order.phone || ''}</small>
                            <br>
                            <span class="order-amount">AED ${parseFloat(order.total_amount).toFixed(2)}</span>
                            <span class="badge bg-${order.payment_status === 'paid' ? 'success' : order.payment_status === 'pending' ? 'warning' : 'danger'} ms-2">
                                ${order.payment_status}
                            </span>
                        </div>
                    </div>
                `).join('');
            }
            
            resultsDiv.classList.add('show');
        }

        // Select an order
        function selectOrder(orderId, orderData) {
            selectedOrderId = orderId;
            selectedOrderData = orderData;
            
            // Hide search results
            document.getElementById('searchResults').classList.remove('show');
            
            // Update search input
            document.getElementById('orderSearch').value = `Order #${orderId} - ${orderData.customer_name}`;
            
            // Display order info
            document.getElementById('orderDetails').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Order ID:</strong> #${orderData.id}<br>
                        <strong>Customer:</strong> ${orderData.customer_name || 'N/A'}<br>
                        <strong>Email:</strong> ${orderData.email || 'N/A'}
                    </div>
                    <div class="col-md-6">
                        <strong>Phone:</strong> ${orderData.phone || 'N/A'}<br>
                        <strong>Total:</strong> AED ${parseFloat(orderData.total_amount).toFixed(2)}<br>
                        <strong>Status:</strong> <span class="badge bg-${orderData.payment_status === 'paid' ? 'success' : 'warning'}">${orderData.payment_status}</span>
                    </div>
                </div>
            `;
            document.getElementById('selectedOrderInfo').style.display = 'block';
            
            // Enable generate button
            document.getElementById('generateBtn').disabled = false;
            
            // Hide previous link if exists
            document.getElementById('linkDisplay').classList.remove('show');
        }

        // Clear selection
        document.getElementById('clearBtn').addEventListener('click', function() {
            selectedOrderId = null;
            selectedOrderData = null;
            document.getElementById('orderSearch').value = '';
            document.getElementById('searchResults').classList.remove('show');
            document.getElementById('selectedOrderInfo').style.display = 'none';
            document.getElementById('generateBtn').disabled = true;
            document.getElementById('linkDisplay').classList.remove('show');
        });

        // Generate payment link
        document.getElementById('generateBtn').addEventListener('click', function() {
            if (!selectedOrderId) return;
            
            // Show loading
            document.getElementById('loadingSpinner').classList.add('show');
            
            fetch('ajax/generate_payment_link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: selectedOrderId,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                document.getElementById('loadingSpinner').classList.remove('show');
                console.log('Generate link response:', data);
                
                if (data.success) {
                    generatedPaymentLink = data.payment_link;
                    displayPaymentLink(data);
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate payment link'));
                }
            })
            .catch(error => {
                document.getElementById('loadingSpinner').classList.remove('show');
                console.error('Generate link error:', error);
                alert('An error occurred: ' + error.message + '\nPlease check the browser console for details.');
            });
        });

        // Display payment link
        function displayPaymentLink(data) {
            document.getElementById('generatedLink').textContent = data.payment_link;
            
            // Update WhatsApp link
            const whatsappMessage = encodeURIComponent(
                `Hello ${selectedOrderData.customer_name},\n\n` +
                `Your order #${selectedOrderId} is ready for payment.\n` +
                `Total Amount: AED ${parseFloat(selectedOrderData.total_amount).toFixed(2)}\n\n` +
                `Click here to complete your payment:\n${data.payment_link}\n\n` +
                `Thank you for shopping with AleppoGift!`
            );
            
            const whatsappPhone = selectedOrderData.phone ? selectedOrderData.phone.replace(/[^0-9]/g, '') : '';
            const whatsappUrl = whatsappPhone 
                ? `https://wa.me/${whatsappPhone}?text=${whatsappMessage}`
                : `https://wa.me/?text=${whatsappMessage}`;
            
            document.getElementById('whatsappBtn').href = whatsappUrl;
            
            // Update Email link
            const emailSubject = encodeURIComponent(`Payment for Order #${selectedOrderId}`);
            const emailBody = encodeURIComponent(
                `Dear ${selectedOrderData.customer_name},\n\n` +
                `Your order #${selectedOrderId} is ready for payment.\n` +
                `Total Amount: AED ${parseFloat(selectedOrderData.total_amount).toFixed(2)}\n\n` +
                `Please click the link below to complete your payment:\n${data.payment_link}\n\n` +
                `If you have any questions, please don't hesitate to contact us.\n\n` +
                `Best regards,\nAleppoGift Team`
            );
            
            const emailTo = selectedOrderData.email || '';
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
            
            // Show link display
            document.getElementById('linkDisplay').classList.add('show');
            
            // Scroll to link
            document.getElementById('linkDisplay').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Copy to clipboard
        function copyToClipboard() {
            navigator.clipboard.writeText(generatedPaymentLink).then(() => {
                const btn = event.target.closest('.share-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-2"></i> Copied!';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            }).catch(err => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = generatedPaymentLink;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                alert('Link copied to clipboard!');
            });
        }

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.order-search')) {
                document.getElementById('searchResults').classList.remove('show');
            }
        });
    </script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        // Close sidebar when clicking on a menu item (mobile only)
        document.addEventListener('DOMContentLoaded', function() {
            const menuLinks = document.querySelectorAll('.sidebar-menu a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        toggleSidebar();
                    }
                });
            });
        });
    </script>
</body>
</html>
