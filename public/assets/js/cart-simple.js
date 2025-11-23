/**
 * Simplified and Optimized Cart JavaScript
 * Clean, fast, and maintainable cart functionality
 */

// Simple toast notification system
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    const toastId = 'toast-' + Date.now();
    
    const colors = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info'
    };
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-white ${colors[type] || colors.success}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Simplified Add to Cart
function addToCart(productId, quantity = 1) {
    // Basic validation
    if (!productId || productId < 1) {
        showToast('Invalid product', 'error');
        return;
    }
    
    // Get button if called from button click
    const button = event?.target?.closest('button');
    const originalText = button?.innerHTML;
    
    // Show loading state
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    }
    
    // AJAX request
    fetch('ajax/add_to_cart_simple.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count
            updateCartCount(data.count);
            
            // Update cart preview if visible
            updateCartPreview();
            
            // Show success message
            showToast(data.message || 'Added to cart!');
            
            // Button success state
            if (button) {
                button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                button.className = button.className.replace('btn-primary', 'btn-success');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.className = button.className.replace('btn-success', 'btn-primary');
                    button.disabled = false;
                }, 1500);
            }
        } else {
            throw new Error(data.message || 'Failed to add to cart');
        }
    })
    .catch(error => {
        showToast(error.message, 'error');
        
        // Reset button
        if (button) {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

// Simplified Cart Operations
function cartOperation(action, productId) {
    const button = event?.target?.closest('button');
    const originalContent = button?.innerHTML;
    
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    fetch('ajax/cart_operations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count
            updateCartCount(data.count);
            
            // Handle specific actions
            if (action === 'remove') {
                // Remove item from DOM
                const cartItem = button.closest('.cart-item, .cart-item-modern, .cart-preview-item');
                if (cartItem) {
                    cartItem.style.transition = 'opacity 0.3s';
                    cartItem.style.opacity = '0';
                    setTimeout(() => cartItem.remove(), 300);
                }
                
                // Reload page if cart is empty and on cart page
                if (data.count === 0 && window.location.pathname.includes('cart.php')) {
                    setTimeout(() => location.reload(), 500);
                }
            } else if (action === 'increase' || action === 'decrease') {
                // Update quantity display
                const qtyDisplay = button.closest('.cart-item, .cart-item-modern')?.querySelector('.quantity-display');
                if (qtyDisplay) {
                    qtyDisplay.textContent = data.new_quantity;
                }
                
                // Update item total if needed
                updateItemTotal(productId, data.new_quantity);
            }
            
            // Update cart preview
            updateCartPreview();
            
            showToast(data.message);
        } else {
            throw new Error(data.message || 'Operation failed');
        }
    })
    .catch(error => {
        showToast(error.message, 'error');
    })
    .finally(() => {
        if (button) {
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    });
}

// Update cart count in UI
function updateCartCount(count) {
    const countElements = document.querySelectorAll('[id*="cart-count"], [id*="cart_count"]');
    countElements.forEach(el => el.textContent = count || '0');
}

// Update cart preview
function updateCartPreview() {
    const preview = document.getElementById('cartPreview');
    if (preview && preview.style.display !== 'none') {
        fetch('ajax/cart_preview_simple.php')
            .then(response => response.text())
            .then(html => {
                const content = preview.querySelector('#cart-items-preview, .cart-preview-content');
                if (content) {
                    content.innerHTML = html;
                }
            })
            .catch(() => {}); // Silent fail
    }
}

// Update individual item total (for cart page)
function updateItemTotal(productId, quantity) {
    const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
    if (!cartItem) return;
    
    const priceElement = cartItem.querySelector('[data-price]');
    const totalElement = cartItem.querySelector('.item-total-price, .item-total');
    
    if (priceElement && totalElement) {
        const price = parseFloat(priceElement.dataset.price || priceElement.textContent.replace(/[^\d.]/g, ''));
        const total = price * quantity;
        totalElement.innerHTML = `<strong>AED ${total.toFixed(2)}</strong>`;
    }
}

// Toggle cart preview
function toggleCart() {
    const preview = document.getElementById('cartPreview');
    if (!preview) return;
    
    if (preview.style.display === 'none' || !preview.style.display) {
        updateCartPreview();
        preview.style.display = 'block';
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (preview.style.display === 'block') {
                preview.style.display = 'none';
            }
        }, 10000);
    } else {
        preview.style.display = 'none';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Load initial cart count
    fetch('ajax/cart_operations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.count);
        }
    })
    .catch(() => {}); // Silent fail
    
    // Event delegation for cart buttons
    document.addEventListener('click', function(e) {
        const target = e.target.closest('button');
        if (!target) return;
        
        // Add to cart buttons
        if (target.classList.contains('btn-add-cart') || target.hasAttribute('data-add-cart')) {
            e.preventDefault();
            const productId = target.dataset.id || target.dataset.productId;
            const quantity = target.dataset.quantity || 1;
            if (productId) {
                addToCart(productId, quantity);
            }
        }
        
        // Cart operation buttons
        if (target.classList.contains('update-qty')) {
            e.preventDefault();
            const action = target.dataset.action;
            const productId = target.dataset.id;
            if (action && productId) {
                cartOperation(action, productId);
            }
        }
        
        if (target.classList.contains('remove-item')) {
            e.preventDefault();
            const productId = target.dataset.id;
            if (productId && confirm('Remove this item from cart?')) {
                cartOperation('remove', productId);
            }
        }
    });
    
    // Form submission for add to cart
    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('add-to-cart-form')) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const productId = formData.get('product_id');
            const quantity = formData.get('quantity') || 1;
            if (productId) {
                addToCart(productId, quantity);
            }
        }
    });
});

// Make functions globally available
window.addToCart = addToCart;
window.cartOperation = cartOperation;
window.toggleCart = toggleCart;
window.showToast = showToast;
