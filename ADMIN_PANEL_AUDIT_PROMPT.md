# Admin panel audit: links, layout, products, orders, payments

Audit and fix the Aleppo Gift admin control panel. Work in the local copy at `D:\xampp\htdocs\saleto.me\public_html\stores\aleppogift\`. Confirm each fix in the browser on the local admin, and keep URLs that also work at the domain root (`https://aleppogift.com/admin/`).

## Login

Open the local admin login page:

`http://localhost/stores/aleppogift/public/admin/login.php`

If that path 404s, try `http://localhost/saleto.me/public_html/stores/aleppogift/public/admin/login.php`. The live equivalent is `https://aleppogift.com/admin/login.php`.

Sign in with:

- Username: `mmurhaf`
- Password: `57108855`

The form posts `username` and `password` to `public/admin/login.php` and redirects to `dashboard.php` on success. Do not change this password, reset any admin account, or edit `admin` rows.

If login fails, stop and report the exact error. Do not create a new admin user.

## Rules

- Edit the files the live admin actually includes. The sidebar is `public/admin/includes/header.php`. Shared styles are `public/admin/assets/admin-theme.css`.
- Do not patch backup or scratch copies: `public/admin/includes0/`, `public/admin/order_detail_0.php`, `public/admin/order_detail_1.php`, `public/admin/order_detail02.php`, `public/admin/order_detail_clean.php`, `public/admin/brands_2.php`, `public/admin/brands_4.php`, `public/admin/categories_2.php`, `public/admin/header.php`, `admin/header.php`, or any `test_*.php`, `*_debug.php`, `fix_arabic_*.php`, or `regenerate_invoice_*.php` that is not linked from the sidebar.
- Do not change database schema. Do not run `query_runner.php` writes, deletes, or updates. Read-only checks are fine.
- Do not place test orders, test payments, or charges. Open create and edit forms, but do not submit a new order, a new payment link, or a payment capture unless a page is already broken on load and a dry submit is the only way to see the error. If you must submit, use an obvious test record and say so.
- Keep bilingual fields (`name_en`, `name_ar`, and the same pattern on orders and invoices). Prices stay in AED.
- Match the existing admin layout: sidebar, top bar, Bootstrap 5, `admin-theme.css`. Do not restyle the whole panel.
- After each group of fixes, reload the affected page in the browser. Check desktop width and a phone-width viewport (sidebar toggle, tables, forms). A screenshot of a resting page is not enough; click the broken control and confirm the fixed result.

## 1. Broken links

Walk every item in `$admin_navigation` in `public/admin/includes/header.php`, plus Profile and Logout. Then open the in-page actions on the pages below (edit, view, delete-confirm, invoice, back, pagination, filters, and ajax search).

Record each link that 404s, points at a missing file, points at a backup copy, uses a wrong relative path (`../` that leaves `/admin/`), opens a blank page, or returns a PHP fatal/warning as the response body.

Fix the href, include, or target file so the link lands on the page that is actually in use:

| Area | Pages to open |
| --- | --- |
| Overview | `dashboard.php`, `analytics.php`, `reports.php` |
| Catalog | `products.php`, `add_product.php`, `bulk-upload-images.php`, `bulk_edit_prices.php`, `product-consolidate.php`, `categories.php`, `brands.php` |
| Sales | `orders.php`, `create_order.php`, `shared_carts.php`, `customers.php`, `coupons.php`, `payment_links.php`, `quick_payment_link.php` |
| Content | `reviews.php`, `testimonials.php` |
| Tools | `change_product_id.php`, `remove_brand_from_names.php`, `email_client.php`, `manage_trust.php`, `trust-badges.php`, `query_runner.php`, `view-logs.php`, `system_status.php`, `currencies.php`, `settings.php` |
| Off-nav, still live | From a product row: `edit_product.php`. From an order row: `order_detail.php`, `edit_invoice.php`, `generate_invoice.php`. From a customer row: `view_customer.php`. Profile: `profile.php`. |

`Public Cart` in the sidebar is `../cart.php` with `target="_blank"`. Leave that as a storefront link. Fix it only if the path 404s from the admin URL.

A link that the sidebar hides because `file_exists` fails is still a bug if the label is supposed to be there: either restore the file or remove the dead nav item. Do not leave a menu label that 404s.

## 2. Design errors

On every page in the table above, fix layout that is actually broken. Look for:

- Content hidden under the sidebar or top bar, or a page with no sidebar because it includes the wrong header (`header.php`, `includes0/header.php`, or a copied header).
- Horizontal overflow on desktop, and tables or toolbars that cannot be used at phone width. The menu button `[data-sidebar-toggle]` must open and close the sidebar.
- Overlapping buttons, labels clipped by `overflow: hidden`, text contrast that fails on the orange admin theme, or icons that render as empty boxes (missing Font Awesome class).
- Raw PHP warnings, notices, or stack traces printed inside the layout.
- Forms with fields stacked on top of each other, buttons outside the card, or a modal that cannot be closed.
- Empty states that show a PHP error or a blank white main area instead of a short “none yet” message.

Fix these in the page file or in `public/admin/assets/admin-theme.css`. Do not add a second CSS framework. Do not change pages that already match the current admin theme.

## 3. Product pages

Open products, add product, edit product (use an existing product), categories, brands, bulk image upload, bulk price edit, and merge products.

Fix errors you can reproduce:

- List, search, filter, pagination, and status toggles that fail or return an error payload.
- Add and edit forms that fatal on GET, lose bilingual names, drop the main image, or save a variation with a wrong price or stock field (`products.price`, `product_variations.additional_price`, `products.stock`, variation stock).
- Image upload and thumbnail paths that 404.
- Category and brand assign controls that submit to a missing script or do not show the saved value after reload.
- Ajax used by these pages (`public/admin/ajax/search_products.php`, variant helpers included by the product form) that 500 or return HTML instead of the JSON the page expects.

Do not invent product names. Do not delete products. If a save would change live catalog data, fix the code path and verify with a no-op reload or by confirming the form still shows the current row.

## 4. Order pages

Open orders, create order, an existing order detail (`order_detail.php` only), invoice edit, invoice generate, shared carts, customers, and a customer detail page.

Fix errors you can reproduce:

- Order list filters, search (`public/admin/ajax/search_orders.php`), pagination, and status changes that error.
- Order detail that fatals, omits line items, shows the wrong currency, or links to `order_detail_0.php` / `order_detail_1.php` / `order_detail02.php`.
- Invoice view, regenerate, and download links that 404 or generate a blank PDF.
- Create-order product search and customer search that fail. Do not submit a new order.
- Customer list and `view_customer.php` links that 404 or show another customer’s orders.

Shared cart and coupon pages are in scope only where they break the order flow (open cart, apply coupon, link back to the order).

## 5. Payment pages

Open payment links, quick payment, coupons (only the payment-related controls), and the payment actions on an existing order and invoice.

Fix errors you can reproduce:

- `payment_links.php` and `quick_payment_link.php` that fatal on load, list the wrong rows, or post to a missing ajax script (`public/admin/ajax/generate_payment_link.php`, `public/admin/ajax/create_quick_payment.php`).
- Buttons that copy or open a payment URL pointing at a dead path.
- Order payment status, method, and amount that disagree with the stored order, or a control that posts to a test script (`test_quick_payment.php`, `ajax/test_quick_payment.php`).
- Invoice totals that do not match the order merchandise, shipping, and discount fields already stored. Do not invent a new payment provider flow.

Do not create a live payment, do not call Ziina or any capture endpoint, and do not email a payment link.

## Done when

- You can sign in as `mmurhaf` and every sidebar item either loads a 200 page with the admin chrome or is an intentional external storefront link.
- Product list, add product, and edit product load without PHP errors. Search and pagination work.
- Order list and `order_detail.php` load without PHP errors. Invoice links from that order resolve.
- Payment links and quick payment load without PHP errors, and their actions do not point at test scripts.
- Desktop and phone-width layouts keep the sidebar, top bar, and primary actions usable on those pages.
- Leave a short list in the reply: each bug, the file changed, and the page you reloaded to confirm it. If a page was already fine, do not claim a fix for it.
