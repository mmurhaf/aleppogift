/**
 * Index Page JavaScript - Enhanced Product Functionality
 * Handles quick view, add to cart, product sharing, and other interactive features
 */

var __ = function (k, p, fallback) {
    var s = (window.AG_I18N && window.AG_I18N[k]) || fallback || k;
    if (p) { for (var n in p) { s = s.split('{' + n + '}').join(p[n]); } }
    return s;
};

// Enhanced product grid initialization
document.addEventListener('DOMContentLoaded', function() {
    // Cart loading is now handled entirely by main.js to prevent conflicts

    // Initialize quick view buttons
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.id;
            if (productId) {
                openQuickView(productId);
            }
        });
    });

    // Initialize add to cart buttons - SINGLE EVENT LISTENER ONLY
    document.querySelectorAll('.add-bundle-form').forEach(form => {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const button = this.querySelector('button');
            const bundleId = this.querySelector('input[name="bundle_id"]').value;
            if (!bundleId || (button && button.disabled)) return;
            const original = button ? button.textContent : '';
            if (button) {
                button.disabled = true;
                button.textContent = __('adding', null, 'Adding...');
            }
            const body = new URLSearchParams();
            body.set('bundle_id', bundleId);
            fetch('ajax/add_bundle_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || __('bundle_add_failed', null, 'Could not add bundle'));
                    if (window.updateCartCount) window.updateCartCount(data.count);
                    if (window.showCartPreview) window.showCartPreview();
                    if (window.showToast) window.showToast(data.message || __('bundle_added', null, 'Bundle added'), 'success');
                    if (button) button.textContent = __('added', null, 'Added');
                })
                .catch(error => {
                    if (window.showToast) window.showToast(error.message || __('bundle_add_failed', null, 'Could not add bundle'), 'error');
                    if (button) button.textContent = original;
                })
                .finally(() => {
                    setTimeout(() => {
                        if (button) {
                            button.disabled = false;
                            button.textContent = original || __('add_bundle', null, 'Add bundle to cart');
                        }
                    }, 1200);
                });
        });
    });

    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            
            const submitBtn = this.querySelector('.add-to-cart');
            if (submitBtn && submitBtn.disabled) return;

            const productId = this.querySelector('input[name="product_id"]').value;
            const quantity = this.querySelector('input[name="quantity"]').value;
            if (typeof window.addToCart === 'function') {
                window.addToCart(productId, quantity, submitBtn);
            }
        });
    });
    
    // Initialize lazy loading for product images
    const productImages = document.querySelectorAll('.product-image img');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src || img.src;
                img.classList.remove('skeleton');
                observer.unobserve(img);
            }
        });
    });
    
    productImages.forEach(img => {
        img.classList.add('skeleton');
        imageObserver.observe(img);
    });
    
    if (document.querySelector('.scroll-to-top')) {
        return;
    }

    // Add scroll to top button
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.className = 'scroll-to-top';
    scrollToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollToTopBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
    document.body.appendChild(scrollToTopBtn);
    
    // Show/hide scroll to top button
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.classList.add('show');
        } else {
            scrollToTopBtn.classList.remove('show');
        }
    });
});

/**
 * Quick view modal functionality
 * Opens a modal with product details for quick viewing
 */
function openQuickView(productId) {
    // Create modal backdrop
    const modal = document.createElement('div');
    modal.className = 'quick-view-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div class="quick-view-content" style="
            background: white;
            border-radius: 12px;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        ">
            <div class="loading-spinner" style="
                text-align: center;
                padding: 60px 40px;
                color: #666;
            ">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>${__('loading_product_details', null, 'Loading product details...')}</p>
            </div>
            <button class="close-modal" onclick="closeQuickView()" style="
                position: absolute;
                top: 15px;
                right: 15px;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                z-index: 10;
            ">&times;</button>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Animate in
    setTimeout(() => modal.style.opacity = '1', 10);
    
    // Load product data via AJAX
    fetch(`ajax/get_product_details.php?id=${productId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayProductDetails(modal, data.product);
            } else {
                showQuickViewError(modal, data.error || __('product_details_failed', null, 'Failed to load product details'));
            }
        })
        .catch(error => {
            console.error('Error loading product:', error);
            showQuickViewError(modal, __('error_with_message', { message: error.message }, 'Error: {message}'));
        });
}

/**
 * Display product details in the quick view modal
 */
function displayProductDetails(modal, product) {
    const content = modal.querySelector('.quick-view-content');
    const priceAED = parseFloat(product.price);
    const priceUSD = (typeof window.usdFromAed === 'function' ? window.usdFromAed(priceAED) : (priceAED * (window.AED_TO_USD_RATE || 0.2723)).toFixed(2));
    
    content.innerHTML = `
        <button class="close-modal" onclick="closeQuickView()" style="
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            z-index: 10;
        ">&times;</button>
        
        <div class="quick-view-body" style="
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
        ">
            <div class="product-image-section">
                <img src="${product.main_image || 'assets/images/no-image.png'}" 
                     alt="${product.name_en}" 
                     style="
                        width: 100%;
                        height: auto;
                        border-radius: 8px;
                        object-fit: cover;
                     ">
            </div>
            
            <div class="product-details-section">
                <h3 style="margin: 0 0 1rem 0; color: #333; font-size: 1.5rem;">${product.name_en}</h3>
                
                ${product.category_name ? `<p style="margin: 0.5rem 0; color: #666;"><strong>${__('category_label', null, 'Category:')}</strong> ${product.category_name}</p>` : ''}
                ${product.brand_name ? `<p style="margin: 0.5rem 0; color: #666;"><strong>${__('brand_label', null, 'Brand:')}</strong> ${product.brand_name}</p>` : ''}
                
                <div class="product-price" style="margin: 1.5rem 0;">
                    <span style="font-size: 1.5rem; font-weight: bold; color: #007bff;">${typeof window.formatStoreMoney === 'function' ? window.formatStoreMoney(priceAED, false) : priceAED.toLocaleString()}</span>
                    <span style="font-size: 1rem; color: #666; margin-left: 0.5rem;">${window.AG_CURRENCY && window.AG_CURRENCY.code !== 'AED' ? '(AED ' + priceAED.toFixed(2) + ')' : '($' + priceUSD + ')'}</span>
                </div>
                
                ${product.description_en ? `
                    <div style="margin: 1.5rem 0;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #333;">${__('description', null, 'Description')}</h4>
                        <p style="color: #666; line-height: 1.6;">${product.description_en}</p>
                    </div>
                ` : ''}
                
                <div class="product-actions" style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button class="btn btn-primary add-to-cart-modal" 
                            data-id="${product.id}" 
                            data-name="${product.name_en}"
                            style="
                                background: #007bff;
                                color: white;
                                border: none;
                                padding: 0.75rem 1.5rem;
                                border-radius: 6px;
                                cursor: pointer;
                                font-weight: 500;
                                transition: background 0.3s ease;
                            "
                            onmouseover="this.style.background='#0056b3'"
                            onmouseout="this.style.background='#007bff'">
                        <i class="fas fa-shopping-cart" style="margin-right: 0.5rem;"></i>${__('add_to_cart', null, 'Add to Cart')}
                    </button>
                    
                    <a href="product.php?id=${product.id}" 
                       style="
                            background: #6c757d;
                            color: white;
                            text-decoration: none;
                            padding: 0.75rem 1.5rem;
                            border-radius: 6px;
                            font-weight: 500;
                            transition: background 0.3s ease;
                            display: inline-flex;
                            align-items: center;
                       "
                       onmouseover="this.style.background='#545b62'"
                       onmouseout="this.style.background='#6c757d'">
                        <i class="fas fa-eye" style="margin-right: 0.5rem;"></i>${__('view_full_details', null, 'View Full Details')}
                    </a>
                </div>
            </div>
        </div>
    `;
    
    // Add event listener for the add to cart button in modal
    const addToCartBtn = content.querySelector('.add-to-cart-modal');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId = this.dataset.id;
            const productName = this.dataset.name;
            
            // Add loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>' + __('adding', null, 'Adding...');
            this.disabled = true;
            
            // Call the existing addToCart function
            addToCart(productId, 1);
            
            // Reset button after a delay
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-shopping-cart" style="margin-right: 0.5rem;"></i>' + __('add_to_cart', null, 'Add to Cart');
                this.disabled = false;
            }, 2000);
        });
    }
}

/**
 * Show error message in quick view modal
 */
function showQuickViewError(modal, errorMessage) {
    const content = modal.querySelector('.quick-view-content');
    content.innerHTML = `
        <button class="close-modal" onclick="closeQuickView()" style="
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        ">&times;</button>
        
        <div style="text-align: center; padding: 60px 40px; color: #666;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc3545; margin-bottom: 1rem;"></i>
            <h3 style="margin: 0 0 1rem 0; color: #333;">${__('toast_error', null, 'Error')}</h3>
            <p style="margin: 0 0 2rem 0;">${errorMessage}</p>
            <button onclick="closeQuickView()" style="
                background: #6c757d;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 6px;
                cursor: pointer;
            ">${__('close', null, 'Close')}</button>
        </div>
    `;
}

/**
 * Close the quick view modal
 */
function closeQuickView() {
    const modal = document.querySelector('.quick-view-modal');
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => {
            if (modal.parentElement) {
                document.body.removeChild(modal);
                document.body.style.overflow = '';
            }
        }, 300);
    }
}

/**
 * Share product using Web Share API or fallback
 */
function shareProduct(name, price, id, url) {
    if (typeof url === 'string' && url.charAt(0) === '/') {
        url = window.location.origin + url;
    }
    const shareText = __('share_text', { name: name, price: price, id: id }, 'Check out this product at Aleppo Gift: {name} - AED {price} - Product Code: {id}') + `\n${url}`;

    if (typeof navigator.share === 'function') {
        navigator.share({
            title: name,
            text: shareText,
            url: url
        }).catch(function (error) {
            if (!error || error.name !== 'AbortError') {
                console.error(error);
            }
        });
        return;
    }

    window.prompt(__('copy_link_prompt', null, 'Copy this link to share:'), url);
}
