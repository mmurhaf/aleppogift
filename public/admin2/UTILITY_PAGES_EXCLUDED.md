# Admin Utility Pages Excluded From Main Shell

These files are intentionally not part of the shared admin shell.

## Excluded by design

- `api_status.php`: JSON endpoint, intended for machine-readable output rather than HTML shell rendering.
- `add_testimonial.php`: POST handler that redirects back to trust management; not a standalone screen.
- `generate_invoice.php`: streams the invoice PDF directly.
- `download_invoice.php`, `delete_image.php`, `set_main_image.php`: action endpoints, not admin screens.

## Diagnostic and one-off scripts intentionally left outside the shell

- `check_*.php`
- `*_test.php`
- `debug_*` and `*_debug.php`
- `fix_*`
- `analyze_*`
- legacy variants such as `regenerate_invoice_fixed.php` and `regenerate_invoice_no_auth.php`

## Legacy operational aliases now redirected to the canonical shell pages

- `dashboard1.php` -> `dashboard.php`
- `brands_0.php`, `brands_1.php` -> `brands.php`
- `categories_1.php` -> `categories.php`
- `admin_coupons.php` -> `coupons.php`
- `status.php` -> `system_status.php`
- `order_detail_0.php`, `order_detail_1.php`, `order_detail_clean.php` -> `order_detail.php`

## Rationale

- These files are mostly maintenance, migration, verification, or recovery utilities.
- Many of them emit raw debug output, JSON, or one-off operational actions.
- Keeping them out of the shared shell avoids mixing production admin UX with troubleshooting scripts.

## Main-shell admin pages

The shared shell is reserved for the operational admin experience: dashboard, catalog, orders, customers, trust management, query tools, and system status.