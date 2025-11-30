<?php
// Calculate cart count properly
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}
?>
<!-- Header -->



    <header class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 px-4 modern-header" role="banner">
        <div class="container-fluid" style="max-width: 1600px;">
            <a class="navbar-brand modern-brand" href="index.php" aria-label="Aleppo Gift Home">
                <img src="uploads/logo.png" alt="Aleppo Gift Logo" class="brand-logo" style="height: 45px;">
                
            </a>

            <button class="navbar-toggler modern-toggler" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Mobile Cart Button (visible only on small screens) -->
            <button class="btn btn-outline-primary position-relative cart-button-mobile d-md-none ms-2" 
                    type="button"
                    data-bs-toggle="offcanvas" 
                    data-bs-target="#cartOffcanvas" 
                    aria-controls="cartOffcanvas"
                    aria-label="Open shopping cart with <?php echo $cart_count; ?> items"
                    title="Open Cart">
                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" 
                      id="cart-count-mobile"
                      aria-label="<?php echo $cart_count; ?> items in cart">
                    <?php echo $cart_count; ?>
                </span>
            </button>
            
            <!-- Expanding Search Form (initially hidden) -->
            <div id="search-bar" class="position-absolute top-0 start-0 w-100 bg-white shadow-sm p-3 d-none" style="z-index: 1060;">
                <div class="container-fluid" style="max-width: 1600px;">
                    <form class="d-flex align-items-center" action="index.php" method="get">
                        <input class="form-control me-2" type="search" 
                            name="search" 
                            placeholder="Search products..." 
                            value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                            aria-label="Search products"
                            autocomplete="off">
                        <button class="btn btn-primary px-4" type="submit" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="search-close" aria-label="Close search">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="navbarNav">
                <nav role="navigation" aria-label="Main navigation">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php" aria-label="Home page">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php" aria-label="Shopping cart">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="shipping.php" aria-label="Shipping information">Shipping</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php" aria-label="About us">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.php" aria-label="Contact us">Contact</a>
                        </li>

                        <!-- Search Icon -->
                        <li class="nav-item">
                            <button class="btn btn-outline-secondary d-flex align-items-center justify-content-center" 
                                    type="button" 
                                    id="search-toggle"
                                    aria-label="Open search"
                                    style="width: 40px; height: 40px; border-radius: 8px;">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </button>
                        </li>

                        <!-- Desktop Cart Button -->
                        <li class="nav-item ms-2">
                            <button class="btn btn-outline-primary position-relative cart-button-header" 
                                    type="button"
                                    onclick="toggleCartPreview()"
                                    aria-label="View shopping cart with <?php echo $cart_count; ?> items"
                                    aria-expanded="false"
                                    aria-controls="cartPreview"
                                    title="View Cart">
                                <i class="fas fa-shopping-cart me-1" aria-hidden="true"></i>
                                <span class="d-none d-lg-inline">Cart</span>
                                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle cart-count-badge" 
                                      id="cart-count"
                                      aria-label="<?php echo $cart_count; ?> items in cart">
                                    <?php echo $cart_count; ?>
                                </span>
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>




        </div>
    </header>

    <!-- Desktop Cart Preview -->
    <div id="cartPreview" 
         class="card shadow-lg position-fixed end-0 mt-1" 
         style="display: none; z-index: 1050; width: 380px; max-height: 80vh; top: 70px; right: 20px; border-radius: 12px; overflow: hidden;"
         role="dialog"
         aria-label="Shopping cart preview">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom" style="padding: 1rem 1.25rem;">
            <h6 class="mb-0 fw-bold"><i class="fas fa-shopping-cart me-2 text-primary" aria-hidden="true"></i>Cart Preview</h6>
            <button type="button" 
                    class="btn-close" 
                    onclick="hideCartPreview()"
                    aria-label="Close cart preview"></button>
        </div>
        <div class="card-body p-0" style="max-height: calc(80vh - 70px); overflow-y: auto;">
            <div id="cart-items-preview">
                <div class="text-center p-4">
                    <i class="fas fa-shopping-cart text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="text-muted mt-3 mb-0">Your cart is empty</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Offcanvas for Mobile -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header bg-primary text-white">
            <h5 class="offcanvas-title" id="cartOffcanvasLabel">
                <i class="fas fa-shopping-cart me-2"></i>Your Cart
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div id="cart-items-offcanvas">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading cart...</p>
                </div>
            </div>
        </div>
    </div>


