# Admin Panel Features — Implementation Prompt

Build a complete **PHP/MySQL admin panel** for an e-commerce gift store with the following features. The admin panel is located at `/public/admin/` and protected by session-based authentication. Use Bootstrap 5, Font Awesome 6, and a consistent custom admin CSS theme (`admin-theme.css`).

---

## 1. Authentication

- Secure login page (`login.php`) with username + password.
- Passwords stored as bcrypt hashes (`password_hash` / `password_verify`).
- Session-based authentication; all pages call `require_admin_login()` before executing.
- Logout page (`logout.php`) that destroys the session.
- Admin profile page (`profile.php` / `settings.php`) to update username, email, and password with current-password verification.

---

## 2. Dashboard

File: `dashboard.php`

- Summary stat cards: Total Orders, Total Products, Total Customers, and Total Revenue.
- Recent orders table (last 20 orders) showing: Order ID, Customer Name, Email, Date, Total Amount, Payment Status, and number of items.
- Color-coded payment status badges: `pending` (yellow), `paid` (green), `failed` (red), `cancelled` (grey).
- Quick links to all major admin sections.

---

## 3. Products Management

Files: `products.php`, `add_product.php`, `edit_product.php`

### Product List (`products.php`)
- Paginated table of all products with thumbnail, name (English & Arabic), SKU, price, stock, category, brand, and status.
- Filter by category and brand via dropdowns.
- Search by product name or SKU.
- **Duplicate** a product (copies all fields + images, sets status to inactive, appends `(Copy)` to name, redirects to edit the new copy).
- **Delete** a product (removes product images records first, then the product).
- Toggle active/inactive status.

### Add Product (`add_product.php`)
- Fields: `name_en`, `name_ar`, `description_en`, `description_ar`, `price`, `cost_price` (optional), `weight` (default: 2), `stock` (default: 10), `SKU` (auto-generated if blank), `category_id`, `brand_id`, `featured` (checkbox), `status`.
- Upload multiple product images; set one as the main/cover image.
- Auto-generate product variations (size/color) from uploaded images.

### Edit Product (`edit_product.php`)
- All add-product fields, pre-filled from the database.
- Image gallery management: upload new images, reorder by drag-and-drop (`display_order`), set main image, delete individual images.
- **Product variations** section: each variation has `size`, `color`, `stock`, `price_modifier`, and an optional linked image. Add/remove/edit variations inline.
- Cost price field shown only if the `cost_price` column exists in the database.

---

## 4. Categories Management

File: `categories.php`, `edit_category.php`

- List all categories with their English name, Arabic name, status, and optional category picture.
- Add new category: `name_en`, `name_ar`, `status`, optional image upload (jpg/jpeg/png/gif/webp, max 5 MB). Image stored in `uploads/categories/`.
- Edit existing category inline.
- Delete category (removes the category picture file from disk).
- Toggle active/inactive status.

---

## 5. Brands Management

File: `brands.php`, `edit_brand.php`

- List all brands with logo thumbnail, English name, Arabic name, and status.
- Add new brand: `name_en`, `name_ar`, `status`, optional logo upload. Logo stored in `uploads/brands/`.
- Edit and delete brands.
- Toggle active/inactive status.
- Handles both `ENUM('active','inactive')` and `TINYINT(1)` status column types transparently.

---

## 6. Orders Management

Files: `orders.php`, `order_detail.php`, `create_order.php`

### Orders List (`orders.php`)
- Full orders table sorted by newest first: Order ID, Customer, Email, Date, Amount, Payment Status.
- **Bulk delete** selected orders (also deletes invoice PDF files from disk and removes `order_items` rows).
- **Update payment status** inline via a dropdown per order: `pending`, `paid`, `failed`, `cancelled`.
- Link to view full order detail and download invoice.

### Order Detail (`order_detail.php`)
- Shows complete order info: customer details, shipping address, Emirates, phone, order date, payment status, shipping cost, discount, total.
- Line items table: product image (with variation image fallback), product name, variation (size/color), quantity, unit price, line total.
- Buttons: Update payment status, Generate Invoice, Download Invoice, Resend Email.
- Update shipment tracking info (courier, tracking number, estimated delivery).

### Create Order (`create_order.php`)
- Admin can manually create an order on behalf of a customer.
- Select or enter customer info: full name, email, phone.
- Choose UAE Emirate for delivery address.
- Add products by search (AJAX), select variation, set quantity.
- Auto-calculates subtotal, shipping cost, discounts, and total.
- Saves order to `orders` + `order_items` tables with `payment_status = 'pending'`.
- Generates and sends invoice to customer email.

---

## 7. Customers Management

File: `customers.php`, `view_customer.php`

- Table of all registered customers: ID, name, email, phone, registration date, total orders count.
- View individual customer: personal info, full order history, total spend.
- No direct add/edit (customers register via checkout); admin can view only.

---

## 8. Coupons / Discount Codes

File: `coupons.php`

- Create coupon: `code` (unique), `discount_type` (`percentage` or `fixed`), `discount_value`, `start_date`, `end_date`, `max_usage`, `status`.
- Edit and delete coupons.
- List all coupons with usage count, validity dates, and status badge.

---

## 9. Invoice Management

Files: `generate_invoice.php`, `edit_invoice.php`, `regenerate_invoice.php`, `download_invoice.php`

- Generate PDF invoices using FPDF (Arabic text support via `ArabicPdfRenderer`).
- Invoice includes: store logo, order details, itemised table with product names (English + Arabic), quantities, prices, shipping cost, discount, grand total.
- Download invoice as PDF.
- Edit/regenerate existing invoice.
- Admin-triggered invoice email resend.

---

## 10. Payment Links

File: `payment_links.php`, `quick_payment_link.php`

- Generate a Ziina payment link for a specific amount (custom or tied to an order).
- Display the shareable payment URL with copy-to-clipboard and WhatsApp share buttons.
- Track and list all generated payment links with their status (pending / paid).

---

## 11. Shared Carts

File: `shared_carts.php`

- Lists all admin-created shared cart links: token, items summary, creation date, expiry date, status (`active`, `expired`).
- **Expire** a cart link immediately.
- **Reactivate** an expired link (extends expiry by 7 days).
- **Delete** a shared cart record.
- Admin creates a shared cart by selecting products + quantities; system generates a unique 64-char hex token stored in `shared_carts` table.

---

## 12. Reports & Analytics

Files: `reports.php`, `analytics.php`

### Sales Reports (`reports.php`)
- Date range filter (default: current month).
- Report types: Overview, Sales by Product, Sales by Category, Sales by Customer.
- Overview stats: Total Orders, Total Revenue, Average Order Value, New Customers, Products Sold.
- Daily sales chart (Chart.js) showing orders and revenue per day.
- Top products table: name, units sold, revenue.
- Top categories table.
- Export to CSV.

### Visitor Analytics (`analytics.php`)
- Real-time visitor log table: IP address (IPv4/IPv6 display), country with flag emoji, city, browser/device, page visited, visit time.
- Country statistics with flag and visit count.
- Visitor totals: Today, Last 7 Days, Last 30 Days.
- IP geolocation stored in `visitors` table via `visitor_tracker.php`.

---

## 13. Product Reviews Management

File: `reviews.php`

- List all submitted product reviews with filters: product, status (approved/pending), rating (1–5), keyword search.
- Paginated (20 per page).
- **Approve** or **Reject** a review.
- **Delete** a review.
- Displays: reviewer name, product, rating (stars), review text, submission date, approval status.

---

## 14. Testimonials Management

File: `testimonials.php`, `manage_trust.php`

- Add testimonials: customer name, photo upload, testimonial text, star rating, status.
- Supports **WhatsApp proof screenshots**: upload screenshot, consent status, optional privacy blur flag.
- Approve/reject/delete testimonials.
- Manage trust badges displayed on the storefront.

---

## 15. Bulk Tools

Files: `bulk_edit_prices.php`, `bulk-upload-images.php`

### Bulk Price Editor (`bulk_edit_prices.php`)
- Table of all products with inline editable fields: `price`, `cost_price`, `stock`.
- Update individual product inline (AJAX) or bulk-update all changed rows at once.
- Only shows `cost_price` column if it exists in the DB.

### Bulk Image Upload (`bulk-upload-images.php`)
- Drop-zone to upload multiple product images at once.
- Each uploaded image auto-creates a new product entry with a placeholder name derived from the filename.
- Skips duplicate filenames.
- Shows success/error/skipped summaries after upload.

---

## 16. Settings

File: `settings.php`

- **Admin Profile**: update username, email, current password, new password.
- **Site Settings** (stored in `site_settings` key-value table):
  - `show_prices` — toggle price visibility on storefront (1/0).
  - Store name, contact email, WhatsApp number.
  - SMTP email configuration (host, port, username, password, from name).
  - Ziina payment gateway API key.
  - Shipping rates per Emirate.

---

## 17. Shipping Configuration

File: `shipping.php` (admin-side)

- Configure shipping cost per UAE Emirate.
- Set free-shipping threshold.
- Manage shipping discount rules.
- Settings saved to DB and used at checkout.

---

## 18. Email & Notification Tools

- **Email client** (`email_client.php`): send a custom email to any customer from within admin.
- Order confirmation email auto-sent on successful checkout (PHPMailer/Resend API).
- WhatsApp notification sent on new order (via `whatsapp_notify.php`).
- **Logs viewer** (`view-logs.php`): tail application log files from within the admin panel.

---

## 19. Merge Products Tool

File: `merge-products.php` (in `/admin/` and `/public/admin/`)

- Search and select two products to merge (one primary, one secondary).
- Moves all images, order_items references, and variations from the secondary product to the primary.
- Deletes the secondary product after merging.

---

## 20. Database & System Tools (Admin-only)

- `query_runner.php` — run raw SQL queries (admin-only, development use).
- `view-logs.php` — view PHP error logs.
- `system_status.php` — PHP version, extensions, DB connection status.
- `api_status.php` — check Ziina and email API connectivity.

---

## Technical Stack

- **Language**: PHP 8.x
- **Database**: MySQL / MariaDB via PDO (`Database` class wrapper)
- **CSS Framework**: Bootstrap 5.3
- **Icons**: Font Awesome 6.6
- **PDF**: FPDF with Arabic text support
- **Email**: PHPMailer + Resend API fallback
- **Payments**: Ziina payment gateway
- **Charts**: Chart.js (for analytics/reports)
- **Security**: CSRF tokens on all destructive forms, prepared statements everywhere, `password_hash` for passwords, admin session guard on every page.
- **Bilingual**: All content fields have `_en` and `_ar` variants (English + Arabic). Admin UI is in English; product data is bilingual.
