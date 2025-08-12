<?php

$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
?>
<!-- Header -->



    <header class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 px-4 modern-header">
        <div class="container-fluid">
            <a class="navbar-brand modern-brand" href="index.php">
                <img src="uploads/logo.png" alt="AleppoGift Logo" class="brand-logo" style="height: 45px;">
                <span class="brand-text">AleppoGift</span>
            </a>
            
            <button class="navbar-toggler modern-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="badge bg-primary position-absolute translate-middle cart-badge" id="cart-count-toggle">
                    <?php echo isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0; ?>
                </span>
                <span class="navbar-toggler-icon"></span>
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
                        <a class="nav-link" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="product.php">Products</a>
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

                    <!-- Cart -->
                    <li class="nav-item ms-2">
                        <button onclick="toggleCart()" class="btn btn-outline-primary position-relative">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="cart-count">
                                <?php echo isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0; ?>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>




        </div>
    </header>


