# AleppoGift E-Commerce Website - Complete Features Documentation

**Website:** https://aleppogift.com  
**Version:** Production 2026  
**Type:** Luxury Gifts & Home Decor E-Commerce Platform  
**Target Market:** UAE and International Shipping

---

## 📋 Table of Contents

1. [Core E-Commerce Features](#core-e-commerce-features)
2. [Product Management](#product-management)
3. [Shopping Cart System](#shopping-cart-system)
4. [Checkout & Payment](#checkout--payment)
5. [Admin Dashboard](#admin-dashboard)
6. [Order Management](#order-management)
7. [Customer Management](#customer-management)
8. [Shipping & Delivery](#shipping--delivery)
9. [Coupon & Discount System](#coupon--discount-system)
10. [Payment Systems](#payment-systems)
11. [Invoice & PDF Generation](#invoice--pdf-generation)
12. [Email & Notifications](#email--notifications)
13. [Analytics & Tracking](#analytics--tracking)
14. [Multi-language Support](#multi-language-support)
15. [Security Features](#security-features)
16. [API & Integrations](#api--integrations)
17. [Social Media Automation](#social-media-automation)
18. [Technical Features](#technical-features)

---

## 🛒 Core E-Commerce Features

### Public Storefront
- **Responsive Design**: Mobile-first approach with Bootstrap 5
- **Product Catalog**: Grid and list view options
- **Product Search**: Real-time search by name, SKU, or description (English & Arabic)
- **Category Browsing**: Hierarchical category navigation with images
- **Brand Filtering**: Filter products by brand
- **Featured Products**: Highlight special products on homepage
- **Product Quick View**: Modal popup for quick product preview
- **Image Gallery**: Multiple product images with zoom functionality
- **Product Variations**: Support for product variants (size, color, custom options)
- **Stock Management**: Real-time stock availability display
- **Price Display**: AED currency with proper formatting
- **Product Sharing**: Share products via WhatsApp, social media, or copy link
- **Wishlist**: Save products for later (session-based)
- **Recently Viewed**: Track recently viewed products

### Navigation
- **Mega Menu**: Category dropdown with images
- **Sticky Header**: Persistent navigation bar
- **Breadcrumbs**: Clear page hierarchy navigation
- **Search Bar**: Global search with autocomplete
- **Language Toggle**: Switch between English and Arabic
- **Shopping Cart Icon**: Live cart item count badge

---

## 📦 Product Management

### Product Structure
```
Products Table:
- id, category_id, brand_id
- name_ar, name_en
- description_ar, description_en
- price, sku, stock
- featured, status
- weight (for shipping calculations)
- created_at
```

### Product Features
- **Multiple Images**: Unlimited product images with ordering
- **Main Image Selection**: Set any image as main product image
- **Product Variations**: 
  - Link images to specific variations
  - Custom variation types (size, color, custom options)
  - Price modifiers for variations
  - Individual stock tracking per variation
  - Variation SKUs
- **Weight-Based Shipping**: Product weight for automatic shipping calculation
- **Stock Management**:
  - Real-time stock levels
  - Automatic stock reduction on order
  - Low stock warnings
  - Out-of-stock handling
- **Product Status**: Active/Inactive products
- **Featured Products**: Mark products as featured for homepage
- **SEO-Friendly URLs**: Clean product URLs

### Image Management
- **Image Variants**: Each image can represent a product variant
- **Automatic Variation Creation**: System creates variations for each image
- **Image Reordering**: Drag-and-drop image ordering
- **Main Image Enhancement**: Automatic main image selection
- **Lazy Loading**: Optimized image loading for performance
- **Image Compression**: Automatic image optimization

---

## 🛍️ Shopping Cart System

### Cart Features
- **Session-Based Cart**: Persistent across browser sessions
- **AJAX Operations**: Real-time cart updates without page reload
- **Cart Preview**: Dropdown cart preview from header
- **Product Validation**: 
  - Verify product exists and is active
  - Check stock availability
  - Validate variation selection
- **Quantity Management**:
  - Increment/decrement buttons
  - Manual quantity input
  - Stock limit validation
  - Automatic removal when quantity = 0
- **Cart Operations**:
  - Add to cart with variation
  - Update quantity
  - Remove items
  - Clear entire cart
- **Cart Calculations**:
  - Subtotal calculation
  - Variation price handling
  - Weight calculation for shipping
  - Tax considerations (if applicable)
- **Error Handling**:
  - User-friendly error messages
  - Graceful invalid item removal
  - Stock validation feedback
- **Mobile Optimization**: Touch-friendly cart interface

### Cart Preview
- **Real-Time Updates**: Live cart item count and total
- **Quick Actions**: Remove items from preview
- **Checkout Button**: Direct link to checkout
- **Empty Cart State**: Friendly message when cart is empty

---

## 💳 Checkout & Payment

### Checkout Features
- **Guest Checkout**: No account required
- **Customer Information Form**:
  - Full name (min 2 characters validation)
  - Email (email format validation)
  - Phone number (required)
  - Address (detailed address)
  - City selection
  - Country selection (195+ countries)
- **Form Validation**:
  - Client-side validation with real-time feedback
  - Server-side validation for security
  - Required field indicators
  - Error message display
  - Field-specific validation rules
- **Cart Summary**:
  - Line items with images
  - Quantity and price per item
  - Subtotal calculation
  - Shipping cost display
  - Discount amount (if applicable)
  - Grand total
- **Shipping Calculation**:
  - Real-time shipping cost calculation
  - Based on country, city, and weight
  - AJAX-based shipping updates
  - Multiple shipping zones:
    - UAE cities (different rates)
    - GCC countries
    - Europe
    - USA and international
- **Payment Method Selection**:
  - Cash on Delivery (UAE only)
  - Credit Card (Ziina Payment Gateway)
  - Bank Transfer (manual)
- **Order Notes**: Optional customer notes field
- **CSRF Protection**: Token-based security
- **Error Recovery**: Handle payment failures gracefully

### Order Processing Flow
1. **Cart Validation**: Verify all items still available
2. **Customer Creation**: Create or find existing customer
3. **Order Creation**: Generate order with unique ID
4. **Payment Processing**: 
   - COD: Immediate confirmation
   - Online: Redirect to payment gateway
5. **Stock Management**: Decrement product stock
6. **Invoice Generation**: Create PDF invoice
7. **Notifications**: Send emails and WhatsApp messages
8. **Thank You Page**: Order confirmation display

---

## 👨‍💼 Admin Dashboard

### Dashboard Overview
- **Statistics Cards**:
  - Total orders (count and revenue)
  - Pending orders
  - Today's orders
  - Customer count
  - Product count
  - Low stock alerts
- **Recent Orders**: Latest 10 orders with status
- **Quick Actions**: Shortcuts to common tasks
- **Charts & Analytics**:
  - Revenue trends
  - Order status distribution
  - Popular products
  - Customer location map

### Admin Navigation
- **Dashboard**: Overview and statistics
- **Products Management**: CRUD operations
- **Categories**: Manage product categories
- **Brands**: Manage product brands
- **Orders**: View and manage orders
- **Customers**: Customer database
- **Coupons**: Discount code management
- **Analytics**: Detailed reporting
- **Settings**: System configuration

### Admin Authentication
- **Secure Login**: Password-protected admin panel
- **Session Management**: Automatic timeout
- **Password Hashing**: Bcrypt password encryption
- **Login Attempts**: Failed login tracking
- **Admin User Management**: Multiple admin accounts

---

## 📊 Order Management

### Order Features
- **Order Listing**:
  - Sortable columns (ID, date, customer, total, status)
  - Filter by status (pending, paid, failed, shipped, delivered)
  - Search by order ID, customer name, or email
  - Pagination for large order lists
  - Date range filtering
- **Order Details View**:
  - Order ID and date
  - Customer information (name, email, phone, address)
  - Order items with images, quantities, and prices
  - Payment information (method, status, reference)
  - Shipping information (address, method, tracking)
  - Order timeline
  - Admin notes
  - Customer notes
- **Order Status Management**:
  - Payment status: Pending, Paid, Failed
  - Shipment status: Pending, Processing, Shipped, Delivered
  - Status change history
- **Admin Order Creation**:
  - Create orders manually from admin panel
  - Search and select existing customers
  - Create new customers on-the-fly
  - Add multiple products with quantities
  - Set shipping method and cost
  - Choose payment method and status
  - Add order notes
  - Generate payment links
  - Automatic invoice generation
- **Invoice Management**:
  - View order invoice
  - Download PDF invoice
  - Regenerate invoice with changes
  - Email invoice to customer
  - Print-friendly invoice format
- **Product Management in Orders**:
  - Add products to existing orders
  - Remove products from orders
  - Update product quantities
  - Real-time total recalculation
  - Stock validation
- **Shipment Management**:
  - Add tracking number
  - Select carrier name
  - Choose shipping method
  - Set shipped date
  - Update shipment status
  - Email tracking information to customer

### Advanced Order Features
- **Order Editing**:
  - Modify shipping cost
  - Apply discounts after order creation
  - Add/remove products
  - Update customer information
  - Change payment status
- **Payment Link Generation**:
  - Generate secure payment link for unpaid orders
  - Send link via WhatsApp or email
  - Token expiry (7 days)
  - One-time use tokens
  - Payment link tracking
- **Quick Payment Links**:
  - Create payment request without full order
  - Custom amount and description
  - Customer information capture
  - Instant payment link generation

---

## 👥 Customer Management

### Customer Database
```
Customers Table:
- id, fullname, email, phone
- address, city, country
- created_at
```

### Customer Features
- **Customer Listing**:
  - Search by name, email, or phone
  - Sort by various fields
  - Filter by registration date
  - Pagination
- **Customer Details**:
  - Contact information
  - Order history
  - Total spent
  - Order count
  - Last order date
  - Delivery addresses
- **Customer Deduplication**: Prevent duplicate customer records by email
- **Customer Search in Admin**: Quick search when creating orders
- **Customer Export**: Export customer data to CSV

---

## 🚚 Shipping & Delivery

### Shipping Calculation System
- **Weight-Based Pricing**:
  - Per-kilogram rates
  - Base rate + additional weight charges
  - Different rates per region
- **Geographic Zones**:
  - **UAE** (30 AED base):
    - Dubai, Abu Dhabi, Sharjah, Ajman (base rate)
    - Al Ain, Fujairah, RAK (higher rates)
  - **Oman** (120 AED base + 30 AED/kg)
  - **GCC Countries** (120 AED base + 30 AED/kg):
    - Qatar, Saudi Arabia, Kuwait, Bahrain
  - **Europe** (220 AED base + 70 AED/kg):
    - Germany, France, Italy, Spain, UK, etc.
  - **USA & International** (300 AED base + 80 AED/kg)
- **Smart Parcel System**:
  - Split orders over 30kg into multiple parcels
  - Optimized shipping cost calculation
  - Maximum parcel weight: 30kg
- **Shipping Calculator Page**:
  - Public shipping cost estimator
  - Country selection (195+ countries)
  - City selection (major cities)
  - Weight input
  - Instant calculation
  - Bilingual interface
- **Real-Time Shipping Calculation**:
  - AJAX-based calculation during checkout
  - Dynamic shipping cost updates
  - Country and city change detection

### Shipping Methods
- **Standard Shipping**: Default shipping option
- **Express Shipping**: Faster delivery option
- **Store Pickup**: Pick up from physical location (if enabled)

### Tracking
- **Tracking Number**: Assign tracking numbers to orders
- **Carrier Selection**: Select shipping carrier
- **Tracking Email**: Automatic email with tracking info
- **Shipment Status Updates**: Track delivery progress

---

## 🎟️ Coupon & Discount System

### Coupon Features
```
Coupons Table:
- id, code (unique)
- discount_type (fixed, percent)
- discount_value
- start_date, end_date
- max_usage, used_count
- status (active, inactive)
```

### Discount Types
- **Fixed Amount**: Subtract fixed AED amount from total
- **Percentage**: Apply percentage discount to subtotal
- **Minimum Order Amount**: Optional minimum purchase requirement
- **Usage Limits**:
  - Maximum total uses
  - Usage tracking
  - Prevent over-use
- **Date Range**: Start and end date validation
- **Status Control**: Active/inactive coupons

### Discount Application
- **Manual Discount in Admin**:
  - Apply discount when regenerating invoice
  - Choose discount type
  - Enter discount value
  - Optional coupon code
  - Real-time total recalculation
- **Discount Display**:
  - Show discount amount in order totals
  - Display coupon code if applicable
  - Clear discount breakdown in invoice

---

## 💰 Payment Systems

### Ziina Payment Gateway Integration
- **Full API Integration**: Production-ready Ziina payment gateway
- **Payment Intent Creation**:
  - Order ID linking
  - Amount in AED (auto-conversion to fils)
  - Custom message/description
  - Success/failure/cancel URLs
  - Test mode toggle
  - Expiry time (24 hours)
- **Security Features**:
  - API key protection (stored in config)
  - SSL/TLS for API communication
  - cURL with secure options
  - Token validation
  - Payment status verification
- **Payment Flow**:
  1. Customer completes checkout
  2. System creates payment intent
  3. Customer redirected to Ziina payment page
  4. Payment processed by Ziina
  5. Customer redirected back based on result
  6. Order status updated automatically
- **Error Handling**:
  - Payment failures logged
  - User-friendly error messages
  - Automatic retry mechanism
  - Failed payment recovery
- **Payment Status Tracking**:
  - Pending: Payment not yet made
  - Processing: Payment in progress
  - Paid: Successfully paid
  - Failed: Payment failed
- **Test Mode**: Sandbox environment for testing

### Cash on Delivery (COD)
- **UAE Only**: Geographic restriction for COD
- **Automatic Validation**: Verify customer country
- **Immediate Confirmation**: No payment gateway needed
- **COD Restrictions**: Custom business rules

### Bank Transfer
- **Manual Payment Option**: For specific customers
- **Admin Manual Marking**: Mark as paid manually
- **Bank Details Display**: Show bank information to customer

---

## 📄 Invoice & PDF Generation

### Invoice Generation System
- **Automated PDF Generation**: 
  - FPDF library integration
  - Professional invoice template
  - Logo and branding
  - Bilingual support (English & Arabic)
- **Invoice Contents**:
  - Company information (name, address, contact)
  - Customer information
  - Order ID and date
  - Line items table:
    - Product name (English & Arabic)
    - Variation details
    - Quantity
    - Unit price
    - Line total
  - Subtotal
  - Shipping cost
  - Discount (if applicable)
  - Grand total
  - Payment method
  - Payment status
  - Tracking number (if shipped)
  - Footer notes
- **Invoice Storage**:
  - PDF saved to `/invoice/` folder
  - Filename format: `invoice_ORDER_ID.pdf`
  - Database reference to PDF file
- **Invoice Management**:
  - View invoice (browser PDF viewer)
  - Download invoice
  - Email invoice to customer
  - Regenerate invoice with updated data
- **Invoice Regeneration Features**:
  - Edit shipping cost
  - Add/remove products
  - Update quantities
  - Apply discounts
  - Change customer information
  - Recalculate totals automatically
  - Preserve invoice number

### Quotation System
- **Generate Quotations**: Create quotes before orders
- **PDF Quotations**: Similar to invoices but marked as quote
- **Quote to Order**: Convert quotations to orders
- **Quote Expiry**: Set expiration dates

---

## 📧 Email & Notifications

### Email System
- **SMTP Configuration**:
  - Configurable SMTP server
  - TLS/SSL support
  - Authentication
  - Custom sender details
- **PHPMailer Integration**: Professional email library
- **Email Types**:
  - Order confirmation
  - Payment receipt
  - Shipping notification
  - Invoice delivery
  - Password reset (if user accounts)
  - Admin notifications
- **Order Confirmation Email**:
  - Customer name personalization
  - Order ID and date
  - Order summary
  - Payment method and status
  - Shipping information
  - Next steps information
  - Contact information (email, WhatsApp, website)
  - Professional HTML template
  - Plain text alternative
  - PDF invoice attachment
- **Admin Email Notifications**:
  - New order alerts
  - Low stock warnings
  - Payment failures
  - Customer inquiries
- **Email Deliverability**:
  - SPF/DKIM configuration
  - Error logging
  - Retry mechanism for failures
  - Bounce handling

### WhatsApp Notifications
- **CallMeBot API Integration**: Automated WhatsApp messaging
- **Admin Notifications**:
  - New order alerts with:
    - Order ID
    - Customer name and phone
    - Location (city, country)
    - Total amount
    - Payment method (COD or Paid)
  - Message formatting with emojis
  - URL encoding for proper delivery
- **Customer Notifications** (optional):
  - Order confirmation
  - Tracking information
  - Payment links
- **WhatsApp Integration in Frontend**:
  - Product inquiry buttons
  - Direct WhatsApp links from product pages
  - Pre-filled message templates
  - WhatsApp number: +971 56 112 5320

---

## 📊 Analytics & Tracking

### Visitor Tracking
```
Visitors Table:
- id, ip_address
- visit_date, visit_count
```

### Tracking Features
- **IP-Based Tracking**: Track unique visitors by IP
- **Daily Visits**: Count daily visit frequency
- **Visit Aggregation**: Sum total visits per IP
- **Geo-Location**: Track visitor country (with GeoIP or ipinfo.io)
- **Visitor Analytics Dashboard**:
  - Total visitors
  - Daily visits
  - Visitor countries
  - Top visiting countries
  - Visitor trends over time

### Order Analytics
- **Sales Reports**:
  - Total revenue
  - Revenue by period (daily, weekly, monthly)
  - Average order value
  - Orders by payment method
  - Orders by status
- **Product Analytics**:
  - Best-selling products
  - Revenue by product
  - Low stock alerts
  - Product performance trends
- **Customer Analytics**:
  - New customers per period
  - Customer locations
  - Repeat customer rate
  - Customer lifetime value
- **Geographic Analytics**:
  - Orders by country
  - Revenue by region
  - Shipping costs by destination

### System Analytics
- **Error Logging**: PHP error logs for debugging
- **Performance Monitoring**: Track page load times
- **Database Query Logging**: Optimize slow queries
- **API Call Logging**: Monitor external API usage

---

## 🌐 Multi-language Support

### Language Features
- **Bilingual Interface**:
  - English (default)
  - Arabic (RTL support)
- **Language Switcher**: Toggle button in header
- **Database Structure**:
  - Separate fields for each language:
    - name_ar, name_en
    - description_ar, description_en
  - Automatic language detection
- **RTL Support**:
  - Right-to-left layout for Arabic
  - Mirrored navigation
  - Proper text alignment
  - Arabic typography
- **Dynamic Content**:
  - Product names in both languages
  - Category names in both languages
  - UI text translations
  - Email templates in both languages
- **SEO for Multiple Languages**:
  - Language-specific URLs
  - hreflang tags
  - Localized meta tags

---

## 🔒 Security Features

### Application Security
- **CSRF Protection**:
  - Token generation and validation
  - Form protection
  - AJAX request security
- **SQL Injection Prevention**:
  - Prepared statements (PDO)
  - Parameter binding
  - Input sanitization
- **XSS Protection**:
  - Output escaping
  - htmlspecialchars usage
  - Content Security Policy headers
- **Session Security**:
  - Secure session handling
  - Session fixation prevention
  - Session timeout
  - HTTPOnly cookies
- **Password Security**:
  - Bcrypt hashing
  - Strong password requirements
  - Password reset tokens
  - Salted hashes
- **Input Validation**:
  - Server-side validation
  - Type checking
  - Length limits
  - Format validation (email, phone, etc.)
- **File Upload Security**:
  - File type validation
  - File size limits
  - Secure file naming
  - Upload directory protection
- **API Security**:
  - API key protection
  - Rate limiting (on critical endpoints)
  - HTTPS enforcement
  - Token expiration

### Access Control
- **Admin Authentication**: Login required for admin panel
- **Role-Based Access**: Admin permissions (if multiple admins)
- **Public Access Control**: Guest vs authenticated user features
- **API Authentication**: Protected API endpoints

---

## 🔗 API & Integrations

### Internal APIs
- **AJAX Endpoints**:
  - `/ajax/add_to_cart.php`: Add product to cart
  - `/ajax/update_cart_qty.php`: Update item quantity
  - `/ajax/remove_from_cart.php`: Remove cart item
  - `/ajax/cart_preview.php`: Get cart preview HTML
  - `/ajax/calculate_shipping.php`: Calculate shipping cost
  - `/ajax/search_products.php`: Product search
  - `/ajax/search_customers.php`: Customer search
  - `/ajax/countries.php`: Get country list
  - `/ajax/manage_order_products.php`: Order product management
- **Response Format**: JSON responses with success/error status
- **Error Handling**: Consistent error messages
- **CORS Support**: Cross-origin request handling

### External Integrations
- **Ziina Payment API**: Payment gateway integration
- **CallMeBot WhatsApp API**: WhatsApp notifications
- **ipinfo.io**: Geo-location services
- **Google Analytics** (if configured): Website analytics
- **Facebook Pixel** (if configured): Ad tracking

### REST API Features (if implemented)
- **Products API**: Get products, search, filter
- **Orders API**: Create orders, get order status
- **Customers API**: Customer CRUD operations
- **Authentication**: API token-based auth

---

## 📱 Social Media Automation

### Social Media Bot (Python Application)
- **Platform:** Local Streamlit application
- **Functionality:**
  - **Product Data Integration**:
    - Direct MySQL connection to products database
    - API endpoint integration
    - CSV import fallback
  - **Video Generation**:
    - Convert product images to 9:16 vertical videos (1080x1920px)
    - Ken Burns effect (zoom/pan) using FFmpeg
    - Dynamic text overlays (product name, price, CTA)
    - Background music integration
    - Quality presets (Low/Medium/High)
  - **Multi-Platform Publishing**:
    - TikTok (Content Posting API)
    - Instagram Reels (2-step container upload)
    - Facebook Reels (Reels API)
    - Draft/private mode for manual review
  - **Scheduling System**:
    - Separate background daemon process
    - Automated posting at optimal times
    - Queue management
  - **Analytics Dashboard**:
    - Daily metrics sync (views, likes, shares, comments)
    - Performance tracking
    - Best products identification
    - Optimal posting times
    - Engagement trend analysis

### Social Media Features
- **WhatsApp Sharing**: Direct share buttons on products
- **Product Share Links**: Copy link, share to social media
- **Social Media Icons**: Links to company social profiles

---

## 🔧 Technical Features

### Frontend Technologies
- **Framework**: Bootstrap 5.3.2
- **CSS**: Custom CSS with CSS variables
- **JavaScript**: Vanilla JS with jQuery
- **Icons**: Font Awesome 6.5
- **Responsive Design**: Mobile-first approach
- **Browser Support**: Modern browsers (Chrome, Firefox, Safari, Edge)

### Backend Technologies
- **Language**: PHP 7.4+ (tested with PHP 8.4)
- **Database**: MySQL 5.7+ / MariaDB
- **PDO**: Database abstraction layer
- **Session Management**: Native PHP sessions
- **Error Handling**: Try-catch blocks with logging

### Database Architecture
```
Tables:
- admin: Admin users
- brands: Product brands
- categories: Product categories  
- coupons: Discount coupons
- customers: Customer information
- orders: Order records
- order_items: Order line items
- products: Product catalog
- product_images: Product image gallery
- product_variations: Product variants
- payment_tokens: Secure payment links
- visitors: Visitor analytics
```

### File Structure
```
/aleppogift
  /config
    - config.php (main configuration)
    - constants.php (site constants)
  /includes
    - Database.php (database class)
    - bootstrap.php (app initialization)
    - cart_helpers.php
    - shipping.php
    - ZiinaPayment.php
    - whatsapp_notify.php
    - send_email_simple.php
    - generate_invoice_pdf.php
    - visitor_tracker.php
    - session.php
    - security.php
  /public
    /admin (admin panel)
    /ajax (AJAX endpoints)
    /assets (CSS, JS, images)
    /invoice (generated PDFs)
    /uploads (product images)
    - index.php (homepage)
    - products.php (product listing)
    - product.php (product detail)
    - cart.php (shopping cart)
    - checkout.php (checkout page)
    - thankyou.php (order confirmation)
    - shipping.php (shipping calculator)
  /vendor (Composer dependencies)
```

### Performance Optimizations
- **Image Optimization**: Compressed product images
- **Lazy Loading**: Deferred image loading
- **AJAX Operations**: Reduced page reloads
- **Database Indexing**: Optimized queries
- **Caching**: Session-based caching
- **CDN**: External resources from CDN
- **Minification**: Minified CSS/JS (production)
- **GZIP Compression**: .htaccess configuration

### Development Features
- **Error Logging**: Comprehensive error logs
- **Debug Mode**: Configurable debug output
- **Test Files**: Extensive testing suite
- **Documentation**: Markdown documentation files
- **Version Control**: Git integration
- **Deployment Scripts**: Automated deployment (PowerShell)
- **Backup Scripts**: Database and file backups
- **Database Migrations**: SQL migration files

### Hosting & Deployment
- **Web Server**: Apache with mod_rewrite
- **PHP Version**: 7.4 - 8.4 compatible
- **MySQL Requirements**: 5.7+ or MariaDB 10+
- **.htaccess**: URL rewriting, security headers
- **SSL/TLS**: HTTPS support
- **File Permissions**: Proper directory permissions
- **Cron Jobs**: For scheduled tasks (if needed)

### Configuration Management
- **Environment Variables**: .env file support
- **Site Settings**: Centralized in config.php
  - Database credentials
  - SMTP settings
  - Payment gateway keys
  - Site URL and name
  - Currency settings
  - Timezone
  - Debug mode
- **Constants File**: Site-wide constants
  - Currency symbol
  - Contact information
  - Social media links
  - API keys

### Error Handling & Logging
- **PHP Error Log**: All errors logged to file
- **Custom Error Handler**: Checkout-specific error handling
- **User-Friendly Messages**: Hide technical details from users
- **Stack Traces**: Full traces in logs for debugging
- **AJAX Error Handling**: JSON error responses
- **Database Error Handling**: Catch and log DB errors

---

## 🎯 Unique Features

### Advanced Features Not Common in E-Commerce
1. **Image-Variation Linking**: Each product image can represent a specific variation
2. **Manual Admin Order Creation**: Full order creation system from admin panel
3. **Quick Payment Links**: Generate payment requests without full orders
4. **Smart Shipping Calculator**: Weight-based + geographic + parcel splitting
5. **WhatsApp Integration**: Admin notifications via WhatsApp API
6. **Arabic Encoding Dashboard**: Special tools for Arabic text handling
7. **Social Media Automation Bot**: Automated video creation and posting
8. **Invoice Regeneration**: Edit orders and regenerate invoices post-creation
9. **Comprehensive Visitor Tracking**: IP-based with geo-location
10. **Multi-Channel Notifications**: Email + WhatsApp + SMS (if configured)
11. **Payment Token System**: Secure, expiring payment links
12. **Shipping Cost API**: Public API for shipping estimates
13. **Product Quick View**: Modal-based product preview
14. **Dynamic Product Variations**: Flexible variation system
15. **Real-Time Stock Validation**: Prevent overselling

---

## 📝 Additional Features

### Customer Experience
- **Professional Design**: Modern, clean interface
- **Fast Loading**: Optimized for performance
- **Easy Navigation**: Intuitive user interface
- **Mobile-Friendly**: Fully responsive
- **Secure Checkout**: SSL and secure payment processing
- **Multiple Payment Options**: Flexibility for customers
- **Order Tracking**: Track shipment status
- **Customer Support**: Multiple contact channels (email, WhatsApp)
- **Product Recommendations**: Related products, featured items
- **Clear Pricing**: Transparent pricing with no hidden costs

### Business Management
- **Inventory Control**: Real-time stock management
- **Order Processing**: Streamlined order fulfillment
- **Customer Database**: Centralized customer information
- **Sales Reports**: Detailed analytics and reporting
- **Flexible Pricing**: Discounts and coupons
- **Marketing Tools**: Featured products, promotions
- **Multi-Channel Selling**: Integration possibilities
- **Scalability**: Built to handle growth
- **Data Export**: Export orders, customers, products
- **Backup Systems**: Regular database backups

---

## 🚀 Future Enhancement Possibilities

Based on the current architecture, the system can be extended with:

1. **Customer Accounts**: User registration and login
2. **Wishlist Persistence**: Database-backed wishlist
3. **Product Reviews**: Customer product reviews and ratings
4. **Loyalty Program**: Points and rewards system
5. **Advanced SEO**: Rich snippets, structured data
6. **Email Marketing**: Newsletter integration
7. **Abandoned Cart Recovery**: Email reminders
8. **Live Chat**: Customer support chat
9. **Multi-Currency**: Support for multiple currencies
10. **Multi-Vendor**: Marketplace functionality
11. **Mobile Apps**: Native iOS/Android apps
12. **Progressive Web App**: PWA capabilities
13. **Advanced Analytics**: Google Analytics 4, custom reports
14. **A/B Testing**: Conversion optimization
15. **Subscription Products**: Recurring orders

---

## 📊 Technical Specifications

### Database Tables Summary
| Table | Purpose | Key Fields |
|-------|---------|-----------|
| products | Product catalog | id, name_ar, name_en, price, stock, weight |
| product_images | Product photos | id, product_id, image_path, is_main |
| product_variations | Product variants | id, product_id, image_id, color, stock |
| categories | Product categories | id, name_ar, name_en, picture |
| brands | Product brands | id, name_ar, name_en, logo |
| orders | Customer orders | id, customer_id, total_amount, payment_status |
| order_items | Order line items | id, order_id, product_id, variation_id, quantity |
| customers | Customer info | id, fullname, email, phone, address |
| coupons | Discount codes | id, code, discount_type, discount_value |
| payment_tokens | Payment links | id, order_id, token, expires_at |
| visitors | Analytics | id, ip_address, visit_date, visit_count |
| admin | Admin users | id, username, password, email |

### API Endpoints Summary
| Endpoint | Method | Purpose |
|----------|--------|---------|
| /ajax/add_to_cart.php | POST | Add item to cart |
| /ajax/update_cart_qty.php | POST | Update cart quantity |
| /ajax/remove_from_cart.php | POST | Remove cart item |
| /ajax/cart_preview.php | GET | Get cart HTML |
| /ajax/calculate_shipping.php | POST | Calculate shipping cost |
| /ajax/search_products.php | GET | Search products |
| /ajax/search_customers.php | GET | Search customers |
| /ajax/countries.php | GET | Get country list |
| /ajax/manage_order_products.php | POST | Manage order items |

---

## 📧 Contact & Support

**Website:** https://aleppogift.com  
**Email:** sales@aleppogift.com  
**WhatsApp:** +971 56 112 5320  
**Location:** United Arab Emirates

---

## 📄 License & Credits

**Developed for:** AleppoGift - Luxury Gifts & Home Decor  
**Technologies Used:**
- PHP, MySQL, JavaScript, Bootstrap, Font Awesome
- FPDF, PHPMailer, Ziina Payment Gateway
- Python (Social Media Automation)
- Streamlit, FFmpeg, OpenCV (Video generation)

---

**Document Version:** 1.0  
**Last Updated:** February 8, 2026  
**Total Features Documented:** 200+

---

*This documentation covers all major features and capabilities of the AleppoGift e-commerce platform. Use this as a reference to understand the system architecture, implement similar features, or plan system enhancements.*
