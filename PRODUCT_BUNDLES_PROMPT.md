# Product bundles

Implement product bundles in the Aleppo Gift PHP store. The schema is already migrated in database/add_product_bundles.sql (tables bundles, bundle_items, order_bundles, plus order_items.bundle_id, line_role, catalog_price, bundle_discount_percent). Do not change that schema. Follow existing admin, cart, and homepage patterns. Cart is $_SESSION['cart']. Products are bilingual (name_en, name_ar). Prices are products.price plus product_variations.additional_price. Stock is products.stock, or the variation stock when a variation is selected.

## Admin

- Add a Bundles item under Catalog in public/admin/includes/header.php.
- Add public/admin/bundles.php to list, create, edit, and delete bundles.
- Fields: name_en, name_ar, description_en, description_ar, discount_percent (0–100), optional image, status, show_on_home, display_order.
- Bundle items: search existing products (reuse public/admin/ajax/search_products.php), optional variation, quantity >= 1. One row per product + variation inside a bundle.
- A bundle must contain at least two items before it can be shown on the home page.
- Deleting a bundle is blocked when order_bundles still references it, or soft-disable it by setting status = 0. Deleting a bundle deletes its bundle_items (ON DELETE CASCADE).

## Home

- In public/index.php, add a Bundles section near the products section.
- Show bundles where status = 1 and show_on_home = 1, ordered by display_order.
- Each card shows the bundle name, the discount percent, the included products with quantities, the catalog total, and the discounted total.
- One “Add bundle to cart” button. If any required product or variation is inactive or short on stock, disable the button and say which item is unavailable.

## Cart

- Add public/ajax/add_bundle_to_cart.php. It loads the bundle and its items, checks stock against quantities already in the cart, then appends one session line per bundle item.
- Session line shape stays compatible with the current cart and adds: bundle_id, bundle_instance (a token created when that bundle is added), line_role = 'bundle', required_quantity (the admin quantity). Do not merge a bundle line into a standalone line of the same product. A second click on the same bundle increases the bundle lines by another required_quantity and raises required_quantity to match.
- Adding a normal product (public/ajax/add_to_cart.php and the cart.php form) still creates or increments a standalone line. If that product is already in the cart only as a bundle line, create a separate line with line_role = 'extra'. Never fold the extra quantity into the bundle line.
- public/cart.php and public/ajax/cart_preview.php must show bundle lines grouped under the bundle name, with catalog price, discounted price, and a clear note when the discount is voided.
- Removing a line or lowering quantity below required_quantity is allowed. Recalculate before rendering totals.

## Discount

Recalculated on every cart read, quantity change, remove, and at checkout.

- Unit catalog price = product price + variation additional price.
- For each bundle_instance:
  - removed_value = sum over its bundle lines of max(0, required_quantity - current_quantity) * unit catalog price. A fully removed line counts as its full required value.
  - replacement_value = catalog value of non-bundle lines added after this bundle. Count each of those lines once. Apply their value to bundles in the order the bundles were added. Quantity of a bundle product above required_quantity is an extra and counts as replacement value, but that extra quantity is charged at full price.
  - If removed_value is 0, or replacement_value >= removed_value, the bundle qualifies. discount = round(sum of current bundle-line catalog totals * discount_percent / 100, 2). Apply it only to the original bundle quantities still in the cart. Extras and replacement lines stay at catalog price.
  - If removed_value > 0 and replacement_value < removed_value, void the discount for that bundle only. All of its lines are charged at catalog price.
- Show the customer a short message: either “Bundle discount applied: X%” or “Bundle discount removed. Add products worth at least {removed_value - replacement_value} to restore it.”
- Existing coupon fields on orders stay independent. Apply the bundle discount first, then the coupon on the merchandise total after bundle discounts.

## Checkout and orders

- When the order is saved, insert one order_bundles row per bundle_instance with the names, percent, removed_value, replacement_value, discount_voided, and discount_amount copied from the calculation.
- Each order_items row stores bundle_id (null for a normal product), line_role (bundle, extra, replacement, or standalone), catalog_price, and bundle_discount_percent (null when that line is not discounted). order_items.price is the unit price actually charged.
- Invoices and the admin order detail must show the bundle name and the bundle discount amount.

Do not invent a second cart store. Keep validation in the existing cart helpers. Match the current admin form, CSRF, and bilingual display style.
