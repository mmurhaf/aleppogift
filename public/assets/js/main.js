


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

        // Add to cart
        $(document).on('submit', '.add-to-cart-form', function (e) {
            e.preventDefault();
            const form = $(this);
            const button = form.find('button');
            const originalText = button.html();
            
            // Show loading state
            button.html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
            button.prop('disabled', true);
            
            $.post('ajax/add_to_cart.php', form.serialize(), function (res) {
                if (res.success) {
                    // Update cart counters
                    $('#cart-count').text(res.count);
                    $('#cart-count-toggle').text(res.count);
                    
                    // Update cart preview
                    $('#cartPreview').load('ajax/cart_preview.php');
                    
                    // Show success message
                    if (res.product_name) {
                        showToast('Success', `${res.product_name} added to cart!`, 'success');
                    }
                    
                    // Update button to show success briefly
                    button.html('<i class="fas fa-check me-2"></i>Added!');
                    button.removeClass('btn-primary').addClass('btn-success');
                    
                    setTimeout(() => {
                        button.html(originalText);
                        button.prop('disabled', false);
                        button.removeClass('btn-success').addClass('btn-primary');
                    }, 1500);
                } else {
                    showToast('Error', res.message || 'Failed to add product to cart', 'error');
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            }, 'json').fail(function(xhr) {
                let errorMsg = 'Network error. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showToast('Error', errorMsg, 'error');
                button.html(originalText);
                button.prop('disabled', false);
            });
        });

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
                    $('#cartPreview').load('ajax/cart_preview.php');
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
            
            // Show loading state
            button.prop('disabled', true);
            
            $.post('ajax/update_cart_qty.php', { product_id: id, action: action }, function (res) {
                if (res.success) {
                    $('#cart-count').text(res.count);
                    $('#cart-count-toggle').text(res.count);
                    $('#cartPreview').load('ajax/cart_preview.php');
                    
                    // Update quantity display if it exists
                    const quantityDisplay = button.siblings('.quantity-display');
                    if (quantityDisplay.length && res.new_quantity !== undefined) {
                        quantityDisplay.text(res.new_quantity);
                    }
                } else {
                    showToast('Warning', res.message || 'Cannot update quantity', 'warning');
                }
                button.prop('disabled', false);
            }, 'json').fail(function() {
                showToast('Error', 'Network error. Please try again.', 'error');
                button.prop('disabled', false);
            });
        });

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


		function toggleCart() {
			const preview = document.getElementById('cartPreview');
			if (!preview) {
				console.error('⚠️ cartPreview element not found');
				return;
			}

			const isHidden = preview.style.display === 'none' || preview.style.display === '';
			preview.style.display = isHidden ? 'block' : 'none';

			// Load cart preview content if showing
			if (isHidden) {
				fetch('ajax/cart_preview.php')
					.then(res => {
						if (!res.ok) {
							throw new Error(`HTTP error! status: ${res.status}`);
						}
						return res.text();
					})
					.then(html => {
						const cartItems = document.getElementById('cart-items-preview');
						if (cartItems) {
							cartItems.innerHTML = html;
						} else {
							console.error('⚠️ cart-items-preview element not found');
						}
					})
					.catch(err => {
						console.error('❌ Failed to load cart preview:', err);
						// Optionally show an error message to the user
						const cartItems = document.getElementById('cart-items-preview');
						if (cartItems) {
							cartItems.innerHTML = '<p class="text-danger">Error loading cart contents</p>';
						}
					});
			}
		}


    // Close cart when clicking outside
    document.addEventListener('click', function(event) {
        const cartPreview = document.getElementById('cartPreview');
        const cartButton = document.querySelector('[onclick="toggleCart()"]');
        
        if (cartPreview.style.display === 'block' && 
            !cartPreview.contains(event.target) && 
            !cartButton.contains(event.target)) {
            cartPreview.style.display = 'none';
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
