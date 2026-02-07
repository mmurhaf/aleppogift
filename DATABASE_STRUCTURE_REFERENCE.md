# Database Structure Reference

**Source:** u933234997_aleppogift.sql (Production Database)

## Important Notes
- **Orders table does NOT have `created_at` column** - use `order_date` instead
- **Orders table does NOT have `updated_at` column**
- **Customers table does NOT have `updated_at` column**

## Table Structures

### admin
```sql
- id (int, primary key)
- username (varchar 50)
- password (varchar 255)
- email (varchar 100, nullable)
- created_at (timestamp, default CURRENT_TIMESTAMP) ✓
```

### brands
```sql
- id (int, primary key)
- name_ar (varchar 255, nullable)
- name_en (varchar 255, nullable)
- status (int, default 1)
- logo (varchar 255, nullable)
```

### categories
```sql
- id (int, primary key)
- name_ar (varchar 255, nullable)
- name_en (varchar 255, nullable)
- status (int, default 1)
- picture (varchar 255, nullable)
```

### coupons
```sql
- id (int, primary key)
- code (varchar 50)
- discount_type (enum: 'fixed', 'percent')
- discount_value (decimal 10,2)
- start_date (date, nullable)
- end_date (date, nullable)
- max_usage (int, default 0)
- used_count (int, default 0)
- status (enum: 'active', 'inactive', default 'active')
- created_at (timestamp, default CURRENT_TIMESTAMP) ✓
- updated_at (timestamp, default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ✓
```

### customers
```sql
- id (int, primary key)
- fullname (varchar 100, nullable)
- email (varchar 100, nullable)
- phone (varchar 20, nullable)
- address (varchar 255, nullable)
- city (varchar 100, nullable)
- country (varchar 100, nullable)
- created_at (timestamp, default CURRENT_TIMESTAMP) ✓
```
**⚠️ NO updated_at column**

### orders
```sql
- id (int, primary key)
- customer_id (int, nullable)
- order_date (timestamp, default CURRENT_TIMESTAMP) ✓
- total_amount (decimal 10,2, nullable)
- payment_status (enum: 'pending', 'paid', 'failed', default 'pending')
- payment_method (varchar 50, nullable)
- payment_reference (varchar 255, nullable)
- invoice_pdf (varchar 255, nullable)
- updated_at (datetime, nullable) ✓
- total_weight (decimal 10,2, nullable)
- shipping_aed (decimal 10,2, nullable)
- note (mediumtext, nullable)
- remarks (mediumtext, nullable)
- coupon_code (varchar 50, nullable)
- discount_type (enum: 'fixed', 'percent', nullable)
- discount_value (decimal 10,2, nullable)
- discount_amount (decimal 10,2, nullable)
- tracking_number (varchar 255, nullable)
- carrier_name (varchar 100, nullable)
- shipping_method (varchar 100, nullable)
- shipment_status (enum: 'pending', 'processing', 'shipped', 'delivered', default 'pending')
- shipped_date (datetime, nullable)
```
**⚠️ NO created_at column - use order_date instead!**

### order_items
```sql
- id (int, primary key)
- order_id (int, nullable)
- product_id (int, nullable)
- product_name (varchar 255, nullable)
- product_name_ar (varchar 255, nullable)
- quantity (int, nullable)
- price (decimal 10,2, nullable)
- total (decimal 10,2, nullable)
- variation_id (int, nullable)
- variation_details (text, nullable)
```

### products
```sql
- id (int, primary key)
- category_id (int, nullable)
- brand_id (int, nullable)
- name_ar (varchar 255, nullable)
- name_en (varchar 255, nullable)
- description_ar (mediumtext, nullable)
- description_en (mediumtext, nullable)
- price (decimal 10,2, nullable)
- sku (varchar 30, nullable)
- stock (int, default 0)
- featured (int, default 0)
- status (int, default 1)
- created_at (timestamp, default CURRENT_TIMESTAMP) ✓
- weight (int, default 1)
```

### product_images
```sql
- id (int, primary key)
- product_id (int, nullable)
- image_path (varchar 255, nullable)
- is_main (tinyint 1, default 0)
- display_order (int, default 0)
```

### product_variations
```sql
- id (int, primary key)
- product_id (int)
- variation_type (varchar 100, nullable)
- variation_value (varchar 255, nullable)
- price_modifier (decimal 10,2, default 0.00)
- stock (int, default 0)
- sku (varchar 100, nullable)
```

### visitors
```sql
- id (int, primary key)
- ip_address (varchar 45)
- visit_date (date)
- visit_count (int, default 1)
```

## Common Mistakes to Avoid

1. ❌ **Don't use `created_at` on orders table**
   ```php
   // WRONG
   INSERT INTO orders (..., created_at) VALUES (..., NOW())
   
   // CORRECT
   INSERT INTO orders (..., order_date) VALUES (..., NOW())
   ```

2. ❌ **Don't use `updated_at` on customers table**
   ```php
   // WRONG
   UPDATE customers SET ..., updated_at = NOW()
   
   // CORRECT
   UPDATE customers SET ... (no updated_at)
   ```

3. ✅ **Correct usage:**
   - Customers: `created_at` has default, don't need to specify in INSERT
   - Orders: Use `order_date` for timestamp, `updated_at` for updates
   - Products: `created_at` has default, don't need to specify in INSERT
   - Admin: `created_at` can be specified or left to default

## Files Fixed
- ✅ public/admin/ajax/create_quick_payment.php
- ✅ public/admin/ajax/process_admin_order.php
- ✅ public/admin/check_orders.php
- ✅ public/admin/regenerate_invoice_fixed.php
- ✅ public/admin/regenerate_invoice_no_auth.php
