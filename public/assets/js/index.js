/**
 * Index Page JavaScript - Enhanced Product Functionality
 * Handles quick view, add to cart, product sharing, and other interactive features
 */

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
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            
            // Prevent double submission
            const submitBtn = this.querySelector('.add-to-cart');
            if (submitBtn.disabled) return;
            
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            
            const productId = this.querySelector('input[name="product_id"]').value;
            const productName = this.querySelector('.add-to-cart').dataset.name;
            const quantity = this.querySelector('input[name="quantity"]').value;
            
                // Get price from the product card safely
                let price = 0;
                const productCard = this.closest('.product-card');
                if (productCard) {
                    const priceElement = productCard.querySelector('.price-current');
                    if (priceElement) {
                        const priceText = priceElement.textContent.replace(/[^\d.]/g, '');
                        price = parseFloat(priceText);
                    }
                }
            
            addToCart(productId, quantity);
            
            // Re-enable button after a delay
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 1000);
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
                <p>Loading product details...</p>
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
                showQuickViewError(modal, data.error || 'Failed to load product details');
            }
        })
        .catch(error => {
            console.error('Error loading product:', error);
            showQuickViewError(modal, `Error: ${error.message}`);
        });
}

/**
 * Display product details in the quick view modal
 */
function displayProductDetails(modal, product) {
    const content = modal.querySelector('.quick-view-content');
    const priceAED = parseFloat(product.price);
    const priceUSD = (priceAED / 3.68).toFixed(2);
    
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
                
                ${product.category_name ? `<p style="margin: 0.5rem 0; color: #666;"><strong>Category:</strong> ${product.category_name}</p>` : ''}
                ${product.brand_name ? `<p style="margin: 0.5rem 0; color: #666;"><strong>Brand:</strong> ${product.brand_name}</p>` : ''}
                
                <div class="product-price" style="margin: 1.5rem 0;">
                    <span style="font-size: 1.5rem; font-weight: bold; color: #007bff;">AED ${priceAED.toLocaleString()}</span>
                    <span style="font-size: 1rem; color: #666; margin-left: 0.5rem;">($${priceUSD})</span>
                </div>
                
                ${product.description_en ? `
                    <div style="margin: 1.5rem 0;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #333;">Description</h4>
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
                        <i class="fas fa-shopping-cart" style="margin-right: 0.5rem;"></i>Add to Cart
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
                        <i class="fas fa-eye" style="margin-right: 0.5rem;"></i>View Full Details
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
            this.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>Adding...';
            this.disabled = true;
            
            // Call the existing addToCart function
            addToCart(productId, 1);
            
            // Reset button after a delay
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-shopping-cart" style="margin-right: 0.5rem;"></i>Add to Cart';
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
            <h3 style="margin: 0 0 1rem 0; color: #333;">Error</h3>
            <p style="margin: 0 0 2rem 0;">${errorMessage}</p>
            <button onclick="closeQuickView()" style="
                background: #6c757d;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 6px;
                cursor: pointer;
            ">Close</button>
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
    const shareText = `Check out this product at Aleppo Gift: ${name} - AED ${price} - Product Code: ${id}\n${url}`;

    if (navigator.share) {
        navigator.share({
            title: name,
            text: shareText,
            url: url
        }).then(() => {
            console.log('Thanks for sharing!');
        }).catch(console.error);
    } else {
        // Fallback (if not supported)
        alert("Sharing is not supported in this browser. Please copy the link manually:\n" + url);
    }
}
