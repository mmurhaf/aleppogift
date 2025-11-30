/* ==========================================================================
   ALEPPOGIFT - ENHANCED UI INTERACTIONS
   Modern JavaScript for improved user experience
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // MOBILE MENU TOGGLE
    // ========================================
    const mobileToggle = document.querySelector('.navbar-toggler, .modern-toggler');
    const navbarNav = document.querySelector('.navbar-nav');
    
    if (mobileToggle && navbarNav) {
        mobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            navbarNav.classList.toggle('show');
            
            // Animate hamburger icon
            const icon = mobileToggle.querySelector('.navbar-toggler-icon');
            if (icon) {
                icon.classList.toggle('active');
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileToggle.contains(e.target) && !navbarNav.contains(e.target)) {
                navbarNav.classList.remove('show');
            }
        });
    }
    
    // ========================================
    // SEARCH TOGGLE
    // ========================================
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.getElementById('search-bar');
    const searchClose = document.getElementById('search-close');
    const searchInput = searchBar?.querySelector('input[name="search"]');
    
    if (searchToggle && searchBar) {
        searchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            searchBar.classList.remove('d-none');
            searchBar.style.display = 'block';
            if (searchInput) {
                searchInput.focus();
            }
        });
    }
    
    if (searchClose && searchBar) {
        searchClose.addEventListener('click', function(e) {
            e.preventDefault();
            searchBar.classList.add('d-none');
            searchBar.style.display = 'none';
        });
    }
    
    // Close search on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && searchBar && !searchBar.classList.contains('d-none')) {
            searchBar.classList.add('d-none');
            searchBar.style.display = 'none';
        }
    });
    
    // ========================================
    // ENHANCED CART FUNCTIONALITY
    // ========================================
    let cartPreviewTimeout;
    
    // Define toggleCart only if it hasn't been defined by other scripts (avoid conflicts)
    if (!window.toggleCart) {
        window.toggleCart = function() {
            const cartPreview = document.getElementById('cartPreview');
            const cartOffcanvas = document.getElementById('cartOffcanvas');
            
            // On mobile, use offcanvas
            if (window.innerWidth <= 768 && cartOffcanvas) {
                const bsOffcanvas = new bootstrap.Offcanvas(cartOffcanvas);
                bsOffcanvas.show();
                loadCartOffcanvas();
            } 
            // On desktop, use fixed preview
            else if (cartPreview) {
                if (cartPreview.style.display === 'none' || !cartPreview.style.display) {
                    showCartPreview();
                } else {
                    hideCartPreview();
                }
            }
        };
    } else {
        console.log('enhanced-ui: window.toggleCart already defined, skipping override');
    }
    
    function showCartPreview() {
        const cartPreview = document.getElementById('cartPreview');
        if (cartPreview) {
            clearTimeout(cartPreviewTimeout);
            cartPreview.style.display = 'block';
            
            // Add show class for animation
            setTimeout(() => {
                cartPreview.classList.add('show');
            }, 10);
            
            loadCartPreview();
            
            // Auto-hide after 5 seconds of no interaction
            cartPreviewTimeout = setTimeout(() => {
                hideCartPreview();
            }, 5000);
        }
    }
    
    function hideCartPreview() {
        const cartPreview = document.getElementById('cartPreview');
        if (cartPreview) {
            clearTimeout(cartPreviewTimeout);
            cartPreview.classList.remove('show');
            
            setTimeout(() => {
                cartPreview.style.display = 'none';
            }, 200);
        }
    }
    
    // Keep cart preview open when hovering
    const cartPreview = document.getElementById('cartPreview');
    if (cartPreview) {
        cartPreview.addEventListener('mouseenter', function() {
            clearTimeout(cartPreviewTimeout);
        });
        
        cartPreview.addEventListener('mouseleave', function() {
            cartPreviewTimeout = setTimeout(() => {
                hideCartPreview();
            }, 2000);
        });
    }
    
    // ========================================
    // CART PREVIEW LOADING
    // ========================================
    function loadCartPreview() {
        const cartPreviewContent = document.querySelector('#cartPreview #cart-items-preview');
        
        if (cartPreviewContent) {
            // Show loading state
            cartPreviewContent.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading cart...</p>
                </div>
            `;
            
            // Load cart contents via AJAX (use main cart preview endpoint with cache-buster)
            fetch('ajax/cart_preview.php?t=' + Date.now())
                .then(response => response.text())
                .then(html => {
                    cartPreviewContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading cart:', error);
                    cartPreviewContent.innerHTML = `
                        <div class="text-center p-4">
                            <p class="text-muted">Unable to load cart</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadCartPreview()">
                                Try Again
                            </button>
                        </div>
                    `;
                });
        }
    }

    function loadCartOffcanvas() {
        const cartOffcanvasContent = document.querySelector('#cartOffcanvas #cart-items-offcanvas');
        
        if (cartOffcanvasContent) {
            // Show loading state
            cartOffcanvasContent.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading cart...</p>
                </div>
            `;
            
            // Load cart contents via AJAX (use main cart preview endpoint with cache-buster)
            fetch('ajax/cart_preview.php?t=' + Date.now())
                .then(response => response.text())
                .then(html => {
                    cartOffcanvasContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading cart:', error);
                    cartOffcanvasContent.innerHTML = `
                        <div class="text-center p-4">
                            <p class="text-muted">Unable to load cart</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadCartOffcanvas()">
                                Try Again
                            </button>
                        </div>
                    `;
                });
        }
    }

    // Make functions globally available
    window.loadCartPreview = loadCartPreview;
    window.loadCartOffcanvas = loadCartOffcanvas;
    window.hideCartPreview = hideCartPreview;
    window.showCartPreview = showCartPreview;
    
    // Create alias for toggleCartPreview to match header.php onclick
    window.toggleCartPreview = window.toggleCart;
    
    // Load cart preview on page load if cart has items
    document.addEventListener('DOMContentLoaded', function() {
        // Load cart preview content when DOM is ready
        setTimeout(() => {
            if (window.loadCartPreview) {
                loadCartPreview();
            }
        }, 500);
        
        // Load offcanvas content when it's shown
        const cartOffcanvas = document.getElementById('cartOffcanvas');
        if (cartOffcanvas) {
            cartOffcanvas.addEventListener('shown.bs.offcanvas', function() {
                if (window.loadCartOffcanvas) {
                    loadCartOffcanvas();
                }
            });
        }
    });
    
    // ========================================
    // SMOOTH SCROLLING FOR ANCHOR LINKS
    // ========================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // ========================================
    // ENHANCED ADD TO CART
    // ========================================
    document.addEventListener('click', function(e) {
        if (e.target.matches('.add-to-cart-btn, .add-to-cart-btn *')) {
            const button = e.target.closest('.add-to-cart-btn');
            if (button) {
                e.preventDefault();
                
                // Add loading state
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
                button.disabled = true;
                
                // Simulate API call (replace with actual AJAX)
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                    button.classList.add('btn-success');
                    
                    // Update cart count (example)
                    updateCartCount();
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalContent;
                        button.classList.remove('btn-success');
                        button.disabled = false;
                    }, 2000);
                }, 1000);
            }
        }
    });
    
    // ========================================
    // UPDATE CART COUNT
    // ========================================
    function updateCartCount() {
        const cartCounts = document.querySelectorAll('#cart-count, .cart-count-badge, #cart-count-toggle');
        
        // This would normally be an AJAX call to get actual cart count
        fetch('ajax/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                cartCounts.forEach(element => {
                    element.textContent = data.count || '0';
                    
                    // Show/hide badge based on count
                    if (data.count > 0) {
                        element.style.display = 'inline-block';
                    } else {
                        element.style.display = 'none';
                    }
                });
            })
            .catch(error => {
                console.error('Error updating cart count:', error);
            });
    }
    
    // ========================================
    // ENHANCED HEADER SCROLL EFFECT
    // ========================================
    let lastScrollTop = 0;
    const header = document.querySelector('.main-header, .modern-header, .navbar');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (header) {
            // Add scrolled class for styling
            if (scrollTop > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            // Hide header on scroll down, show on scroll up (improved UX)
            if (scrollTop > lastScrollTop && scrollTop > 500) {
                header.classList.add('hidden');
            } else {
                header.classList.remove('hidden');
            }
        }
        
        lastScrollTop = scrollTop;
    }, { passive: true }); // Passive listener for better scroll performance
    
    // ========================================
    // INITIALIZE CART COUNT
    // ========================================
    updateCartCount();
    
    // ========================================
    // CLOSE CART PREVIEW ON OUTSIDE CLICK
    // ========================================
    document.addEventListener('click', function(e) {
        const cartPreview = document.getElementById('cartPreview');
        const cartButton = document.querySelector('.cart-button-header');
        
        if (cartPreview && cartButton) {
            if (!cartPreview.contains(e.target) && !cartButton.contains(e.target)) {
                hideCartPreview();
            }
        }
    });
});

// ========================================
// UTILITY FUNCTIONS
// ========================================

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle me-2"></i>
            ${message}
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    // Hide toast after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}
