<?php
// Testing Dashboard for AleppoGift
// This dashboard provides access to all testing and diagnostic tools
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing Dashboard - AleppoGift</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #ff7f00 0%, #ff5722 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .nav-buttons {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .nav-btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background: #ff7f00;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-btn:hover {
            background: #e56b00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .content {
            padding: 30px;
        }

        .test-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .category {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border-left: 5px solid #ff7f00;
        }

        .category h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category h3 i {
            color: #ff7f00;
        }

        .test-list {
            list-style: none;
        }

        .test-list li {
            margin-bottom: 12px;
        }

        .test-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }

        .test-link:hover {
            background: #ff7f00;
            color: white;
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .test-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .test-description {
            font-size: 0.85rem;
            color: #666;
            margin-left: 30px;
            margin-top: 5px;
        }

        .test-link:hover .test-description {
            color: rgba(255,255,255,0.9);
        }

        .status-indicator {
            margin-left: auto;
            padding: 4px 8px;
            background: #e8f5e8;
            color: #2e7d32;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .footer {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            color: #666;
            border-top: 1px solid #eee;
        }

        .admin-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .admin-link:hover {
            background: #555;
        }

        .search-box {
            margin: 20px 0;
            text-align: center;
        }

        .search-input {
            padding: 12px 20px;
            width: 300px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            border-color: #ff7f00;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-flask"></i> Testing Dashboard</h1>
            <p>Comprehensive testing and diagnostic tools for AleppoGift</p>
        </div>

        <div class="nav-buttons">
            <a href="../index.php" class="nav-btn">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="../admin/dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
            </a>
            <a href="../" class="nav-btn">
                <i class="fas fa-folder"></i> Root Directory
            </a>
            <a href="javascript:window.print()" class="nav-btn">
                <i class="fas fa-print"></i> Print Report
            </a>
        </div>

        <div class="content">
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search test files...">
            </div>

            <div class="test-categories">
                <!-- Root Test Files (Production Access) -->
                <div class="category" style="border-left-color: #e91e63;">
                    <h3><i class="fas fa-folder-open"></i> Root Test Files</h3>
                    <ul class="test-list">
                        <li>
                            <a href="root_proxy.php" class="test-link" style="font-weight: bold;">
                                <i class="fas fa-server"></i>
                                Root Files Browser
                                <span class="status-indicator" style="background: #e91e63;">PRODUCTION</span>
                            </a>
                            <div class="test-description">Access all test files from root directory (normally inaccessible in production)</div>
                        </li>
                        <li>
                            <a href="scan_root_files.php" class="test-link">
                                <i class="fas fa-search"></i>
                                Root Files Scanner
                            </a>
                            <div class="test-description">Scan and discover test files in root directory</div>
                        </li>
                        <li>
                            <a href="root_files_quick_reference.html" class="test-link">
                                <i class="fas fa-book"></i>
                                Quick Reference Guide
                            </a>
                            <div class="test-description">Documentation and usage examples for root files access</div>
                        </li>
                    </ul>
                </div>

                <!-- Special Dashboards -->
                <div class="category">
                    <h3><i class="fas fa-tachometer-alt"></i> Special Dashboards</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../arabic_encoding_dashboard.php" class="test-link">
                                <i class="fas fa-language"></i>
                                Arabic Encoding Dashboard
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Comprehensive Arabic encoding management dashboard</div>
                        </li>
                        <li>
                            <a href="../system_status.php" class="test-link">
                                <i class="fas fa-heartbeat"></i>
                                System Status Dashboard
                            </a>
                            <div class="test-description">Overall system health and status monitoring</div>
                        </li>
                        <li>
                            <a href="../admin_test_page.html" class="test-link">
                                <i class="fas fa-user-shield"></i>
                                Admin Test Page
                            </a>
                            <div class="test-description">HTML test page for admin functionality</div>
                        </li>
                        <li>
                            <a href="../arabic_fix_launcher.html" class="test-link">
                                <i class="fas fa-rocket"></i>
                                Arabic Fix Launcher
                            </a>
                            <div class="test-description">HTML launcher for Arabic encoding fixes</div>
                        </li>
                    </ul>
                </div>

                <!-- Database Tests -->
                <div class="category">
                    <h3><i class="fas fa-database"></i> Database Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="test_db_connection.php" class="test-link">
                                <i class="fas fa-plug"></i>
                                Database Connection Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test basic database connectivity</div>
                        </li>
                        <li>
                            <a href="test_db_connection_enhanced.php" class="test-link">
                                <i class="fas fa-search"></i>
                                Enhanced DB Connection Test
                            </a>
                            <div class="test-description">Detailed database diagnostics</div>
                        </li>
                        <li>
                            <a href="test_db_detailed.php" class="test-link">
                                <i class="fas fa-list"></i>
                                Detailed Database Test
                            </a>
                            <div class="test-description">Comprehensive database structure test</div>
                        </li>
                        <li>
                            <a href="test_db_local.php" class="test-link">
                                <i class="fas fa-home"></i>
                                Local Database Test
                            </a>
                            <div class="test-description">Test local development database</div>
                        </li>
                        <li>
                            <a href="test_db_quick.php" class="test-link">
                                <i class="fas fa-bolt"></i>
                                Quick Database Test
                            </a>
                            <div class="test-description">Fast database health check</div>
                        </li>
                        <li>
                            <a href="../test_db_products.php" class="test-link">
                                <i class="fas fa-box"></i>
                                Products Database Test
                            </a>
                            <div class="test-description">Test product data and structure</div>
                        </li>
                        <li>
                            <a href="../test_web_db.php" class="test-link">
                                <i class="fas fa-globe"></i>
                                Web Database Test
                            </a>
                            <div class="test-description">Test web-accessible database functions</div>
                        </li>
                    </ul>
                </div>

                <!-- Email Tests -->
                <div class="category">
                    <h3><i class="fas fa-envelope"></i> Email Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="email_test.php" class="test-link">
                                <i class="fas fa-at"></i>
                                Basic Email Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test basic email functionality</div>
                        </li>
                        <li>
                            <a href="test_enhanced_email.php" class="test-link">
                                <i class="fas fa-paper-plane"></i>
                                Enhanced Email Test
                            </a>
                            <div class="test-description">Advanced email testing with templates</div>
                        </li>
                        <li>
                            <a href="test_email_advanced.php" class="test-link">
                                <i class="fas fa-cogs"></i>
                                Advanced Email Test
                            </a>
                            <div class="test-description">Complex email scenarios</div>
                        </li>
                        <li>
                            <a href="test_email_smtp.php" class="test-link">
                                <i class="fas fa-server"></i>
                                SMTP Email Test
                            </a>
                            <div class="test-description">Test SMTP configuration</div>
                        </li>
                        <li>
                            <a href="test_direct_email.php" class="test-link">
                                <i class="fas fa-direct-hit"></i>
                                Direct Email Test
                            </a>
                            <div class="test-description">Direct email sending test</div>
                        </li>
                        <li>
                            <a href="test_cc_email.php" class="test-link">
                                <i class="fas fa-copy"></i>
                                CC Email Test
                            </a>
                            <div class="test-description">Test CC and BCC functionality</div>
                        </li>
                        <li>
                            <a href="../test_email_detailed.php" class="test-link">
                                <i class="fas fa-envelope-open"></i>
                                Detailed Email Test
                            </a>
                            <div class="test-description">Comprehensive email testing</div>
                        </li>
                        <li>
                            <a href="../test_email_local.php" class="test-link">
                                <i class="fas fa-home"></i>
                                Local Email Test
                            </a>
                            <div class="test-description">Test local email configuration</div>
                        </li>
                        <li>
                            <a href="../test_ipage_smtp.php" class="test-link">
                                <i class="fas fa-cloud"></i>
                                iPage SMTP Test
                            </a>
                            <div class="test-description">Test iPage hosting SMTP</div>
                        </li>
                        <li>
                            <a href="../test_aleppogift_email.php" class="test-link">
                                <i class="fas fa-gifts"></i>
                                AleppoGift Email Test
                            </a>
                            <div class="test-description">Test AleppoGift specific email templates</div>
                        </li>
                    </ul>
                </div>

                <!-- Cart & Checkout Tests -->
                <div class="category">
                    <h3><i class="fas fa-shopping-cart"></i> Cart & Checkout Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="test_cart_direct.php" class="test-link">
                                <i class="fas fa-cart-plus"></i>
                                Direct Cart Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test cart functionality directly</div>
                        </li>
                        <li>
                            <a href="test_cart_valid.php" class="test-link">
                                <i class="fas fa-check-circle"></i>
                                Cart Validation Test
                            </a>
                            <div class="test-description">Validate cart operations</div>
                        </li>
                        <li>
                            <a href="test_cart_endpoints.php" class="test-link">
                                <i class="fas fa-link"></i>
                                Cart Endpoints Test
                            </a>
                            <div class="test-description">Test cart API endpoints</div>
                        </li>
                        <li>
                            <a href="../test_cart.php" class="test-link">
                                <i class="fas fa-shopping-basket"></i>
                                Main Cart Test
                            </a>
                            <div class="test-description">Comprehensive cart testing</div>
                        </li>
                        <li>
                            <a href="../test_cart_fix.php" class="test-link">
                                <i class="fas fa-wrench"></i>
                                Cart Fix Test
                            </a>
                            <div class="test-description">Test cart bug fixes</div>
                        </li>
                        <li>
                            <a href="../test_cart_ajax.php" class="test-link">
                                <i class="fas fa-sync"></i>
                                Cart AJAX Test
                            </a>
                            <div class="test-description">Test AJAX cart operations</div>
                        </li>
                        <li>
                            <a href="../test_add_to_cart.php" class="test-link">
                                <i class="fas fa-plus"></i>
                                Add to Cart Test
                            </a>
                            <div class="test-description">Test add to cart functionality</div>
                        </li>
                        <li>
                            <a href="test_checkout_email.php" class="test-link">
                                <i class="fas fa-credit-card"></i>
                                Checkout Email Test
                            </a>
                            <div class="test-description">Test checkout email notifications</div>
                        </li>
                        <li>
                            <a href="test_checkout_email_simple.php" class="test-link">
                                <i class="fas fa-envelope"></i>
                                Simple Checkout Email Test
                            </a>
                            <div class="test-description">Basic checkout email test</div>
                        </li>
                        <li>
                            <a href="../test_checkout.php" class="test-link">
                                <i class="fas fa-cash-register"></i>
                                Main Checkout Test
                            </a>
                            <div class="test-description">Complete checkout process test</div>
                        </li>
                    </ul>
                </div>

                <!-- Payment Tests -->
                <div class="category">
                    <h3><i class="fas fa-credit-card"></i> Payment Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../test_ziina_simple.php" class="test-link">
                                <i class="fas fa-money-bill"></i>
                                Simple Ziina Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Basic Ziina payment test</div>
                        </li>
                        <li>
                            <a href="../test_ziina_comprehensive.php" class="test-link">
                                <i class="fas fa-chart-line"></i>
                                Comprehensive Ziina Test
                            </a>
                            <div class="test-description">Full Ziina payment integration test</div>
                        </li>
                        <li>
                            <a href="../test_ziina_success.php" class="test-link">
                                <i class="fas fa-check"></i>
                                Ziina Success Test
                            </a>
                            <div class="test-description">Test successful payment flow</div>
                        </li>
                        <li>
                            <a href="../test_ziina_thankyou.php" class="test-link">
                                <i class="fas fa-heart"></i>
                                Ziina Thank You Test
                            </a>
                            <div class="test-description">Test payment thank you page</div>
                        </li>
                    </ul>
                </div>

                <!-- Shipping Tests -->
                <div class="category">
                    <h3><i class="fas fa-shipping-fast"></i> Shipping Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="test_shipping_calculations.php" class="test-link">
                                <i class="fas fa-calculator"></i>
                                Shipping Calculations
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test shipping cost calculations</div>
                        </li>
                        <li>
                            <a href="test_shipping_examples.php" class="test-link">
                                <i class="fas fa-truck"></i>
                                Shipping Examples
                            </a>
                            <div class="test-description">Test various shipping scenarios</div>
                        </li>
                        <li>
                            <a href="test_shipping_page.php" class="test-link">
                                <i class="fas fa-map"></i>
                                Shipping Page Test
                            </a>
                            <div class="test-description">Test shipping information page</div>
                        </li>
                        <li>
                            <a href="test_ajax_shipping.php" class="test-link">
                                <i class="fas fa-sync-alt"></i>
                                AJAX Shipping Test
                            </a>
                            <div class="test-description">Test AJAX shipping calculations</div>
                        </li>
                        <li>
                            <a href="test_uae_shipping_update.php" class="test-link">
                                <i class="fas fa-flag"></i>
                                UAE Shipping Update Test
                            </a>
                            <div class="test-description">Test UAE specific shipping updates</div>
                        </li>
                        <li>
                            <a href="../test_uae_symbol_simple.php" class="test-link">
                                <i class="fas fa-coins"></i>
                                UAE Symbol Test
                            </a>
                            <div class="test-description">Test UAE currency symbol display</div>
                        </li>
                    </ul>
                </div>

                <!-- System Diagnostics -->
                <div class="category">
                    <h3><i class="fas fa-stethoscope"></i> System Diagnostics</h3>
                    <ul class="test-list">
                        <li>
                            <a href="debug_checkout.php" class="test-link">
                                <i class="fas fa-bug"></i>
                                Debug Checkout
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Debug checkout process</div>
                        </li>
                        <li>
                            <a href="debug_checkout_simple.php" class="test-link">
                                <i class="fas fa-search"></i>
                                Simple Checkout Debug
                            </a>
                            <div class="test-description">Basic checkout debugging</div>
                        </li>
                        <li>
                            <a href="debug_filter.php" class="test-link">
                                <i class="fas fa-filter"></i>
                                Debug Filter
                            </a>
                            <div class="test-description">Debug product filtering</div>
                        </li>
                        <li>
                            <a href="check_fpdf.php" class="test-link">
                                <i class="fas fa-file-pdf"></i>
                                Check FPDF
                            </a>
                            <div class="test-description">Test PDF generation library</div>
                        </li>
                        <li>
                            <a href="check_products.php" class="test-link">
                                <i class="fas fa-boxes"></i>
                                Check Products
                            </a>
                            <div class="test-description">Verify product data integrity</div>
                        </li>
                        <li>
                            <a href="check_products_structure.php" class="test-link">
                                <i class="fas fa-sitemap"></i>
                                Check Products Structure
                            </a>
                            <div class="test-description">Verify product database structure</div>
                        </li>
                        <li>
                            <a href="check_product_status.php" class="test-link">
                                <i class="fas fa-clipboard-check"></i>
                                Check Product Status
                            </a>
                            <div class="test-description">Check product availability status</div>
                        </li>
                        <li>
                            <a href="test_config.php" class="test-link">
                                <i class="fas fa-cog"></i>
                                Configuration Test
                            </a>
                            <div class="test-description">Test system configuration</div>
                        </li>
                        <li>
                            <a href="test_site.php" class="test-link">
                                <i class="fas fa-globe"></i>
                                Site Test
                            </a>
                            <div class="test-description">General site functionality test</div>
                        </li>
                        <li>
                            <a href="server_test.php" class="test-link">
                                <i class="fas fa-server"></i>
                                Server Test
                            </a>
                            <div class="test-description">Test server configuration and environment</div>
                        </li>
                        <li>
                            <a href="simple_connection_test.php" class="test-link">
                                <i class="fas fa-link"></i>
                                Simple Connection Test
                            </a>
                            <div class="test-description">Basic connectivity test</div>
                        </li>
                        <li>
                            <a href="simple_test.php" class="test-link">
                                <i class="fas fa-play"></i>
                                Simple Test
                            </a>
                            <div class="test-description">Quick system check</div>
                        </li>
                        <li>
                            <a href="../test_php.php" class="test-link">
                                <i class="fab fa-php"></i>
                                PHP Test
                            </a>
                            <div class="test-description">Test PHP configuration and modules</div>
                        </li>
                        <li>
                            <a href="../test_functions_simple.php" class="test-link">
                                <i class="fas fa-code"></i>
                                Functions Test
                            </a>
                            <div class="test-description">Test core functions</div>
                        </li>
                    </ul>
                </div>

                <!-- AJAX Tests -->
                <div class="category">
                    <h3><i class="fas fa-sync-alt"></i> AJAX Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="test_ajax_path.php" class="test-link">
                                <i class="fas fa-route"></i>
                                AJAX Path Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test AJAX request paths</div>
                        </li>
                        <li>
                            <a href="../test_ajax_add_to_cart.php" class="test-link">
                                <i class="fas fa-cart-arrow-down"></i>
                                AJAX Add to Cart Test
                            </a>
                            <div class="test-description">Test AJAX add to cart functionality</div>
                        </li>
                        <li>
                            <a href="../test_ajax_path.php" class="test-link">
                                <i class="fas fa-route"></i>
                                Root AJAX Path Test
                            </a>
                            <div class="test-description">Test AJAX paths from root directory</div>
                        </li>
                    </ul>
                </div>

                <!-- Root Database Tests -->
                <div class="category">
                    <h3><i class="fas fa-database"></i> Root Database Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../test_db_connection.php" class="test-link">
                                <i class="fas fa-plug"></i>
                                Root DB Connection Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test basic database connectivity from root</div>
                        </li>
                        <li>
                            <a href="../test_db_connection_arabic.php" class="test-link">
                                <i class="fas fa-language"></i>
                                Arabic DB Connection Test
                            </a>
                            <div class="test-description">Test database connection with Arabic encoding</div>
                        </li>
                        <li>
                            <a href="../test_db_connection_enhanced.php" class="test-link">
                                <i class="fas fa-search-plus"></i>
                                Enhanced DB Connection Test
                            </a>
                            <div class="test-description">Enhanced database connectivity diagnostics</div>
                        </li>
                        <li>
                            <a href="../test_db_detailed.php" class="test-link">
                                <i class="fas fa-list-alt"></i>
                                Detailed DB Test
                            </a>
                            <div class="test-description">Comprehensive database structure analysis</div>
                        </li>
                        <li>
                            <a href="../test_db_quick.php" class="test-link">
                                <i class="fas fa-bolt"></i>
                                Quick DB Test
                            </a>
                            <div class="test-description">Fast database health check</div>
                        </li>
                    </ul>
                </div>

                <!-- Root Debug Tools -->
                <div class="category">
                    <h3><i class="fas fa-bug"></i> Root Debug Tools</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../debug_add_to_cart.php" class="test-link">
                                <i class="fas fa-cart-plus"></i>
                                Debug Add to Cart
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Debug cart addition functionality</div>
                        </li>
                        <li>
                            <a href="../debug_admin_pages.php" class="test-link">
                                <i class="fas fa-user-shield"></i>
                                Debug Admin Pages
                            </a>
                            <div class="test-description">Debug admin interface issues</div>
                        </li>
                        <li>
                            <a href="../debug_email_simple.php" class="test-link">
                                <i class="fas fa-envelope-open"></i>
                                Debug Email Simple
                            </a>
                            <div class="test-description">Simple email debugging</div>
                        </li>
                        <li>
                            <a href="../debug_email_test.php" class="test-link">
                                <i class="fas fa-at"></i>
                                Debug Email Test
                            </a>
                            <div class="test-description">Comprehensive email debugging</div>
                        </li>
                        <li>
                            <a href="../debug_filter.php" class="test-link">
                                <i class="fas fa-filter"></i>
                                Debug Filter
                            </a>
                            <div class="test-description">Debug product filtering system</div>
                        </li>
                        <li>
                            <a href="../debug_production_db.php" class="test-link">
                                <i class="fas fa-database"></i>
                                Debug Production DB
                            </a>
                            <div class="test-description">Debug production database issues</div>
                        </li>
                    </ul>
                </div>

                <!-- Arabic Encoding Tools -->
                <div class="category">
                    <h3><i class="fas fa-language"></i> Arabic Encoding Tools</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../analyze_arabic_text.php" class="test-link">
                                <i class="fas fa-search"></i>
                                Analyze Arabic Text
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Analyze Arabic text encoding issues</div>
                        </li>
                        <li>
                            <a href="../analyze_corruption_detailed.php" class="test-link">
                                <i class="fas fa-microscope"></i>
                                Analyze Corruption Detailed
                            </a>
                            <div class="test-description">Detailed text corruption analysis</div>
                        </li>
                        <li>
                            <a href="../analyze_corruption_patterns.php" class="test-link">
                                <i class="fas fa-chart-line"></i>
                                Analyze Corruption Patterns
                            </a>
                            <div class="test-description">Identify text corruption patterns</div>
                        </li>
                        <li>
                            <a href="../config_arabic_fix.php" class="test-link">
                                <i class="fas fa-cog"></i>
                                Config Arabic Fix
                            </a>
                            <div class="test-description">Configure Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_advanced.php" class="test-link">
                                <i class="fas fa-wrench"></i>
                                Fix Arabic Advanced
                            </a>
                            <div class="test-description">Advanced Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_cli.php" class="test-link">
                                <i class="fas fa-terminal"></i>
                                Fix Arabic CLI
                            </a>
                            <div class="test-description">Command-line Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_direct.php" class="test-link">
                                <i class="fas fa-direct-hit"></i>
                                Fix Arabic Direct
                            </a>
                            <div class="test-description">Direct Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_encoding.php" class="test-link">
                                <i class="fas fa-code"></i>
                                Fix Arabic Encoding
                            </a>
                            <div class="test-description">Main Arabic encoding fix tool</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_encoding_corrected.php" class="test-link">
                                <i class="fas fa-check-circle"></i>
                                Fix Arabic Encoding Corrected
                            </a>
                            <div class="test-description">Corrected Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_offline.php" class="test-link">
                                <i class="fas fa-wifi"></i>
                                Fix Arabic Offline
                            </a>
                            <div class="test-description">Offline Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_ultimate.php" class="test-link">
                                <i class="fas fa-star"></i>
                                Fix Arabic Ultimate
                            </a>
                            <div class="test-description">Ultimate Arabic encoding solution</div>
                        </li>
                        <li>
                            <a href="../fix_arabic_ultimate_pattern.php" class="test-link">
                                <i class="fas fa-pattern"></i>
                                Fix Arabic Ultimate Pattern
                            </a>
                            <div class="test-description">Pattern-based Arabic encoding fixes</div>
                        </li>
                        <li>
                            <a href="../question_mark_arabic_fix.php" class="test-link">
                                <i class="fas fa-question"></i>
                                Question Mark Arabic Fix
                            </a>
                            <div class="test-description">Fix question mark issues in Arabic text</div>
                        </li>
                        <li>
                            <a href="../test_html_entity_fix.php" class="test-link">
                                <i class="fas fa-code"></i>
                                HTML Entity Fix Test
                            </a>
                            <div class="test-description">Test HTML entity encoding fixes</div>
                        </li>
                    </ul>
                </div>

                <!-- Admin & System Check Tools -->
                <div class="category">
                    <h3><i class="fas fa-shield-alt"></i> Admin & System Check Tools</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../check_admin_user.php" class="test-link">
                                <i class="fas fa-user-check"></i>
                                Check Admin User
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Verify admin user credentials</div>
                        </li>
                        <li>
                            <a href="../check_products.php" class="test-link">
                                <i class="fas fa-boxes"></i>
                                Check Products
                            </a>
                            <div class="test-description">Verify product data integrity</div>
                        </li>
                        <li>
                            <a href="../check_products_structure.php" class="test-link">
                                <i class="fas fa-sitemap"></i>
                                Check Products Structure
                            </a>
                            <div class="test-description">Verify product database structure</div>
                        </li>
                        <li>
                            <a href="../check_product_status.php" class="test-link">
                                <i class="fas fa-clipboard-check"></i>
                                Check Product Status
                            </a>
                            <div class="test-description">Check product availability status</div>
                        </li>
                        <li>
                            <a href="../test_admin_pages_fixed.php" class="test-link">
                                <i class="fas fa-user-cog"></i>
                                Test Admin Pages Fixed
                            </a>
                            <div class="test-description">Test fixed admin page functionality</div>
                        </li>
                        <li>
                            <a href="../test_admin_redirect.php" class="test-link">
                                <i class="fas fa-exchange-alt"></i>
                                Test Admin Redirect
                            </a>
                            <div class="test-description">Test admin page redirects</div>
                        </li>
                        <li>
                            <a href="../verify_admin_fix.php" class="test-link">
                                <i class="fas fa-check-double"></i>
                                Verify Admin Fix
                            </a>
                            <div class="test-description">Verify admin fixes are working</div>
                        </li>
                        <li>
                            <a href="../fix_admin_paths.php" class="test-link">
                                <i class="fas fa-route"></i>
                                Fix Admin Paths
                            </a>
                            <div class="test-description">Fix admin path configuration</div>
                        </li>
                    </ul>
                </div>

                <!-- API & Status Tools -->
                <div class="category">
                    <h3><i class="fas fa-plug"></i> API & Status Tools</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../api_status.php" class="test-link">
                                <i class="fas fa-server"></i>
                                API Status
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Check API endpoint status</div>
                        </li>
                        <li>
                            <a href="../server_test.php" class="test-link">
                                <i class="fas fa-hdd"></i>
                                Root Server Test
                            </a>
                            <div class="test-description">Test server configuration from root</div>
                        </li>
                        <li>
                            <a href="../test_site.php" class="test-link">
                                <i class="fas fa-globe"></i>
                                Root Site Test
                            </a>
                            <div class="test-description">General site functionality test from root</div>
                        </li>
                        <li>
                            <a href="../test_config.php" class="test-link">
                                <i class="fas fa-cogs"></i>
                                Root Configuration Test
                            </a>
                            <div class="test-description">Test system configuration from root</div>
                        </li>
                    </ul>
                </div>

                <!-- Cart & Checkout Root Tests -->
                <div class="category">
                    <h3><i class="fas fa-shopping-cart"></i> Cart & Checkout Root Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../test_cart_direct.php" class="test-link">
                                <i class="fas fa-cart-plus"></i>
                                Root Cart Direct Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test cart functionality directly from root</div>
                        </li>
                        <li>
                            <a href="../test_cart_endpoints.php" class="test-link">
                                <i class="fas fa-link"></i>
                                Root Cart Endpoints Test
                            </a>
                            <div class="test-description">Test cart API endpoints from root</div>
                        </li>
                        <li>
                            <a href="../test_cart_valid.php" class="test-link">
                                <i class="fas fa-check-circle"></i>
                                Root Cart Validation Test
                            </a>
                            <div class="test-description">Validate cart operations from root</div>
                        </li>
                        <li>
                            <a href="../test_checkout_email.php" class="test-link">
                                <i class="fas fa-credit-card"></i>
                                Root Checkout Email Test
                            </a>
                            <div class="test-description">Test checkout email notifications from root</div>
                        </li>
                        <li>
                            <a href="../quick_cart_test.php" class="test-link">
                                <i class="fas fa-bolt"></i>
                                Quick Cart Test
                            </a>
                            <div class="test-description">Fast cart functionality test</div>
                        </li>
                    </ul>
                </div>

                <!-- Email Root Tests -->
                <div class="category">
                    <h3><i class="fas fa-envelope"></i> Email Root Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../test_email_direct.php" class="test-link">
                                <i class="fas fa-paper-plane"></i>
                                Root Direct Email Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test direct email sending from root</div>
                        </li>
                        <li>
                            <a href="../test_working_email.php" class="test-link">
                                <i class="fas fa-check"></i>
                                Test Working Email
                            </a>
                            <div class="test-description">Test known working email configuration</div>
                        </li>
                        <li>
                            <a href="../basic_email_test.php" class="test-link">
                                <i class="fas fa-at"></i>
                                Basic Email Test
                            </a>
                            <div class="test-description">Basic email functionality test</div>
                        </li>
                        <li>
                            <a href="../simple_email_test.php" class="test-link">
                                <i class="fas fa-envelope-simple"></i>
                                Simple Email Test
                            </a>
                            <div class="test-description">Simple email sending test</div>
                        </li>
                    </ul>
                </div>

                <!-- Location & Shipping Root Tests -->
                <div class="category">
                    <h3><i class="fas fa-map-marked-alt"></i> Location & Shipping Root Tests</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../test_country_loading.php" class="test-link">
                                <i class="fas fa-flag"></i>
                                Country Loading Test
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test country data loading</div>
                        </li>
                        <li>
                            <a href="../test_fallback_countries.php" class="test-link">
                                <i class="fas fa-globe-americas"></i>
                                Fallback Countries Test
                            </a>
                            <div class="test-description">Test fallback country mechanism</div>
                        </li>
                        <li>
                            <a href="../test_shipping_countries.php" class="test-link">
                                <i class="fas fa-shipping-fast"></i>
                                Shipping Countries Test
                            </a>
                            <div class="test-description">Test shipping country configurations</div>
                        </li>
                        <li>
                            <a href="../test_shipping_include.php" class="test-link">
                                <i class="fas fa-truck"></i>
                                Shipping Include Test
                            </a>
                            <div class="test-description">Test shipping include files</div>
                        </li>
                    </ul>
                </div>

                <!-- Utilities & SQL Tools -->
                <div class="category">
                    <h3><i class="fas fa-tools"></i> Utilities & SQL Tools</h3>
                    <ul class="test-list">
                        <li>
                            <a href="../test_and_generate_sql.php" class="test-link">
                                <i class="fas fa-database"></i>
                                Test and Generate SQL
                                <span class="status-indicator">READY</span>
                            </a>
                            <div class="test-description">Test and generate SQL statements</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>AleppoGift Testing Dashboard</strong></p>
            <p>Use these tools to test and diagnose system functionality in production environment.</p>
            <a href="../admin/dashboard.php" class="admin-link">
                <i class="fas fa-shield-alt"></i> Back to Admin Dashboard
            </a>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const testLinks = document.querySelectorAll('.test-link');
            
            testLinks.forEach(link => {
                const text = link.textContent.toLowerCase();
                const listItem = link.closest('li');
                
                if (text.includes(searchTerm)) {
                    listItem.style.display = 'block';
                } else {
                    listItem.style.display = 'none';
                }
            });
        });

        // Add click tracking
        document.querySelectorAll('.test-link').forEach(link => {
            link.addEventListener('click', function() {
                console.log('Testing: ' + this.href);
            });
        });
    </script>
</body>
</html>
