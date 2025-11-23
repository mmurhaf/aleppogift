<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Testing Tools - AleppoGift</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .tool-card { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #007bff; }
        .tool-card h3 { margin-top: 0; color: #007bff; }
        .tool-card a { display: inline-block; background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-top: 10px; }
        .tool-card a:hover { background: #0056b3; }
        .info { color: #007bff; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 AleppoGift Email Testing Tools</h1>
        <p>Complete suite of email testing tools for your production environment.</p>
        
        <div class="success">
            <strong>✅ Status:</strong> All email testing tools have been moved to the public folder for production testing.
        </div>
        
        <div class="tool-card">
            <h3>🎯 Quick Email Test</h3>
            <p>Simple email test using the same system as your checkout process. Perfect for quick verification.</p>
            <a href="production_email_test.php">Run Quick Test</a>
        </div>
        
        <div class="tool-card">
            <h3>🔧 SMTP Diagnostic Test</h3>
            <p>Advanced SMTP connection testing with multiple password options. Tests both PHPMailer and basic mail() function.</p>
            <a href="smtp_test.php">Run SMTP Test</a>
        </div>
        
        <div class="tool-card">
            <h3>🛒 Checkout Email Test</h3>
            <p>Tests the exact email functions used in your checkout process. Simulates order confirmation emails.</p>
            <a href="checkout_email_test.php">Run Checkout Test</a>
        </div>
        
        <div class="tool-card">
            <h3>🔍 System Diagnostic</h3>
            <p>Comprehensive diagnostic tool that checks all system components including database, email functions, and file permissions.</p>
            <a href="checkout_diagnostic.php">Run Full Diagnostic</a>
        </div>
        
        <div class="info">
            <h3>📋 Testing Instructions</h3>
            <ol>
                <li><strong>Start with Quick Email Test</strong> - This will tell you immediately if emails are working</li>
                <li><strong>If emails fail, run SMTP Test</strong> - This will help identify password or connection issues</li>
                <li><strong>Use Checkout Test</strong> - To verify the exact checkout email functions</li>
                <li><strong>Run Full Diagnostic</strong> - If you need to check all system components</li>
            </ol>
        </div>
        
        <div class="tool-card">
            <h3>⚙️ Current Configuration</h3>
            <?php
            require_once '../config/config.php';
            ?>
            <p><strong>SMTP Host:</strong> <?= SMTP_HOST ?></p>
            <p><strong>SMTP Port:</strong> <?= SMTP_PORT ?></p>
            <p><strong>Email From:</strong> <?= EMAIL_FROM ?></p>
            <p><strong>Environment:</strong> <?= defined('ENVIRONMENT') ? ENVIRONMENT : 'not set' ?></p>
        </div>
        
        <div class="info">
            <h3>🔗 Quick Links</h3>
            <p><a href="../checkout.php">🛒 Checkout Page</a> - Test the actual checkout process</p>
            <p><a href="../">🏠 Main Website</a> - Back to aleppogift.com</p>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center;">
            <small>AleppoGift Email Testing Suite • Production Environment</small>
        </div>
    </div>
</body>
</html>
