<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Cart Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Final Cart Functionality Test</h2>
        
        <div class="alert alert-info">
            <h5>Test Instructions:</h5>
            <ol>
                <li><strong>Add Item to Cart:</strong> Click "Add Test Item" to add a product to cart</li>
                <li><strong>Open Cart Multiple Times:</strong> Click "Open Cart" 3-4 times and verify items show consistently</li>
                <li><strong>Test Mobile Cart:</strong> Resize window to mobile size and test mobile cart button</li>
                <li><strong>Check Console:</strong> Open browser console to see debug information</li>
            </ol>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Cart Actions</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-success mb-2" onclick="addTestItem()">
                            <i class="fas fa-plus"></i> Add Test Item to Cart
                        </button>
                        <br>
                        <button class="btn btn-primary mb-2" 
                                data-bs-toggle="offcanvas" 
                                data-bs-target="#cartOffcanvas">
                            <i class="fas fa-shopping-cart"></i> Open Cart (Test #<span id="openCounter">1</span>)
                        </button>
                        <br>
                        <button class="btn btn-info mb-2" onclick="checkCartCount()">
                            <i class="fas fa-hashtag"></i> Check Cart Count
                        </button>
                        <br>
                        <button class="btn btn-warning mb-2" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Clear Cart
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div id="testResults">
                            <p class="text-muted">No tests run yet...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile Cart Button (visible only on small screens) -->
        <div class="d-md-none fixed-bottom p-3" style="background: rgba(255,255,255,0.9);">
            <button class="btn btn-primary w-100" 
                    data-bs-toggle="offcanvas" 
                    data-bs-target="#cartOffcanvas">
                <i class="fas fa-shopping-cart"></i> Mobile Cart
                <span class="badge bg-danger ms-2" id="cart-count-mobile">0</span>
            </button>
        </div>
    </div>

    <!-- Cart Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header bg-primary text-white">
            <h5 class="offcanvas-title" id="cartOffcanvasLabel">
                <i class="fas fa-shopping-cart me-2"></i>Your Cart
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div id="cartPreview">
                <div class="text-center p-4">
                    <p class="text-muted">Cart will load when opened...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let openCount = 0;
        let testResults = [];
        
        function updateResults() {
            const resultsDiv = document.getElementById('testResults');
            if (testResults.length === 0) {
                resultsDiv.innerHTML = '<p class="text-muted">No tests run yet...</p>';
            } else {
                resultsDiv.innerHTML = '<ul class="list-unstyled">' + 
                    testResults.map(result => `<li class="${result.success ? 'text-success' : 'text-danger'}">
                        <i class="fas fa-${result.success ? 'check' : 'times'}"></i> ${result.message}
                    </li>`).join('') + '</ul>';
            }
        }
        
        function addResult(message, success = true) {
            testResults.push({ message, success, time: new Date().toLocaleTimeString() });
            updateResults();
            console.log(`${success ? '✅' : '❌'} ${message}`);
        }
        
        // Initialize cart offcanvas event listener
        document.addEventListener('DOMContentLoaded', function() {
            const cartOffcanvas = document.getElementById('cartOffcanvas');
            if (cartOffcanvas) {
                cartOffcanvas.addEventListener('show.bs.offcanvas', function () {
                    openCount++;
                    document.getElementById('openCounter').textContent = openCount + 1;
                    
                    addResult(`Cart opening attempt #${openCount}`);
                    
                    $('#cartPreview').html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading cart...</p></div>');
                    
                    $('#cartPreview').load('ajax/cart_preview.php', function(response, status, xhr) {
                        if (status === "error") {
                            addResult(`Cart load failed (attempt #${openCount}): ${xhr.statusText}`, false);
                        } else {
                            const hasContent = response.includes('cart-item-preview') || response.includes('Your cart is empty');
                            addResult(`Cart loaded successfully (attempt #${openCount}) - Content: ${hasContent ? 'Found' : 'None'}`, hasContent);
                        }
                    });
                });
            }
            
            // Initial cart count check
            checkCartCount();
        });
        
        function addTestItem() {
            $.post('ajax/add_to_cart.php', {
                product_id: 1,
                quantity: 1
            }, function(res) {
                if (res.success) {
                    addResult(`Test item added to cart. New count: ${res.count}`);
                    $('#cart-count-mobile').text(res.count);
                } else {
                    addResult(`Failed to add test item: ${res.message}`, false);
                }
            }).fail(function(xhr) {
                addResult(`Add to cart failed: ${xhr.statusText}`, false);
            });
        }
        
        function checkCartCount() {
            $.get('ajax/get_cart_count.php', function(res) {
                if (res.success) {
                    addResult(`Current cart count: ${res.count}`);
                    $('#cart-count-mobile').text(res.count);
                } else {
                    addResult(`Failed to get cart count: ${res.message}`, false);
                }
            }).fail(function(xhr) {
                addResult(`Cart count check failed: ${xhr.statusText}`, false);
            });
        }
        
        function clearCart() {
            // This would need a clear_cart.php endpoint, but for now just show what would happen
            addResult(`Clear cart functionality would need to be implemented`);
        }
    </script>
</body>
</html>
