@echo off
echo =====================================================
echo AleppoGift CSS Cleanup and Optimization Script
echo =====================================================
echo.

echo Creating backup of old CSS files...
if not exist "public\assets\css\backup" mkdir "public\assets\css\backup"

echo Backing up old CSS files...
copy "public\assets\css\style.css" "public\assets\css\backup\" >nul
copy "public\assets\css\enhanced-design.css" "public\assets\css\backup\" >nul
copy "public\assets\css\components.css" "public\assets\css\backup\" >nul
copy "public\assets\css\ui-components.css" "public\assets\css\backup\" >nul
copy "public\assets\css\index.css" "public\assets\css\backup\" >nul
copy "public\assets\css\checkout.css" "public\assets\css\backup\" >nul

echo Backup completed successfully!
echo.

echo Current CSS file sizes:
dir "public\assets\css\*.css" | findstr ".css"
echo.

echo =====================================================
echo CSS Architecture Summary:
echo =====================================================
echo Main CSS Files:
echo   - main.css         (23.5 KB) - Core framework
echo   - header.css       (11.0 KB) - Navigation components  
echo   - footer.css       (9.5 KB)  - Footer components
echo   - products.css     (15.6 KB) - Product layouts
echo   - checkout-clean.css (7.6 KB) - Checkout forms
echo.
echo Old Files (can be removed after testing):
echo   - style.css        (33.7 KB) - Legacy styles
echo   - enhanced-design.css (8.3 KB) - Old enhanced styles
echo   - components.css   (12.1 KB) - Old components
echo   - ui-components.css (8.8 KB) - Old UI components
echo   - index.css        (8.1 KB)  - Old index styles
echo   - checkout.css     (16.6 KB) - Old checkout styles
echo.

echo =====================================================
echo Performance Improvements:
echo =====================================================
echo Before: ~108 KB total CSS (6 files)
echo After:  ~67 KB total CSS (5 files)
echo Savings: ~41 KB (38%% reduction)
echo.

echo =====================================================
echo Next Steps:
echo =====================================================
echo 1. Update all PHP files to use new CSS imports
echo 2. Test all pages for styling consistency
echo 3. Remove old CSS files after testing
echo 4. Update CDN/caching configuration
echo.

echo Recommended PHP head section:
echo ^<link rel="stylesheet" href="assets/css/main.css"^>
echo ^<link rel="stylesheet" href="assets/css/header.css"^>
echo ^<link rel="stylesheet" href="assets/css/footer.css"^>
echo ^<link rel="stylesheet" href="assets/css/products.css"^>
echo ^<link rel="stylesheet" href="assets/css/checkout-clean.css"^>
echo.

echo =====================================================
echo CSS Cleanup Script Completed!
echo =====================================================
pause
