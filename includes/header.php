<?php
// Calculate cart count properly
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}
?>
<!-- Header -->



    <header class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 px-4 modern-header">
        <div class="container-fluid" style="max-width: 1600px;"">
            <a class="navbar-brand modern-brand" href="index.php">
                <img src="uploads/logo.png" alt="aleppogift Logo" class="brand-logo" style="height: 45px;">
                
            </a>

            <button class="navbar-toggler modern-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Mobile Cart Button (visible only on small screens) -->
            <button class="btn btn-outline-primary position-relative cart-button-mobile d-md-none ms-2" 
                    onclick="toggleCartPreview()"
                    title="Open Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="cart-count-mobile">
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
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">Cart</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shipping.php">Shipping</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>

                    <!-- Search Icon -->
                    <li class="nav-item mx-2">
                        <button class="btn btn-outline-secondary" type="button" id="search-toggle">
                            <i class="fas fa-search"></i>
                        </button>
                    </li>

                    <!-- Simplified Cart Button -->
                    <li class="nav-item ms-2">
                        <button class="btn btn-outline-primary position-relative" 
                                onclick="toggleCartPreview()"
                                title="View Cart">
                            <i class="fas fa-shopping-cart me-1"></i>
                            <span class="d-none d-md-inline">Cart</span>
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="cart-count">
                                <?php echo $cart_count; ?>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>




        </div>
    </header>

    <!-- Simplified Desktop Cart Preview -->
    <div id="cartPreview" class="card shadow position-absolute end-0 mt-2 me-4" style="display: none; z-index: 1050; width: 350px; max-height: 500px; overflow-y: auto;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Cart Preview</h6>
            <button type="button" class="btn-close btn-sm" onclick="hideCartPreview()"></button>
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


