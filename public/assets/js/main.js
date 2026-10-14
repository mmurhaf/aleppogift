
var __ = function (k, p, fallback) {
    var s = (window.AG_I18N && window.AG_I18N[k]) || fallback || k;
    if (p) { for (var n in p) { s = s.split('{' + n + '}').join(p[n]); } }
    return s;
};

    $(document).ready(function () {
		// Future JavaScript functions can be added here
console.log("AleppoGift JS loaded.");

        // Initialize toast notifications
        function showToast(title, message, type = 'info') {
            const toastTypes = {
                'success': 'text-bg-success',
                'error': 'text-bg-danger', 
                'warning': 'text-bg-warning',
                'info': 'text-bg-info'
            };
            
            const toastClass = toastTypes[type] || toastTypes['info'];
            const toastId = 'toast-' + Date.now();
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${toastClass}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="${__('close', null, 'Close')}"></button>
                    </div>
                    <div class="toast-body text-white">
                        ${message}
                    </div>
                </div>
            `;
            
            // Create toast container if it doesn't exist
            if (!$('#toast-container').length) {
                $('body').append('<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>');
            }
            
            $('#toast-container').append(toastHtml);
            
            const toastElement = new bootstrap.Toast(document.getElementById(toastId), {
                delay: type === 'error' ? 5000 : 3000
            });
            toastElement.show();
            
            // Remove toast from DOM after it's hidden
            $(`#${toastId}`).on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        // Make showToast globally available
        window.showToast = showToast;

        // Add to cart - DISABLED to prevent conflicts with simplified version
        // $(document).on('submit', '.add-to-cart-form', function (e) {
        //     e.preventDefault();
        //     const form = $(this);
        //     const button = form.find('button');
        //     handleAddToCart(form, button);
        // });

        // Handle direct add to cart button clicks - DISABLED to prevent conflicts
        // $(document).on('click', '.btn-add-cart', function (e) {
        //     e.preventDefault();
        //     const button = $(this);
        //     const form = button.closest('.add-to-cart-form');
        //     
        //     if (form.length) {
        //         handleAddToCart(form, button);
        //     } else {
        //         // Handle standalone button (create virtual form data)
        //         const productId = button.data('id');
        //         const quantity = button.data('quantity') || 1;
        //         
        //         if (productId) {
        //             const formData = {
        //                 product_id: productId,
        //                 quantity: quantity
        //             };
        //             handleAddToCartData(formData, button);
        //         }
        //     }
        // });

        // Enhanced add to cart handler function
        function handleAddToCart(form, button) {
            const originalText = button.html();
            
            // Add loading state with enhanced animation
            button.addClass('loading');
            button.html('<i class="fas fa-spinner fa-spin me-2"></i>' + __('adding', null, 'Adding...'));
            button.prop('disabled', true);
            
            $.post('ajax/add_to_cart.php', form.serialize(), function (res) {
                handleAddToCartResponse(res, button, originalText);
            }, 'json').fail(function(xhr) {
                handleAddToCartError(xhr, button, originalText);
            });
        }

        // Handle add to cart with data object
        function handleAddToCartData(formData, button) {
            const originalText = button.html();
            
            // Add loading state with enhanced animation
            button.addClass('loading');
            button.html('<i class="fas fa-spinner fa-spin me-2"></i>' + __('adding', null, 'Adding...'));
            button.prop('disabled', true);
            
            $.post('ajax/add_to_cart.php', formData, function (res) {
                handleAddToCartResponse(res, button, originalText);
            }, 'json').fail(function(xhr) {
                handleAddToCartError(xhr, button, originalText);
            });
        }

        // Handle successful add to cart response
        function handleAddToCartResponse(res, button, originalText) {
            if (res.success) {
                // Update cart counters - including mobile
                $('#cart-count').text(res.count);
                $('#cart-count-toggle').text(res.count);
                $('#cart-count-mobile').text(res.count);
                
                // Reload both cart previews
                if (window.loadCartPreview) {
                    loadCartPreview();
                }
                if (window.loadCartOffcanvas) {
                    loadCartOffcanvas();
                }
                
                // Show success message
                if (res.product_name) {
                    showToast(__('toast_success', null, 'Success'), __('product_added', { name: res.product_name }, '{name} added to cart!'), 'success');
                }
                
                // Enhanced success animation for btn-add-cart
                button.removeClass('loading').addClass('success');
                button.html('<i class="fas fa-check me-2"></i>' + __('added_excl', null, 'Added!'));
                
                setTimeout(() => {
                    button.removeClass('success');
                    button.html(originalText);
                    button.prop('disabled', false);
                }, 2000);
            } else {
                showToast(__('toast_error', null, 'Error'), res.message || __('failed_to_add_product', null, 'Failed to add product to cart'), 'error');
                button.removeClass('loading');
                button.html(originalText);
                button.prop('disabled', false);
            }
        }

        // Handle add to cart error
        function handleAddToCartError(xhr, button, originalText) {
            let errorMsg = __('network_error', null, 'Network error. Please try again.');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            showToast(__('toast_error', null, 'Error'), errorMsg, 'error');
            button.removeClass('loading');
            button.html(originalText);
            button.prop('disabled', false);
        }

        // Cart removal is handled once in storefront.js.

        // Update quantity
        $(document).on('click', '.update-qty', function () {
            const id = $(this).data('id');
            const action = $(this).data('action');
            const button = $(this);
            const quantityDisplay = button.siblings('.quantity-display').length 
                ? button.siblings('.quantity-display') 
                : button.closest('.quantity-controls, .quantity-controls-small').find('.quantity-display');
            
            const currentQuantity = parseInt(quantityDisplay.text()) || 1;
            
            // Don't allow decrease below 1
            if (action === 'decrease' && currentQuantity <= 1) {
                showToast(__('toast_info', null, 'Info'), __('min_qty', null, 'Minimum quantity is 1. Use remove button to delete item.'), 'info');
                return;
            }
            
            // Show loading state
            button.prop('disabled', true);
            const originalIcon = button.find('i').attr('class');
            button.find('i').attr('class', 'fas fa-spinner fa-spin');
            
            const qtyPayload = { product_id: id, action: action };
            const cartKey = $(this).attr('data-key');
            if (cartKey !== undefined && cartKey !== '') {
                qtyPayload.cart_key = cartKey;
            }
            $.post('ajax/update_cart_qty.php', qtyPayload, function (res) {
                if (res.success) {
                    $('#cart-count').text(res.count);
                    $('#cart-count-toggle').text(res.count);
                    $('#cart-count-mobile').text(res.count);
                    
                    // Reload both cart previews
                    if (window.loadCartPreview) {
                        loadCartPreview();
                    }
                    if (window.loadCartOffcanvas) {
                        loadCartOffcanvas();
                    }
                    
                    // Update quantity display with animation
                    if (res.new_quantity !== undefined) {
                        quantityDisplay.fadeOut(150, function() {
                            $(this).text(res.new_quantity).fadeIn(150);
                        });
                        
                        // Update total price if on cart page
                        const cartItem = button.closest('.cart-item-modern');
                        if (cartItem.length) {
                            updateCartItemTotal(cartItem, res.new_quantity);
                        }
                    }
                } else {
                    showToast(__('toast_warning', null, 'Warning'), res.message || __('qty_update_failed', null, 'Cannot update quantity'), 'warning');
                }
                
                // Reset button
                button.prop('disabled', false);
                button.find('i').attr('class', originalIcon);
            }, 'json').fail(function() {
                showToast(__('toast_error', null, 'Error'), __('network_error', null, 'Network error. Please try again.'), 'error');
                button.prop('disabled', false);
                button.find('i').attr('class', originalIcon);
            });
        });

        // Function to update cart item total on cart page
        function storeMoneyLabel(amount, withReference) {
            if (typeof window.formatStoreMoney === 'function') {
                return window.formatStoreMoney(amount, !!withReference);
            }
            return 'AED ' + (Number(amount) || 0).toFixed(2);
        }

        function updateCartItemTotal(cartItem, newQuantity) {
            const unitAed = parseFloat(cartItem.find('[data-unit-aed]').attr('data-unit-aed') || '');
            let newTotal = NaN;
            if (!isNaN(unitAed)) {
                newTotal = unitAed * newQuantity;
            } else {
                let priceText = cartItem.find('.current-price').text();
                if (!priceText) {
                    priceText = cartItem.find('.item-price').text();
                }
                const price = parseFloat(priceText.replace(/[^\d.]/g, ''));
                if (isNaN(price)) {
                    console.warn('Could not parse price from:', priceText);
                    return;
                }
                newTotal = price * newQuantity;
            }

            const label = storeMoneyLabel(newTotal, true);
            const totalElement = cartItem.find('.item-total-price');
            if (totalElement.length) {
                totalElement.fadeOut(150, function() {
                    $(this).html('<strong>' + label + '</strong>').fadeIn(150);
                });
            } else {
                const altTotalElement = cartItem.find('.item-total');
                if (altTotalElement.length) {
                    altTotalElement.fadeOut(150, function() {
                        $(this).text(label).fadeIn(150);
                    });
                }
            }

            if (typeof updateCartSummary === 'function') {
                updateCartSummary();
            }
        }

        // Function to recalculate cart summary
        function updateCartSummary() {
            let subtotal = 0;
            
            // Support both cart-item and cart-item-modern classes
            const cartItems = $('.cart-item, .cart-item-modern');
            
            if (cartItems.length === 0) {
                console.log('No cart items found for updateCartSummary');
                return;
            }
            
            let subtotalAed = 0;
            let pricedFromAed = 0;
            cartItems.each(function() {
                const unitAed = parseFloat($(this).find('[data-unit-aed]').attr('data-unit-aed') || '');
                const qty = parseInt($(this).find('.quantity-display').text(), 10);
                if (!isNaN(unitAed) && !isNaN(qty)) {
                    subtotalAed += unitAed * qty;
                    pricedFromAed++;
                    return;
                }
                let totalText = $(this).find('.item-total-price strong').text();
                if (!totalText) {
                    totalText = $(this).find('.item-total').text();
                }
                
                const total = parseFloat(totalText.replace(/[^\d.]/g, ''));
                if (!isNaN(total)) {
                    subtotal += total;
                }
            });
            if (pricedFromAed > 0) {
                subtotal = subtotalAed;
            }
            
            console.log('Cart subtotal calculated:', subtotal);
            
            // Update subtotal in summary - try multiple selectors
            const subtotalLabel = storeMoneyLabel(subtotal, true);
            const subtotalElement = $('.summary-row:first .fw-bold');
            if (subtotalElement.length) {
                subtotalElement.text(subtotalLabel);
            } else {
                $('.summary-row:first span:last-child').text(subtotalLabel);
            }
            
            const grandTotalElement = $('.grand-total .text-success');
            if (grandTotalElement.length) {
                grandTotalElement.text(subtotalLabel);
            } else {
                const shipping = 30;
                $('.grand-total span:last-child').text(storeMoneyLabel(subtotal + shipping, true));
            }
        }
        
        // Make updateCartSummary globally available
        window.updateCartSummary = updateCartSummary;

        // Cart removal is handled once in storefront.js.

        // Enhanced addToCart function for button clicks (not forms)
        window.addToCart = function(productId, quantity = 1) {
            // Client-side validation
            if (!productId || productId <= 0) {
                showToast(__('toast_error', null, 'Error'), __('invalid_product_selected', null, 'Invalid product selected'), 'error');
                return;
            }
            
            if (!quantity || quantity <= 0) {
                showToast(__('toast_error', null, 'Error'), __('invalid_quantity', null, 'Invalid quantity'), 'error');
                return;
            }
            
            const button = event && event.target ? $(event.target.closest('button')) : null;
            const originalText = button ? button.html() : '';
            
            // Show loading state if button exists
            if (button) {
                button.html('<i class="fas fa-spinner fa-spin me-2"></i>' + __('adding', null, 'Adding...'));
                button.prop('disabled', true);
            }
            
            $.post('ajax/add_to_cart.php', { 
                product_id: productId, 
                quantity: quantity 
            }, function (res) {
                if (res.success) {
                    // Update every cart badge, including the mobile one.
                    if (typeof updateCartCount === 'function') {
                        updateCartCount(res.count);
                    } else {
                        $('#cart-count, #cart-count-toggle, #cart-count-mobile').text(res.count);
                    }
                    
                    if (window.cartPreviewTimeout) {
                        clearTimeout(window.cartPreviewTimeout);
                        window.cartPreviewTimeout = null;
                    }
                    if (window.matchMedia('(max-width: 991.98px)').matches && window.bootstrap) {
                        var addedDrawer = document.getElementById('cartOffcanvas');
                        if (addedDrawer) bootstrap.Offcanvas.getOrCreateInstance(addedDrawer).show();
                    } else {
                        var addedPreview = document.getElementById('cartPreview');
                        if (addedPreview) addedPreview.style.display = 'block';
                        if (window.loadCartPreview) window.loadCartPreview();
                    }
                    
                    // Animate cart button
                    const cartButton = $('[onclick="toggleCart()"]');
                    cartButton.addClass('animate__animated animate__pulse');
                    setTimeout(() => cartButton.removeClass('animate__animated animate__pulse'), 1000);
                    
                } else {
                    // Enhanced error handling
                    let errorMessage = res.message || __('failed_to_add_item', null, 'Failed to add item to cart');
                    if (res.error_code === 'INVALID_PRODUCT_ID') {
                        errorMessage = __('select_valid_product', null, 'Please select a valid product');
                    } else if (res.error_code === 'INVALID_QUANTITY') {
                        errorMessage = __('select_valid_quantity', null, 'Please select a valid quantity');
                    }
                    showToast(__('toast_error', null, 'Error'), errorMessage, 'error');
                }
                
                // Reset button
                if (button) {
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            }, 'json').fail(function(xhr) {
                let errorMsg = __('network_error', null, 'Network error. Please try again.');
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {
                    // Keep default error message
                }
                showToast(__('toast_error', null, 'Error'), errorMsg, 'error');
                if (button) {
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            });
        };

   // Reset button functionality
    $('.btn-reset').on('click', function(e) {
        e.preventDefault();
        
        // Clear form fields
        $('#search').val('');
        $('#category').val('');
        $('#brand').val('');
        
        // Submit the empty form
        $(this).closest('form').submit();
    });

    // Search toggle handlers removed - now handled by enhanced-ui.js to avoid conflicts

    });


        // Simplified cart toggle - redirects to new implementation
        window.toggleCart = function() {
            console.log('toggleCart: redirecting to simplified cart preview');
            // Redirect to simplified function if available
            if (typeof toggleCartPreview === 'function') {
                toggleCartPreview();
            } else {
                console.warn('toggleCartPreview function not found - loading simplified cart');
                // Fallback - try to show cart preview
                const preview = document.getElementById('cartPreview');
                if (preview) {
                    preview.style.display = preview.style.display === 'none' ? 'block' : 'none';
                }
            }
        };


    document.addEventListener('click', function(event) {
        const cartPreview = document.getElementById('cartPreview');
        const cartButton = document.getElementById('cart-toggle-desktop');
        if (!cartPreview) return;
        const isVisible = window.getComputedStyle(cartPreview).display !== 'none';
        if (!isVisible) return;
        if (cartPreview.contains(event.target)) return;
        if (cartButton && cartButton.contains(event.target)) return;
        cartPreview.style.display = 'none';
    });
    
// Share functions for social media
function shareToFacebook(text) {
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href) + '&quote=' + text, '_blank');
}

function shareToInstagram(text) {
    // Instagram doesn't have a direct share API, this will open in a new tab
    window.open('https://www.instagram.com/', '_blank');
}

function shareToTikTok(text) {
    window.open('https://www.tiktok.com/', '_blank');
}

function toggleShareMenu(button) {
    const shareMenu = button.closest('.share-container').querySelector('.share-menu');
    shareMenu.classList.toggle('show');
    
    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!button.contains(e.target) && !shareMenu.contains(e.target)) {
            shareMenu.classList.remove('show');
        }
    }, { once: true });
}

// Share functions (implement these as needed)
function shareToFacebook(text) {
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}&quote=${text}`, '_blank');
}

function shareToInstagram(text) {
    // Instagram doesn't have direct sharing, this would typically open the app
    alert(__('share_copy_instagram', { link: text }, 'Copy this link to share on Instagram: {link}'));
}

function shareToTikTok(text) {
    // TikTok sharing implementation
    alert(__('share_copy_tiktok', { link: text }, 'Copy this link to share on TikTok: {link}'));
}

// Enhanced cart functionality
// NOTE: unified toggleCart defined earlier; keep behavior consistent for offcanvas fallback
// Backwards-compatible handler: simply delegate to the unified function and ensure preview reload
if (!window.toggleCart) {
    window.toggleCart = function() {
        const cartOffcanvas = document.getElementById('cartOffcanvas');
        if (cartOffcanvas) {
            const bsOffcanvas = new bootstrap.Offcanvas(cartOffcanvas);
            bsOffcanvas.toggle();
            const _cb = 't=' + Date.now();
            console.log('toggleCart (fallback): reloading preview');
            $('#cartPreview').load('ajax/cart_preview.php?' + _cb);
        }
    };
} else {
    // Ensure offcanvas reload uses cache-buster (in case previous code created an instance without reload)
    (function(){
        const cartOffcanvas = document.getElementById('cartOffcanvas');
        if (cartOffcanvas) {
            cartOffcanvas.addEventListener('show.bs.offcanvas', function() {
                const _cb = 't=' + Date.now();
                console.log('Offcanvas show: reloading cart preview');
                $('#cartPreview').load('ajax/cart_preview.php?' + _cb);
            });
        }
    })();
}

// Update cart count with proper badge visibility
function updateCartCount(count) {
    const countInt = parseInt(count) || 0;
    
    // Update main cart count
    $('#cart-count').text(countInt);
    $('#cart-count-toggle').text(countInt);
    $('#cart-count-mobile').text(countInt);
    
    ['#cart-count', '#cart-count-toggle', '#cart-count-mobile'].forEach(badge => {
        $(badge).show();
    });
}

// Export function globally
window.updateCartCount = updateCartCount;

// Proceed to checkout with validation
window.proceedToCheckout = function() {
    // Check if cart has items
    const cartCount = parseInt($('#cart-count').text()) || 0;
    
    if (cartCount === 0) {
        showToast(__('toast_info', null, 'Info'), __('empty_cart_notice', null, 'Your cart is empty. Add some items first!'), 'info');
        return;
    }
    
    // Show loading state
    const checkoutBtn = event.target;
    const originalText = checkoutBtn.innerHTML;
    checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + __('processing', null, 'Processing...');
    checkoutBtn.disabled = true;
    
    // Small delay for better UX
    setTimeout(() => {
        window.location.href = 'checkout.php';
    }, 500);
};

// Initialize enhanced cart functionality on page load
$(document).ready(function() {
    // Initialize cart offcanvas event listener to load cart preview
    const cartOffcanvas = document.getElementById('cartOffcanvas');
    if (cartOffcanvas) {
        // Remove any existing event listeners to prevent duplicates
        cartOffcanvas.removeEventListener('show.bs.offcanvas', null);
        
        cartOffcanvas.addEventListener('show.bs.offcanvas', function () {
            console.log('🛒 Loading cart preview...');
            
            // Force reload cart preview every time offcanvas is shown
            $('#cartPreview').html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">' + __('loading', null, 'Loading...') + '</span></div><p class="mt-2 text-muted">' + __('loading_cart', null, 'Loading cart...') + '</p></div>');
            
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            const url = 'ajax/cart_preview.php?t=' + timestamp;
            
            $('#cartPreview').load(url, function(response, status, xhr) {
                if (status === "error") {
                    console.error('❌ Cart preview load failed:', xhr.status, xhr.statusText);
                    $(this).html('<div class="text-center p-4"><p class="text-danger">' + __('error_loading_cart_contents', null, 'Error loading cart contents') + '</p><button class="btn btn-sm btn-primary" onclick="location.reload()">' + __('refresh_page', null, 'Refresh Page') + '</button></div>');
                } else {
                    console.log('✅ Cart preview loaded successfully');
                    console.log('Response length:', response.length);
                    
                    // Verify content is not empty
                    if (response.trim().length === 0) {
                        console.warn('⚠️ Empty response from cart preview');
                        $(this).html('<div class="text-center p-4"><p class="text-warning">' + __('empty_preview_response', null, 'Cart preview returned empty response') + '</p><button class="btn btn-sm btn-primary" onclick="location.reload()">' + __('refresh_page', null, 'Refresh Page') + '</button></div>');
                    }
                }
            });
        });
    }
    
    // Load cart count on page load
    $.get('ajax/get_cart_count.php', function(res) {
        if (res.count !== undefined) {
            $('#cart-count').text(res.count);
            $('#cart-count-toggle').text(res.count);
            $('#cart-count-mobile').text(res.count);
        }
    }, 'json').fail(function() {
        console.log('Cart count load failed - using session fallback');
    });
    
    // Auto-update cart summary if on cart page
    if (window.location.pathname.includes('cart.php')) {
        setTimeout(function() {
            if (typeof window.updateCartSummary === 'function') {
                window.updateCartSummary();
            }
        }, 500);
    }

    // Storefront owns add-to-cart, toasts, and badges after this file's copy is registered.
    if (typeof window.__installStorefront === 'function') {
        window.__installStorefront();
    }
});

// Debug helper: call from browser console to test cart preview endpoint
window.testLoadCartPreview = function() {
    const url = 'ajax/cart_preview.php?t=' + Date.now();
    console.log('testLoadCartPreview: fetching', url);
    fetch(url)
        .then(res => res.text())
        .then(html => {
            console.log('testLoadCartPreview: response length =', html.length);
            console.log(html.slice(0, 300));
        })
        .catch(err => console.error('testLoadCartPreview error', err));
};
