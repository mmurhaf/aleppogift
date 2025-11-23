<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart Preview Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Cart Preview Test</h2>
        
        <!-- Test Cart Button -->
        <button class="btn btn-primary position-relative" onclick="toggleCartPreview()">
            <i class="fas fa-shopping-cart me-1"></i>
            Cart
            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="cart-count">0</span>
        </button>
        
        <!-- Add Test Product -->
        <div class="mt-3">
            <form class="add-to-cart-form" method="post" action="ajax/add_to_cart.php">
                <input type="hidden" name="product_id" value="1">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-success">Add Test Product to Cart</button>
            </form>
        </div>
    </div>
    
    <!-- Simplified Cart Preview -->
    <div id="cartPreview" class="card shadow position-absolute end-0 mt-2 me-4" style="display: none; z-index: 1050; width: 350px; max-height: 500px; overflow-y: auto;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Cart Preview</h6>
            <button type="button" class="btn-close btn-sm" onclick="hideCartPreview()"></button>
        </div>
        <div class="card-body p-0">
            <div id="cart-items-container">
                <div class="text-center p-3">
                    <i class="fas fa-shopping-cart text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 mb-0">Your cart is empty</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple cart preview toggle
        function toggleCartPreview() {
            const preview = document.getElementById('cartPreview');
            if (preview.style.display === 'none' || preview.style.display === '') {
                showCartPreview();
            } else {
                hideCartPreview();
            }
        }
        
        function showCartPreview() {
            const preview = document.getElementById('cartPreview');
            const container = document.getElementById('cart-items-container');
            
            preview.style.display = 'block';
            
            fetch('ajax/simple_cart_preview.php')
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                    console.log('Cart preview loaded successfully');
                })
                .catch(error => {
                    console.error('Error loading cart:', error);
                    container.innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i><p class="mb-0 small">Error loading cart</p></div>';
                });
                
            setTimeout(hideCartPreview, 10000);
        }
        
        function hideCartPreview() {
            document.getElementById('cartPreview').style.display = 'none';
        }
        
        document.addEventListener('click', function(event) {
            const preview = document.getElementById('cartPreview');
            const cartButton = document.querySelector('button[onclick="toggleCartPreview()"]');
            
            if (preview && preview.style.display === 'block' && 
                !preview.contains(event.target) && 
                !cartButton.contains(event.target)) {
                hideCartPreview();
            }
        });
        
        function removeFromCart(productId) {
            if (!confirm('Remove this item from cart?')) return;
            
            fetch('ajax/remove_from_cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-count').textContent = data.count || 0;
                    showCartPreview();
                    alert('Item removed from cart');
                } else {
                    alert('Error removing item from cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error removing item from cart');
            });
        }
        
        $(document).ready(function() {
            $(document).on('submit', '.add-to-cart-form', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const button = form.find('button[type="submit"]');
                const originalText = button.html();
                
                button.prop('disabled', true);
                button.html('<i class="fas fa-spinner fa-spin me-1"></i>Adding...');
                
                $.post('ajax/add_to_cart.php', form.serialize())
                    .done(function(response) {
                        if (response.success) {
                            $('#cart-count').text(response.cart_count || 0);
                            
                            button.html('<i class="fas fa-check me-1"></i>Added!');
                            button.removeClass('btn-success').addClass('btn-primary');
                            
                            setTimeout(showCartPreview, 500);
                            
                            setTimeout(function() {
                                button.html(originalText);
                                button.removeClass('btn-primary').addClass('btn-success');
                                button.prop('disabled', false);
                            }, 2000);
                            
                        } else {
                            throw new Error(response.message || 'Unknown error');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Add to cart error:', error);
                        button.html('<i class="fas fa-exclamation-triangle me-1"></i>Error');
                        
                        setTimeout(function() {
                            button.html(originalText);
                            button.prop('disabled', false);
                        }, 3000);
                    });
            });
        });
    </script>
</body>
</html>
