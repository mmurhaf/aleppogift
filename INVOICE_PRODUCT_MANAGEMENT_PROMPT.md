# Prompt: Add Product Management Functionality to Invoice Regeneration Page

## **Objective**
Enhance `admin/regenerate_invoice.php` to allow administrators to dynamically add and remove products from an order's product list with real-time total and shipping calculations.

---

## **Requirements**

### **1. Product List UI Enhancements**
In the existing "Order Items" card section (currently read-only table):

**Add to each product row:**
- **Minus button** (🗑️ or `-`) - Remove product from order
- **Plus button** (`+`) - Increase quantity by 1
- **Minus button** (`-`) - Decrease quantity by 1 (minimum 1)
- **Quantity input field** - Manual quantity entry with validation

**Add at the top of product list:**
- **"Add Product" button** with plus icon (`+`)
- When clicked, show a new row with:
  - **Product dropdown/select control** - Populated from `products` table (active products only)
  - **Quantity input** - Default value: 1, minimum: 1
  - **"Add" button** - Confirm addition
  - **"Cancel" button** - Hide the add row

---

### **2. AJAX Functionality**

**Create JavaScript functions for:**

1. **Add Product**
   ```javascript
   - Fetch all active products via AJAX endpoint
   - Display in select dropdown with product name and price
   - On confirm: Add to order_items table via AJAX
   - Recalculate totals
   - Update UI with new product row
   ```

2. **Remove Product**
   ```javascript
   - Show confirmation dialog
   - Delete from order_items table via AJAX
   - Recalculate totals
   - Remove row from UI with animation
   ```

3. **Update Quantity**
   ```javascript
   - Validate quantity (min: 1, max: available stock)
   - Update order_items table via AJAX
   - Recalculate totals
   - Update row display with new subtotal
   ```

---

### **3. Backend AJAX Endpoints**

Create new PHP file: `admin/ajax/manage_order_products.php`

**Actions to handle:**

```php
// Action: 'add_product'
- Parameters: order_id, product_id, quantity
- Validation: Check product exists, is active, has stock
- Check for variation support (if applicable)
- Insert into order_items table
- Return: success status, new item data, updated totals

// Action: 'remove_product'
- Parameters: order_id, product_id (or order_item_id)
- Delete from order_items table
- Return: success status, updated totals

// Action: 'update_quantity'
- Parameters: order_id, product_id, new_quantity
- Validation: Check stock availability
- Update order_items table
- Return: success status, updated totals

// Action: 'get_products'
- Return: JSON array of active products with id, name_en, price, stock

// Action: 'calculate_totals'
- Parameters: order_id
- Use existing cart logic from includes/helpers/cart.php
- Calculate subtotal using getCartTotalAndWeight() logic
- Calculate shipping using calculateShippingCost() from includes/shipping.php
- Return: {subtotal, shipping, total, weight}
```

---

### **4. Calculation Logic**

**Use existing functions from the checkout system:**

1. **Cart Total Calculation** - Based on `includes/helpers/cart.php`:
   ```php
   - Sum of (product.price * quantity) for all order_items
   - Add variation.additional_price if variation_id exists
   - Calculate total weight: Sum of (product.weight * quantity)
   ```

2. **Shipping Calculation** - Based on `includes/shipping.php`:
   ```php
   - Use calculateShippingCost($country, $city, $totalWeight)
   - Get country and city from customer or order data
   - Apply the same logic as checkout page:
     * UAE: 30 AED (60 AED for Al Gharbia)
     * Oman: 70 AED + 10 AED per kg above 5kg
     * GCC: 120 AED + 30 AED per additional kg (8kg parcels)
     * Europe: 220 AED + 70 AED per additional kg
     * Other: 300 AED + 80 AED per additional kg
   ```

3. **Grand Total**:
   ```php
   $grandTotal = $subtotal + $shipping - $discount
   ```

---

### **5. Real-Time Updates**

**When any product change occurs:**
1. Recalculate order items subtotal
2. Recalculate total weight
3. Recalculate shipping cost based on weight and location
4. Update grand total
5. Update ALL affected fields in the UI:
   - Order items table (quantities, subtotals, row total)
   - Subtotal display
   - Shipping cost display
   - Grand total display
   - Update hidden form fields: `shipping_aed`, `total_amount`

**Display updates with:**
- Loading spinner during AJAX calls
- Success/error toast notifications
- Smooth animations for row additions/removals
- Highlight changed values (fade effect)

---

### **6. UI/UX Enhancements**

**Visual Design:**
- Use Bootstrap 5 buttons with icons (Font Awesome)
- Color scheme:
  - Add: `btn-success` (green)
  - Remove: `btn-danger` (red)
  - Increase: `btn-outline-primary`
  - Decrease: `btn-outline-secondary`
- Add hover effects and tooltips
- Disable buttons during AJAX processing
- Show loading states with spinners

**Product Selection:**
- Use Select2 or Bootstrap select for better UX
- Include product image thumbnails (if available)
- Show price and stock status in dropdown
- Filter out products already in order
- Search functionality for large product lists

**Validation Feedback:**
- Stock availability warnings
- Quantity range indicators
- Error messages for failed operations
- Confirmation dialogs for destructive actions

---

### **7. Database Updates**

**Ensure order table updates after each change:**
```sql
UPDATE orders SET 
    total_amount = :new_total,
    shipping_aed = :new_shipping,
    updated_at = NOW()
WHERE id = :order_id
```

**Track changes in order_items:**
```sql
-- Add audit fields if needed
ALTER TABLE order_items ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

---

### **8. Security Considerations**

- **CSRF Protection**: Validate `$_SESSION['csrf_token']` on all AJAX requests
- **Admin Authentication**: Verify `require_admin_login()` before any operation
- **Input Validation**: Sanitize all POST parameters
- **SQL Injection**: Use prepared statements with PDO
- **Permission Checks**: Ensure order can be modified (not delivered/cancelled)

---

### **9. File Structure**

**New/Modified Files:**
```
admin/
├── regenerate_invoice.php (MODIFY - Add product management UI)
└── ajax/
    └── manage_order_products.php (CREATE - Handle AJAX operations)

includes/
├── helpers/
│   └── cart.php (REFERENCE - Use existing functions)
└── shipping.php (REFERENCE - Use calculateShippingCost())
```

---

### **10. Testing Checklist**

- [ ] Add new product to order - verify insertion and total update
- [ ] Remove product from order - verify deletion and recalculation
- [ ] Increase/decrease quantity - verify updates
- [ ] Manual quantity input - validate bounds
- [ ] Product out of stock - show error
- [ ] Duplicate product - prevent or merge quantities
- [ ] Shipping recalculation - verify accuracy for different countries
- [ ] Form submission - verify all changes persist
- [ ] Edge cases: empty cart, single product, max weight

---

### **11. Code Example Structure**

**JavaScript Skeleton:**
```javascript
function addProductRow() { /* Show add product form */ }
function confirmAddProduct(productId, quantity) { /* AJAX add */ }
function removeProduct(orderItemId) { /* Confirm & AJAX remove */ }
function updateQuantity(orderItemId, newQuantity) { /* AJAX update */ }
function recalculateTotals(orderData) { /* Update UI */ }
function showNotification(message, type) { /* Toast notification */ }
```

**PHP AJAX Response Format:**
```php
echo json_encode([
    'success' => true/false,
    'message' => 'Operation completed',
    'data' => [
        'subtotal' => 150.00,
        'shipping' => 30.00,
        'total' => 180.00,
        'weight' => 5.0,
        'items' => [ /* updated order items array */ ]
    ]
]);
```

---

## **Expected Result**
A fully functional product management interface within the invoice regeneration page that:
- Allows adding products from a searchable dropdown
- Enables quantity adjustments with instant feedback
- Supports product removal with confirmation
- Automatically recalculates totals and shipping using the same logic as cart/checkout
- Updates the database and UI in real-time
- Maintains data consistency between order_items and orders tables
- Provides a smooth, professional admin experience

---

## **Implementation Notes**

### **Key Files Reference:**
- `admin/regenerate_invoice.php` - Main file to modify
- `includes/helpers/cart.php` - Contains `getCartTotalAndWeight()` function
- `includes/shipping.php` - Contains `calculateShippingCost()` function
- `public/checkout.php` - Reference for calculation flow

### **Database Tables:**
- `orders` - Main order record (total_amount, shipping_aed)
- `order_items` - Individual products in order (product_id, quantity, price)
- `products` - Product master data (name_en, price, weight, stock, status)
- `product_variations` - Product variations (additional_price, stock)
- `customers` - Customer data (country, city for shipping calculation)

### **Important Considerations:**
1. Maintain consistency with existing cart/checkout logic
2. Handle product variations if they exist in the order
3. Recalculate shipping based on customer's country and total weight
4. Update both order_items and orders table on every change
5. Provide clear feedback for all operations
6. Ensure admin-only access with proper authentication
7. Use existing database connection and helper functions

---

This prompt provides complete specifications for implementing the requested functionality while maintaining consistency with the existing cart and checkout system logic.
