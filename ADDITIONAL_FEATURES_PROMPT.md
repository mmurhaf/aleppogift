# More features to add

Add the features below to the existing Aleppo Gift PHP store. Keep the current page-per-file layout (`public/*.php`, `public/admin/*.php`, `public/ajax/*.php`, `includes/`). Do not introduce a router or a second cart. The cart stays `$_SESSION['cart']`. Prices stay stored and charged in AED. The header currency switcher in `includes/header.php` and `includes/currencies.php` is display-only; do not change stored prices, order totals, invoices, or Ziina charges. Save store options in `site_settings` through `get_site_setting()` in `includes/functions.php`. Add admin sidebar items in `public/admin/includes/header.php`. Put schema changes in a new SQL file under `database/`. Reuse the current admin form style, CSRF checks, Bootstrap 5, and Font Awesome. Product text stays bilingual (`name_en` / `name_ar`, `description_en` / `description_ar`).

Do not rebuild what already works: products, categories, brands, bundles, coupons, Ziina, cash on delivery, shared carts, invoices, quotations, reports, analytics, testimonials, trust badges, bulk price edit, bulk image upload, merge products, the currency page, or the email client.

---

## 1. Store settings the other features read

Extend `public/admin/settings.php`. Keep the existing Profile, Site, System, and Maintenance tabs. Add the fields below and read them everywhere they are named. Blank secret fields keep the current value.

- **Store:** free-shipping threshold (default 500 AED), low-stock alert quantity, products per page. `show_prices` already exists.
- **VAT:** Charge VAT off by default. Rate defaults to 5%. TRN is stored and printed only after Charge VAT is on.
- **Gift wrap:** fee per box, default 40 AED. Zero means free wrapping. Checkout multiplies the fee by the number of units.
- **Same-day delivery:** fee for Dubai, Abu Dhabi, and Al Ain, stored as a setting so checkout and the shipping page use the same number.
- **Contact and social:** phone, WhatsApp, address, and social profile URLs.
- **SEO:** English and Arabic meta title and description, Google Analytics id.
- **Appearance:** logo and favicon uploads under `uploads/settings/`. The header and invoices use the uploaded logo when one exists, otherwise the current logo.
- **Email:** SMTP host, port, encryption, username, password, from address, from name, and admin notification address. `public/admin/email_client.php` reads these instead of hardcoded IMAP and SMTP constants. Password fields stay unchanged when submitted empty.
- **Payment:** Ziina API key, sandbox flag, and webhook secret, used by the existing Ziina checkout.

---

## 2. Shipping rules and the admin rate card

`includes/shipping.php` already quotes UAE (30 AED, Al Gharbia 60), Oman, GCC, Europe, and the rest of the world by weight. Change the quote, do not replace the zone table.

- UAE orders whose merchandise total is at or above the free-shipping threshold ship free. Other countries never do. The threshold applies before gift wrap and VAT.
- Same-day delivery is an optional checkout choice only when the country is the UAE and the city is Dubai, Abu Dhabi, or Al Ain. It replaces the standard UAE rate with the same-day fee from settings. It is not free when the order crosses the free-shipping threshold.
- `public/cart.php` and `public/checkout.php` already call the shipping calculator. They must show the free-shipping result and the same-day choice.
- Update `public/shipping.php` so the page matches the calculator, including the free UAE threshold, same-day cities, and a note that a non-AED display currency is approximate and the order is charged in AED.
- Add `public/admin/shipping.php` as a read-only rate card under Tools: each zone, the free-shipping rule, the same-day rule, and three example quotes (country, city, weight, zone, cost) computed by `calculateShippingCost()`.

---

## 3. Gift checkout, VAT, packing slip

On `public/checkout.php`, add an optional gift-order block:

- Recipient name and phone, card message, gift wrap checkbox, and “hide prices on the packing slip”.
- Gift wrap adds `fee × unit count` to the total. Show it as its own summary line.
- VAT is added only when Charge VAT is on. It is calculated on merchandise after bundle and coupon discounts, plus shipping and gift wrap. With Charge VAT off, checkout, invoices, and PDFs omit the VAT line and the TRN.
- When the visitor’s display currency is not AED, the summary states that converted prices are approximate and payment is charged in AED.
- Save recipient, phone, card message, wrap flag, wrap amount, hide-prices flag, VAT rate, VAT amount, and TRN on the order.

Invoices (`includes/generate_invoice_pdf.php` and the HTML invoice) gain a gift-wrap line when wrap was charged, and a VAT line plus TRN only when that order has a tax amount.

Add a packing slip from the admin order page (`public/admin/order_detail.php` and the invoice editor): PDF of the same line items. It omits payment status. Prices are hidden only when the customer asked to hide them. Gift orders also show recipient, phone, card message, and whether wrap was requested.

---

## 4. Stock: reserve, release, and block

`public/checkout.php` currently subtracts `products.stock` while the order is saved. Change that.

- A cash-on-delivery order reserves stock when the order is saved.
- A Ziina order does not reserve stock until payment is confirmed. The confirmation path is `public/thankyou.php` (it marks the order paid) and `public/payment.php`. Reserve there, once. A second visit must not subtract again.
- Checkout still refuses the order when the product, or the chosen variation, does not have enough stock for the cart plus what is already reserved.
- In `public/admin/orders.php`, cancelling or refunding an order that holds stock releases it. Moving that order back to an active status (`pending` or `paid`) reserves it again and is blocked when stock is not enough.
- Record on the order whether stock is currently reserved so the release and re-reserve each run once.

---

## 5. Shipment email

`public/admin/edit_invoice.php` already stores courier, tracking number, shipment status, and shipped date. When the status becomes shipped, or the tracking number changes on an order that is already shipped, email the customer with the courier, tracking number, and a link to track the order. Do not send that email for any other status change. Use the existing mail helpers in `includes/send_email_simple.php`.

---

## 6. Language switcher

Product, category, and brand text is already stored in English and Arabic. The storefront UI is English only.

- Add `public/set_language.php` for `en` and `ar`. Store the choice in the session and return the visitor to the previous page.
- Add the switcher to `includes/header.php` next to the currency switcher.
- Arabic renders the page `dir="rtl"`. Product names, descriptions, category names, brand names, and the shared UI strings (cart, checkout, buttons, empty states, shipping page) follow the active language.
- Invoices and PDFs keep both English and Arabic product names, as they do now.

---

## 7. Shop filters, sorting, and the visit mix

`public/products.php` filters by category, brand, and name. Sorts are featured, newest, and on-sale. Extend that page and the homepage product grid in `public/index.php`.

- Search matches `name_en`, `name_ar`, and SKU.
- Price range uses `min_price` and `max_price` in AED.
- Sorts: For you, newest, price low to high, price high to low, best rated, best seller. Best rated uses the average of approved reviews. Best seller uses units sold on non-cancelled orders.
- For you is the default. Each visitor gets a stable random order for the visit, stored in the session. After 30 idle minutes the next visit gets a new order. Shop, category, brand, search, related products on `public/product.php`, and the category menu use that order. Choosing any other sort replaces it.
- Filters stay in the query string and combine.
- Product cards show a sale badge when the product discount is greater than zero, an out-of-stock badge when stock is zero, and the star average plus review count when the product has approved reviews.

---

## 8. Reviews on the product page

Admin review moderation already lives in `public/admin/reviews.php`, including approve, reject, delete, and `admin_response`. `public/submit_review.php` and `public/mark_helpful.php` exist. `public/product.php` does not show any of this.

- On the product page, list approved reviews: stars, reviewer name, text, date, and the admin response when one exists.
- Show a 5-to-1 star distribution with percentage bars.
- Write-a-review form: name, email, rating 1–5, text. Save as unapproved. Approving or rejecting in admin recalculates that product’s average rating and review count.
- Mark as helpful posts to `public/mark_helpful.php` and increments `helpful_count` without a login. One vote per review per session.
- Keep the existing JSON-LD product schema and add the review aggregate when the product has approved reviews.

---

## 9. Wishlist

`public/assets/js/storefront.js` toggles a heart in `localStorage` and shows a toast. Nothing is stored on the server.

- Guests store product IDs in the session. Signed-in customers use a `wishlists` table, unique per customer and product.
- `POST public/ajax/wishlist_toggle.php` adds or removes one product and returns JSON. The heart buttons on `public/index.php`, `public/products.php`, and `public/product.php` call it. A normal form post works when JavaScript is off.
- `public/wishlist.php` lists the current guest or customer wishlist. Signed-in customers also reach it from the account page.
- On login or registration, copy the guest IDs onto the account and clear the guest list. Logout clears the customer session and the guest wishlist.

---

## 10. Customer accounts

There is no storefront login. Admin customer records in `public/admin/customers.php` stay as they are. Add account pages and link them from `includes/header.php`.

- `public/register.php`: name, email, phone, password, confirmation. Password at least 8 characters. Duplicate emails are rejected. Session is regenerated after registration.
- `public/login.php`: email and password. Session is regenerated after login.
- `public/logout.php`: clears the customer session.
- `public/forgot_password.php` emails a one-hour reset link. Store the token as a hash. `public/reset_password.php` sets the new password. The account profile can change the password after the current password is confirmed.
- `public/account.php`: counts for orders, paid spend, wishlist items, and reviews, plus recent orders.
- `public/account_orders.php` and `public/account_order.php`: paginated orders and one order. The detail page opens only an order whose email matches the signed-in customer.
- `public/account_profile.php`: update name and phone.
- `public/account_addresses.php`: add and delete saved addresses (label, name, phone, two address lines, city, state, country, postal code, default flag).
- Checkout accepts a guest. A signed-in customer can pick a saved address. The form fills from that address, and the server uses it when a required field was left empty.
- Every account page redirects to login when no customer is signed in.

---

## 11. Order tracking

Add `public/track_order.php` and a footer link.

- Form: order number and the shipping email used at checkout.
- A match shows payment status, shipment status, courier, tracking number, and estimated delivery when those fields exist.
- A mismatch or a missing field says the order was not found. Do not say which of the two values was wrong.
- The shipment email in section 5 links here.

---

## 12. Contact messages

`public/process_contact.php` emails `info@aleppogift.com` and does not store the message.

- Save every submission in `contact_messages`: name, email, phone, subject, message, IP, read flag, emailed flag, created time.
- Send the notification with the visitor’s address as Reply-To. If mail fails, keep the row and set the emailed flag to false.
- Add `public/admin/contact_messages.php`: 30 per page, unread count, open marks the row read, delete removes it. Show name, email, subject, body, IP, emailed flag, and time. Put it under Content in the admin sidebar.

---

## 13. Newsletter admin and unsubscribe

`public/ajax/subscribe_newsletter.php` inserts an email into `newsletter_subscribers`. Extend that table with status (`active` / `unsubscribed`), a token, and subscribed-at. Do not drop existing rows.

- An existing unsubscribed address is reactivated. An address that is already active gets “you are already subscribed”.
- `public/newsletter_unsubscribe.php` takes the email and token and sets status to unsubscribed.
- Add `public/admin/newsletter.php` under Content:
  - Subscriber list, 30 per page, with delete.
  - Export active subscribers as CSV.
  - Campaigns: name, subject, HTML body, optional send time. Status is `scheduled` when a time is set, otherwise `draft`.
  - Send delivers the campaign to every active subscriber through the branded mail layout, appends that subscriber’s unsubscribe link, and stores `sent_at` and `sent_count`. A campaign that reaches nobody stays unsent and the page tells the admin to check mail settings.

---

## 14. Gallery moderation

`public/customer_gallery.php` lists approved rows from `customer_photos`. There is no admin page for them.

- Add `public/admin/gallery.php` under Content.
- List submissions with the photo, customer name, linked product, type, and status.
- Approve a photo so it appears on the gallery. Feature a photo for the homepage. Delete a photo and its file.
- The homepage may show up to four featured approved photos. Privacy-masked images stay blurred, using the same blur flag pattern as testimonials.

---

## 15. Admin users

Login is a single admin account. `public/admin/create_admin.php` is a setup script, not a user manager.

- Add `public/admin/users.php`, visible only to `super_admin`.
- List username, name, email, role, and active flag.
- Create, edit, and delete users. Password is bcrypt and at least 8 characters. Roles: `super_admin`, `admin`, `manager`. Leaving the password blank on edit keeps the current hash.
- A user cannot delete their own account. The last active `super_admin` cannot be deleted or deactivated.
- Existing `require_admin_login()` still protects every admin page. Role checks apply only where this prompt names them.

---

## 16. Bulk delete on the product list

`public/admin/orders.php` can bulk-delete orders. `public/admin/products.php` cannot bulk-delete products.

- Add checkboxes and a delete-selected action with a confirm step.
- Delete the selected products’ image records and files, then the products.
- Skip a product that is referenced by an order item, and say which ones were skipped.

---

## 17. Supplier fields and draft import

Published products stay on the storefront. Add supplier columns on `products` when they are missing: `supplier_name`, `supplier_url`, `supplier_sku`, `availability`, `listing_status` (`draft` or `published`), `import_batch_id`, `import_notes`. Existing products are `published`. Drafts are excluded from the shop, search, related products, homepage, and cart add.

- On `public/admin/edit_product.php`, those fields are editable.
- Add `public/admin/supplier_import.php` under Catalog.
  - CSV template download. Files must be `.csv` and 2 MB or smaller, up to 500 rows. Fields: markup percent (default 80), default supplier name, default supplier URL.
  - Required columns: `product_name`, `supplier_cost`. Optional: `currency` (blank means AED), `selling_price` (AED; blank uses the markup), `category`, `brand`, `image_url` (stored as a link, not downloaded), `availability` or `stock`, `supplier`, `supplier_url`, `supplier_sku`, `description`, `name_ar`.
  - Category and brand must already exist. Unknown names are left blank and are not created.
  - The same supplier plus the same supplier SKU is skipped.
  - Link import: paste a public catalog URL or a single product URL. Read up to the chosen max (100) and follow pagination up to 8 pages. Price role Auto treats DubGift, Krmeen, and Saleto prices as the selling price. Every other site is supplier cost, and the markup sets the selling price. The admin can force Selling price or Supplier cost. Convert foreign amounts to AED with the display rates in `includes/currencies.php`.
  - Nothing is published by the import. The result table lists created and skipped rows. Select drafts and publish them, or discard them. Discard deletes drafts only. It does not delete a product that is already published.
  - Log each run in `supplier_import_batches` (filename or URL, supplier, markup, row counts).
  - A help section on the same page explains the columns and the link price-role rule.

---

## 18. Catalog data import

Add `public/admin/data_import.php` under Tools, visible only to `super_admin`.

- Copy catalog rows from a source MySQL database into this store. The admin types the source host, port, database, user, and password on the page. Do not store those credentials.
- Analyze connects and, for each chosen entity (categories, brands, products, product images, variations), compares column names: shared, source-only, and target-only, with row counts.
- Run copies the selected entities into matching columns only. It does not drop local columns and does not publish drafts created by section 17.

---

## 19. API status

Add `public/admin/api_status.php` under Tools.

- Check Ziina with the configured key and show reachable or not, without printing the key.
- Check SMTP by confirming the configured host and port accept a connection, and offer the existing test-message action.
- Show the last error string when a check fails.

---

## Done when

- A guest can filter and sort the shop, switch language, subscribe and unsubscribe, send a contact message that remains in the admin inbox, track an order, and check out with gift wrap and optional same-day delivery.
- A customer can register, log in, save an address, keep a wishlist, and open only their own orders.
- UAE orders at or above the threshold ship free. VAT and the TRN appear only when Charge VAT is on and that order was taxed.
- Ziina stock is reserved only after payment. Cancel and refund put it back.
- Marking an order shipped emails the customer.
- Admin can moderate the gallery, run a newsletter, manage admin users, bulk-delete products that are not on orders, import supplier drafts, and import catalog rows from another database.
