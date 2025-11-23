// Enhanced AleppoGift Website JavaScript
// Modern interactions and improved user experience

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize all components - cart disabled to prevent conflicts
    initializeAnimations();
    initializeProductCards();
    initializeScrollToTop();
    // initializeEnhancedCart(); // DISABLED - using simplified cart
    initializeFilters();
    
    console.log('🎉 AleppoGift Enhanced Design Loaded');
});

// Smooth scroll animations for cards
function initializeAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);
    
    // Observe all product cards
    document.querySelectorAll('.product-card').forEach(card => {
        observer.observe(card);
    });
}

// Enhanced product card interactions
function initializeProductCards() {
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        // Add hover effect for product overlay
        const overlay = card.querySelector('.product-overlay');
        
        card.addEventListener('mouseenter', () => {
            if (overlay) {
                overlay.style.opacity = '1';
                overlay.style.transform = 'translateY(0)';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            if (overlay) {
                overlay.style.opacity = '0';
                overlay.style.transform = 'translateY(20px)';
            }
        });
        
        // Enhanced add to cart button - DISABLED to prevent conflicts with main.js
        // const addToCartBtn = card.querySelector('.add-to-cart');
        // if (addToCartBtn) {
        //     addToCartBtn.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         
        //         const productId = this.dataset.id;
        //         const productName = this.dataset.name;
        //         
        //         // Add loading state
        //         this.classList.add('loading');
        //         this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        //         
        //         // Simulate cart addition (replace with actual AJAX call)
        //         addToCart(productId, productName, this);
        //     });
        // }
        
        // Quick view functionality
        const quickViewBtn = card.querySelector('.btn-quick-view');
        if (quickViewBtn) {
            quickViewBtn.addEventListener('click', function() {
                const productId = this.dataset.id;
                openQuickView(productId);
            });
        }
        
        // Wishlist functionality
        const wishlistBtn = card.querySelector('.btn-wishlist');
        if (wishlistBtn) {
            wishlistBtn.addEventListener('click', function() {
                this.classList.toggle('active');
                const icon = this.querySelector('i');
                if (this.classList.contains('active')) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    showToast('Added to wishlist!', 'success');
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    showToast('Removed from wishlist', 'info');
                }
            });
        }
    });
}

// Enhanced add to cart functionality
function addToCart(productId, productName, button) {
    // Client-side validation
    if (!productId || productId <= 0) {
        showToast('Invalid product selected', 'error');
        button.classList.remove('loading');
        return;
    }
    
    fetch('ajax/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${encodeURIComponent(productId)}&quantity=1`
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading state
        button.classList.remove('loading');
        
        if (data.success) {
            // Success animation
            button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
            button.classList.add('success');
            
            // Update cart count
            updateCartCount();
            
            // Show success toast
            showToast(`${productName} added to cart!`, 'success');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Add to Cart';
                button.classList.remove('success');
            }, 2000);
            
        } else {
            // Enhanced error handling
            let errorMessage = data.message || 'Failed to add to cart';
            if (data.error_code === 'INVALID_PRODUCT_ID') {
                errorMessage = 'Please select a valid product';
            } else if (data.error_code === 'INVALID_QUANTITY') {
                errorMessage = 'Please select a valid quantity';
            }
            
            // Error state
            button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error';
            button.classList.add('error');
            showToast(errorMessage, 'error');
            
            // Reset button after 3 seconds
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Add to Cart';
                button.classList.remove('error');
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.classList.remove('loading');
        button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error';
        showToast('Network error. Please try again.', 'error');
    });
}

// Quick view modal functionality
function openQuickView(productId) {
    // Create modal backdrop
    const modal = document.createElement('div');
    modal.className = 'quick-view-modal';
    modal.innerHTML = `
        <div class="quick-view-content">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading product details...</p>
            </div>
            <button class="close-modal" onclick="closeQuickView()">&times;</button>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Animate in
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Load product data via AJAX
    console.log('Fetching product details for ID:', productId);
    const fetchUrl = `ajax/get_product_details.php?id=${productId}`;
    console.log('Fetch URL:', fetchUrl);
    
    fetch(fetchUrl)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.text(); // Get text first to see raw response
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    displayProductDetails(modal, data.product);
                } else {
                    showError(modal, data.error || 'Failed to load product details');
                }
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                showError(modal, 'Invalid response format: ' + text.substring(0, 100));
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            console.error('Error type:', error.constructor.name);
            console.error('Error message:', error.message);
            showError(modal, `Network error: ${error.message}`);
        });
}

function displayProductDetails(modal, product) {
    const content = modal.querySelector('.quick-view-content');
    const priceAED = parseFloat(product.price);
    const priceUSD = (priceAED / 3.68).toFixed(2);
    
    content.innerHTML = `
        <button class="close-modal" onclick="closeQuickView()">&times;</button>
        <div class="quick-view-body">
            <div class="product-image-section">
                <img src="${product.main_image}" alt="${product.name_en}" class="quick-view-image">
            </div>
            <div class="product-details-section">
                <h3 class="product-title">${product.name_en}</h3>
                ${product.category_name ? `<p class="product-category"><strong>Category:</strong> ${product.category_name}</p>` : ''}
                ${product.brand_name ? `<p class="product-brand"><strong>Brand:</strong> ${product.brand_name}</p>` : ''}
                
                <div class="product-price">
                    <span class="price-aed"><img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol">${priceAED.toLocaleString()}</span>
                    <span class="price-usd">($${priceUSD})</span>
                </div>
                
                ${product.description_en ? `<div class="product-description">
                    <h4>Description</h4>
                    <p>${product.description_en}</p>
                </div>` : ''}
                
                <div class="product-actions">
                    <button class="btn btn-primary add-to-cart-modal" data-id="${product.id}" data-name="${product.name_en}">
                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                    </button>
                    <a href="product.php?id=${product.id}" class="btn btn-secondary">
                        <i class="fas fa-eye me-2"></i>View Full Details
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
            this.classList.add('loading');
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            
            addToCart(productId, productName, this);
        });
    }
}

function showError(modal, errorMessage) {
    const content = modal.querySelector('.quick-view-content');
    content.innerHTML = `
        <button class="close-modal" onclick="closeQuickView()">&times;</button>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Error</h3>
            <p>${errorMessage}</p>
            <button class="btn btn-secondary" onclick="closeQuickView()">Close</button>
        </div>
    `;
}

function closeQuickView() {
    const modal = document.querySelector('.quick-view-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(modal);
            document.body.style.overflow = '';
        }, 300);
    }
}

// Scroll to top functionality
function initializeScrollToTop() {
    // Create scroll to top button
    const scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-to-top';
    scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollBtn.title = 'Back to top';
    document.body.appendChild(scrollBtn);
    
    // Show/hide based on scroll position
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });
    
    // Smooth scroll to top
    scrollBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Enhanced cart functionality
function initializeEnhancedCart() {
    // Update cart count display
    updateCartCount();
    
    // Cart preview auto-hide
    let cartPreviewTimeout;
    const cartPreview = document.getElementById('cartPreview');
    
    if (cartPreview) {
        cartPreview.addEventListener('mouseenter', () => {
            clearTimeout(cartPreviewTimeout);
        });
        
        cartPreview.addEventListener('mouseleave', () => {
            cartPreviewTimeout = setTimeout(() => {
                cartPreview.style.display = 'none';
            }, 3000);
        });
    }
}

// Update cart count
function updateCartCount() {
    fetch('ajax/get_cart_count.php')
    .then(response => response.json())
    .then(data => {
        const cartCountElements = document.querySelectorAll('#cart-count, #cart-count-toggle');
        cartCountElements.forEach(element => {
            if (element) {
                element.textContent = data.count || 0;
                if (data.count > 0) {
                    element.style.display = 'flex';
                } else {
                    element.style.display = 'none';
                }
            }
        });
    })
    .catch(error => console.error('Error updating cart count:', error));
}

// Enhanced filter functionality
function initializeFilters() {
    const filterForm = document.querySelector('.modern-filter-form');
    const searchInput = document.getElementById('search');
    
    if (searchInput) {
        // Add search suggestions (implement as needed)
        searchInput.addEventListener('input', debounce(function() {
            const query = this.value;
            if (query.length > 2) {
                // Implement search suggestions
                console.log('Search suggestions for:', query);
            }
        }, 300));
    }
    
    // Enhanced form submission
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.filter-submit');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Filtering...';
            }
        });
    }
}

// Toast notification system
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="toast-icon fas ${getToastIcon(type)}"></i>
            <span class="toast-message">${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 4000);
    
    // Click to dismiss
    toast.addEventListener('click', () => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    });
}

function getToastIcon(type) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    return icons[type] || icons.info;
}

// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Performance monitoring
function logPerformance() {
    window.addEventListener('load', () => {
        const perfData = performance.timing;
        const loadTime = perfData.loadEventEnd - perfData.navigationStart;
        console.log(`⚡ Page loaded in ${loadTime}ms`);
    });
}

// Initialize performance monitoring
logPerformance();
