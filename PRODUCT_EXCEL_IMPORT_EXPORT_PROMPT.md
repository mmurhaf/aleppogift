# Product Excel export and import

Add an admin feature that exports every product to an Excel file, lets the admin edit names, price, cost price, stock, status, featured, and weight in Excel, then uploads that file back. On upload, compare each row to the current `products` row and update only the fields that changed.

Work in the local copy at `D:\xampp\htdocs\saleto.me\public_html\stores\aleppogift\`. Confirm the flow in the browser on the local admin. Keep URLs that also work at the domain root (`https://aleppogift.com/admin/`).

## Login

Open the local admin login page:

`http://localhost/stores/aleppogift/public/admin/login.php`

If that path 404s, try `http://localhost/saleto.me/public_html/stores/aleppogift/public/admin/login.php`.

Sign in with:

- Username: `mmurhaf`
- Password: `57108855`

Do not change this password, reset any admin account, or edit `admin` rows.

## Rules

- Edit the files the live admin actually includes. The sidebar is `public/admin/includes/header.php`. Shared styles are `public/admin/assets/admin-theme.css`.
- Do not patch backup or scratch copies: `public/admin/includes0/`, `admin/header.php`, or any `test_*.php` / `*_debug.php`.
- Do not change database schema except the optional `cost_price` column described below. Do not run `query_runner.php` writes.
- Update only the `products` table. Do not touch `product_variations`, images, categories, brands, descriptions, or orders.
- Prices stay in AED (`products.price` and `products.cost_price`, decimals). Stock is `products.stock` (integer, minimum 0). Status is `products.status`: `1` active, `0` inactive. Featured is `products.featured`: `1` featured, `0` not featured. Weight is `products.weight` in kilograms, minimum 0, the same unit `edit_product.php` already saves. Names are bilingual: `name_en` and `name_ar`. Keep Arabic text as UTF-8.
- `cost_price` is optional. `edit_product.php` and `bulk_edit_prices.php` already read it when the column exists. If `SHOW COLUMNS FROM products LIKE 'cost_price'` is empty, add only that column the same way `bulk_edit_prices.php` does (`DECIMAL(10,2) NULL` after `price`). Do not add any other column.
- Match the existing admin layout: sidebar, top bar, Bootstrap 5, `admin-theme.css`. Require admin login and a CSRF token on the upload, the same way `public/admin/edit_customer.php` does.
- `composer.json` currently requires only `dompdf/dompdf`. Add `phpoffice/phpspreadsheet` and use it for `.xlsx` read and write. Do not hand-roll an xlsx parser. Do not accept `.csv` or `.xls` for the import.

## Page

- Add a Catalog item in `public/admin/includes/header.php`, next to Bulk Edit Products: label `Excel Products`, file `product_excel.php`, icon `fa-file-excel`.
- Add `public/admin/product_excel.php` with two actions: **Download Excel** and **Upload Excel**.
- On `public/admin/products.php`, add the same two actions in the existing toolbar so the admin can start from the product list. Both must post or link to `product_excel.php`.

## Export

Download every product, ordered by `products.id`, as `products.xlsx`.

Sheet name: `Products`. One header row, then one data row per product. Columns, in this order:

| Column | Header | Writable on import |
| --- | --- | --- |
| A | `id` | No. Match key only. |
| B | `name_en` | Yes |
| C | `name_ar` | Yes |
| D | `price` | Yes |
| E | `cost_price` | Yes. Empty cell stores NULL. |
| F | `stock` | Yes |
| G | `status` | Yes. Store `1` or `0`. |
| H | `featured` | Yes. Store `1` or `0`. |
| I | `weight` | Yes. Kilograms. |

- Freeze the header row. Bold the header. Set column widths so English and Arabic names are readable.
- Lock column A (and the header row) so Excel marks `id` as not for editing. Still read `id` on import if the lock is removed; the lock is a hint, not the security check.
- `price` and `cost_price` are numbers with two decimal places. Leave `cost_price` blank when the database value is NULL. `stock`, `status`, `featured`, and `id` are integers. `weight` is a number.
- Include inactive products. Do not filter the export by the product-list search.

## Import

The upload form accepts one `.xlsx` file, max 5 MB. Read the first sheet. Require the header row above, case-sensitive, in that order. If the header does not match, reject the file and change nothing.

For each data row:

1. `id` must be a positive integer that already exists in `products`. Unknown ids, blank ids, and duplicate ids in the file are errors for that row. Do not insert products. Do not delete products that are missing from the file.
2. Compare the uploaded values to the current row after normalizing: trim names, compare `price` and `cost_price` as two-decimal numbers, stock as an integer, status and featured as `1` or `0`, weight as a number. Treat a blank `cost_price` as NULL, and treat NULL and blank as equal.
3. A row with no difference is skipped.
4. A row with a difference updates only the changed columns among `name_en`, `name_ar`, `price`, `cost_price`, `stock`, `status`, `featured`, `weight`.
5. Reject the row, and do not write it, when:
   - `name_en` or `name_ar` is empty or longer than 255 characters
   - `price` is missing, not numeric, or below 0
   - `cost_price` is not numeric or below 0 (a blank cell is valid and sets NULL)
   - `stock` is missing, not an integer, or below 0
   - `status` is anything other than `1` or `0` (also accept the text `active` / `inactive`, case-insensitive, and store `1` / `0`)
   - `featured` is anything other than `1` or `0` (also accept `yes` / `no`, case-insensitive, and store `1` / `0`)
   - `weight` is missing, not numeric, or below 0

Run the accepted updates in one database transaction. If the transaction fails, roll back and show the error. Rows that failed validation stay unchanged and are listed; they do not abort the valid rows.

Before writing, show a preview the admin must confirm:

- Counts: rows in the file, rows that will update, rows unchanged, rows with errors.
- A table of the updates: product id, field, current value, new value. Cap the on-screen table at 200 changes and say when more exist.
- A table of row errors with the spreadsheet row number and the reason.
- Buttons: **Apply updates** and **Cancel**. Apply posts the stored preview (session or a temp file under a non-public temp dir), not a second blind parse that could differ. Cancel deletes that preview and writes nothing.

After apply, show how many products were updated and how many rows were skipped or rejected. Then delete the temp file.

## Done when

- Signed in as `mmurhaf`, Catalog → Excel Products downloads `products.xlsx` containing every product and the nine columns above, with Arabic names intact.
- Editing a name, a price, a cost price, a stock value, a status, featured, and a weight in that file, then uploading it, shows those changes in the preview and no other products.
- Confirming the preview updates only those fields on those products. Reloading the product list and `edit_product.php` shows the new values.
- Uploading the original unmodified file previews zero updates and writes nothing.
- A row with a bad price, a negative cost price, a negative stock, a negative weight, an empty name, or an unknown id is listed as an error and that product is unchanged.
- A blank `cost_price` clears that product’s cost price to NULL. A blank `weight` is an error and does not change the row.
- Desktop and phone-width layouts keep the sidebar, the download button, and the upload form usable.
