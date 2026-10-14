/**
 * Simplified and Optimized Cart JavaScript
 * Clean, fast, and maintainable cart functionality
 * Fixed: Consolidated cart logic, improved event handling
 */

var __ = function (k, p, fallback) {
    var s = (window.AG_I18N && window.AG_I18N[k]) || fallback || k;
    if (p) { for (var n in p) { s = s.split('{' + n + '}').join(p[n]); } }
    return s;
};

// Prevent duplicate initialization
if (window.cartInitialized) {
    console.log('Cart already initialized, skipping...');
} else {
    window.cartInitialized = true;

// Auto-hide timer for cart preview
let cartAutoHideTimer = null;

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

// Fixed: Add to Cart with proper event handling
function addToCart(productId, quantity = 1, eventObj = null) {
    // Basic validation
    if (!productId || productId < 1) {
        showToast(__('invalid_product', null, 'Invalid product'), 'error');
        return;
    }
    
    // Get button - use passed event or try to find from global event
    const evt = eventObj || (typeof event !== 'undefined' ? event : null);
    const button = evt?.target?.closest('button') || 
                   document.querySelector(`[data-product-id="${productId}"]`);
    const originalText = button?.innerHTML;
    const originalClasses = button?.className;
    
    // Show loading state
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + __('adding', null, 'Adding...');
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
            // Update all cart counts
            updateAllCartCounts(data.count);
            
            // Update cart preview if visible
            refreshCartPreview();
            
            // Show success message
            showToast(data.message || __('added_to_cart', null, 'Added to cart!'));
            
            // Button success state - Fixed: store and restore original classes
            if (button) {
                button.classList.remove('btn-primary', 'btn-outline-primary');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="fas fa-check me-2"></i>' + __('added_excl', null, 'Added!');
                
                setTimeout(() => {
                    button.className = originalClasses;
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 1500);
            }
        } else {
            throw new Error(data.message || __('failed_to_add', null, 'Failed to add to cart'));
        }
    })
    .catch(error => {
        showToast(error.message, 'error');
        
        // Reset button
        if (button) {
            button.innerHTML = originalText;
            button.className = originalClasses;
            button.disabled = false;
        }
    });
}

// Simplified Cart Operations
function cartOperation(action, productId, eventObj = null) {
    const evt = eventObj || (typeof event !== 'undefined' ? event : null);
    const button = evt?.target?.closest('button');
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
            // Update all cart counts
            updateAllCartCounts(data.count);
            
            // Handle specific actions
            if (action === 'remove') {
                // Remove item from DOM
                const cartItem = button?.closest('.cart-item, .cart-item-modern, .cart-preview-item, .cart-item-preview');
                if (cartItem) {
                    cartItem.style.transition = 'opacity 0.3s, transform 0.3s';
                    cartItem.style.opacity = '0';
                    cartItem.style.transform = 'translateX(-20px)';
                    setTimeout(() => cartItem.remove(), 300);
                }
                
                // Reload page if cart is empty and on cart page
                if (data.count === 0 && window.location.pathname.includes('cart.php')) {
                    setTimeout(() => location.reload(), 500);
                }
            } else if (action === 'increase' || action === 'decrease') {
                // Update quantity display
                const qtyDisplay = button?.closest('.cart-item, .cart-item-modern, .quantity-controls')?.querySelector('.quantity-display');
                if (qtyDisplay) {
                    qtyDisplay.textContent = data.new_quantity;
                }
                
                // Update item total if needed
                updateItemTotal(productId, data.new_quantity);
            }
            
            // Update cart preview
            refreshCartPreview();
            
            showToast(data.message);
        } else {
            throw new Error(data.message || __('operation_failed', null, 'Operation failed'));
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

// Fixed: Update ALL cart count elements consistently
function updateAllCartCounts(count) {
    const countValue = count || '0';
    
    // Update by specific IDs
    ['cart-count', 'cart-count-mobile', 'cart-count-toggle', 'cart_count'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = countValue;
    });
    
    // Also update by class or attribute selectors
    document.querySelectorAll('[id*="cart-count"], [id*="cart_count"], .cart-count-badge').forEach(el => {
        el.textContent = countValue;
    });
    
    // Animate the badge
    animateCartBadge();
}

// Animate cart badge when count changes
function animateCartBadge() {
    document.querySelectorAll('.cart-count-badge, [id*="cart-count"]').forEach(badge => {
        badge.classList.add('pulse-once');
        setTimeout(() => badge.classList.remove('pulse-once'), 500);
    });
}

// Legacy function name for compatibility
function updateCartCount(count) {
    updateAllCartCounts(count);
}

// Fixed: Refresh cart preview with proper content loading
function refreshCartPreview() {
    const preview = document.getElementById('cartPreview');
    const offcanvas = document.getElementById('cart-items-offcanvas');
    
    // Update desktop preview if visible
    if (preview && window.getComputedStyle(preview).display !== 'none') {
        loadCartContent('#cart-items-preview');
    }
    
    // Update offcanvas if visible
    if (offcanvas) {
        const offcanvasEl = document.getElementById('cartOffcanvas');
        if (offcanvasEl && offcanvasEl.classList.contains('show')) {
            loadCartContent('#cart-items-offcanvas');
        }
    }
}

// Legacy function name
function updateCartPreview() {
    refreshCartPreview();
}

// Load cart content into specified container
function loadCartContent(selector) {
    const container = document.querySelector(selector);
    if (!container) return;
    
    fetch('ajax/cart_preview.php?t=' + Date.now())
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(err => {
            console.warn('Failed to load cart content:', err);
        });
}

// Update individual item total (for cart page)
function updateItemTotal(productId, quantity) {
    const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
    if (!cartItem) return;
    
    const priceElement = cartItem.querySelector('[data-price]');
    const totalElement = cartItem.querySelector('.item-total-price, .item-total, .item-total-modern');
    
    if (priceElement && totalElement) {
        const price = parseFloat(priceElement.dataset.price || priceElement.textContent.replace(/[^\d.]/g, ''));
        const total = price * quantity;
        totalElement.innerHTML = `<strong>AED ${total.toFixed(2)}</strong>`;
    }
    
    // Also update cart page totals if present
    recalculateCartTotals();
}

// Recalculate cart page totals
function recalculateCartTotals() {
    let grandTotal = 0;
    document.querySelectorAll('.cart-item, .cart-item-modern').forEach(item => {
        const totalEl = item.querySelector('.item-total-price, .item-total');
        if (totalEl) {
            const amount = parseFloat(totalEl.textContent.replace(/[^\d.]/g, '')) || 0;
            grandTotal += amount;
        }
    });
    
    // Update summary if exists
    const summaryTotal = document.querySelector('.grand-total .total-amount, .summary-total');
    if (summaryTotal && grandTotal > 0) {
        summaryTotal.textContent = `AED ${grandTotal.toFixed(2)}`;
    }
}

// Fixed: Toggle cart preview with auto-hide timer reset
function toggleCartPreview() {
    const preview = document.getElementById('cartPreview');
    if (!preview) {
        console.warn('Cart preview element not found');
        return;
    }
    
    const isVisible = window.getComputedStyle(preview).display !== 'none';
    
    if (isVisible) {
        hideCartPreview();
    } else {
        showCartPreview();
    }
}

// Show cart preview
function showCartPreview() {
    const preview = document.getElementById('cartPreview');
    if (!preview) return;
    
    // Load content first
    loadCartContent('#cart-items-preview');
    
    // Show preview
    preview.style.display = 'block';
    
    // Update ARIA
    const cartButton = document.querySelector('[onclick*="toggleCart"], .cart-button-header');
    if (cartButton) {
        cartButton.setAttribute('aria-expanded', 'true');
    }
    
    // Reset auto-hide timer
    resetAutoHideTimer();
}

// Hide cart preview
function hideCartPreview() {
    const preview = document.getElementById('cartPreview');
    if (preview) {
        preview.style.display = 'none';
    }
    
    // Update ARIA
    const cartButton = document.querySelector('[onclick*="toggleCart"], .cart-button-header');
    if (cartButton) {
        cartButton.setAttribute('aria-expanded', 'false');
    }
    
    // Clear timer
    clearTimeout(cartAutoHideTimer);
}

// Fixed: Reset auto-hide timer on interaction
function resetAutoHideTimer() {
    clearTimeout(cartAutoHideTimer);
    cartAutoHideTimer = setTimeout(() => {
        hideCartPreview();
    }, 10000); // 10 seconds
}

// Legacy function name
function toggleCart() {
    // Check screen size for responsive behavior
    if (window.innerWidth <= 768) {
        // Mobile: use offcanvas
        const offcanvas = document.getElementById('cartOffcanvas');
        if (offcanvas) {
            try {
                const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvas);
                bsOffcanvas.show();
                loadCartContent('#cart-items-offcanvas');
            } catch (e) {
                console.warn('Offcanvas error:', e);
            }
        }
    } else {
        // Desktop: use preview dropdown
        toggleCartPreview();
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
            updateAllCartCounts(data.count);
        }
    })
    .catch(() => {}); // Silent fail
    
    // Fixed: Add mouse interaction handlers for cart preview
    const preview = document.getElementById('cartPreview');
    if (preview) {
        preview.addEventListener('mouseenter', () => {
            clearTimeout(cartAutoHideTimer);
        });
        preview.addEventListener('mouseleave', () => {
            resetAutoHideTimer();
        });
    }
    
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
                addToCart(productId, quantity, e);
            }
        }
        
        // Cart operation buttons
        if (target.classList.contains('update-qty')) {
            e.preventDefault();
            const action = target.dataset.action;
            const productId = target.dataset.id;
            if (action && productId) {
                cartOperation(action, productId, e);
            }
        }
        
        if (target.classList.contains('remove-item')) {
            e.preventDefault();
            const productId = target.dataset.id;
            if (productId && confirm(__('confirm_remove_item', null, 'Remove this item from cart?'))) {
                cartOperation('remove', productId, e);
            }
        }
    });
    
    // Close cart preview when clicking outside
    document.addEventListener('click', function(e) {
        const preview = document.getElementById('cartPreview');
        const cartButton = document.querySelector('[onclick*="toggleCart"], .cart-button-header');
        
        if (!preview || window.getComputedStyle(preview).display === 'none') return;
        
        const clickedInPreview = preview.contains(e.target);
        const clickedCartButton = cartButton && cartButton.contains(e.target);
        
        if (!clickedInPreview && !clickedCartButton) {
            hideCartPreview();
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
                addToCart(productId, quantity, e);
            }
        }
    });
    
    // Offcanvas cart loading
    const cartOffcanvas = document.getElementById('cartOffcanvas');
    if (cartOffcanvas) {
        cartOffcanvas.addEventListener('show.bs.offcanvas', function() {
            loadCartContent('#cart-items-offcanvas');
        });
    }
});

// Make functions globally available
window.addToCart = addToCart;
window.cartOperation = cartOperation;
window.toggleCart = toggleCart;
window.toggleCartPreview = toggleCartPreview;
window.hideCartPreview = hideCartPreview;
window.showCartPreview = showCartPreview;
window.showToast = showToast;
window.updateCartCount = updateCartCount;
window.updateAllCartCounts = updateAllCartCounts;
window.refreshCartPreview = refreshCartPreview;

} // End of cartInitialized check
