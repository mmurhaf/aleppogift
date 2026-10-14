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

// Fetch UAE Emirates for dropdown
$emirates = ['Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'];

$page_title = 'Create Order';
require_once __DIR__ . '/includes/header.php';
?>
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
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e3e6f0;
        }
        
        .product-item {
            border: 1px solid #e3e6f0;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 0.35rem;
            background: #f8f9fc;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.25rem;
        }
        
        .customer-preview {
            background: #f8f9fc;
            padding: 1rem;
            border-radius: 0.35rem;
            border: 1px solid #e3e6f0;
            margin-top: 1rem;
        }
        
        .order-summary {
            position: sticky;
            top: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .order-summary h5 {
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .summary-total {
            font-size: 1.3rem;
            font-weight: bold;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-create-order {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-create-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .search-dropdown {
            position: absolute;
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            width: 100%;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            display: none;
        }
        
        .search-item {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fc;
        }
        
        .search-item:hover {
            background: #f8f9fc;
        }
        
        .selected-products-list {
            min-height: 100px;
        }
        
        .empty-state {
            text-align: center;
            color: #858796;
            padding: 2rem;
        }
        
        .badge-payment {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-plus-circle text-primary"></i> Create New Order
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                            <li class="breadcrumb-item active">Create Order</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="quick_payment_link.php" class="btn btn-warning me-2">
                        <i class="fas fa-bolt me-1"></i> Quick Payment
                    </a>
                    <a href="payment_links.php" class="btn btn-success me-2">
                        <i class="fas fa-link me-1"></i> Payment Links
                    </a>
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>

        <form id="createOrderForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row">
                <!-- Left Column - Form -->
                <div class="col-lg-8">
                    <!-- Customer Selection -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-user me-2"></i>Customer Information
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Search Existing Customer</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="customerSearch" 
                                           placeholder="Search by name, email, or phone...">
                                    <div id="customerDropdown" class="search-dropdown"></div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-primary w-100" id="newCustomerBtn">
                                    <i class="fas fa-user-plus me-1"></i> Create New Customer
                                </button>
                            </div>
                        </div>

                        <!-- New Customer Form (Hidden by default) -->
                        <div id="newCustomerForm" style="display: none;">
                            <hr>
                            <h6 class="mb-3">New Customer Details</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="customer_name" id="customerName" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="customer_email" id="customerEmail" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="text" class="form-control" name="customer_phone" id="customerPhone" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City/Emirate *</label>
                                    <select class="form-select" name="customer_city" id="customerCity" required>
                                        <option value="">Select Emirate</option>
                                        <?php foreach($emirates as $emirate): ?>
                                            <option value="<?php echo $emirate; ?>"><?php echo $emirate; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address *</label>
                                    <textarea class="form-control" name="customer_address" id="customerAddress" rows="2" required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Customer Preview -->
                        <div id="customerPreview" class="customer-preview" style="display: none;">
                            <h6><i class="fas fa-check-circle text-success"></i> Selected Customer</h6>
                            <input type="hidden" id="selectedCustomerId" name="customer_id">
                            <div id="customerPreviewContent"></div>
                        </div>
                    </div>

                    <!-- Product Selection -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-shopping-cart me-2"></i>Products
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Search Products</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="productSearch" 
                                       placeholder="Search products by name or SKU...">
                                <div id="productDropdown" class="search-dropdown"></div>
                            </div>
                        </div>

                        <div id="selectedProducts" class="selected-products-list">
                            <div class="empty-state">
                                <i class="fas fa-box-open fa-3x mb-3"></i>
                                <p>No products added yet. Search and add products above.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping & Payment -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-truck me-2"></i>Shipping & Payment
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shipping Method *</label>
                                <select class="form-select" name="shipping_method" id="shippingMethod" required>
                                    <option value="">Select Shipping Method</option>
                                    <option value="standard">Standard Shipping</option>
                                    <option value="express">Express Shipping</option>
                                    <option value="pickup">Store Pickup</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shipping Cost (AED)</label>
                                <input type="number" class="form-control" name="shipping_cost" id="shippingCost" 
                                       value="0" step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-select" name="payment_method" id="paymentMethod" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cod">Cash on Delivery (COD)</option>
                                    <option value="online">Online Payment</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Status</label>
                                <select class="form-select" name="payment_status" id="paymentStatus">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Order Notes</label>
                                <textarea class="form-control" name="order_notes" id="orderNotes" 
                                          rows="3" placeholder="Any special instructions or notes..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-section">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="sendPaymentLinkBtn">
                                <i class="fas fa-paper-plane me-1"></i> Create Order & Send Payment Link
                            </button>
                            <button type="button" class="btn btn-success" id="createOrderBtn">
                                <i class="fas fa-check me-1"></i> Create Order Only
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='orders.php'">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h5><i class="fas fa-calculator me-2"></i>Order Summary</h5>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="summarySubtotal">AED 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span id="summaryShipping">AED 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Items:</span>
                            <span id="summaryItemCount">0</span>
                        </div>
                        
                        <div class="summary-total">
                            <div class="d-flex justify-content-between">
                                <span>Total:</span>
                                <span id="summaryTotal">AED 0.00</span>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-top border-white border-opacity-25">
                            <small class="d-block mb-2">
                                <i class="fas fa-info-circle me-1"></i> Payment Link Features:
                            </small>
                            <ul class="small mb-0 ps-3">
                                <li>PDF invoice attached</li>
                                <li>Direct payment gateway link</li>
                                <li>Order tracking information</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store selected products
        let selectedProducts = [];
        let selectedCustomer = null;
        let searchTimeout = null;

        // Customer Search
        document.getElementById('customerSearch').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                document.getElementById('customerDropdown').style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch('ajax/search_customers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: query })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.customers.length > 0) {
                        displayCustomerDropdown(data.customers);
                    } else {
                        document.getElementById('customerDropdown').style.display = 'none';
                    }
                });
            }, 300);
        });

        function displayCustomerDropdown(customers) {
            const dropdown = document.getElementById('customerDropdown');
            dropdown.innerHTML = customers.map(customer => `
                <div class="search-item" onclick='selectCustomer(${JSON.stringify(customer)})'>
                    <strong>${customer.fullname}</strong><br>
                    <small class="text-muted">${customer.email} | ${customer.phone}</small>
                </div>
            `).join('');
            dropdown.style.display = 'block';
        }

        function selectCustomer(customer) {
            selectedCustomer = customer;
            document.getElementById('selectedCustomerId').value = customer.id;
            document.getElementById('customerDropdown').style.display = 'none';
            document.getElementById('customerSearch').value = customer.fullname;
            document.getElementById('newCustomerForm').style.display = 'none';
            
            document.getElementById('customerPreviewContent').innerHTML = `
                <p class="mb-1"><strong>Name:</strong> ${customer.fullname}</p>
                <p class="mb-1"><strong>Email:</strong> ${customer.email}</p>
                <p class="mb-1"><strong>Phone:</strong> ${customer.phone}</p>
                <p class="mb-0"><strong>City:</strong> ${customer.city || 'N/A'}</p>
            `;
            document.getElementById('customerPreview').style.display = 'block';
        }

        // New Customer Toggle
        document.getElementById('newCustomerBtn').addEventListener('click', function() {
            const form = document.getElementById('newCustomerForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            document.getElementById('customerPreview').style.display = 'none';
            document.getElementById('customerSearch').value = '';
            selectedCustomer = null;
        });

        // Product Search
        document.getElementById('productSearch').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                document.getElementById('productDropdown').style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch('ajax/search_products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: query })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.products.length > 0) {
                        displayProductDropdown(data.products);
                    } else {
                        document.getElementById('productDropdown').style.display = 'none';
                    }
                });
            }, 300);
        });

        function displayProductDropdown(products) {
            const dropdown = document.getElementById('productDropdown');
            dropdown.innerHTML = products.map(product => `
                <div class="search-item" onclick='addProduct(${JSON.stringify(product)})'>
                    <strong>${product.name_en}</strong><br>
                    <small class="text-muted">AED ${product.price} | Stock: ${product.stock}</small>
                </div>
            `).join('');
            dropdown.style.display = 'block';
        }

        function addProduct(product) {
            // Check if product already added
            const exists = selectedProducts.find(p => p.id === product.id);
            if (exists) {
                alert('Product already added to order');
                return;
            }

            selectedProducts.push({
                ...product,
                quantity: 1
            });

            document.getElementById('productDropdown').style.display = 'none';
            document.getElementById('productSearch').value = '';
            updateProductsList();
            updateSummary();
        }

        function updateProductsList() {
            const container = document.getElementById('selectedProducts');
            
            if (selectedProducts.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-box-open fa-3x mb-3"></i>
                        <p>No products added yet. Search and add products above.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = selectedProducts.map((product, index) => `
                <div class="product-item">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            ${product.image_path ? 
                                `<img src="../${product.image_path}" class="product-image" alt="${product.name_en}">` :
                                '<div class="product-image bg-secondary d-flex align-items-center justify-content-center"><i class="fas fa-image text-white"></i></div>'
                            }
                        </div>
                        <div class="col">
                            <strong>${product.name_en}</strong><br>
                            <small class="text-muted">SKU: ${product.sku || 'N/A'} | Price: AED ${product.price}</small>
                        </div>
                        <div class="col-auto">
                            <div class="input-group" style="width: 120px;">
                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                        onclick="updateQuantity(${index}, -1)">-</button>
                                <input type="number" class="form-control form-control-sm text-center" 
                                       value="${product.quantity}" min="1" max="${product.stock}"
                                       onchange="setQuantity(${index}, this.value)">
                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                        onclick="updateQuantity(${index}, 1)">+</button>
                            </div>
                        </div>
                        <div class="col-auto">
                            <strong>AED ${(product.price * product.quantity).toFixed(2)}</strong>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-danger btn-sm" onclick="removeProduct(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateQuantity(index, delta) {
            const product = selectedProducts[index];
            const newQty = product.quantity + delta;
            
            if (newQty < 1 || newQty > product.stock) return;
            
            product.quantity = newQty;
            updateProductsList();
            updateSummary();
        }

        function setQuantity(index, value) {
            const qty = parseInt(value);
            const product = selectedProducts[index];
            
            if (qty < 1 || qty > product.stock) {
                updateProductsList();
                return;
            }
            
            product.quantity = qty;
            updateSummary();
        }

        function removeProduct(index) {
            selectedProducts.splice(index, 1);
            updateProductsList();
            updateSummary();
        }

        function updateSummary() {
            const subtotal = selectedProducts.reduce((sum, p) => sum + (p.price * p.quantity), 0);
            const shipping = parseFloat(document.getElementById('shippingCost').value) || 0;
            const total = subtotal + shipping;
            
            document.getElementById('summarySubtotal').textContent = `AED ${subtotal.toFixed(2)}`;
            document.getElementById('summaryShipping').textContent = `AED ${shipping.toFixed(2)}`;
            document.getElementById('summaryItemCount').textContent = selectedProducts.length;
            document.getElementById('summaryTotal').textContent = `AED ${total.toFixed(2)}`;
        }

        // Shipping cost change
        document.getElementById('shippingCost').addEventListener('input', updateSummary);

        // Create Order
        document.getElementById('createOrderBtn').addEventListener('click', function() {
            submitOrder(false);
        });

        document.getElementById('sendPaymentLinkBtn').addEventListener('click', function() {
            submitOrder(true);
        });

        function submitOrder(sendPaymentLink) {
            // Validation
            if (!selectedCustomer && document.getElementById('newCustomerForm').style.display === 'none') {
                alert('Please select or create a customer');
                return;
            }

            if (selectedProducts.length === 0) {
                alert('Please add at least one product');
                return;
            }

            const shippingMethod = document.getElementById('shippingMethod').value;
            const paymentMethod = document.getElementById('paymentMethod').value;

            if (!shippingMethod || !paymentMethod) {
                alert('Please select shipping and payment methods');
                return;
            }

            // Prepare order data
            const orderData = {
                csrf_token: document.querySelector('[name="csrf_token"]').value,
                customer: selectedCustomer ? { id: selectedCustomer.id } : {
                    name: document.getElementById('customerName').value,
                    email: document.getElementById('customerEmail').value,
                    phone: document.getElementById('customerPhone').value,
                    city: document.getElementById('customerCity').value,
                    address: document.getElementById('customerAddress').value,
                    country: 'United Arab Emirates'
                },
                products: selectedProducts.map(p => ({
                    id: p.id,
                    quantity: p.quantity,
                    price: p.price
                })),
                shipping_method: shippingMethod,
                shipping_cost: parseFloat(document.getElementById('shippingCost').value) || 0,
                payment_method: paymentMethod,
                payment_status: document.getElementById('paymentStatus').value,
                notes: document.getElementById('orderNotes').value,
                send_payment_link: sendPaymentLink
            };

            // Show loading
            const btn = sendPaymentLink ? document.getElementById('sendPaymentLinkBtn') : document.getElementById('createOrderBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

            // Submit order
            fetch('ajax/process_admin_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    alert('Order created successfully! Order ID: #' + data.order_id);
                    window.location.href = 'order_detail.php?id=' + data.order_id;
                } else {
                    alert('Error: ' + (data.error || 'Failed to create order'));
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                alert('Error: ' + error.message);
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.position-relative')) {
                document.getElementById('customerDropdown').style.display = 'none';
                document.getElementById('productDropdown').style.display = 'none';
            }
        });
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
