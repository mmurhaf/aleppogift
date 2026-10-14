# Website (Frontend) Features — Implementation Prompt

Build a complete **PHP/MySQL e-commerce storefront** for a gift store targeting UAE customers. The public website is located at `/public/`. Use Bootstrap 5, Font Awesome 6, a consistent header/footer via `includes/header.php` and `includes/footer.php`, and support both **English and Arabic** content throughout.

---

## 1. Homepage

File: `index.php`

- Hero/banner section.
- Featured categories grid: loads active categories from DB, each showing the category picture, English name, and a link to the products page filtered by that category.
- Featured products section: products where `featured = 1`, shown as product cards (image, name, price, Add-to-Cart button).
- Brands row: logos of all active brands.
- Trust badges strip (from `trust_badges` table via `social_proof_widgets.php`): e.g., "Free Shipping", "Secure Payment", "Easy Returns".
- Testimonials section: approved customer testimonials with star ratings and WhatsApp proof screenshots (blurred if privacy flag is set).
- Social proof widgets: live visitor count, recent order notification popups.
- Pagination for the full product listing below the featured sections (16 products per page).

### Product Filtering on Homepage / Products Page
- Filter by **Category** (dropdown or sidebar).
- Filter by **Brand** (dropdown or sidebar).
- **Search** by product name (English or Arabic) or product ID.
- URL-parameter driven filters (`?category=`, `?brand=`, `?search=`) — all combinable.
- Compatibility: old-style links like `?page=product_details&code=SKU` redirect to the search filter automatically.

---

## 2. Products Listing Page

File: `products.php` (or handled in `index.php`)

- Grid layout (4 columns desktop, 2 columns mobile).
- Each product card shows:
  - Main product image (with lazy loading).
  - Product name (English).
  - Price (shown/hidden based on `show_prices` site setting).
  - Stock status (in-stock / out-of-stock).
  - Brand name.
  - "Add to Cart" button + "Quick View" button.
- Pagination: 16 products per page with prev/next and page number links.
- Empty-state message when no products match filters.

---

## 3. Product Detail Page

File: `product.php?id={id}`

- **Main image** displayed large; clicking thumbnail images in the gallery updates the main image.
- **Image gallery**: thumbnails ordered by `display_order`; clicking any thumbnail swaps the main image.
- **Variation selector**: if the product has variations (size/color), display them as selectable swatches or dropdowns. Selecting a variation swaps the main image to the variation's linked image and updates the price modifier and stock count dynamically via JavaScript.
- **Price** display (respects `show_prices` setting; shows AED symbol using the UAE dirham symbol utility).
- **Stock indicator**: shows remaining stock or "Out of Stock".
- **Add to Cart** button: adds selected product + variation to the session cart via AJAX (no page reload). Displays a cart preview/toast on success.
- **Quantity selector** before adding to cart.
- **Product description** (English and Arabic tabs or sections).
- **Product details**: Brand, Category, SKU, Weight.
- **Breadcrumb**: Home > Category > Product Name.
- **Related products** section: up to 4 products from the same category (excludes current product).
- **Product reviews section**: 
  - Displays approved reviews with star ratings, reviewer name, and review text.
  - Star distribution summary (5 stars through 1 star with percentage bars).
  - "Write a Review" form: reviewer name, email, rating (1–5 stars), review text. Submitted reviews are saved as pending (awaiting admin approval).
  - Helpful votes on reviews.
- **SEO meta tags**: title, description, og:image dynamically set from product data.
- **Schema.org structured data** (JSON-LD) for Product with price, availability, and review aggregate.

---

## 4. Cart

File: `cart.php`

- Session-based cart stored in `$_SESSION['cart']` as an array of `{product_id, variation_id, quantity, added_at}`.
- Cart page shows:
  - Product image (variation image if available, else main product image).
  - Product name + variation (size/color).
  - Unit price.
  - Quantity input (update on change via form submit).
  - Line total.
  - Remove item button (POST with CSRF token).
- **Subtotal**, **Shipping cost** (auto-calculated from UAE Emirate selected at checkout), **Coupon discount**, **Grand total** shown in a summary box.
- **Coupon code** input: validates the code against DB (checks type, value, dates, max usage). Applies percentage or fixed discount to the subtotal. Stores coupon state in session.
- **Update Cart** button to re-calculate after quantity changes.
- **Cart Preview / Mini-cart**: a slide-in panel accessible from the header showing current cart items + totals without leaving the page (AJAX-powered).
- CSRF protection on all cart modification forms.
- Invalid/unavailable cart items are detected and removed with a warning shown.

---

## 5. Checkout

File: `checkout.php`

- Requires a non-empty cart; redirects to `cart.php` if cart is empty.
- **Customer info form**:
  - Full Name, Email, Phone Number.
  - Option to use details of a returning customer (session-stored).
- **Shipping address**:
  - UAE Emirate selection (dropdown: Abu Dhabi, Dubai, Sharjah, Ajman, Umm Al Quwain, Ras Al Khaimah, Fujairah).
  - Detailed address / delivery notes field.
- **Order summary** shown alongside the form: items, subtotal, shipping, discount, total.
- **Shipping cost** calculated dynamically (AJAX) when the Emirate is selected. Respects per-Emirate rates from admin settings.
- **Payment method**: Ziina payment gateway (generates a Ziina payment link and redirects). Cash on Delivery option if enabled.
- **Order validation**: checks all cart items still exist and are in stock before saving the order.
- On submission: creates a record in `orders` table + `order_items`, stores customer in `customers` table (upsert by email), sends order confirmation email to customer, sends WhatsApp notification to store owner.
- Redirects to `thankyou.php` on success or to a payment link for online payment.
- Handles Ziina payment callback (success/fail).

---

## 6. Thank-You / Order Confirmation Page

File: `thankyou.php`

- Displays order ID, customer name, and a summary of items ordered.
- Shows estimated delivery info.
- **Download Invoice** link.
- **View Invoice** link.
- Clears the session cart on successful load.
- Friendly message with "Continue Shopping" button.

---

## 7. Invoice & Quotation

Files: `view_invoice.php`, `download_invoice.php`, `download_quotation.php`

- **View Invoice**: renders the invoice as an HTML page (print-friendly) with store logo, order details, itemised table, total, and payment info.
- **Download Invoice**: generates and downloads a PDF version (FPDF-based, Arabic text support).
- **Download Quotation**: similar to invoice but labelled as quotation; used before payment.
- PDFs include: store branding, order number, date, customer info, product table (English + Arabic names), quantities, unit prices, shipping, discount, grand total, QR code or payment link if applicable.

---

## 8. Shared Cart

Files: `shared-cart.php`, `shared_cart_create.php`

- Admin can create a "shared cart" link containing pre-selected products.
- When a customer visits the shared cart URL (`shared-cart.php?token={64-char-hex}`), the products in the shared cart are loaded into their session cart automatically.
- Token is validated against the `shared_carts` table (must be `active` and not past `expires_at`).
- After loading, customer is redirected to the cart page.

---

## 9. Contact Page

File: `contact.php` / `contactus.php`

- Contact form: Name, Email, Message.
- Form submission sends an email notification to the store email via `process_contact.php`.
- Store address, phone, and WhatsApp link displayed.
- Google Maps embed (optional).

---

## 10. Static / Informational Pages

- `about.php` — About the store.
- `faq.html` — Frequently Asked Questions.
- `privacy_policy.html` — Privacy policy.
- `terms_of_service.html` — Terms of service.
- `returns.html` — Returns & refund policy.

---

## 11. Customer Gallery

File: `customer_gallery.php`

- A page displaying approved customer photos/testimonials from the `testimonials` table.
- Shows customer name, photo, optional WhatsApp screenshot (with blur if privacy flag is set), star rating, and text.

---

## 12. Sitemap

Files: `sitemap.php`, `sitemap.xml`

- Dynamically generated XML sitemap including all active product pages, categories, and static pages.
- Submitted-friendly format for Google Search Console.

---

## 13. Session & Security

- Session started via `includes/session.php`; PHP session cookie set to `HttpOnly` and `SameSite=Lax`.
- All user-facing forms use CSRF tokens where destructive actions are involved (cart removal, checkout submission).
- All database queries use PDO prepared statements — no raw string interpolation of user input.
- Uploaded file types validated by extension AND MIME type; files renamed with `uniqid()`.
- `bootstrap.php` centralises includes (config, DB, functions, session) to avoid path issues.

---

## 14. Visitor Tracking

File: `includes/visitor_tracker.php`

- Logs each page visit to a `visitors` table: IP, country, country code, city, browser, OS, page URL, timestamp.
- IP-to-country resolved via an external GeoIP API (or fallback to a bundled database).
- Used by the admin analytics page to show live visitor statistics.

---

## 15. Review System

Files: `submit_review.php`, `mark_helpful.php`, `includes/review_section.php`, `includes/ProductReviews.php`

- Customers can submit reviews on the product detail page (name, email, rating 1–5, text).
- Reviews saved with `is_approved = 0` until approved in admin.
- Approved reviews shown on the product page with star rating display.
- "Mark as Helpful" button increments a `helpful_count` on a review without requiring login.

---

## 16. Bilingual Support (English & Arabic)

- All product, category, and brand names stored as `name_en` / `name_ar` pairs.
- Product descriptions stored as `description_en` / `description_ar`.
- UI text: primary language is English; Arabic names shown as secondary labels or in `dir="rtl"` sections.
- AED currency symbol rendered correctly using `uae_symbol_utils.php` (handles font/encoding issues with the "`د.إ`" symbol).

---

## 17. Social Proof Widgets

File: `includes/social_proof_widgets.php`

- **Live visitor counter**: shows how many people are currently viewing the site (based on recent `visitors` table records).
- **Recent order notifications**: small pop-up toast ("Ahmad from Dubai just ordered X") using recent orders data.
- **Trust badges**: icons + text for "Secure Checkout", "Free Returns", "Fast Delivery", etc. Managed from admin.

---

## 18. Email Notifications

- **Order Confirmation** (customer): sent automatically on checkout success with order summary, invoice download link, and store contact info.
- **New Order Alert** (store owner): sent to admin email with full order details.
- **WhatsApp notification** to store owner WhatsApp number via `whatsapp_notify.php`.
- **Invoice email**: admin can resend invoice to customer from the order detail page.
- Email engine: PHPMailer (SMTP) with Resend API as fallback. Both configured via admin settings.

---

## 19. Payment Integration — Ziina

- **Checkout flow**: calls `ZiinaPayment.php` to create a payment link, stores the link + Ziina reference in the order, then redirects the customer to the Ziina-hosted payment page.
- **Return URL**: `payment.php` or `thankyou.php` receives the callback, verifies payment status with Ziina API, updates `orders.payment_status`, and shows the result to the customer.
- Admin can also generate standalone Ziina payment links from the admin panel.

---

## 20. Technical Stack

- **Language**: PHP 8.x
- **Database**: MySQL / MariaDB via PDO (custom `Database` class)
- **CSS Framework**: Bootstrap 5.3
- **Icons**: Font Awesome 6.6
- **PDF**: FPDF with Arabic text support
- **Email**: PHPMailer + Resend API
- **Payments**: Ziina payment gateway
- **Session cart**: PHP `$_SESSION`; no login required to shop.
- **Bilingual**: English (primary) + Arabic (secondary) for all product content.
- **UAE-specific**: currency in AED (`د.إ`), Emirates-based shipping zones, UAE phone format.
- **SEO**: dynamic meta tags, Open Graph tags, JSON-LD structured data, XML sitemap.
- **Security**: CSRF tokens, prepared statements, file upload validation, `HttpOnly` session cookies.
