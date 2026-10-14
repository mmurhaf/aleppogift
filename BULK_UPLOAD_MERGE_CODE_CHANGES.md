# Code Changes Reference - Bulk Upload & Merge Products

## Changes Made to Fix Compatibility Issues

This document shows the exact code changes made to make the imported files compatible with your system.

---

## File 1: bulk-upload-images.php

### Change 1: CSRF Token Validation (Line 17)
```php
// ❌ BEFORE:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && validateCSRFToken($_POST['csrf_token'])) {

// ✅ AFTER:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
```

**Reason:** Your system uses `verifyCsrfToken()` (lowercase), not `validateCSRFToken()`.

---

### Change 2: Product Insert Method (Lines 163-170)
```php
// ❌ BEFORE:
$productId = $db->insert($productSql, $productParams);

if (!$productId) {
    // Check if getLastError method exists (backward compatibility)
    $dbError = method_exists($db, 'getLastError') ? $db->getLastError() : '';
    throw new Exception("Database error: Unable to insert product" . ($dbError ? ": $dbError" : ""));
}

// ✅ AFTER:
$db->query($productSql, $productParams);
$productId = $db->lastInsertId();

if (!$productId) {
    throw new Exception("Database error: Unable to insert product");
}
```

**Reason:** Your Database class doesn't have an `insert()` method. Use `query()` then `lastInsertId()`.

---

### Change 3: Image Insert Method (Lines 185-189)
```php
// ❌ BEFORE:
$imageId = $db->insert($imageSql, $imageParams);

if (!$imageId) {
    throw new Exception("Database error: Unable to insert product image");
}

// ✅ AFTER:
$db->query($imageSql, $imageParams);
$imageId = $db->lastInsertId();

if (!$imageId) {
    throw new Exception("Database error: Unable to insert product image");
}
```

**Reason:** Same as above - use `query()` + `lastInsertId()` instead of `insert()`.

---

### Change 4: CSRF Token Generation (Line 436)
```php
// ❌ BEFORE:
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// ✅ AFTER:
<input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
```

**Reason:** Your system uses `generateCsrfToken()` (lowercase), not `generateCSRFToken()`.

---

### Change 5: Footer Include Path (Line 561)
```php
// ❌ BEFORE:
<?php include 'footer.php'; ?>

// ✅ AFTER:
<?php include 'includes/footer.php'; ?>
```

**Reason:** Footer is located in `admin/includes/footer.php`, not `admin/footer.php`.

---

## File 2: merge-products.php

### Change 1: CSRF Token Validation (Line 21)
```php
// ❌ BEFORE:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && validateCSRFToken($_POST['csrf_token'])) {

// ✅ AFTER:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
```

**Reason:** Function name mismatch.

---

### Change 2: Update Images Execute Method (Lines 56-62)
```php
// ❌ BEFORE:
foreach ($imagesToMove as $image) {
    $updated = $db->execute(
        "UPDATE product_images SET product_id = ?, display_order = ?, is_main = 0 WHERE id = ?",
        [$primaryProductId, $nextOrder, $image['id']]
    );
    
    if ($updated) {
        $movedImagesCount++;
        $nextOrder++;
    }
}

// ✅ AFTER:
foreach ($imagesToMove as $image) {
    $db->query(
        "UPDATE product_images SET product_id = ?, display_order = ?, is_main = 0 WHERE id = ?",
        [$primaryProductId, $nextOrder, $image['id']]
    );
    
    $movedImagesCount++;
    $nextOrder++;
}
```

**Reason:** Your Database class doesn't have `execute()` method. Use `query()` instead.  
**Note:** Removed the `if ($updated)` check since `query()` doesn't return affected rows count. The operation succeeds or throws exception.

---

### Change 3: Delete Products Execute Method (Lines 67-72)
```php
// ❌ BEFORE:
foreach ($duplicateIds as $duplicateId) {
    $deleted = $db->execute("DELETE FROM products WHERE id = ?", [$duplicateId]);
    if ($deleted) {
        $deletedCount++;
    }
}

// ✅ AFTER:
foreach ($duplicateIds as $duplicateId) {
    $db->query("DELETE FROM products WHERE id = ?", [$duplicateId]);
    $deletedCount++;
}
```

**Reason:** Same as above - use `query()` instead of `execute()`.

---

### Change 4: CSRF Token Generation (Line 258)
```php
// ❌ BEFORE:
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// ✅ AFTER:
<input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
```

**Reason:** Function name mismatch.

---

### Change 5: Footer Include Path (Line 611)
```php
// ❌ BEFORE:
<?php include 'footer.php'; ?>

// ✅ AFTER:
<?php include 'includes/footer.php'; ?>
```

**Reason:** Incorrect path.

---

## Summary of Methods Changed

### Database Method Mapping

| ❌ Old Method (Doesn't Exist) | ✅ New Method (Your System) |
|-------------------------------|----------------------------|
| `$db->insert($sql, $params)` | `$db->query($sql, $params);`<br>`$id = $db->lastInsertId();` |
| `$db->execute($sql, $params)` | `$db->query($sql, $params);` |

### Function Name Mapping

| ❌ Old Function | ✅ New Function | Location |
|----------------|----------------|----------|
| `validateCSRFToken($token)` | `verifyCsrfToken($token)` | `includes/functions.php` |
| `generateCSRFToken()` | `generateCsrfToken()` | `includes/functions.php` |

### Path Corrections

| ❌ Old Path | ✅ New Path |
|------------|-----------|
| `include 'footer.php'` | `include 'includes/footer.php'` |

---

## Why These Changes Were Needed

### 1. Database Class Design Difference
The imported files came from a system where the Database class had specialized methods:
- `insert()` - for INSERT queries
- `execute()` - for UPDATE/DELETE queries

**Your system uses a unified approach:**
- `query()` - handles all SQL operations
- `lastInsertId()` - gets auto-increment ID after INSERT

### 2. Function Naming Convention  
The imported files used **PascalCase** for CSRF functions (`validateCSRFToken`, `generateCSRFToken`).

**Your system uses camelCase** (`verifyCsrfToken`, `generateCsrfToken`).

### 3. Directory Structure
The imported files expected footer at `admin/footer.php`.

**Your structure:** `admin/includes/footer.php`

---

## Testing the Changes

### Verify Database Methods Work
```php
// Test insert
$db->query("INSERT INTO products (...) VALUES (...)", $params);
$id = $db->lastInsertId(); // ✅ Should return new ID

// Test update  
$db->query("UPDATE products SET ... WHERE id = ?", [$id]); // ✅ Should work

// Test delete
$db->query("DELETE FROM products WHERE id = ?", [$id]); // ✅ Should work
```

### Verify CSRF Functions Work
```php
// Test token generation
$token = generateCsrfToken(); // ✅ Should return token string

// Test token verification
$valid = verifyCsrfToken($token); // ✅ Should return true
```

### Verify Footer Includes
```php
// Test include path
include 'includes/footer.php'; // ✅ Should load footer
```

---

## Additional Notes

### Error Handling
All database operations are wrapped in try-catch blocks:
```php
try {
    $db->beginTransaction();
    // ... operations ...
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    // ... error handling ...
}
```

### Transaction Support
Your Database class properly supports transactions:
- `beginTransaction()` ✅
- `commit()` ✅
- `rollBack()` ✅ (note: camelCase 'B')

---

**Changes Applied:** February 14, 2026  
**Files Modified:** 2  
**Total Code Changes:** 10  
**Backward Compatibility:** Maintained ✅
