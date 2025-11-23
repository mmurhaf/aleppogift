# Quick Guide: Shipping & Discount Features

## 🎯 What's New?

### 1️⃣ Editable Shipping with Auto-Recalculate
### 2️⃣ Discount & Coupon System

---

## 📍 Where to Find It

```
admin/regenerate_invoice.php
│
├── Order Items Section
│   └── Totals Table
│       ├── Subtotal: AED XX.XX
│       ├── Shipping: AED XX.XX [Recalculate] ⬅️ NEW BUTTON
│       ├── Discount: - AED XX.XX ⬅️ NEW ROW (if discount applied)
│       └── Grand Total: AED XX.XX
│
├── Order Details Section
│   ├── Shipping Cost (AED) ⬅️ NOW EDITABLE WITH MANUAL OVERRIDE
│   ├── Grand Total (AED) ⬅️ AUTO-CALCULATED
│   ├── Payment Method
│   └── Payment Status
│
└── Discount & Coupon Section ⬅️ NEW SECTION
    ├── Coupon Code
    ├── Discount Type (Fixed/Percent)
    ├── Discount Value
    └── Discount Amount (calculated)
```

---

## 🚚 How to Use: Shipping

### Option 1: Manual Entry
```
1. Go to "Order Details" section
2. Find "Shipping Cost (AED)" field
3. Enter your custom amount (e.g., 45.00)
4. Grand total updates automatically ✓
```

### Option 2: Auto-Calculate
```
1. Scroll to "Order Items" table
2. Find shipping row in totals
3. Click [Recalculate] button
4. System calculates based on:
   - Customer's country & city
   - Total order weight
   - Standard shipping rates
5. Shipping updates automatically ✓
```

---

## 🏷️ How to Use: Discount

### Fixed Amount Discount
```
Example: Give AED 50 discount

1. Go to "Discount & Coupon" section
2. Coupon Code: SAVE50 (optional)
3. Discount Type: Fixed Amount (AED)
4. Discount Value: 50
5. Discount Amount: 50.00 (auto-calculated) ✓
6. Grand Total: Updates with -50 AED ✓
```

### Percentage Discount
```
Example: Give 10% discount on AED 500 order

1. Go to "Discount & Coupon" section
2. Coupon Code: DEAL10 (optional)
3. Discount Type: Percentage (%)
4. Discount Value: 10
5. Discount Amount: 50.00 (auto-calculated from 10% of 500) ✓
6. Grand Total: Updates with -50 AED ✓
```

---

## 🧮 Calculation Examples

### Example 1: No Discount
```
Subtotal:     AED 500.00
Shipping:     AED  30.00
--------------------------
Grand Total:  AED 530.00
```

### Example 2: Fixed Discount
```
Subtotal:     AED 500.00
Shipping:     AED  30.00
Discount:     AED -50.00 (SAVE50)
--------------------------
Grand Total:  AED 480.00
```

### Example 3: Percentage Discount
```
Subtotal:     AED 500.00
Shipping:     AED  30.00
Discount:     AED -50.00 (10% of 500)
--------------------------
Grand Total:  AED 480.00
```

### Example 4: Heavy Order with Recalculated Shipping
```
Products:     5 items @ 3kg each = 15kg total
Customer:     Germany

Action: Click [Recalculate]
Result: Shipping = 220 + (14 × 70) = AED 1,200

Subtotal:     AED 1,000.00
Shipping:     AED 1,200.00 (auto-calculated)
Discount:     AED  -100.00 (10%)
--------------------------
Grand Total:  AED 2,100.00
```

---

## 💡 Pro Tips

### Shipping:
- ✅ Use [Recalculate] for standard rates
- ✅ Manual entry for special deals
- ✅ Shipping updates when products added/removed
- ✅ Based on customer's saved country/city

### Discount:
- ✅ Use "Fixed" for flat discounts (e.g., 50 AED off)
- ✅ Use "Percent" for promotional rates (e.g., 10% off)
- ✅ Coupon code is optional but recommended for tracking
- ✅ Percentage applies to subtotal (before shipping)
- ✅ Discount shows in invoice if > 0

### Both:
- ✅ All changes save when you click "Update All & Regenerate Invoice"
- ✅ Real-time preview before saving
- ✅ Totals update automatically as you type
- ✅ Visual highlights show what changed

---

## ⚠️ Important Notes

1. **Discount applies to subtotal BEFORE shipping**
   - Not affected by shipping changes
   - Only recalculates if you change discount type/value

2. **Shipping can be overridden**
   - Manual entry takes precedence
   - Click [Recalculate] to use auto-calculation

3. **Grand Total formula:**
   ```
   Subtotal + Shipping - Discount = Grand Total
   ```

4. **All fields save on form submission**
   - Don't forget to click "Update All & Regenerate Invoice"
   - Changes won't save without submitting

---

## 🎨 Visual Indicators

| Element | Color/Style | Meaning |
|---------|-------------|---------|
| Yellow background | #fff3cd | Auto-calculated (read-only) |
| White background | #ffffff | Editable field |
| Warning section | Yellow header | Important: Discount section |
| Blue button | Outline | Action button (Recalculate) |
| Green total | Success color | Final grand total |
| Red text | Danger color | Discount amount (negative) |

---

## ✅ Quick Checklist

Before submitting the form:

- [ ] Shipping cost correct? (manual or recalculated)
- [ ] Discount type selected? (if applying discount)
- [ ] Discount value entered? (if applying discount)
- [ ] Discount amount looks correct?
- [ ] Grand total looks correct?
- [ ] Coupon code entered? (optional)
- [ ] All other order details correct?
- [ ] Ready to click "Update All & Regenerate Invoice"

---

## 🔄 Workflow Summary

```
1. Load order → Shows current values
2. Edit shipping → Grand total updates
3. Add discount → Grand total updates
4. Add/remove products → All totals update
5. Click Recalculate → Shipping updates
6. Review totals → Verify accuracy
7. Submit form → Saves to database
8. View invoice → See updated totals
```

---

**Quick Access:** `admin/regenerate_invoice.php?id={order_id}`
