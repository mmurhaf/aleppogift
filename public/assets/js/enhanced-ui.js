/* ==========================================================================
   ALEPPOGIFT - ENHANCED UI INTERACTIONS
   Modern JavaScript for improved user experience
   ========================================================================== */

var __ = function (k, p, fallback) {
    var s = (window.AG_I18N && window.AG_I18N[k]) || fallback || k;
    if (p) { for (var n in p) { s = s.split('{' + n + '}').join(p[n]); } }
    return s;
};

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // MOBILE MENU TOGGLE - Fixed for Bootstrap collapse
    // ========================================
    const mobileToggle = document.querySelector('.navbar-toggler, .modern-toggler');
    const navbarNav = document.querySelector('#navbarNav');
    
    if (mobileToggle && navbarNav) {
        // Close mobile menu when clicking on a nav link
        const navLinks = navbarNav.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (this.getAttribute('data-bs-toggle') === 'dropdown') {
                    return;
                }
                const bsCollapse = bootstrap.Collapse.getInstance(navbarNav);
                if (bsCollapse) {
                    bsCollapse.hide();
                }
            });
        });
    }
    
    // ========================================
    // SEARCH TOGGLE
    // ========================================
    const searchToggle = document.getElementById('search-toggle');
    const searchToggleMobile = document.getElementById('search-toggle-mobile');
    const searchBar = document.getElementById('search-bar');
    const searchClose = document.getElementById('search-close');
    const searchInput = searchBar?.querySelector('input[name="search"]');

    function openSearch(e) {
        e.preventDefault();
        searchBar.classList.remove('d-none');
        searchBar.style.display = 'block';
        if (searchInput) {
            searchInput.focus();
        }
    }

    if (searchToggle && searchBar) {
        searchToggle.addEventListener('click', openSearch);
    }

    if (searchToggleMobile && searchBar) {
        searchToggleMobile.addEventListener('click', openSearch);
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
    
    // Define cart functions globally BEFORE they are called
    window.showCartPreview = function() {
        const cartPreview = document.getElementById('cartPreview');
        if (cartPreview) {
            if (window.cartPreviewTimeout) {
                clearTimeout(window.cartPreviewTimeout);
            }
            cartPreview.style.display = 'block';
            
            // Add show class for animation
            setTimeout(() => {
                cartPreview.classList.add('show');
            }, 10);
            
            if (window.loadCartPreview) {
                window.loadCartPreview();
            }
            
            // Auto-hide after 5 seconds of no interaction
            window.cartPreviewTimeout = setTimeout(() => {
                window.hideCartPreview();
            }, 5000);
        }
    };
    
    window.hideCartPreview = function() {
        const cartPreview = document.getElementById('cartPreview');
        if (cartPreview) {
            if (window.cartPreviewTimeout) {
                clearTimeout(window.cartPreviewTimeout);
            }
            cartPreview.classList.remove('show');
            
            setTimeout(() => {
                cartPreview.style.display = 'none';
            }, 200);
        }
    };
    
    // Keep cart preview open when hovering
    const cartPreview = document.getElementById('cartPreview');
    if (cartPreview) {
        cartPreview.addEventListener('mouseenter', function() {
            if (window.cartPreviewTimeout) {
                clearTimeout(window.cartPreviewTimeout);
            }
        });
        
        cartPreview.addEventListener('mouseleave', function() {
            window.cartPreviewTimeout = setTimeout(() => {
                window.hideCartPreview();
            }, 2000);
        });
    }
    
    // ========================================
    // CART PREVIEW LOADING - Global functions
    // ========================================
    window.loadCartPreview = function() {
        const cartPreviewContent = document.querySelector('#cartPreview #cart-items-preview');
        
        if (cartPreviewContent) {
            // Show loading state
            cartPreviewContent.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">${__('loading', null, 'Loading...')}</span>
                    </div>
                    <p class="mt-2 text-muted">${__('loading_cart', null, 'Loading cart...')}</p>
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
                            <p class="text-muted">${__('unable_to_load_cart', null, 'Unable to load cart')}</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.loadCartPreview()">
                                ${__('try_again', null, 'Try Again')}
                            </button>
                        </div>
                    `;
                });
        }
    };

    window.loadCartOffcanvas = function() {
        const cartOffcanvasContent = document.querySelector('#cartOffcanvas #cart-items-offcanvas');
        
        if (cartOffcanvasContent) {
            // Show loading state
            cartOffcanvasContent.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">${__('loading', null, 'Loading...')}</span>
                    </div>
                    <p class="mt-2 text-muted">${__('loading_cart', null, 'Loading cart...')}</p>
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
                            <p class="text-muted">${__('unable_to_load_cart', null, 'Unable to load cart')}</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.loadCartOffcanvas()">
                                ${__('try_again', null, 'Try Again')}
                            </button>
                        </div>
                    `;
                });
        }
    };
    
    // Define toggleCartPreview to match header.php onclick
    window.toggleCartPreview = function() {
        const cartPreview = document.getElementById('cartPreview');
        const cartOffcanvas = document.getElementById('cartOffcanvas');
        
        // On mobile, use offcanvas
        if (window.innerWidth <= 768 && cartOffcanvas) {
            const bsOffcanvas = new bootstrap.Offcanvas(cartOffcanvas);
            bsOffcanvas.show();
            window.loadCartOffcanvas();
        } 
        // On desktop, use fixed preview
        else if (cartPreview) {
            if (cartPreview.style.display === 'none' || !cartPreview.style.display) {
                window.showCartPreview();
            } else {
                window.hideCartPreview();
            }
        }
    };
    
    // Load offcanvas content when it's shown
    const cartOffcanvasEl = document.getElementById('cartOffcanvas');
    if (cartOffcanvasEl) {
        cartOffcanvasEl.addEventListener('shown.bs.offcanvas', function() {
            window.loadCartOffcanvas();
        });
    }
    
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
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + __('adding', null, 'Adding...');
                button.disabled = true;
                
                // Simulate API call (replace with actual AJAX)
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-check me-2"></i>' + __('added_excl', null, 'Added!');
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
            
            // Note: Removed auto-hide behavior to prevent blinking issues
            // Header will remain visible for better user experience
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
        
        if (e.target.closest && e.target.closest('.add-to-cart, .card-float-cart, .add-to-cart-form')) {
            return;
        }
        if (cartPreview && cartButton) {
            if (!cartPreview.contains(e.target) && !cartButton.contains(e.target)) {
                window.hideCartPreview();
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
