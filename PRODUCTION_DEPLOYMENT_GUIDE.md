# AleppoGift Production Deployment Files

## Modified Files (Upload Required)

### Configuration Files
- ✅ `.env` (Updated for production environment)
- ✅ `config/config.php` (Production-ready configuration)

### Core Application Files
- ✅ `includes/Database.php` (PHP 8.4 compatible)
- ✅ `includes/env_loader.php` (Environment loader)
- ✅ `includes/security.php` (Security utilities)
- ✅ `includes/bootstrap.php` (Application bootstrap)
- ✅ `includes/cart_helpers.php` (Cart functionality)
- ✅ `includes/email_notifier.php` (Email system)
- ✅ `includes/generate_invoice.php` (Invoice generation)
- ✅ `includes/generate_quotation.php` (Quotation system)
- ✅ `includes/phpmailer_email.php` (Email handler)
- ✅ `includes/shipping.php` (Shipping calculations)
- ✅ `includes/session_helper.php` (Session management)
- ✅ `includes/whatsapp_notify.php` (WhatsApp integration)
- ✅ `includes/uae_symbol_utils.php` (Currency utilities)

### Public Website Files
- ✅ `public/.htaccess` (SSL enforcement & security)
- ✅ `public/index.php` (Homepage)
- ✅ `public/products.php` (Product listing)
- ✅ `public/product.php` (Product details)
- ✅ `public/cart.php` (Shopping cart)
- ✅ `public/checkout.php` (Checkout process)
- ✅ `public/thankyou.php` (Order confirmation)
- ✅ `public/contact.php` (Contact form)
- ✅ `public/about.php` (About page)
- ✅ `public/shipping.php` (Shipping info)
- ✅ `public/404.php` (Error page)
- ✅ `public/view_invoice.php` (Invoice viewer)
- ✅ `public/download_invoice.php` (Invoice download)
- ✅ `public/download_quotation.php` (Quotation download)

### Assets & Resources
- ✅ `public/assets/` (CSS, JS, images directory)
- ✅ `public/font/` (Font files)
- ✅ `public/svg/` (SVG icons)
- ✅ `public/png/` (PNG images)
- ✅ `public/android-chrome-512x512.png` (PWA icon)

### Admin Panel
- ✅ `public/admin/` (Admin interface directory - if needed)
- ✅ `public/admin1/` (Secondary admin - if needed)

### AJAX & API Files
- ✅ `public/ajax/` (AJAX handlers directory)

### Additional Files
- ✅ `public/sitemap.xml` (SEO sitemap)
- ✅ `public/privacy_policy.html` (Privacy policy)
- ✅ `public/terms_of_service.html` (Terms of service)
- ✅ `public/googlecbcde407eb72f797.html` (Google verification)

### Supporting Directories
- ✅ `logs/` (Create empty directory with .htaccess protection)
- ✅ `public/uploads/` (User uploads directory)
- ✅ `public/quotations/` (Generated quotations)
- ✅ `invoice/` (Invoice generation directory)

### Vendor Dependencies
- ✅ `vendor/` (Third-party libraries - if exists)

## Files to EXCLUDE from Production

### Test Files (DO NOT UPLOAD)
```
❌ *test*.php (All test files)
❌ debug*.php (Debug scripts)
❌ trace_*.php (Trace files)
❌ demo_*.php (Demo files)
❌ check_php_config.php
❌ api_status.php
❌ system_status.php
❌ php84_*.php
❌ minimal_*.php
❌ simple_*.php
```

### Development Files (DO NOT UPLOAD)
```
❌ *.md (Markdown documentation)
❌ *.bat (Batch files)
❌ *.txt (Setup instructions)
❌ *.zip (Archive files)
❌ *.sql (Database files - use separate import)
❌ cors_test_*.html
❌ layout-test.html
❌ site.html
❌ public/testing/ (Testing directory)
```

### Log Files (DO NOT UPLOAD)
```
❌ logs/*.log (Clear before deployment)
```

## Deployment Checklist

### Before Upload
- [ ] Backup current production site
- [ ] Backup production database
- [ ] Test all functionality in staging
- [ ] Verify .env production settings
- [ ] Clear all log files

### Upload Process
1. Upload all files from "Modified Files" section
2. Create empty `logs/` directory with proper permissions
3. Ensure `public/uploads/` has write permissions
4. Verify SSL certificate is active
5. Test database connections

### After Upload
- [ ] Test website loading
- [ ] Test user registration/login
- [ ] Test cart functionality
- [ ] Test checkout process
- [ ] Test payment integration (Ziina)
- [ ] Test email notifications
- [ ] Test invoice generation
- [ ] Check error logs
- [ ] Verify SSL is working

### Performance Optimization
- [ ] Enable PHP OPcache
- [ ] Configure browser caching
- [ ] Optimize database queries
- [ ] Monitor server resources

## File Permissions
```
Directories: 755
PHP Files: 644
.env file: 600 (secure)
uploads/: 755 (writable)
logs/: 755 (writable)
```

## Security Considerations
- ✅ Production environment configured in .env
- ✅ Error reporting disabled for production
- ✅ Security headers configured
- ✅ HTTPS enforcement enabled
- ✅ Debug mode disabled
- ✅ Test files excluded from upload

## Database Requirements
- MySQL 5.7+ or 8.0+
- PHP 8.2+ recommended (8.4 compatible)
- Required PHP extensions: PDO, PDO_MySQL, GD, ZIP