# Admin Order Creation - Quick Start Guide

## 🚀 Quick Access

**Admin Panel URL:** `https://aleppogift.ae/public/admin/create_order.php`

**Navigation:**
- Dashboard → Sidebar → "Create Order"
- Orders Page → "Create Order" button

---

## 📋 Step-by-Step Guide

### Step 1: Select Customer
**Option A - Existing Customer:**
1. Type customer name, email, or phone in search box
2. Click on customer from dropdown
3. Customer details will appear in preview

**Option B - New Customer:**
1. Click "Create New Customer" button
2. Fill in required fields:
   - Full Name ✓
   - Email ✓
   - Phone ✓
   - City/Emirate ✓
   - Address ✓
3. Customer will be created when order is submitted

---

### Step 2: Add Products
1. Search product by name or SKU
2. Click product to add to order
3. Use +/- buttons to adjust quantity
4. Click trash icon to remove product
5. Add as many products as needed

---

### Step 3: Configure Shipping
1. Select shipping method:
   - Standard Shipping
   - Express Shipping
   - Store Pickup
2. Enter shipping cost (AED)
3. Cost auto-updates in summary

---

### Step 4: Set Payment Details
1. Choose payment method:
   - Cash on Delivery (COD)
   - Online Payment
   - Bank Transfer
2. Select payment status:
   - Pending (default)
   - Paid
   - Failed
3. Add order notes (optional)

---

### Step 5: Create Order

**Option A - Create Order Only:**
- Click "Create Order Only"
- Order created without email notification
- Use for paid orders or internal orders

**Option B - Send Payment Link:**
- Click "Create Order & Send Payment Link"
- Order created + Email sent to customer
- Includes PDF invoice
- Payment link valid for 7 days

---

## ✉️ What Customers Receive

### Payment Link Email Includes:
- ✅ Order number and details
- ✅ "Complete Payment Now" button
- ✅ Secure payment link
- ✅ Invoice PDF (if supported)
- ✅ Contact information
- ✅ 7-day validity notice

### Payment Page Features:
- Customer information display
- Product list with images
- Total amount breakdown
- Invoice download link
- Payment method selection (COD/Online)
- Secure token validation

---

## 🎯 Use Cases

### 1. Phone Orders
Customer calls → Admin creates order → Sends payment link

### 2. WhatsApp Orders
Customer messages → Admin creates order → Shares payment link

### 3. Pre-Orders
Create order → Mark as pending → Send payment link when ready

### 4. B2B Orders
Create order for business customer → Bank transfer method

### 5. Walk-in Customers
Create order → Mark as paid → Print invoice

---

## 💡 Tips & Best Practices

### Customer Management:
- ✓ Always search before creating new customer
- ✓ Check email duplicates
- ✓ Use correct UAE emirate names
- ✓ Include complete address

### Product Selection:
- ✓ Verify stock availability
- ✓ Check product prices
- ✓ Confirm quantities with customer
- ✓ Review total before submitting

### Shipping:
- ✓ Confirm shipping method with customer
- ✓ Calculate accurate shipping cost
- ✓ Consider weight for international orders
- ✓ Use "Store Pickup" for local customers

### Payment:
- ✓ Set correct payment status
- ✓ Use COD for cash orders
- ✓ Online for card payments
- ✓ Bank Transfer for bulk orders

### Notes:
- ✓ Add special instructions
- ✓ Note customer preferences
- ✓ Record delivery timing
- ✓ Mark urgent orders

---

## 🔒 Security Features

- **CSRF Protection** - All forms protected
- **Admin Authentication** - Login required
- **Secure Tokens** - SHA256 encrypted payment links
- **Token Expiration** - 7-day validity
- **Stock Validation** - Prevents overselling
- **Transaction Safety** - Atomic database operations

---

## ⚡ Quick Reference

### Keyboard Shortcuts (Future):
- Search customer: Focus search box
- Add product: Quick search
- Create order: Submit form

### Status Indicators:
- 🟢 Green badge = Selected customer
- 🔵 Blue summary = Real-time calculations
- 🟡 Yellow = Items in cart

### Action Buttons:
- **Primary (Blue)** = Send Payment Link
- **Success (Green)** = Create Order Only
- **Danger (Red)** = Remove Product
- **Secondary (Gray)** = Cancel/Back

---

## 🛠️ Troubleshooting

### Issue: Customer not found
**Solution:** Create new customer or check spelling

### Issue: Product out of stock
**Solution:** Remove product or reduce quantity

### Issue: Email not received
**Solution:** Check spam folder, verify email address

### Issue: Payment link expired
**Solution:** Create new order or contact admin

### Issue: Cannot add product
**Solution:** Check product is active and has stock

---

## 📊 Order Summary Panel

**Real-time Display:**
- Subtotal (all products)
- Shipping cost
- Item count
- Total amount

**Updates When:**
- Product added/removed
- Quantity changed
- Shipping cost modified

---

## 🎨 UI Features

### Customer Section:
- Real-time search dropdown
- Customer preview card
- Create new customer form

### Products Section:
- Live product search
- Product images
- Stock indicator
- Quantity controls
- Remove buttons

### Summary Section:
- Sticky panel (stays visible)
- Gradient design
- Clear totals
- Payment info

---

## 📞 Support Information

**Admin Help:**
- Check documentation: `ADMIN_ORDER_CREATION_SYSTEM.md`
- View order details after creation
- Edit order if needed in Orders page
- Regenerate invoice if required

**Customer Support:**
- Email: info@aleppogift.ae
- Phone: +971 56 112 5320
- Website: https://aleppogift.ae

---

## ✅ Pre-Launch Checklist

Before creating first order:
- [ ] Test with sample customer
- [ ] Verify email delivery
- [ ] Check payment link works
- [ ] Confirm invoice generation
- [ ] Test stock updates
- [ ] Review email template
- [ ] Verify all payment methods
- [ ] Test on mobile device

---

## 🎓 Training Points

### For Admin Staff:
1. How to search customers efficiently
2. Creating new customers correctly
3. Product search best practices
4. Shipping cost calculation
5. When to send payment links
6. How to verify order details
7. Handling customer questions
8. Processing phone orders

### Common Scenarios:
- Repeat customer ordering
- New customer first order
- Bulk order processing
- Special delivery requests
- Discount applications
- Payment issues
- Order modifications

---

## 📈 Success Metrics

### Track:
- Orders created per day
- Payment links sent
- Email delivery rate
- Payment link conversion
- Average order value
- Customer satisfaction

### Monitor:
- Order creation errors
- Email failures
- Stock issues
- Payment link expiry
- Customer feedback

---

## 🔄 Workflow Diagram

```
Admin Login
    ↓
Navigate to Create Order
    ↓
Search/Create Customer
    ↓
Add Products to Order
    ↓
Configure Shipping & Payment
    ↓
Choose Action:
    ├→ Create Order Only → Order Created → Done
    └→ Send Payment Link → Order Created → Email Sent → Customer Receives Link
                                ↓
                        Customer Visits Payment Page
                                ↓
                        Selects Payment Method
                                ↓
                        Completes Payment
                                ↓
                        Order Updated → Done
```

---

## 📝 Notes

- All orders create PDF invoices automatically
- Stock levels update immediately on order creation
- Payment links expire after 7 days
- Orders can be edited from Orders page
- Invoices can be regenerated if needed
- Customer information can be updated later

---

## 🌟 Advanced Features

### Coming Soon:
- Barcode scanning
- Product recommendations
- Customer order history
- Quick reorder
- Bulk import
- WhatsApp integration
- SMS notifications
- Payment gateway integration

---

**Need Help?** Contact the development team or refer to the full documentation in `ADMIN_ORDER_CREATION_SYSTEM.md`
