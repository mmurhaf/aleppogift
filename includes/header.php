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
                    data-bs-toggle="offcanvas" 
                    data-bs-target="#cartOffcanvas" 
                    aria-controls="cartOffcanvas"
                    aria-label="Open shopping cart"
                    title="Open Cart">
                <a class="nav-link" href="cart.php" aria-label="View cart"><i class="fas fa-shopping-cart" aria-hidden="true"></i></a>
                   
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" 
                      id="cart-count-mobile"
                      aria-label="<?php echo $cart_count; ?> items in cart">
                    <?php echo isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0; ?>
                </span>
            </button>
                      
                        <!-- Expanding Search Form (initially hidden) -->
            <div id="search-bar" class="position-absolute top-0 start-0 w-100 bg-white shadow p-2 d-none">
                <form class="d-flex" action="index.php" method="get">
                    <input class="form-control me-2" type="search" 
                        name="search" 
                        placeholder="Search products..." 
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        aria-label="Search">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <button type="button" class="btn btn-light ms-2" id="search-close">
                        <i class="fas fa-times"></i>
                    </button>
                </form>
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
                        <li class="nav-item mx-2">
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    id="search-toggle"
                                    aria-label="Open search">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </button>
                        </li>

                        <!-- Simplified Cart Button -->
                        <li class="nav-item ms-2">
                            <button class="btn btn-outline-primary position-relative" 
                                    onclick="toggleCartPreview()"
                                    aria-label="View shopping cart with <?php echo $cart_count; ?> items"
                                    aria-expanded="false"
                                    aria-controls="cartPreview"
                                    title="View Cart">
                                <i class="fas fa-shopping-cart me-1" aria-hidden="true"></i>
                                <span class="d-none d-md-inline">Cart</span>
                                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" 
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

    <!-- Simplified Desktop Cart Preview -->
    <div id="cartPreview" 
         class="card shadow position-absolute end-0 mt-2 me-4" 
         style="display: none; z-index: 1050; width: 350px; max-height: 500px; overflow-y: auto;"
         role="dialog"
         aria-label="Shopping cart preview">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-shopping-cart me-2" aria-hidden="true"></i>Cart Preview</h6>
            <button type="button" 
                    class="btn-close btn-sm" 
                    onclick="hideCartPreview()"
                    aria-label="Close cart preview"></button>
        </div>
        <div class="card-body p-0">
            <div id="cart-items-container">
                <div class="text-center p-3">
                    <i class="fas fa-shopping-cart text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 mb-0">Your cart is empty</p>
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


