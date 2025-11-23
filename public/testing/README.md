# AleppoGift Testing Dashboard

## Overview
This testing dashboard provides centralized access to all testing and diagnostic tools for the AleppoGift e-commerce platform. It's designed for production environment testing and system diagnostics.

## Access
- **Main Dashboard**: `/public/testing/index.php`
- **Quick Access**: `/public/tests.php` (redirects to dashboard)
- **Admin Link**: Available in Admin Dashboard sidebar

## File Organization

### Database Tests
- `test_db_connection.php` - Basic database connectivity
- `test_db_connection_enhanced.php` - Enhanced database diagnostics
- `test_db_detailed.php` - Comprehensive database structure test
- `test_db_local.php` - Local development database test
- `test_db_quick.php` - Fast database health check

### Email Tests
- `email_test.php` - Basic email functionality
- `test_enhanced_email.php` - Advanced email testing with templates
- `test_email_advanced.php` - Complex email scenarios
- `test_email_smtp.php` - SMTP configuration test
- `test_direct_email.php` - Direct email sending test
- `test_cc_email.php` - CC and BCC functionality test

### Cart & Checkout Tests
- `test_cart_direct.php` - Direct cart functionality test
- `test_cart_valid.php` - Cart validation test
- `test_cart_endpoints.php` - Cart API endpoints test
- `test_checkout_email.php` - Checkout email notifications
- `test_checkout_email_simple.php` - Basic checkout email test

### Shipping Tests
- `test_shipping_calculations.php` - Shipping cost calculations
- `test_shipping_examples.php` - Various shipping scenarios
- `test_shipping_page.php` - Shipping information page test
- `test_ajax_shipping.php` - AJAX shipping calculations
- `test_uae_shipping_update.php` - UAE specific shipping updates

### System Diagnostics
- `debug_checkout.php` - Checkout process debugging
- `debug_checkout_simple.php` - Basic checkout debugging
- `debug_filter.php` - Product filtering debugging
- `check_fpdf.php` - PDF generation library test
- `check_products.php` - Product data integrity verification
- `check_products_structure.php` - Product database structure verification
- `check_product_status.php` - Product availability status check
- `test_config.php` - System configuration test
- `test_site.php` - General site functionality test
- `server_test.php` - Server configuration and environment test
- `simple_connection_test.php` - Basic connectivity test
- `simple_test.php` - Quick system check

### AJAX Tests
- `test_ajax_path.php` - AJAX request paths test

## Features

### Dashboard Features
- **Categorized Organization**: Tests are organized by functionality
- **Search Functionality**: Real-time search through test files
- **Visual Status Indicators**: Ready status for key tests
- **Responsive Design**: Works on all devices
- **Quick Navigation**: Direct links to admin and home

### Security
- All test files are now in the public directory for production access
- No sensitive information exposed in file names
- Admin authentication required for admin dashboard access

## Usage Instructions

1. **Access the Dashboard**
   - Navigate to `/public/testing/` in your browser
   - Or use the link in the Admin Dashboard sidebar

2. **Run Tests**
   - Click on any test link to run individual tests
   - Tests open in the same or new window based on configuration
   - Review test outputs for system status

3. **Search Tests**
   - Use the search box to find specific tests
   - Search works across test names and descriptions

4. **Troubleshooting**
   - Check database tests first for connectivity issues
   - Run system diagnostics for general problems
   - Use email tests to verify mail functionality

## Production Notes

- All test files are now accessible via web browser
- Test files moved from root directory to organized public structure
- Dashboard provides comprehensive overview of all testing tools
- Integrated with existing admin interface
- Ready for production environment testing

## Technical Details

- Built with responsive CSS Grid layout
- JavaScript-powered search functionality
- Font Awesome icons for visual clarity
- Bootstrap-inspired styling
- Mobile-friendly responsive design

## Maintenance

To add new test files:
1. Place test file in `/public/testing/` directory
2. Update `index.php` dashboard to include new test
3. Add appropriate category and description
4. Follow naming convention: `test_[functionality]_[type].php`

Last Updated: September 2025
