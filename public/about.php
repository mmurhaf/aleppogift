<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
     <!--<link rel="stylesheet" href="assets/css/style.css">-->
	<link rel="stylesheet" href="assets/css/index.css">
	<link rel="stylesheet" href="assets/css/enhanced-design.css">
	<link rel="stylesheet" href="assets/css/components.css">
	<link rel="stylesheet" href="assets/css/ui-components.css">
	<link rel="stylesheet" href="assets/css/header-fixes.css">
	
	<!-- Google Fonts for Enhanced Typography -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>
    <div class="container">
		<!-- Cart Preview -->
		<div id="cartPreview" class="card shadow position-absolute end-0 mt-2 me-4 cart-preview" style="display: none;">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h5 class="card-title mb-0"><i class="fas fa-shopping-cart me-2"></i>Your Cart</h5>
					<button type="button" class="btn-close" aria-label="Close cart" onclick="toggleCart()"></button>
				</div>
				<div id="cart-items-preview">
					<p class="text-muted text-center py-3">Your cart is empty</p>
				</div>
				<div class="d-grid gap-2 mt-3">
					<a href="cart.php" class="btn btn-primary">View Full Cart</a>
					<a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
				</div>
			</div>
		</div>

    <!-- Main Content -->
    <main class="container my-4">
        <!-- Hero Section -->
        <section class="hero-section modern-hero text-center mb-5">
            <div class="hero-content">
                <div class="hero-badge">✨ About Us</div>
                <h1 class="hero-title">Our Story</h1>
                <p class="hero-subtitle">Bringing the finest Chinese gifts to your doorstep</p>
            </div>
        </section>
    
    <div class="container">
        <div class="about-section">
            <h2 class="mb-4">About AleppoGift</h2>
            <p>AleppoGift was founded in 2020 with a simple mission: to share the rich cultural heritage of China through authentic, high-quality gifts and products. What began as a small family business has grown into a trusted name for Chinese handicrafts, gourmet foods, and traditional gifts worldwide.</p>
            
            <p>Based in Dubai, we work directly with artisans and producers in China to bring you genuine products while supporting local communities. Each item in our collection tells a story of Chinese craftsmanship and tradition.</p>
        </div>
        
        <div class="about-section">
            <h2 class="mb-4">Our Mission & Values</h2>
            
            <div class="mission-values">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h3>Authenticity</h3>
                    <p>We guarantee 100% authentic Chinese products, sourced directly from artisans and traditional producers.</p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Quality</h3>
                    <p>Every product undergoes rigorous quality checks to meet our high standards before reaching you.</p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Community</h3>
                    <p>We support Chinese artisans and small businesses by providing them with international market access.</p>
                </div>
            </div>
        </div>
        
        <div class="about-section">
            <h2 class="mb-4">Our Products</h2>
            <p>AleppoGift specializes in:</p>
            <ul>
                <li>Traditional Chinese sweets and gourmet foods</li>
                <li>Handcrafted soap and olive oil products</li>
                <li>Authentic Chinese handicrafts and home decor</li>
                <li>Unique gift sets perfect for any occasion</li>
                <li>Custom corporate gifts with Chinese flair</li>
            </ul>
            
            <p>Whether you're looking for a taste of home or wanting to share Chinese culture with friends, we have something special for every need.</p>
        </div>
        
        <div class="about-section team-section">
            <h2 class="mb-4">Meet Our Team</h2>
            
            <div class="row">
                <div class="col-md-4 team-member">
                    <img src="../assets/images/team1.jpg" alt="Mohammed Al-Halabi" class="team-img">
                    <h3>Mohammed Al-Halabi</h3>
                    <p>Founder & CEO</p>
                </div>
                
                <div class="col-md-4 team-member">
                    <img src="../assets/images/team2.jpg" alt="Amina Khoury" class="team-img">
                    <h3>Amina Khoury</h3>
                    <p>Head of Product</p>
                </div>
                
                <div class="col-md-4 team-member">
                    <img src="../assets/images/team3.jpg" alt="Youssef Hamwi" class="team-img">
                    <h3>Youssef Hamwi</h3>
                    <p>Customer Relations</p>
                </div>
            </div>
        </div>
    </main>

    </div>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js"></script>
	<script src="assets/js/enhanced-main.js"></script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>