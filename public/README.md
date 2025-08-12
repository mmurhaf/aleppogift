
🛒 AleppoGift Full E-Commerce System - Local Setup Guide
✅ REQUIREMENTS

    PHP 7.4+ (XAMPP / WAMP / MAMP recommended)

    MySQL Server (with phpMyAdmin)

    Composer (for dependencies like PHPMailer / FPDF)

🛠 INSTALLATION STEPS
1️⃣ Extract the Project

    Extract the folder to:
    C:\xampp\htdocs\aleppogift

2️⃣ Import the Database

    Open phpMyAdmin.

    Create a new database named:
    aleppogift
    
    Import the SQL file:

        Locate aleppogift_db.sql

        Paste contents or use Import tab in phpMyAdmin

3️⃣ Set Up Database Connection

    Open:
    aleppogift/config/config.php

Confirm or edit your DB credentials:
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default is empty for XAMPP
define('DB_NAME', 'aleppogift');

4️⃣ Launch the Website

    Visit:
    http://localhost/aleppogift/public/
    
    5️⃣ Admin Panel Access

    URL:
    http://localhost/aleppogift/admin/login.php
    
    Username: admin
    Password: admin123

🌐 LIVE SERVER DEPLOYMENT

    Upload the full folder contents to your hosting server’s public_html or a subdirectory.

    Then, update the following in config/config.php:
    define('SITE_URL', 'https://yourdomain.com/');

✉️ SMTP EMAIL SETUP

    In config/config.php, set your SMTP email settings:

    define('SMTP_HOST', 'smtp.yourmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', 'your-email@domain.com');
    define('SMTP_PASS', 'your-email-password');

    Used by includes/email_notifier.php to send:

        Order confirmations

        Invoices (PDF attachments)

💳 PAYMENT GATEWAY: Ziina

    Ziina API integration is fully implemented in:
    includes/ziina_payment.php

    To go live:

        Replace YOUR_ZIINA_SECRET_KEY in the file with your production key.

    Sandbox/Production redirect URLs can be configured per Ziina API documentation.

📦 FEATURES INCLUDED

    Public storefront with cart and checkout

    Admin dashboard for product/category management

    PDF invoice generation (generate_invoice.php)

    Email notifications on order confirmation

    Ziina payment intent and status handling

    Responsive front-end and simple styling