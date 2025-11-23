# Admin Panel Documentation

## Overview
This directory contains the administrative interface for the AleppoGift e-commerce platform.

## Security Features
- Session-based authentication
- CSRF protection tokens
- Input validation and sanitization
- File upload security checks
- Admin action logging

## File Structure

### Core Files
- `index.php` - Redirects to dashboard
- `dashboard.php` - Main admin dashboard
- `login.php` - Admin login interface
- `logout.php` - Session termination

### Product Management
- `products.php` - Product listing and management
- `add_product.php` - Add new products
- `edit_product.php` - Edit existing products
- `delete_image.php` - Delete product images

### Category & Brand Management
- `categories.php` - Category management
- `edit_category.php` - Edit categories
- `brands.php` - Brand management
- `edit_brand.php` - Edit brands
- `brand_edit.php` - Legacy redirect to edit_brand.php

### Order Management
- `orders.php` - Order listing
- `order_detail.php` - Detailed order view
- `download_invoice.php` - Invoice generation
- `regenerate_invoice.php` - Invoice regeneration

### Customer Management
- `customers.php` - Customer listing
- `view_customer.php` - Customer details

### Promotional Tools
- `coupons.php` - Coupon management

### Utilities
- `tools/` - Administrative tools
  - `generate_thumbnails.php` - Image thumbnail generation
  - `link_products_to_brands.php` - Product-brand linking utility

### Includes
- `includes/header.php` - Standardized admin header
- `includes/footer.php` - Standardized admin footer
- `includes/security.php` - Security helper functions

### Assets
- `assets/admin-theme.css` - Admin panel styling

## Security Considerations

### Authentication
All admin pages require authentication via `require_admin_login()` function.

### Input Validation
- All numeric IDs are validated and cast to integers
- File uploads are validated for type, size, and MIME type
- User input is sanitized before database operations

### CSRF Protection
Use the security helper functions for CSRF token generation and verification:
```php
// Generate token
$csrf_token = generate_csrf_token();

// Verify token
if (!verify_csrf_token($_POST['csrf_token'])) {
    // Handle invalid token
}
```

### File Upload Security
Use the `validate_file_upload()` function for secure file handling:
```php
if (validate_file_upload($_FILES['upload'])) {
    $secure_filename = generate_secure_filename($_FILES['upload']['name']);
    // Process upload
}
```

## Database Operations
All database queries use prepared statements through the Database class to prevent SQL injection.

## Logging
Admin actions are logged using the `log_admin_action()` function for audit purposes.

## Best Practices
1. Always validate input data
2. Use prepared statements for database queries
3. Implement proper error handling
4. Log significant admin actions
5. Use secure file upload procedures
6. Include CSRF protection on forms
7. Sanitize output data

## Recent Fixes Applied
1. Added authentication checks to all admin pages
2. Improved input validation for delete operations
3. Secured file upload operations
4. Standardized session handling
5. Created reusable header/footer includes
6. Added security helper functions
7. Improved error handling and logging

## Future Enhancements
- Role-based access control
- Two-factor authentication
- Enhanced audit logging
- Bulk operations interface
- Advanced reporting features