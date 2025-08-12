<?php
require_once('../config/config.php');
require_once('../includes/Database.php');
$db = new Database();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - AleppoGift</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6b8a;
            --secondary-color: #f8f9fa;
            --accent-color: #e67e22;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .about-hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), url('../assets/images/about-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 5rem 0;
            margin-bottom: 3rem;
            text-align: center;
        }
        
        .about-section {
            background-color: white;
            border-radius: 8px;
            padding: 3rem;
            margin-bottom: 3rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .mission-values {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .value-card {
            background-color: var(--secondary-color);
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .value-card:hover {
            transform: translateY(-5px);
        }
        
        .value-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .team-section {
            margin-top: 4rem;
        }
        
        .team-member {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .team-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .about-hero {
                padding: 3rem 0;
            }
            
            .about-section {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="about-hero">
        <div class="container">
            <h1 class="display-4 fw-bold">Our Story</h1>
            <p class="lead">Bringing the finest Syrian gifts to your doorstep</p>
        </div>
    </div>
    
    <div class="container">
        <div class="about-section">
            <h2 class="mb-4">About AleppoGift</h2>
            <p>AleppoGift was founded in 2020 with a simple mission: to share the rich cultural heritage of Syria through authentic, high-quality gifts and products. What began as a small family business has grown into a trusted name for Syrian handicrafts, gourmet foods, and traditional gifts worldwide.</p>
            
            <p>Based in Dubai, we work directly with artisans and producers in Syria to bring you genuine products while supporting local communities. Each item in our collection tells a story of Syrian craftsmanship and tradition.</p>
        </div>
        
        <div class="about-section">
            <h2 class="mb-4">Our Mission & Values</h2>
            
            <div class="mission-values">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h3>Authenticity</h3>
                    <p>We guarantee 100% authentic Syrian products, sourced directly from artisans and traditional producers.</p>
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
                    <p>We support Syrian artisans and small businesses by providing them with international market access.</p>
                </div>
            </div>
        </div>
        
        <div class="about-section">
            <h2 class="mb-4">Our Products</h2>
            <p>AleppoGift specializes in:</p>
            <ul>
                <li>Traditional Syrian sweets and gourmet foods</li>
                <li>Handcrafted soap and olive oil products</li>
                <li>Authentic Syrian handicrafts and home decor</li>
                <li>Unique gift sets perfect for any occasion</li>
                <li>Custom corporate gifts with Syrian flair</li>
            </ul>
            
            <p>Whether you're looking for a taste of home or wanting to share Syrian culture with friends, we have something special for every need.</p>
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
    </div>
    
    <?php include('../includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>