# 🔧 URGENT FIX: Variant Stock Issue

## Problem
Auto-created variants had stock set to 0, causing "Insufficient variation stock. Available: 0" error when customers try to add products to cart.

## Solution Applied

### 1. Code Fixed ✅
Updated both auto-creation files to inherit parent product's stock:

- **File**: `public_html/public/admin/ajax/auto_create_variants.php`
  - Now fetches parent product's stock
  - Sets variant stock to parent stock (instead of 0)

- **File**: `public_html/public/admin/add_product.php`
  - Auto-created variants now use `$stock` from parent product
  - No longer hardcoded to 0

### 2. Fix Existing Data

**IMPORTANT**: The code fix only affects NEW variants created from now on. You need to fix existing variants with 0 stock.

#### Quick Fix (2 steps):

**Step 1: Run Fix Script**
Visit this URL in your browser:
```
https://www.aleppogift.com/fix_variant_stock.php?password=YOUR_SETUP_PASSWORD
```

This will:
- Find all auto-created variants with 0 stock
- Update them to match their parent product's stock
- Show you a summary of what was updated

**Step 2: Verify**
Check product that was giving error (ID 744):
```
https://www.aleppogift.com/product.php?id=744
```

Try adding to cart - should work now!

---

## Alternative: Manual SQL Fix

If you prefer to run SQL directly:

```sql
-- Update all auto-created variants to inherit parent stock
UPDATE product_variations v
INNER JOIN products p ON v.product_id = p.id
SET v.stock = p.stock
WHERE v.image_id IS NOT NULL 
  AND v.stock = 0
  AND p.stock > 0;

-- Check results
SELECT v.id, v.product_id, v.stock as variant_stock, p.stock as product_stock, p.name_en
FROM product_variations v
INNER JOIN products p ON v.product_id = p.id
WHERE v.image_id IS NOT NULL;
```

---

## What Changed

### Before:
```php
'stock' => 0  // ❌ Hardcoded to 0
```

### After:
```php
'stock' => $variant_stock  // ✅ Inherits from parent product
```

---

## Testing Checklist

- [ ] Visit fix_variant_stock.php to update existing variants
- [ ] Test product ID 744 (or any product that was showing error)
- [ ] Try adding to cart - should work
- [ ] Create a new product with images → verify variants have correct stock
- [ ] Edit existing product → auto-create variants → verify stock is inherited

---

## Prevention

Going forward, all auto-created variants will:
1. Inherit parent product's stock automatically
2. Work correctly with add-to-cart functionality
3. No more "Insufficient stock" errors for variants

---

## Quick Commands

**Run fix:**
```
https://www.aleppogift.com/fix_variant_stock.php?password=YOUR_SETUP_PASSWORD
```

**Check specific product:**
```sql
SELECT v.*, p.stock as parent_stock
FROM product_variations v
LEFT JOIN products p ON v.product_id = p.id
WHERE v.product_id = 744;
```

**Count affected variants:**
```sql
SELECT COUNT(*) as zero_stock_variants
FROM product_variations
WHERE image_id IS NOT NULL AND stock = 0;
```

---

## Status: FIXED ✅

The code is now correct. Just run the fix script to update existing data and you're all set!
