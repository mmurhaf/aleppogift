


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
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
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
            button.html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
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
            button.html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
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
                    showToast('Success', `${res.product_name} added to cart!`, 'success');
                }
                
                // Enhanced success animation for btn-add-cart
                button.removeClass('loading').addClass('success');
                button.html('<i class="fas fa-check me-2"></i>Added!');
                
                setTimeout(() => {
                    button.removeClass('success');
                    button.html(originalText);
                    button.prop('disabled', false);
                }, 2000);
            } else {
                showToast('Error', res.message || 'Failed to add product to cart', 'error');
                button.removeClass('loading');
                button.html(originalText);
                button.prop('disabled', false);
            }
        }

        // Handle add to cart error
        function handleAddToCartError(xhr, button, originalText) {
            let errorMsg = 'Network error. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            showToast('Error', errorMsg, 'error');
            button.removeClass('loading');
            button.html(originalText);
            button.prop('disabled', false);
        }

        // Remove from cart
        $(document).on('click', '.remove-item', function () {
            const id = $(this).data('id');
            const button = $(this);
            
            // Show loading state
            button.prop('disabled', true);
            
            $.post('ajax/remove_from_cart.php', { product_id: id }, function (res) {
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
                    
                    showToast('Success', res.message || 'Item removed from cart', 'success');
                } else {
                    showToast('Error', res.message || 'Failed to remove item', 'error');
                }
                button.prop('disabled', false);
            }, 'json').fail(function() {
                showToast('Error', 'Network error. Please try again.', 'error');
                button.prop('disabled', false);
            });
        });

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
                showToast('Info', 'Minimum quantity is 1. Use remove button to delete item.', 'info');
                return;
            }
            
            // Show loading state
            button.prop('disabled', true);
            const originalIcon = button.find('i').attr('class');
            button.find('i').attr('class', 'fas fa-spinner fa-spin');
            
            $.post('ajax/update_cart_qty.php', { product_id: id, action: action }, function (res) {
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
                    showToast('Warning', res.message || 'Cannot update quantity', 'warning');
                }
                
                // Reset button
                button.prop('disabled', false);
                button.find('i').attr('class', originalIcon);
            }, 'json').fail(function() {
                showToast('Error', 'Network error. Please try again.', 'error');
                button.prop('disabled', false);
                button.find('i').attr('class', originalIcon);
            });
        });

        // Function to update cart item total on cart page
        function updateCartItemTotal(cartItem, newQuantity) {
            // Try to find price element - support multiple selectors
            let priceText = cartItem.find('.current-price').text();
            if (!priceText) {
                priceText = cartItem.find('.item-price').text();
            }
            
            const price = parseFloat(priceText.replace(/[^\d.]/g, ''));
            if (isNaN(price)) {
                console.warn('Could not parse price from:', priceText);
                return;
            }
            
            const newTotal = price * newQuantity;
            
            // Try to update total element - support multiple selectors
            const totalElement = cartItem.find('.item-total-price');
            if (totalElement.length) {
                totalElement.fadeOut(150, function() {
                    $(this).html('<strong>AED ' + newTotal.toFixed(2) + '</strong>').fadeIn(150);
                });
            } else {
                // Alternative selector for cart.php
                const altTotalElement = cartItem.find('.item-total');
                if (altTotalElement.length) {
                    altTotalElement.fadeOut(150, function() {
                        $(this).text('AED ' + newTotal.toFixed(2)).fadeIn(150);
                    });
                }
            }
            
            // Update cart summary totals
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
            
            cartItems.each(function() {
                // Try different selectors for item total
                let totalText = $(this).find('.item-total-price strong').text();
                if (!totalText) {
                    totalText = $(this).find('.item-total').text();
                }
                
                const total = parseFloat(totalText.replace(/[^\d.]/g, ''));
                if (!isNaN(total)) {
                    subtotal += total;
                }
            });
            
            console.log('Cart subtotal calculated:', subtotal);
            
            // Update subtotal in summary - try multiple selectors
            const subtotalElement = $('.summary-row:first .fw-bold');
            if (subtotalElement.length) {
                subtotalElement.text('AED ' + subtotal.toFixed(2));
            } else {
                // Alternative selector for cart.php
                $('.summary-row:first span:last-child').text('AED ' + subtotal.toFixed(2));
            }
            
            // Update grand total - try multiple selectors
            const grandTotalElement = $('.grand-total .text-success');
            if (grandTotalElement.length) {
                grandTotalElement.text('AED ' + subtotal.toFixed(2));
            } else {
                // Alternative selector for cart.php (including shipping)
                const shipping = 30; // Default shipping cost
                $('.grand-total span:last-child').text('AED ' + (subtotal + shipping).toFixed(2));
            }
        }
        
        // Make updateCartSummary globally available
        window.updateCartSummary = updateCartSummary;

        // Enhanced remove from cart with confirmation for cart page
        $(document).on('click', '.remove-item', function (e) {
            e.preventDefault();
            
            const id = $(this).data('id');
            const button = $(this);
            const cartItem = button.closest('.cart-item-modern, .cart-item-preview');
            
            // Show confirmation for cart page (not preview)
            if (cartItem.hasClass('cart-item-modern')) {
                if (!confirm('Are you sure you want to remove this item from your cart?')) {
                    return;
                }
            }
            
            // Show loading state
            const originalHtml = button.html();
            button.html('<i class="fas fa-spinner fa-spin"></i>');
            button.prop('disabled', true);
            
            $.post('ajax/remove_from_cart.php', { product_id: id }, function (res) {
                if (res.success) {
                    $('#cart-count').text(res.count);
                    $('#cart-count-toggle').text(res.count);
                    
                    // Animate item removal
                    cartItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Reload cart preview (cache-busted + debug)
                        if (window.loadCartPreview) {
                            loadCartPreview();
                        }
                        if (window.loadCartOffcanvas) {
                            loadCartOffcanvas();
                        }
                        
                        // If on cart page and no items left, reload page
                        if (window.location.pathname.includes('cart.php') && res.count === 0) {
                            location.reload();
                        } else if (cartItem.hasClass('cart-item-modern')) {
                            // Update cart summary
                            updateCartSummary();
                        }
                    });
                    
                    showToast('Success', res.message || 'Item removed from cart', 'success');
                } else {
                    showToast('Error', res.message || 'Failed to remove item', 'error');
                    button.html(originalHtml);
                    button.prop('disabled', false);
                }
            }, 'json').fail(function() {
                showToast('Error', 'Network error. Please try again.', 'error');
                button.html(originalHtml);
                button.prop('disabled', false);
            });
        });

        // Enhanced addToCart function for button clicks (not forms)
        window.addToCart = function(productId, quantity = 1) {
            // Client-side validation
            if (!productId || productId <= 0) {
                showToast('Error', 'Invalid product selected', 'error');
                return;
            }
            
            if (!quantity || quantity <= 0) {
                showToast('Error', 'Invalid quantity', 'error');
                return;
            }
            
            const button = event && event.target ? $(event.target.closest('button')) : null;
            const originalText = button ? button.html() : '';
            
            // Show loading state if button exists
            if (button) {
                button.html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
                button.prop('disabled', true);
            }
            
            $.post('ajax/add_to_cart.php', { 
                product_id: productId, 
                quantity: quantity 
            }, function (res) {
                if (res.success) {
                    // Update cart counters
                    $('#cart-count').text(res.count);
                    $('#cart-count-toggle').text(res.count);
                    
                    // Reload cart preview (cache-busted + debug)
                    (function(){ const _cb = 't=' + Date.now(); console.log('Reloading cart preview (addToCart)'); $('#cartPreview').load('ajax/cart_preview.php?' + _cb); })();
                    
                    // Show success message
                    showToast('Success', res.message || 'Item added to cart!', 'success');
                    
                    // Animate cart button
                    const cartButton = $('[onclick="toggleCart()"]');
                    cartButton.addClass('animate__animated animate__pulse');
                    setTimeout(() => cartButton.removeClass('animate__animated animate__pulse'), 1000);
                    
                } else {
                    // Enhanced error handling
                    let errorMessage = res.message || 'Failed to add item to cart';
                    if (res.error_code === 'INVALID_PRODUCT_ID') {
                        errorMessage = 'Please select a valid product';
                    } else if (res.error_code === 'INVALID_QUANTITY') {
                        errorMessage = 'Please select a valid quantity';
                    }
                    showToast('Error', errorMessage, 'error');
                }
                
                // Reset button
                if (button) {
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            }, 'json').fail(function(xhr) {
                let errorMsg = 'Network error. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {
                    // Keep default error message
                }
                showToast('Error', errorMsg, 'error');
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


            document.getElementById('search-toggle').addEventListener('click', function() {
            document.getElementById('search-bar').classList.remove('d-none');
            document.querySelector('#search-bar input').focus();
                });

                document.getElementById('search-close').addEventListener('click', function() {
                    document.getElementById('search-bar').classList.add('d-none');
                });

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


    // Close cart when clicking outside
    document.addEventListener('click', function(event) {
        const cartPreview = document.getElementById('cartPreview');
        const cartButton = document.querySelector('[onclick="toggleCart()"]');

        // If preview isn't present, nothing to do
        if (!cartPreview) return;

        // Determine visibility safely
        const isVisible = window.getComputedStyle(cartPreview).display !== 'none';

        // If visible and click is outside both preview and toggle button, hide preview
        const clickedOutsidePreview = !cartPreview.contains(event.target);
        const clickedOutsideButton = cartButton ? !cartButton.contains(event.target) : true;

        if (isVisible && clickedOutsidePreview && clickedOutsideButton) {
            // If using Offcanvas, let bootstrap handle it; otherwise hide inline preview
            const cartOffcanvas = document.getElementById('cartOffcanvas');
            if (cartOffcanvas) {
                try {
                    const bs = bootstrap.Offcanvas.getInstance(cartOffcanvas);
                    if (bs) bs.hide();
                } catch (e) {
                    cartOffcanvas.classList.remove('show');
                }
            } else {
                cartPreview.style.display = 'none';
            }
        }
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
    alert('Copy this link to share on Instagram: ' + text);
}

function shareToTikTok(text) {
    // TikTok sharing implementation
    alert('Copy this link to share on TikTok: ' + text);
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
    
    // Handle badge visibility
    const badges = ['#cart-count', '#cart-count-toggle', '#cart-count-mobile'];
    badges.forEach(badge => {
        const element = $(badge);
        if (countInt === 0) {
            element.hide();
        } else {
            element.show();
        }
    });
}

// Export function globally
window.updateCartCount = updateCartCount;

// Proceed to checkout with validation
window.proceedToCheckout = function() {
    // Check if cart has items
    const cartCount = parseInt($('#cart-count').text()) || 0;
    
    if (cartCount === 0) {
        showToast('Info', 'Your cart is empty. Add some items first!', 'info');
        return;
    }
    
    // Show loading state
    const checkoutBtn = event.target;
    const originalText = checkoutBtn.innerHTML;
    checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
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
            $('#cartPreview').html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading cart...</p></div>');
            
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            const url = 'ajax/cart_preview.php?t=' + timestamp;
            
            $('#cartPreview').load(url, function(response, status, xhr) {
                if (status === "error") {
                    console.error('❌ Cart preview load failed:', xhr.status, xhr.statusText);
                    $(this).html('<div class="text-center p-4"><p class="text-danger">Error loading cart contents</p><button class="btn btn-sm btn-primary" onclick="location.reload()">Refresh Page</button></div>');
                } else {
                    console.log('✅ Cart preview loaded successfully');
                    console.log('Response length:', response.length);
                    
                    // Verify content is not empty
                    if (response.trim().length === 0) {
                        console.warn('⚠️ Empty response from cart preview');
                        $(this).html('<div class="text-center p-4"><p class="text-warning">Cart preview returned empty response</p><button class="btn btn-sm btn-primary" onclick="location.reload()">Refresh Page</button></div>');
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
