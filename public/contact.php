<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - AleppoGift</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-vhXpM9L+O7XN5I/dkD7eC/rJk0XLM8xQp7y8fC9KtDzfdU9J+XZPldOZDjQ+oZOG6ZlODb3WyzlsVQF7b0Vmmw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #e67e22; /* AleppoGift orange */
            --secondary-color: #2c3e50;
            --light-color: #f9f9f9;
            --dark-color: #333;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--light-color);
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .contact-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem 0;
            background: linear-gradient(135deg, var(--primary-color), #d35400);
            color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .contact-header h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .contact-content {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .contact-info {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .contact-info h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .contact-info p {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .contact-info i {
            margin-right: 1rem;
            color: var(--primary-color);
            font-size: 1.2rem;
            width: 1.5rem;
            text-align: center;
        }
        
        .contact-info a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .contact-info a:hover {
            color: var(--primary-color);
        }
        
        .social-section {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .social-section h3 {
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: white;
            color: var(--secondary-color);
            border-radius: 50%;
            font-size: 1.5rem;
            transition: all 0.3s;
            box-shadow: var(--shadow);
        }
        
        .social-icons a:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-5px);
        }
        
        .back-link {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: var(--secondary-color);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .back-link:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .back-link i {
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .contact-content {
                flex-direction: column;
            }
            
            .contact-header h2 {
                font-size: 2rem;
            }
        }
    </style>
    
<style>
.social-section {
    text-align: center;
    margin: 3rem 0;
    padding: 2rem;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.social-section h3 {
    color: #e67e22;
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

.social-subtitle {
    color: #666;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.social-icons {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.social-icons a {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 80px;
    padding: 1rem 0.5rem;
    text-decoration: none;
    color: #2c3e50;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.social-icons a:hover {
    transform: translateY(-5px);
    background-color: #f8f8f8;
}

.social-icons i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: #e67e22;
}

.social-name {
    font-size: 0.9rem;
    font-weight: 500;
}

@media (max-width: 600px) {
    .social-icons {
        gap: 1rem;
    }
    
    .social-icons a {
        width: 70px;
        padding: 0.8rem 0.3rem;
    }
    
    .social-icons i {
        font-size: 1.8rem;
    }
}
</style>
</head>
<body>
    <div class="container">
        <div class="contact-header">
            <h2>Get In Touch With Us</h2>
            <p>We'd love to hear from you! Reach out through any of these channels.</p>
        </div>
        
        <div class="contact-content">
            <div class="contact-info">
                <h3>Contact Information</h3>
                <p>
                    <i class="fas fa-envelope"></i>
                    <strong>Email:</strong> <a href="mailto:info@aleppogift.com">info@aleppogift.com</a>
                </p>
                <p>
                    <i class="fas fa-phone"></i>
                    <strong>Phone:</strong> <a href="tel:+971561125320">+971 56 112 5320</a>
                </p>
                <p>
                    <i class="fas fa-map-marker-alt"></i>
                    <strong>Address:</strong> Online store only, Deira, Dubai, UAE
                </p>
                <p>
                    <i class="fas fa-clock"></i>
                    <strong>Business Hours:</strong> Sunday - Friday, 9:00 AM - 6:00 PM
                </p>
            </div>
        </div>
        
<div class="social-section">
    <h3>Follow Us On Social Media</h3>
    <p class="social-subtitle">Stay updated with our latest products and offers</p>
    
    <div class="social-icons">
        <a href="https://www.facebook.com/aleppo.gift.2025/" target="_blank" aria-label="Facebook">
            <i class="fab fa-facebook-f"></i>
            <span class="social-name">Facebook</span>
        </a>
        <a href="https://www.instagram.com/best_deal_in_uae/" target="_blank" aria-label="Instagram">
            <i class="fab fa-instagram"></i>
            <span class="social-name">Instagram</span>
        </a>
        <a href="https://www.tiktok.com/@aleppogift" target="_blank" aria-label="TikTok">
            <i class="fab fa-tiktok"></i>
            <span class="social-name">TikTok</span>
        </a>
        <a href="https://www.pinterest.com/Krmeencom" target="_blank" aria-label="Pinterest">
            <i class="fab fa-pinterest"></i>
            <span class="social-name">Pinterest</span>
        </a>
    </div>
</div>

        
        <p><a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
    </div>
</body>
</html>