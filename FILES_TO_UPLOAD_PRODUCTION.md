# Files to Upload to Production - AJAX Shipping Fix

## 🔄 Modified Files (Must Upload)

### 1. **Core Configuration Files**
```
config/config.php
```
**Changes:** Added guards to prevent duplicate constant definitions
- Fixed `CURRENCY_SYMBOL` redefinition warnings
- Protected other constants from redefinition

### 2. **Shipping System Files**
```
includes/shipping.php
```
**Changes:** Removed duplicate POST processing code
- Cleaned up file to only contain `calculateShippingCost()` function

### 3. **AJAX Endpoint Files**
```
public/ajax/calculate_shipping.php
```
**Changes:** Major enhancements for clean JSON output
- Added output buffering (`ob_start`, `ob_clean`)
- Suppressed PHP warnings for production
- Enhanced error handling with try-catch blocks
- Input validation improvements

### 4. **Checkout System Files**
```
public/checkout.php
public/checkout_0.php
public/checkout_00.php
```
**Changes:** Improved JavaScript AJAX error handling
- Added HTTP status code checking
- Added JSON parsing error protection
- Added fallback to default shipping (30 AED) on failures
- Better console logging for debugging

## 🆕 New Files (Optional - For Testing)

### Testing & Diagnostic Tools
```
public/ajax/ajax_test_simple.php
test_ajax_shipping_live.html
diagnostic_shipping.php
AJAX_SHIPPING_FIX_DOCUMENTATION.md
FILES_TO_UPLOAD_PRODUCTION.md (this file)
```
**Note:** These are testing/diagnostic files and not required for production

## 📋 Upload Priority

### **CRITICAL (Upload First):**
1. `config/config.php` - Fixes duplicate constant warnings
2. `public/ajax/calculate_shipping.php` - Core AJAX endpoint fix
3. `includes/shipping.php` - Cleaned shipping function

### **IMPORTANT (Upload Second):**
4. `public/checkout.php` - Main checkout page
5. `public/checkout_0.php` - Checkout backup version
6. `public/checkout_00.php` - Checkout backup version

### **OPTIONAL (Testing Only):**
7. `public/ajax/ajax_test_simple.php` - Simple test endpoint
8. `test_ajax_shipping_live.html` - Live testing tool
9. `diagnostic_shipping.php` - Diagnostic script

## 🚀 Deployment Steps

### Step 1: Backup Current Files
Before uploading, backup these production files:
- `config/config.php`
- `includes/shipping.php`
- `public/ajax/calculate_shipping.php`
- `public/checkout.php`

### Step 2: Upload in Order
1. **Upload config files first:**
   ```
   config/config.php
   ```

2. **Upload core shipping files:**
   ```
   includes/shipping.php
   public/ajax/calculate_shipping.php
   ```

3. **Upload checkout pages:**
   ```
   public/checkout.php
   public/checkout_0.php
   public/checkout_00.php
   ```

### Step 3: Test After Upload
1. Test checkout page shipping calculation
2. Check browser console for errors
3. Verify clean JSON responses (no PHP warnings)

## 🧪 Quick Test Commands

After upload, test these scenarios:
1. **UAE Shipping:** Should show "30.00 AED"
2. **Oman Shipping:** Should calculate correctly based on weight
3. **Error Handling:** Should fallback to "30.00 AED" if API fails

## ⚠️ Rollback Plan

If issues occur, restore these backup files:
- `config/config.php` (most critical)
- `public/ajax/calculate_shipping.php`
- `includes/shipping.php`

The JavaScript changes in checkout files are safe improvements and shouldn't cause issues.

## 📊 Expected Results

**Before Fix:**
```
Raw response: <br /><b>Warning</b>: Constant CURRENCY_SYMBOL already defined...{"success":true...}
Result: JSON parse error, "Calculating..." persists
```

**After Fix:**
```
Raw response: {"success":true,"country":"United Arab Emirates","city":"Dubai","totalWeight":2.5,"shippingAED":"30.00","shippingCost":30,"currency":"AED","timestamp":"2025-11-05 02:50:40"}
Result: Clean JSON, shipping cost displays correctly
```