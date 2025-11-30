<?php
/**
 * Root Test Files Proxy
 * Allows accessing test files from root folder via production-accessible public/testing/ directory
 * 
 * Usage: root_proxy.php?file=test_file_name.php
 * Security: Only allows access to test/debug files, not sensitive files
 */

// Get the requested file
$requestedFile = isset($_GET['file']) ? $_GET['file'] : '';

// Security: Whitelist of allowed test files (prevents directory traversal attacks)
$allowedFiles = [
    // PHP Test Files
    'test_add_to_cart.php',
    'test_ajax_add_to_cart.php',
    'test_cart_button.php',
    'test_categories_display.php',
    'test_category_creation.php',
    'test_database_debug.php',
    'test_edit_product_improved.php',
    'test_image_variations.php',
    'test_invoice_migration.php',
    'test_invoice_product_management.php',
    'test_regenerate_fix.php',
    'test_shipping_ajax_debug.php',
    'test_uae_symbol_simple.php',
    'test_ziina_comprehensive.php',
    'test_ziina_simple.php',
    'test_ziina_success.php',
    'test_ziina_thankyou.php',
    'simple_db_test.php',
    'minimal_sql_test.php',
    'php84_compatibility_test.php',
    'php84_driver_diagnostic.php',
    
    // HTML Test Files
    'test_ajax_shipping_live.html',
    'test_cart_ajax.html',
    'test_cart_enhanced.html',
    'test_cart_mobile.html',
    'test_cart_preview.html',
    'test_cors.html',
    'test_font_characters.html',
    'test_quick_view.html',
    'test_uae_symbol.html',
    'cors_test_page.html',
    
    // Diagnostic/Debug Files
    'debug_sql_comprehensive.php',
    'diagnostic_shipping.php',
    'check_category_structure.php',
    'check_php_config.php',
    'demo_uae_utilities.php',
    'api_status.php',
    'system_status.php',
    'site-structure.php',
    
    // Database Setup/Migration Files
    'setup_local_database.php',
    'add_category_picture_column.php',
    'add_shipment_columns.php',
    'fix_brand_paths.php',
    
    // Dashboard Files
    'arabic_encoding_dashboard.php',
    'admin_test_page.html',
    'arabic_fix_launcher.html',
];

// If no file requested, show the file browser
if (empty($requestedFile)) {
    showFileBrowser($allowedFiles);
    exit;
}

// Security check
if (!in_array($requestedFile, $allowedFiles)) {
    header('HTTP/1.1 403 Forbidden');
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 50px; text-align: center; }
            .error { color: #d32f2f; font-size: 24px; margin-bottom: 20px; }
            .message { color: #666; }
            a { color: #ff7f00; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="error">⛔ Access Denied</div>
        <div class="message">The requested file is not in the allowed list.</div>
        <p><a href="root_proxy.php">← Back to File Browser</a></p>
    </body>
    </html>
    ');
}

// Build the full path to the root file
$rootPath = dirname(dirname(__DIR__)); // Go up two levels from public/testing to root
$filePath = $rootPath . DIRECTORY_SEPARATOR . $requestedFile;

// Check if file exists
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>File Not Found</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 50px; text-align: center; }
            .error { color: #ff9800; font-size: 24px; margin-bottom: 20px; }
            .message { color: #666; }
            a { color: #ff7f00; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="error">📂 File Not Found</div>
        <div class="message">The requested file does not exist: ' . htmlspecialchars($requestedFile) . '</div>
        <p><a href="root_proxy.php">← Back to File Browser</a></p>
    </body>
    </html>
    ');
}

// Change to root directory so relative paths work correctly
chdir($rootPath);

// Include the file
include $filePath;

/**
 * Show a browsable list of available test files
 */
function showFileBrowser($files) {
    // Organize files by type
    $phpFiles = array_filter($files, function($f) { return substr($f, -4) === '.php'; });
    $htmlFiles = array_filter($files, function($f) { return substr($f, -5) === '.html'; });
    
    // Check which files actually exist
    $rootPath = dirname(dirname(__DIR__));
    $existingFiles = [];
    $missingFiles = [];
    
    foreach ($files as $file) {
        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $file;
        if (file_exists($fullPath)) {
            $existingFiles[] = $file;
        } else {
            $missingFiles[] = $file;
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Root Test Files Browser</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            
            .container {
                max-width: 1200px;
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
                font-size: 2rem;
                margin-bottom: 10px;
            }
            
            .header p {
                font-size: 1rem;
                opacity: 0.9;
            }
            
            .nav-buttons {
                padding: 20px;
                border-bottom: 1px solid #eee;
                text-align: center;
            }
            
            .nav-btn {
                display: inline-block;
                padding: 10px 20px;
                margin: 0 5px;
                background: #ff7f00;
                color: white;
                text-decoration: none;
                border-radius: 20px;
                transition: all 0.3s;
            }
            
            .nav-btn:hover {
                background: #e56b00;
                transform: translateY(-2px);
            }
            
            .content {
                padding: 30px;
            }
            
            .search-box {
                margin-bottom: 30px;
            }
            
            .search-input {
                width: 100%;
                padding: 15px;
                font-size: 16px;
                border: 2px solid #ddd;
                border-radius: 10px;
                outline: none;
            }
            
            .search-input:focus {
                border-color: #ff7f00;
            }
            
            .stats {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 30px;
                display: flex;
                justify-content: space-around;
                flex-wrap: wrap;
            }
            
            .stat-item {
                text-align: center;
                padding: 10px;
            }
            
            .stat-number {
                font-size: 2rem;
                font-weight: bold;
                color: #ff7f00;
            }
            
            .stat-label {
                color: #666;
                margin-top: 5px;
            }
            
            .file-section {
                margin-bottom: 40px;
            }
            
            .file-section h2 {
                color: #333;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 3px solid #ff7f00;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .file-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 15px;
            }
            
            .file-card {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 15px;
                transition: all 0.3s;
                border-left: 4px solid #ff7f00;
            }
            
            .file-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                background: #fff;
            }
            
            .file-card.missing {
                opacity: 0.5;
                border-left-color: #ccc;
            }
            
            .file-link {
                display: flex;
                align-items: center;
                gap: 10px;
                text-decoration: none;
                color: #333;
                font-weight: 500;
                font-size: 0.95rem;
            }
            
            .file-link i {
                color: #ff7f00;
                font-size: 1.2rem;
            }
            
            .file-card.missing .file-link i {
                color: #ccc;
            }
            
            .file-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 0.75rem;
                margin-left: auto;
            }
            
            .status-available {
                background: #4caf50;
                color: white;
            }
            
            .status-missing {
                background: #f44336;
                color: white;
            }
            
            .warning {
                background: #fff3cd;
                border: 1px solid #ffc107;
                color: #856404;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            
            .warning i {
                margin-right: 10px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-folder-open"></i> Root Test Files Browser</h1>
                <p>Access test files from root directory via production URL</p>
            </div>
            
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn">
                    <i class="fas fa-arrow-left"></i> Back to Testing Dashboard
                </a>
                <a href="../index.php" class="nav-btn">
                    <i class="fas fa-home"></i> Site Home
                </a>
            </div>
            
            <div class="content">
                <div class="warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> This proxy allows you to access test files from the root directory that are normally not accessible in production environments where the domain points to the /public folder.
                </div>
                
                <div class="search-box">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search test files...">
                </div>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($existingFiles); ?></div>
                        <div class="stat-label">Available Files</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($phpFiles); ?></div>
                        <div class="stat-label">PHP Files</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($htmlFiles); ?></div>
                        <div class="stat-label">HTML Files</div>
                    </div>
                    <?php if (count($missingFiles) > 0): ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($missingFiles); ?></div>
                        <div class="stat-label">Missing Files</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- PHP Test Files -->
                <div class="file-section">
                    <h2><i class="fas fa-file-code"></i> PHP Test Files</h2>
                    <div class="file-grid">
                        <?php 
                        $phpTestFiles = array_filter($existingFiles, function($f) { 
                            return substr($f, -4) === '.php'; 
                        });
                        sort($phpTestFiles);
                        foreach ($phpTestFiles as $file): 
                        ?>
                            <div class="file-card" data-filename="<?php echo strtolower($file); ?>">
                                <a href="root_proxy.php?file=<?php echo urlencode($file); ?>" class="file-link">
                                    <i class="fas fa-code"></i>
                                    <?php echo htmlspecialchars($file); ?>
                                    <span class="file-status status-available">✓</span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- HTML Test Files -->
                <div class="file-section">
                    <h2><i class="fas fa-file-alt"></i> HTML Test Files</h2>
                    <div class="file-grid">
                        <?php 
                        $htmlTestFiles = array_filter($existingFiles, function($f) { 
                            return substr($f, -5) === '.html'; 
                        });
                        sort($htmlTestFiles);
                        foreach ($htmlTestFiles as $file): 
                        ?>
                            <div class="file-card" data-filename="<?php echo strtolower($file); ?>">
                                <a href="root_proxy.php?file=<?php echo urlencode($file); ?>" class="file-link">
                                    <i class="fas fa-file-code"></i>
                                    <?php echo htmlspecialchars($file); ?>
                                    <span class="file-status status-available">✓</span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Missing Files (if any) -->
                <?php if (count($missingFiles) > 0): ?>
                <div class="file-section">
                    <h2><i class="fas fa-exclamation-triangle"></i> Missing Files</h2>
                    <div class="file-grid">
                        <?php foreach ($missingFiles as $file): ?>
                            <div class="file-card missing" data-filename="<?php echo strtolower($file); ?>">
                                <div class="file-link">
                                    <i class="fas fa-times-circle"></i>
                                    <?php echo htmlspecialchars($file); ?>
                                    <span class="file-status status-missing">Not Found</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const cards = document.querySelectorAll('.file-card');
                
                cards.forEach(card => {
                    const filename = card.getAttribute('data-filename');
                    if (filename.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}
?>
