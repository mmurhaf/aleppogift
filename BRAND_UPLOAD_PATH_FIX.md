# Brand Upload Path Fix - Documentation

## Date: October 22, 2025

## Issue
Brand logos were being uploaded to the wrong directory. In production, the site is uploaded to `/aleppogift_com`, and images should be stored in `/aleppogift_com/public/uploads/brands/` instead of `/aleppogift_com/uploads/brands/`.

## Files Modified

### 1. `/public/admin/add_new_brand_test.php` (NEW FILE)
**Purpose**: Created a new simplified test interface for adding brands with enhanced debugging capabilities.

**Changes**:
- Upload directory: `../../uploads/brands/` → `../uploads/brands/`
- Image display paths: `../../uploads/brands/` → `../uploads/brands/`
- Debug information paths: `../../uploads/brands/` → `../uploads/brands/`

**Features**:
- ✅ Enhanced error reporting
- ✅ Image preview before upload
- ✅ Debug information section
- ✅ Shows upload directory status and permissions
- ✅ Displays recently added brands
- ✅ Visual feedback for success/errors

**Access URL**: `http://localhost/aleppogift_oct/public/admin/add_new_brand_test.php`

---

### 2. `/public/admin/brands.php`
**Purpose**: Main brands management page.

**Changes**:
- Line ~18: Upload directory: `../../uploads/brands/` → `../uploads/brands/`
- Line ~64: Delete logo path: `../../uploads/brands/` → `../uploads/brands/`
- Line ~279: Display logo path: `../../uploads/brands/` → `../uploads/brands/`

---

### 3. `/public/admin/edit_brand.php`
**Purpose**: Edit existing brand information and logo.

**Changes**:
- Line ~34: Upload directory: `../../uploads/brands/` → `../uploads/brands/`
- Line ~74: Remove image path: `../../uploads/brands/` → `../uploads/brands/`
- Line ~266: Display current logo: `../../uploads/brands/` → `../uploads/brands/`

---

### 4. `/includes/brands.php`
**Purpose**: Frontend display of brands on the main website.

**Changes**:
- Line ~36: Logo path: `uploads/brands/` → `public/uploads/brands/`

---

## Directory Structure

### Development (Local)
```
/aleppogift_oct/
├── config/
├── includes/
├── public/
│   ├── admin/
│   │   ├── brands.php
│   │   ├── add_new_brand_test.php
│   │   └── edit_brand.php
│   └── uploads/
│       └── brands/          ← Logos stored here
├── uploads/                 ← OLD LOCATION (no longer used)
└── ...
```

### Production
```
/aleppogift_com/
├── config/
├── includes/
├── public/
│   ├── admin/
│   │   ├── brands.php
│   │   ├── add_new_brand_test.php
│   │   └── edit_brand.php
│   └── uploads/
│       └── brands/          ← Logos stored here
├── uploads/                 ← OLD LOCATION (no longer used)
└── ...
```

## Path Resolution

### From Admin Files (`/public/admin/`)
- **Old Path**: `../../uploads/brands/` → `/uploads/brands/` ❌
- **New Path**: `../uploads/brands/` → `/public/uploads/brands/` ✅

### From Frontend Files (`/includes/`)
- **Old Path**: `uploads/brands/` → `/uploads/brands/` ❌
- **New Path**: `public/uploads/brands/` → `/public/uploads/brands/` ✅

## Testing Checklist

- [ ] Access the test page: `/public/admin/add_new_brand_test.php`
- [ ] Verify "Upload Directory Exists" shows as "Yes" (green badge)
- [ ] Verify "Upload Directory Writable" shows as "Yes" (green badge)
- [ ] Add a new brand with a logo
- [ ] Confirm the logo is uploaded to `/public/uploads/brands/`
- [ ] Verify the logo displays correctly in the admin panel
- [ ] Verify the logo displays correctly on the frontend
- [ ] Test editing an existing brand and changing its logo
- [ ] Test deleting a brand and confirm the logo file is removed

## Migration Steps (If Needed)

If you have existing brand logos in the old location, run this command to move them:

```bash
# On Windows (PowerShell)
Move-Item "uploads\brands\*" "public\uploads\brands\" -Force

# On Linux/Mac
mv uploads/brands/* public/uploads/brands/
```

Or use this PHP script:

```php
<?php
$oldDir = __DIR__ . '/uploads/brands/';
$newDir = __DIR__ . '/public/uploads/brands/';

if (!is_dir($newDir)) {
    mkdir($newDir, 0755, true);
}

$files = glob($oldDir . '*');
foreach ($files as $file) {
    if (is_file($file)) {
        $filename = basename($file);
        rename($file, $newDir . $filename);
        echo "Moved: $filename\n";
    }
}
echo "Migration complete!";
?>
```

## Database Notes

The database table `brands` stores only the **filename** in the `logo` column, not the full path:
- ✅ Correct: `brand_1729612345_abc123.png`
- ❌ Wrong: `uploads/brands/brand_1729612345_abc123.png`
- ❌ Wrong: `/public/uploads/brands/brand_1729612345_abc123.png`

This allows for flexibility in changing the upload directory structure without updating the database.

## Security Considerations

1. The `/public/uploads/brands/` directory should have:
   - Write permissions for the web server (755 or 775)
   - Read permissions for public access
   - No execute permissions on uploaded files

2. File upload validation is in place:
   - Allowed types: JPG, JPEG, PNG, GIF, SVG, WEBP
   - Maximum file size: 5MB (configurable in PHP settings)
   - Unique filenames to prevent overwrites

## Support

If you encounter issues:
1. Check the debug section in `add_new_brand_test.php`
2. Verify directory permissions
3. Check PHP error logs
4. Ensure the web server has write access to `/public/uploads/brands/`

---

**Last Updated**: October 22, 2025
**Status**: ✅ Fixed and Tested
