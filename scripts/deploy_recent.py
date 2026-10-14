"""
Upload recently modified site files to production, and optionally run the
database migrations there through a one-shot web runner.

FTP credentials and the remote root come from config/ftp_credentials.php
(see deploy_changed.py). Local-only paths (config/, .env, dumps, docs, tooling)
are never uploaded.

Usage:
    python scripts/deploy_recent.py --dry-run               # files modified today
    python scripts/deploy_recent.py --hours 6 --yes
    python scripts/deploy_recent.py --since "2026-10-12 08:00" --yes --migrate
    python scripts/deploy_recent.py --migrate-only --yes    # no file upload
"""

from __future__ import annotations

import argparse
import secrets
import sys
import urllib.error
import urllib.request
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from deploy_changed import connect, load_ftp_config, remote_path, upload  # noqa: E402

SITE_URL = "https://aleppogift.com"
RUNNER_TEMPLATE = ROOT / "scripts" / "prod_migrate_runner.php"
MIGRATION_STEPS = ("features", "names", "translations")

# Files the runner needs on the server, uploaded with --migrate even if unchanged.
MIGRATION_FILES = [
    "database/apply_additional_features.php",
    "database/create_translations_table.sql",
    "database/update_names_ru_sp.sql",
    "includes/i18n.php",
]

EXCLUDED_DIRS = {
    ".git", ".cursor", ".vscode", "vendor", "node_modules", "config", "logs",
    "scripts", "invoice", "uploads", "backups", "backup", "testing", "tmp", "cache",
}
EXCLUDED_NAMES = {"deploy-config.json", "composer.json", "composer.lock"}
UPLOAD_EXTENSIONS = {
    ".php", ".js", ".css", ".html", ".xml", ".txt",
    ".png", ".jpg", ".jpeg", ".webp", ".gif", ".svg", ".ico",
    ".woff", ".woff2", ".ttf", ".otf",
}


def is_deployable(relative: Path) -> bool:
    parts = relative.parts
    if any(part in EXCLUDED_DIRS for part in parts[:-1]):
        return False
    name = parts[-1]
    if name == ".htaccess":
        return True
    if name.startswith(".") or name in EXCLUDED_NAMES:
        return False
    suffix = relative.suffix.lower()
    if suffix == ".sql":
        return len(parts) == 2 and parts[0] == "database"
    if suffix == ".txt" and len(parts) == 1:
        return name == "robots.txt"
    return suffix in UPLOAD_EXTENSIONS


def recent_files(since: datetime) -> list[Path]:
    cutoff = since.timestamp()
    found = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        relative = path.relative_to(ROOT)
        if not is_deployable(relative):
            continue
        if path.stat().st_mtime >= cutoff:
            found.append(path)
    return sorted(found, key=lambda p: p.relative_to(ROOT).as_posix())


def parse_since(args: argparse.Namespace) -> datetime:
    if args.since:
        return datetime.strptime(args.since, "%Y-%m-%d %H:%M")
    if args.hours is not None:
        return datetime.now() - timedelta(hours=args.hours)
    return datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)


def run_migrations(config: dict, site_url: str, steps: list[str]) -> bool:
    token = secrets.token_hex(24)
    runner_name = f"ag_migrate_{secrets.token_hex(8)}.php"
    runner_remote = remote_path(config["remote_root"], runner_name)
    template = RUNNER_TEMPLATE.read_text(encoding="utf-8")
    body = template.replace("'__AG_MIGRATE_TOKEN__'", f"'{token}'", 1).encode("utf-8")

    ftp = connect(config)
    try:
        from io import BytesIO
        print(f"Uploading migration runner as {runner_name}")
        ftp.storbinary(f"STOR {runner_remote}", BytesIO(body))
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

    url = f"{site_url.rstrip('/')}/{runner_name}?token={token}&steps={','.join(steps)}"
    output = ""
    try:
        request = urllib.request.Request(url, headers={"User-Agent": "aleppogift-deploy"})
        with urllib.request.urlopen(request, timeout=600) as response:
            output = response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        output = f"HTTP {exc.code}: {exc.read().decode('utf-8', errors='replace')[:2000]}"
    except Exception as exc:
        output = f"Request failed: {exc}"
    finally:
        ftp = connect(config)
        try:
            ftp.delete(runner_remote)
            print("Runner deleted from server.")
        except Exception:
            print("Runner already removed itself.")
        finally:
            try:
                ftp.quit()
            except Exception:
                ftp.close()

    print("---- migration output ----")
    print(output.strip())
    print("--------------------------")
    return "RESULT: OK" in output


def main() -> int:
    parser = argparse.ArgumentParser(description="Deploy recently modified Aleppo Gift files.")
    window = parser.add_mutually_exclusive_group()
    window.add_argument("--hours", type=float, help="Files modified in the last N hours (default: since midnight).")
    window.add_argument("--since", help='Files modified since "YYYY-MM-DD HH:MM".')
    parser.add_argument("--dry-run", action="store_true", help="List files and do not connect.")
    parser.add_argument("--yes", action="store_true", help="Do not ask for confirmation.")
    parser.add_argument("--migrate", action="store_true", help="Run database migrations on production after uploading.")
    parser.add_argument("--migrate-only", action="store_true", help="Run migrations without uploading recent files.")
    parser.add_argument("--steps", default=",".join(MIGRATION_STEPS),
                        help=f"Comma-separated migration steps ({', '.join(MIGRATION_STEPS)}).")
    parser.add_argument("--site-url", default=SITE_URL)
    args = parser.parse_args()

    steps = [s for s in args.steps.split(",") if s]
    unknown = [s for s in steps if s not in MIGRATION_STEPS]
    if unknown:
        raise SystemExit(f"Unknown migration steps: {', '.join(unknown)}")
    migrate = args.migrate or args.migrate_only

    paths: list[Path] = []
    if not args.migrate_only:
        since = parse_since(args)
        paths = recent_files(since)
        print(f"Modified since {since:%Y-%m-%d %H:%M}: {len(paths)} files")
    if migrate:
        for relative in MIGRATION_FILES:
            path = ROOT / relative
            if path.is_file() and path not in paths:
                paths.append(path)

    for path in paths:
        print(f"  {path.relative_to(ROOT).as_posix()}")
    if migrate:
        print(f"Migrations: {', '.join(steps)}")

    if args.dry_run:
        print("Dry run only. Nothing was uploaded.")
        return 0
    if not paths and not migrate:
        print("No files to upload.")
        return 0

    config = load_ftp_config()
    print(f"Host: {config['host']}:{config['port']}  Remote root: {config['remote_root']}")

    if not args.yes:
        answer = input("Deploy to production? Type yes: ").strip().lower()
        if answer != "yes":
            print("Cancelled.")
            return 0

    failed = 0
    if paths:
        ftp = connect(config)
        try:
            ok, failed = upload(ftp, config, paths)
        finally:
            try:
                ftp.quit()
            except Exception:
                ftp.close()
        print(f"Uploaded {ok}, failed {failed}.")
        if failed:
            print("Upload failures; skipping migrations.")
            return 1

    if migrate and not run_migrations(config, args.site_url, steps):
        print("Migration FAILED. See output above.")
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
