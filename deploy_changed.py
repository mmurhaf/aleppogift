"""
Upload the storefront files changed in this session to production.

Credentials and the remote root come from config/ftp_credentials.php.
The password is not stored in this file.

Usage:
    python deploy_changed.py --dry-run
    python deploy_changed.py --yes
"""

from __future__ import annotations

import argparse
import re
import sys
from ftplib import FTP, FTP_TLS, error_perm
from pathlib import Path

ROOT = Path(__file__).resolve().parent
CREDENTIALS = ROOT / "config" / "ftp_credentials.php"

# Products catalog layout aligned with the homepage shop grid.
CHANGED_FILES = [
    "includes/bootstrap.php",
    "includes/footer.php",
    "includes/header.php",
    "includes/shipping.php",
    "includes/store_features.php",
    "includes/supplier_import.php",
    "includes/generate_invoice_pdf.php",
    "includes/product_reviews_block.php",
    "includes/ZiinaPayment.php",
    "database/apply_additional_features.php",
    "public/index.php",
    "public/products.php",
    "public/product.php",
    "public/cart.php",
    "public/checkout.php",
    "public/shipping.php",
    "public/thankyou.php",
    "public/payment.php",
    "public/process_contact.php",
    "public/login.php",
    "public/register.php",
    "public/logout.php",
    "public/forgot_password.php",
    "public/reset_password.php",
    "public/account.php",
    "public/account_orders.php",
    "public/account_order.php",
    "public/account_profile.php",
    "public/account_addresses.php",
    "public/wishlist.php",
    "public/track_order.php",
    "public/set_language.php",
    "public/newsletter_unsubscribe.php",
    "public/submit_review.php",
    "public/mark_helpful.php",
    "public/ajax/add_to_cart.php",
    "public/ajax/wishlist_toggle.php",
    "public/ajax/calculate_shipping.php",
    "public/ajax/subscribe_newsletter.php",
    "public/assets/js/storefront.js",
    "public/admin/includes/header.php",
    "public/admin/settings.php",
    "public/admin/orders.php",
    "public/admin/edit_invoice.php",
    "public/admin/order_detail.php",
    "public/admin/products.php",
    "public/admin/edit_product.php",
    "public/admin/reviews.php",
    "public/admin/login.php",
    "public/admin/email_client.php",
    "public/admin/shipping.php",
    "public/admin/contact_messages.php",
    "public/admin/newsletter.php",
    "public/admin/gallery.php",
    "public/admin/users.php",
    "public/admin/supplier_import.php",
    "public/admin/data_import.php",
    "public/admin/api_status.php",
    "public/admin/packing_slip.php",
    # Storefront translations (en/ar/ru/sp)
    "includes/i18n.php",
    "includes/currencies.php",
    "includes/visitor_open.php",
    "includes/review_section.php",
    "includes/social_proof_widgets.php",
    "includes/trust_badges.php",
    "includes/cart_line.php",
    "includes/cart_helpers.php",
    "includes/helpers/cart.php",
    "includes/helpers/bundles.php",
    "includes/brands.php",
    "includes/categories.php",
    *[f"includes/lang/{lang}/{section}.php"
      for lang in ("en", "ar", "ru")
      for section in ("common", "date", "catalog", "product", "cart", "checkout",
                      "order", "account", "pages", "js")],
    "includes/lang/sp/common.php",
    "database/create_translations_table.sql",
    "database/sync_translations.php",
    "database/check_translations.php",
    "public/about.php",
    "public/contact.php",
    "public/faq.php",
    "public/testimonials.php",
    "public/customer_gallery.php",
    "public/404.php",
    "public/403.php",
    "public/terms_of_service.php",
    "public/privacy_policy.php",
    "public/returns.php",
    "public/site.php",
    "public/ajax/add_bundle_to_cart.php",
    "public/ajax/update_cart_qty.php",
    "public/ajax/remove_from_cart.php",
    "public/ajax/cart_preview.php",
    "public/ajax/apply_coupon.php",
    "public/ajax/set_currency.php",
    "public/assets/js/main.js",
    "public/assets/js/index.js",
    "public/assets/js/enhanced-main.js",
    "public/assets/js/enhanced-ui.js",
    "public/assets/js/cart-simple.js",
    # Shared cart (used by cart.php and ajax/cart_preview.php)
    "includes/shared_cart.php",
    "public/ajax/update_shared_cart.php",
    "public/ajax/save_shared_cart.php",
    "public/ajax/add_to_cart_simple.php",
    "public/ajax/clear_cart.php",
    "public/ajax/cart_operations.php",
    "public/admin/shared_carts.php",
]


def php_value(text: str, key: str) -> str | None:
    match = re.search(rf"'{re.escape(key)}'\s*=>\s*'([^']*)'", text)
    if not match:
        return None
    return match.group(1).replace("\\\\", "\\")


def load_ftp_config() -> dict:
    if not CREDENTIALS.is_file():
        raise SystemExit(f"Missing FTP config: {CREDENTIALS}")

    text = CREDENTIALS.read_text(encoding="utf-8")
    host = php_value(text, "host")
    user = php_value(text, "user")
    password = php_value(text, "password")
    remote_root = php_value(text, "production_root")
    port_match = re.search(r"'port'\s*=>\s*(\d+|null)", text)
    port = 21
    if port_match and port_match.group(1) != "null":
        port = int(port_match.group(1))

    if not host or not user or not password or not remote_root:
        raise SystemExit("Could not read host, user, password, or production_root from ftp_credentials.php")

    return {
        "host": host,
        "port": port,
        "user": user,
        "password": password,
        "remote_root": remote_root.rstrip("/"),
        "use_tls": "TLS" in text or "tls" in text.lower(),
    }


def connect(config: dict) -> FTP:
    if config["use_tls"]:
        try:
            ftp = FTP_TLS()
            ftp.connect(config["host"], config["port"], timeout=30)
            ftp.login(config["user"], config["password"])
            ftp.prot_p()
            ftp.set_pasv(True)
            return ftp
        except Exception as exc:
            print(f"Explicit TLS failed ({exc}). Trying plain FTP.")

    ftp = FTP()
    ftp.connect(config["host"], config["port"], timeout=30)
    ftp.login(config["user"], config["password"])
    ftp.set_pasv(True)
    return ftp


def ensure_remote_dir(ftp: FTP, remote_dir: str, created: set[str]) -> None:
    parts = [part for part in remote_dir.split("/") if part]
    current = ""
    for part in parts:
        current += "/" + part
        if current in created:
            continue
        try:
            ftp.mkd(current)
        except error_perm:
            pass
        created.add(current)


def remote_path(remote_root: str, relative: str) -> str:
    return remote_root + "/" + relative.replace("\\", "/").lstrip("/")


def files_to_upload() -> list[Path]:
    missing = []
    found = []
    for relative in CHANGED_FILES:
        path = ROOT / relative
        if path.is_file():
            found.append(path)
        else:
            missing.append(relative)
    if missing:
        print("Missing locally, will skip:")
        for name in missing:
            print(f"  {name}")
    return found


def upload(ftp: FTP, config: dict, paths: list[Path]) -> tuple[int, int]:
    created: set[str] = set()
    ok = 0
    failed = 0
    for path in paths:
        relative = path.relative_to(ROOT).as_posix()
        destination = remote_path(config["remote_root"], relative)
        remote_dir = destination.rsplit("/", 1)[0]
        print(f"Uploading {relative}")
        try:
            ensure_remote_dir(ftp, remote_dir, created)
            with path.open("rb") as handle:
                ftp.storbinary(f"STOR {destination}", handle)
            ok += 1
        except Exception as exc:
            failed += 1
            print(f"  FAILED {relative}: {exc}")
    return ok, failed


def main() -> int:
    parser = argparse.ArgumentParser(description="Deploy the changed Aleppo Gift storefront files.")
    parser.add_argument("--dry-run", action="store_true", help="Print the upload list and do not connect.")
    parser.add_argument("--yes", action="store_true", help="Upload without asking for confirmation.")
    args = parser.parse_args()

    config = load_ftp_config()
    paths = files_to_upload()
    if not paths:
        print("No files to upload.")
        return 1

    print(f"Host: {config['host']}:{config['port']}")
    print(f"User: {config['user']}")
    print(f"Remote root: {config['remote_root']}")
    print(f"Files: {len(paths)}")
    for path in paths:
        print(f"  {path.relative_to(ROOT).as_posix()}")

    if args.dry_run:
        print("Dry run only. Nothing was uploaded.")
        return 0

    if not args.yes:
        answer = input("Upload these files to production? Type yes: ").strip().lower()
        if answer != "yes":
            print("Cancelled.")
            return 0

    ftp = connect(config)
    try:
        ok, failed = upload(ftp, config, paths)
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

    print(f"Uploaded {ok}, failed {failed}.")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
