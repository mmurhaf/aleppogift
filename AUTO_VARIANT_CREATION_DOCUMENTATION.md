# Auto-Create Variants for Product Images

## Overview
This feature automatically creates product variants linked to each product image, allowing customers to select specific images when choosing product variations.

## Features Implemented

### 1. Database Migration
**File**: `public_html/database/add_image_id_to_variations.sql`

This SQL migration adds the `image_id` column to the `product_variations` table, enabling variants to be linked to specific product images.

**To run the migration:**
```sql
-- Execute this SQL in your database
source public_html/database/add_image_id_to_variations.sql;
```

Or manually run via phpMyAdmin or MySQL command line.

### 2. Edit Product Page - Manual Variant Creation
**File**: `public_html/public/admin/edit_product.php`

**New Feature**: "Auto-Create Variants for Images" button

**How to use:**
1. Navigate to Admin Panel → Products → Edit any product
2. Scroll to the "Current Images" section
3. Click the **"Auto-Create Variants for Images"** button
4. Confirm the action
5. The system will automatically create one variant for each image that doesn't already have a linked variant

**What it does:**
- Creates a product variation for each product image
- Links each variation to its corresponding image via `image_id`
- Names variants as "Main Image Variant" for the main image and "Image Variant 1", "Image Variant 2", etc. for others
- Skips images that already have linked variants
- Sets `additional_price` to 0.00 and `stock` to 0 by default (you can edit these later)

### 3. Add Product Page - Automatic Variant Creation
**File**: `public_html/public/admin/add_product.php`

**Automatic Behavior:**
When adding a new product, if the admin:
- Uploads product images
- Does NOT specify any manual variations

Then the system will **automatically create** variants for each uploaded image.

**Example Workflow:**
1. Admin Panel → Products → Add Product
2. Fill in product details (name, price, category, etc.)
3. Upload multiple images (e.g., 3 images)
4. Leave "Product Variations" section empty
5. Click "Add Product"
6. **Result**: 3 variants are automatically created and linked to the 3 images

### 4. AJAX Endpoint
**File**: `public_html/public/admin/ajax/auto_create_variants.php`

Backend API endpoint that handles the auto-creation logic.

**Request:**
- Method: POST
- Parameter: `product_id`

**Response:**
```json
{
  "success": true,
  "message": "Successfully created 3 variant(s)",
  "created_count": 3,
  "skipped_count": 0,
  "variants": [
    {
      "id": 45,
      "image_id": 12,
      "name": "Main Image Variant"
    },
    {
      "id": 46,
      "image_id": 13,
      "name": "Image Variant 2"
    },
    {
      "id": 47,
      "image_id": 14,
      "name": "Image Variant 3"
    }
  ]
}
```

## Use Cases

### Use Case 1: Product with Multiple Color Options
**Scenario**: A t-shirt available in Red, Blue, and Green

**Steps:**
1. Upload 3 images (one for each color)
2. Click "Auto-Create Variants for Images"
3. Edit the auto-created variants:
   - Variant 1: Change color from "Main Image Variant" to "Red"
   - Variant 2: Change color from "Image Variant 2" to "Blue"
   - Variant 3: Change color from "Image Variant 3" to "Green"
4. Set stock and pricing for each variant

### Use Case 2: Product with Different Angles
**Scenario**: A decorative item shown from different angles

**Steps:**
1. Upload multiple images showing different views
2. System auto-creates variants for each image
3. Customers can select which view/angle they want to see
4. All variants can have the same price (additional_price = 0)

### Use Case 3: Quick Product Addition
**Scenario**: Admin wants to quickly add products without manual variant setup

**Steps:**
1. Add product with basic info
2. Upload images
3. Don't specify variations
4. Submit → Variants are automatically created
5. Edit variants later if needed

## Benefits

1. **Time-Saving**: No need to manually create variants for each image
2. **Consistency**: Every image can have a corresponding variant
3. **Flexibility**: Can edit auto-created variants or delete them as needed
4. **User Experience**: Customers can see the exact image for each variant they select
5. **Backward Compatible**: Doesn't affect existing products or manual variant creation

## Technical Details

### Database Schema Changes
```sql
-- product_variations table now includes:
ALTER TABLE product_variations 
ADD COLUMN image_id INT(11) DEFAULT NULL;

-- Foreign key ensures referential integrity
FOREIGN KEY (image_id) REFERENCES product_images(id);
```

### Variant Naming Convention
- Main image → "Main Image Variant"
- Other images → "Image Variant 1", "Image Variant 2", etc.

### Default Values for Auto-Created Variants
- `size`: NULL
- `color`: Auto-generated name (e.g., "Main Image Variant")
- `additional_price`: 0.00
- `stock`: 0
- `image_id`: Linked to corresponding product image

## Important Notes

1. **Migration Required**: Run the SQL migration before using this feature
2. **Existing Data**: Won't affect existing products until you manually trigger auto-creation
3. **Duplicate Prevention**: Won't create duplicate variants for images that already have linked variants
4. **Editable**: All auto-created variants can be edited or deleted like normal variants
5. **Optional**: This is an optional feature - manual variant creation still works as before

## Troubleshooting

### Issue: Button doesn't work
**Solution**: Check browser console for JavaScript errors. Ensure `ajax/auto_create_variants.php` file exists.

### Issue: Variants not appearing
**Solution**: Refresh the page after clicking the button. Check if images exist for the product.

### Issue: Database error
**Solution**: Ensure the migration was run successfully and `image_id` column exists in `product_variations` table.

### Issue: No images to create variants for
**Solution**: Upload images first, then use the auto-create feature.

## Future Enhancements (Possible)

1. Bulk auto-create for all products
2. Custom naming templates for auto-created variants
3. Auto-assign stock based on product stock
4. Image-based variant management interface
5. Ability to auto-link existing variants to images

## Summary

This feature streamlines product management by automatically creating variants for each product image. It's particularly useful for:
- Products with multiple color/style options shown in different images
- Quick product additions where variants can be refined later
- Ensuring every product image has a selectable variant option

The system maintains backward compatibility while adding powerful automation to save admin time and improve the customer experience.
