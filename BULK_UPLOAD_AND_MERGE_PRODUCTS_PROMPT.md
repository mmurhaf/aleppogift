# Prompt: Bulk Image Upload + Merge Products Features

Implement two admin tools for a PHP/MySQL e-commerce admin panel. Both pages sit inside the admin area, share a common `header.php`/`footer.php` layout, use Bootstrap 5, Font Awesome icons, and a `$db` database wrapper that exposes `fetch()`, `fetchAll()`, `query()`, `lastInsertId()`, `beginTransaction()`, `commit()`, and `rollBack()`.

---

## Feature 1 — Bulk Image Upload (`bulk-upload-images.php`)

### Purpose
Let admins upload many product photos at once. Each uploaded image automatically creates a new, immediately-visible product stub that can be refined later.

### Behavior

**Upload form**
- Multi-file `<input type="file">` accepting JPG, JPEG, PNG, GIF, WEBP.
- Max 5 MB per file; validated server-side with `getimagesize()` + file-size check.
- Client-side live preview grid appears as soon as files are chosen (FileReader API, 2×2 grid per card, image + filename + KB).
- On submit, a `confirm()` dialog tells the admin how many images/products will be created.
- CSRF token generated once per session with `bin2hex(random_bytes(32))` and verified with `hash_equals()`.

**Per-file server processing**
1. Validate extension and size; skip with an error message on failure.
2. Compute `md5_file()` of the upload; query `product_images` for every existing file on disk and compare hashes — skip with a "duplicate" notice if a match is found.
3. Generate a unique filename: `product_{timestamp}_{incrementingNumber}.{ext}`.
4. Move file to `uploads/products/` (create directory if absent, mode `0755`).
5. Wrap the following in a DB transaction:
   - `INSERT INTO products` with defaults: `name_en`/`name_ar` = `"New Product #N"`, `description_en`/`description_ar` = upload-date sentence, `price = 499.00`, auto-generated SKU (`SKU000001` style), `stock = 10`, `category_id = NULL`, `brand_id = NULL`, `featured = 0`, `status = 1` (active/visible).
   - `INSERT INTO product_images` with `product_id`, `image_path`, `is_main = 1`, `display_order = 1`.
6. On transaction failure: rollback and delete the moved file.

**Results display (shown after POST)**
- Green success alert + count.
- Yellow warning alert + count for duplicates.
- Red error alert listing per-file failures.
- Success table: Product ID | Name | SKU | Thumbnail | Edit / View buttons.
- Duplicate-skipped table: Filename | Reason badge | Existing product name | Edit button.

**"Products Awaiting Details" panel**
Below the form, query products where `category_id IS NULL OR brand_id IS NULL`, ordered by `created_at DESC`, limit 10. Display as a table: ID | Thumbnail | Name | SKU | Price | Category badge (Set/Not Set) | Brand badge (Set/Not Set) | Created date | Edit button.

**Header buttons**
- "Merge Products" → `product-consolidate.php#productsMenu`
- "Back to Products" → `products.php`

---

## Feature 2 — Merge Duplicate Products (`product-consolidate.php`)

### Purpose
Allow an admin to select multiple duplicate product cards and merge them into the oldest one (lowest ID), with all images from the deleted duplicates reassigned to the survivor.

### Architecture & Safety
- Wrap the entire page logic in `ob_start()` / `ob_get_clean()` inside a `try/catch(Throwable)` block; on exception, discard the buffer and render a diagnostic panel (message, file, line, stack trace preview).
- Register a `register_shutdown_function` that catches fatal PHP errors and logs them plus emits an HTML comment.
- All helper logic lives in standalone functions prefixed `mergeProducts*` (no class needed).
- `declare(strict_types=1)` at top.

### Schema detection
On every page load, `SHOW COLUMNS FROM product_images` and set boolean flags:
- `has_main_image_flag` — column `is_main` exists.
- `has_display_order` — column `display_order` exists.
Use these flags throughout to build SQL dynamically so the page works with varying schemas.

### Filter bar (GET form, `id="searchForm"`)
- Free-text search input (searches `p.id`, `p.name_en`, `p.sku`).
- Category `<select>` — populated from `categories` table, plus an "Uncategorized" option (`value="null"`); auto-submits on `change`.
- Brand `<select>` — populated from `brands` table, plus "No Brand" option; auto-submits on `change`.
- Manual "Search" button.

### Product grid (POST form, `id="mergeForm"`)
Query up to 500 products with LEFT JOINs to `categories`, `brands`, and `product_images` (COUNT). Display as a responsive card grid. Each card contains:
- Checkbox `.product-checkbox` (value = product ID).
- 2×2 image thumbnail grid (main image highlighted with a blue border and "Main" badge; extra images shown as "+N more" tile).
- Compact info section: name, ID, SKU, price, category badge, brand badge, image count badge.

**Selection mechanics (vanilla JS)**
- Clicking a card's checkbox toggles `.is-selected` class (yellow border + subtle background).
- "Select All" / "Deselect All" buttons.
- Selected count label (`id="selectedCount"`).
- Merge button (`id="mergeButton"`) is disabled until ≥ 2 products are selected; its label updates to show `"Merge into Product #N"` where N is the lowest selected ID.

**Merge confirmation**
On form submit a multi-line `confirm()` shows: total count, which ID is kept, which IDs are deleted, warning about images being moved, "cannot be undone" notice.

**Merge logic (server-side POST)**
1. CSRF verification via `verifyCsrfToken()`.
2. Normalize IDs: cast to int, deduplicate, filter > 0, sort ascending — first ID = primary/survivor.
3. Move all `product_images` rows from duplicate IDs to primary: update `product_id`, append to `display_order` sequence, set `is_main = 0` (schema-flag-guarded).
4. `DELETE FROM products` for each duplicate ID.
5. All inside a single DB transaction; rollback on any exception.
6. Return a result array: `primary_id`, `primary_name`, `images_moved`, `products_deleted`, `total_merged`.

**Success banner**
Green dismissible alert with result sentence + action buttons: "Edit Merged Product" → `edit_product.php?id=N` and "View All Products" → `products.php`.

**Header buttons**
- "Back to Products" → `products.php`
- "Bulk Upload" → `bulk-upload-images.php#productsMenu`

### CSS (inline `<style>`)
- `.product-merge-card` — 2px border, transition; `.is-selected` — yellow border + shadow + tinted background.
- `.product-images-grid` — CSS Grid 2 columns, `aspect-ratio: 1` thumbnails with `object-fit: cover`.
- `.product-thumb.main-image` — blue border.
- `.main-badge` — absolute top-right overlay.
- `.more-images` — dashed border flex center for "+N" tile.

---

## Database schema assumptions

```sql
products (id PK, name_en, name_ar, description_en, description_ar, price DECIMAL, sku, stock INT, category_id FK NULL, brand_id FK NULL, featured TINYINT, status TINYINT, created_at TIMESTAMP)

product_images (id PK, product_id FK, image_path VARCHAR, is_main TINYINT, display_order INT)

categories (id PK, name_en, name_ar)
brands     (id PK, name_en, name_ar)
```

The merge page detects `is_main` and `display_order` columns at runtime and degrades gracefully if absent.

---

## Cross-linking
The two pages link to each other in their header button bars so admins can flow naturally: upload → merge → edit product.
