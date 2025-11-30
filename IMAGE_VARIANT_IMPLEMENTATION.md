# Product Image Variation Implementation

## Overview
This implementation links product images to product variations, allowing customers to select specific product variants (colors, shapes, etc.) by clicking on different product images. This ensures that when a customer adds a product to the cart, the exact variant they viewed is recorded.

## Problem Solved
**Before**: Customers could see multiple product images (different colors/shapes) but couldn't specify which one they wanted. The default image was always added to cart, causing confusion for sellers who didn't know which variant to send.

**After**: Each product image is linked to a specific variation. When customers click an image, the corresponding variation is automatically selected, and that exact variant is added to the cart.

## Database Changes

### 1. Added `image_id` Column to `product_variations` Table
```sql
ALTER TABLE `product_variations` 
ADD COLUMN `image_id` INT(11) DEFAULT NULL AFTER `product_id`,
ADD INDEX `idx_image_id` (`image_id`);
```

### Updated Table Structure
```
product_variations:
- id (auto-increment) 
- product_id
- image_id (NEW - links to product_images.id)
- size
- color (now used for variant name like "Product 1", "Product 2")
- additional_price
- stock
```

## Implementation Files

### 1. SQL Migration Script
**File**: `add_image_variations.sql`

**What it does**:
- Adds `image_id` column to `product_variations` table
- Automatically creates variations for all existing products with multiple images
- Each image gets a variation named "Product 1", "Product 2", etc.
- All variations get the full product stock
- Additional price is set to 0.00 for all

**How to run in production**:
```sql
-- Execute this file in your production database
mysql -u username -p database_name < add_image_variations.sql
```

Or run sections manually in phpMyAdmin/database tool.

### 2. Product Details Page
**File**: `public/product.php`

**Changes made**:
1. **PHP Backend** (lines 26-50):
   - Fetches all images with their IDs
   - Fetches variations with linked image information
   - Creates `$all_images` array for variation mapping

2. **HTML Updates** (thumbnail section):
   - Each thumbnail now has `data-image-id` and `data-variation-id` attributes
   - Clicking a thumbnail calls `selectImageVariation()` function
   - Variation dropdown updated to show better variant names

3. **JavaScript Enhancement** (lines 588-610):
   - New `selectImageVariation()` function
   - Automatically selects variation when image is clicked
   - Visual feedback when variation is auto-selected (orange border)
   - Maintains backward compatibility

### 3. Add Product Page
**File**: `public/admin/add_product.php`

**New Features**:
- "Product Variations" section added
- Dynamic form to add multiple variations
- Each variation can have:
  - Size (optional)
  - Color/Name (required - at least size or color)
  - Additional price
  - Stock quantity
- Variations automatically get IDs from database

## How It Works

### For Customers:
1. Customer visits product details page
2. Sees main image and thumbnail gallery
3. Clicks on different images to view them
4. **When clicking an image**: 
   - Image enlarges in main view
   - Variation dropdown automatically selects matching variant
   - Orange highlight shows which variant was selected
5. Customer adds to cart
6. Exact variant (linked to that image) is saved in order

### For Admin:
1. **Existing Products**: 
   - Run SQL script to auto-generate variations
   - Each existing image becomes a variation

2. **New Products**:
   - Upload product images as usual
   - Add variations in the new "Product Variations" section
   - Can manually link variations to images (future enhancement)
   - Or let system auto-generate after product creation

3. **Order Management**:
   - In `admin/order_detail.php`, variation info shows which specific variant was ordered
   - Seller knows exactly which color/shape to send

## Database Query Examples

### Check Products with Variations
```sql
SELECT 
    p.id,
    p.name_en,
    COUNT(DISTINCT pi.id) AS image_count,
    COUNT(DISTINCT pv.id) AS variation_count
FROM products p
LEFT JOIN product_images pi ON p.id = pi.product_id
LEFT JOIN product_variations pv ON p.id = pv.product_id
WHERE pi.product_id > 0
GROUP BY p.id
HAVING image_count > 1;
```

### View Variation-Image Links
```sql
SELECT 
    pv.id AS variation_id,
    p.name_en,
    pv.color AS variant_name,
    pi.image_path,
    pi.display_order
FROM product_variations pv
JOIN product_images pi ON pv.image_id = pi.id
JOIN products p ON pv.product_id = p.id
WHERE pv.image_id IS NOT NULL
ORDER BY p.id, pi.display_order;
```

### Check Orders with Variants
```sql
SELECT 
    o.id AS order_id,
    o.customer_name,
    p.name_en AS product,
    pv.color AS variant_selected,
    pi.image_path AS variant_image
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
JOIN products p ON oi.product_id = p.id
LEFT JOIN product_variations pv ON oi.variation_id = pv.id
LEFT JOIN product_images pi ON pv.image_id = pi.id
WHERE oi.variation_id IS NOT NULL
ORDER BY o.id DESC;
```

## Deployment Steps for Production

### Step 1: Backup Database
```bash
# Create backup before making changes
mysqldump -u username -p database_name > backup_before_variations.sql
```

### Step 2: Test on Staging (if available)
1. Run `add_image_variations.sql` on staging database
2. Test product pages
3. Test add to cart functionality
4. Test order creation
5. Verify admin order details show variants

### Step 3: Deploy to Production
1. Upload updated files:
   - `public/product.php`
   - `public/admin/add_product.php`
   
2. Run SQL script:
   ```sql
   -- In phpMyAdmin or MySQL console
   source /path/to/add_image_variations.sql;
   ```

3. Verify:
   - Check products have variations
   - Test clicking images selects variants
   - Test checkout process
   - Check admin panel shows variant in orders

### Step 4: Monitor
- Check error logs for any issues
- Test several products
- Verify orders show correct variants

## Configuration Options

### Variant Naming Convention
In `add_image_variations.sql`, the variants are named "Product 1", "Product 2", etc.

To change naming:
```sql
-- Current:
CONCAT('Product ', @row_num) AS color

-- Change to:
CONCAT('Variant ', @row_num) AS color
-- Or:
CONCAT('Option ', @row_num) AS color
```

### Stock Distribution
Currently, each variation gets the full product stock:
```sql
p.stock AS stock
```

To split stock equally:
```sql
FLOOR(p.stock / image_count) AS stock
```

## Troubleshooting

### Issue: Variations not showing
**Solution**: Check if product has multiple images
```sql
SELECT product_id, COUNT(*) as img_count 
FROM product_images 
GROUP BY product_id 
HAVING img_count > 1;
```

### Issue: Variation dropdown not auto-selecting
**Solution**: 
1. Check browser console for JavaScript errors
2. Verify variation has `image_id` set
3. Clear browser cache

### Issue: Wrong variant added to cart
**Solution**:
1. Ensure customer selected variation before adding to cart
2. Check `order_items.variation_id` is being saved
3. Verify variation exists in database

## Future Enhancements

1. **Manual Image-Variant Linking**: Add UI in admin to manually link images to variations
2. **Variant Images Upload**: Upload different image per variation when creating product
3. **Variant Stock Management**: Separate stock tracking per variant in admin panel
4. **Variant Filtering**: Filter products by available variants on category pages
5. **Color Swatches**: Show color swatches instead of dropdown for better UX

## Support

For issues or questions:
1. Check database logs
2. Check browser console for JavaScript errors
3. Verify SQL queries executed successfully
4. Test with a single product first before bulk operations

---

**Last Updated**: November 30, 2025
**Version**: 1.0
