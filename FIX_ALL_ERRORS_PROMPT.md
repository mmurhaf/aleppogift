# Fix prompt: Aleppo Gift storefront errors

Verified on 30 September 2026 against the live site `https://aleppogift.com/` and the local copy at `D:\xampp\htdocs\saleto.me\public_html\stores\aleppogift\`.

You are fixing the customer-facing store. Work in the local copy, then the result must behave correctly both at the domain root (`https://aleppogift.com/`) and in the local subdirectory (`/stores/aleppogift/`).

## Rules

- Edit the files that the live pages actually include. Do not patch backup copies such as `public/index99.php`, `public/index_0.php`, `public/cart_8.php`, `public/checkout_0.php`, `public/product_0.php`, `public/admin2/`, or `includes/header_old.php`.
- Keep prices in AED. The dirham mark must stay the official UAE Dirham symbol, readable without a private font.
- Do not invent product titles, Arabic names, or category names. Where the database has a placeholder, hide it or flag it for a human. Only replace a placeholder when the real name already exists elsewhere on that product.
- Do not deploy `includes/footer.php` over production until it is reconciled. The file on disk and the footer served by `https://aleppogift.com/` have diverged. See bug 12.
- After the code changes, verify in a browser on the homepage, a product page, the cart, and checkout. Check a phone-width viewport and a desktop viewport. Confirm the cart badge, search, add-to-cart feedback, brand section, category filter, and footer links.

## 1. Header hides the cart and search on every page

**What customers see:** The header is only the logo and the hamburger. There is no cart icon and no search icon. The cart count in the DOM can be correct and still invisible.

**Cause:** `includes/header.php` lines 68–77, comment “Category-only public mode”, forces this CSS on all breakpoints:

```css
#search-toggle-mobile,
.cart-button-mobile,
#search-bar,
#search-toggle,
.cart-button-header,
#cartPreview,
#cartOffcanvas {
    display: none !important;
}
```

Live check: `.cart-button-mobile` and `#search-toggle-mobile` both compute to `display: none`. `#cartOffcanvas` is also forced hidden, so its body stays on the placeholder “Loading cart...” forever.

**Fix:**

- Remove that hide rule. The store still sells: Add to Cart, cart.php, and checkout.php are active.
- Show the cart button on mobile and on desktop, with a visible badge.
- Show search on mobile (the existing `#search-toggle-mobile` / `#search-bar`) and on desktop.
- Let `#cartPreview` and `#cartOffcanvas` open. When the mobile drawer opens, load `ajax/cart_preview.php` into `#cart-items-offcanvas` and replace the spinner. Do not leave “Loading cart...” as the resting state.
- There is no desktop cart button in the nav markup today. Add one that opens the preview and shows the same count as `#cart-count-mobile`.
- In `includes/header.php` the Cart nav item is `<a class="nav-link" href="/cart.php" target="_blank">`. Remove `target="_blank"` and `rel="noopener noreferrer"`. Cart should open in the same tab.

## 2. Add to cart succeeds, then the UI forgets it

**What customers see:** On the homepage, Add to Cart changes to “Adding...” and then back to “Add to Cart”. No toast stays on screen. The badge does not change. The item is actually stored: `ajax/get_cart_count.php` returns `count: 1`, and `cart.php` / `checkout.php` show the line.

**Causes:**

- `public/index.php` loads, in order, `assets/js/main.js`, `assets/js/enhanced-main.js`, `assets/js/enhanced-ui.js`, `assets/js/index.js`. Each file defines its own `addToCart` and `showToast` with different signatures.
  - `index.js` calls `addToCart(productId, quantity)`.
  - `enhanced-main.js` defines `addToCart(productId, productName, button)` and then calls `button.classList` with no null check. If that copy is the one that runs, the click throws before the user gets feedback.
  - `main.js` `window.addToCart` (around line 409) updates `#cart-count` and `#cart-count-toggle` only. The badge that exists in the header is `#cart-count-mobile`.
- `public/product.php` (around line 1230) reads `response.cart_count`. `public/ajax/add_to_cart.php` returns `count`, not `cart_count`, so the product page writes `0` even when the post succeeds.
- `public/index.php` `removeFromCart()` does `document.getElementById('cart-count').textContent = ...`. That id is not in the header, so removal throws.
- The document click handler in `public/index.php` (around line 611) calls `cartButton.contains(...)` for `button[onclick="toggleCartPreview()"]`. That button does not exist, so the handler throws whenever the preview is open.

**Fix:**

- One add-to-cart function, one toast function, one cart-count updater. Point the homepage, product page, quick view, and cart preview at those.
- The updater must set every real badge, including `#cart-count-mobile`, from the `count` field.
- Product page must read `response.count`.
- Guard every `getElementById` / `querySelector` before using it.
- After a successful add, keep a visible confirmation (toast plus badge) and refresh the preview HTML.
- Buttons that are missing must not throw.

## 3. Broken `onerror` text in the brand section

**File:** `includes/brands.php` lines 37–41.

The `onerror` attribute is outside the `<img>` tag, so it is a text node:

```php
<img src="<?= $logoPath; ?>"
    alt="..."
    class="brand-image"
    loading="lazy">
    onerror="this.src='assets/images/no-image.png'">
```

Crawlers and assistive tech read `onerror="this.src='assets/images/no-image.png'"` next to every brand. The circular container clips it visually, and a missing logo never falls back.

**Fix:** Put `onerror` on the `<img>`. Use a path that works from the site root, and clear the handler inside the fallback so a missing placeholder cannot loop: `this.onerror=null;this.src='...'`.

## 4. Brand logo paths are prefixed twice

**File:** `includes/brands.php` line 35 always prepends `public/uploads/brands/`.

The `brands.logo` column is mixed. Some rows are a filename (`villari.png`). Others already contain `uploads/brands/versace.png`. The rendered URL becomes `public/uploads/brands/uploads/brands/versace.png`. Those URLs happen to return images today. A new upload that stores only a filename, or only the relative folder, will 404 depending on which format was saved.

**Fix:** One normalizer. Accept a bare filename, `uploads/brands/...`, or `public/uploads/brands/...`, and emit a single public URL. Use it in the storefront and in the admin brand list. Do not change rows that already display until the normalizer is proven against the current 22 logos.

## 5. Public category named “not defined”

The homepage category `<select name="category">` includes `<option value="1">not defined</option>` because `categories.status = 1` and `name_en` is the literal string `not defined`.

**Fix:** Exclude placeholder categories from every public filter and brand/category pill (`name_en` empty, `not defined`, `undefined`, `null`). In admin, list category id 1, show how many products use it, and let a person rename it or move those products. Do not rename it in code to a guessed label.

## 6. Placeholder Arabic names and description-length English titles

On the first homepage page, 7 of 30 Arabic lines are `New Product #...` (examples: product ids 926, 923, 922, 921, 918, 917, 916). Several English titles are long generated sentences, not product names (the fruit bowl title is the full description).

**Fix:**

- Add an admin filter for `name_ar` matching `New Product #%` or blank, and for `name_en` longer than a normal title.
- Do not auto-generate Arabic. If `name_ar` is a placeholder and a real Arabic name is not already stored, leave the Arabic line off the card rather than printing `New Product #916`.
- Keep the existing English title until a person edits it. Do not rewrite catalog copy in this pass.

## 7. Dirham symbol and USD rate disagree

- Homepage cards (`public/index.php` line 369) print `&#x00EA;` (ê) and rely on the private `UAESymbol` font in `public/assets/css/index.css`. With the font, it looks like the dirham mark. Without the font, and in search snippets, the price is `ê312`.
- `public/product.php` prints `د.إ`.
- Cart preview HTML uses `assets/svg/UAE_Dirham_Symbol.svg`.
- USD on listing and product pages divides by the hardcoded `3.68` (`public/index.php` line 371, `public/product.php` line 839, `assets/js/index.js`, `assets/js/enhanced-main.js`).
- Checkout uses `getAedToUsdRate()` / `AED_TO_USD_RATE` default `0.2723` (`includes/functions.php`, `public/checkout.php` around line 1022). The same 312 AED line is `$84.78` on the homepage and `USD 84.96` on checkout.

**Fix:** Render the dirham mark from `assets/svg/UAE_Dirham_Symbol.svg` (or the existing helper in `includes/uae_symbol_utils.php`) on the homepage, product page, quick view, cart, and checkout. Use one conversion function for the USD figure everywhere, and print it as an approximate secondary amount. `config/constants.php` defines `USD_TO_AED_RATE` as `3.68`; checkout does not use it. Pick the constant checkout already uses for orders and use that same value on the catalog.

## 8. Trust counters are rewritten and padded on every homepage view

**File:** `includes/social_proof_widgets.php` lines 36–46.

Every homepage request runs four `UPDATE`s and stores `realCount + 500` orders, `+ 500` customers, `+ 2500` products sold, and `+ 30` countries. The heading says “Built on real delivery history” and “Live counters update from completed orders”.

The numbers do animate once the block is scrolled into view (`data-target` was 556, 551, 44, 2696). The bug is the write-on-read and the padding, not the animation.

**Fix:** Read the four aggregates, cache them (file or a short TTL), and display those values. Remove the `+ 500 / + 500 / + 2500 / + 30` offsets and the `UPDATE` on page view. If a cached row is missing, show the query result directly so a failed script cannot leave the counters at 0.

## 9. Wishlist does not save

`public/assets/js/enhanced-main.js` toggles the heart icon and shows “Added to wishlist!” with no request. The product page button does the same kind of local-only toggle.

**Fix:** Either persist the wishlist (existing customer/session storage if the project already has it) or remove the control and the toast. A success toast for a click that is forgotten on refresh is a bug.

## 10. Footer newsletter and payment marks

On production the footer form is `action="/contact.html" method="get"`. Subscribe sends the address to the contact page as a query string and stores nothing.

The footer also shows Visa, Mastercard, PayPal, and Apple Pay. Checkout offers only Cash on Delivery (UAE) and Ziina.

**Fix:**

- Point Subscribe at a real endpoint that validates the email and stores it, and show success or a field error. If there is no newsletter table yet, submit to the contact form as a named “newsletter” message through the existing contact handler. Do not use `method="get"` to `contact.html`.
- Show only methods the checkout can charge: COD and the card mark that matches Ziina. Remove PayPal and Apple Pay until those methods exist.

## 11. Local footer does not match production

`includes/footer.php` in this workspace still links to `/stores/aleppogift/...` and to `products.php` (“All Products”, “New Arrivals”, “Best Sellers”, “Sale Items”).

The live site footer uses root paths (`/`, `/contact.html`, `/faq.html`, `/shipping.php`, `/returns.html`, `/terms_of_service.html`, `/privacy_policy.html`, `/site.html`) and a Categories column (Category Gallery, WhatsApp, Ask for Available Products, Contact Form), not `products.php`.

**Fix:** Treat the live footer structure as the one to keep. Update the local file to those root-relative URLs, with a single base-path helper so local `/stores/aleppogift/` and production `/` both resolve. Then apply bug 10 to that file. Do not copy the local `/stores/aleppogift/products.php` links onto production.

## 12. Verify these flows after the fix

1. Homepage at phone width and desktop width: cart icon visible, badge `0` when empty, search opens and finds a product.
2. Add the Ginori fruit bowl (id 90980). Badge becomes 1, a toast stays visible, the preview lists the item and `AED 312.00`. No `ê` in the price. No `onerror=` text in the brand grid.
3. Open the product page and add it again. Badge becomes 2. USD on the product page matches USD on the homepage and on checkout for the same AED amount.
4. Cart page: change quantity, remove the line, badge returns to 0. Cart link does not open a new tab.
5. Category dropdown has no “not defined”. Arabic cards do not say “New Product #”.
6. Trust counters show the unpadded aggregates and do not `UPDATE social_proof_stats` on refresh.
7. Footer Subscribe does not navigate to `contact.html?email=...`. Payment icons match COD and Ziina. Footer links return 200 on `https://aleppogift.com/`.
8. Wishlist no longer claims success unless the choice is still there after reload.
