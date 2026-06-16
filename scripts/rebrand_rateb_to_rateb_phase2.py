#!/usr/bin/env python3
"""Replace remaining rateb tokens with rateb in runtime code (phase 2 — paths, functions, tables)."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIRS = {"archive", "Designed", "node_modules", ".git", "vendor", "rateb_mobile"}
ALLOW_EXT = {".php", ".js", ".css", ".htaccess", ".example", ".yml", ".sh", ".py", ".inc", ".sql"}

# Order matters: longer / more specific tokens first.
REPLACEMENTS = [
    ("rateb_site_content_", "rateb_site_content_"),
    ("rateb_site_content", "rateb_site_content"),
    ("rateb_uploads", "rateb_uploads"),
    ("rateb_cms_cache", "rateb_cms_cache"),
    ("rateb_cms_media", "rateb_cms_media"),
    ("__rateb_home", "__rateb_home"),
    ("rateb-site-content", "rateb-site-content"),
    ("rateb-home-deploy", "rateb-home-deploy"),
    ("rateb-payment-trace", "rateb-payment-trace"),
    ("rateb_api_session", "rateb_api_session"),
    ("rateb-control-sso", "rateb-control-sso"),
    ("rateb_control_sso", "rateb_control_sso"),
    ("rateb-mega-nav", "rateb-mega-nav"),
    ("rateb-enterprise", "rateb-enterprise"),
    ("rateb-operational", "rateb-operational"),
    ("rateb-architecture", "rateb-architecture"),
    ("rateb-procurement", "rateb-procurement"),
    ("rateb-security", "rateb-security"),
    ("rateb-marketing", "rateb-marketing"),
    ("rateb-barcode", "rateb-barcode"),
    ("rateb-gallery", "rateb-gallery"),
    ("rateb-profile", "rateb-profile"),
    ("rateb-chrome", "rateb-chrome"),
    ("rateb-public", "rateb-public"),
    ("rateb-home", "rateb-home"),
    ("rateb-about", "rateb-about"),
    ("rateb-brand", "rateb-brand"),
    ("rateb-clean-url", "rateb-clean-url"),
    ("rateb-page-stamp", "rateb-page-stamp"),
    ("rateb-overlay", "rateb-overlay"),
    ("rateb-build", "rateb-build"),
    ("rateb-deploy", "rateb-deploy"),
    ("rateb-perms", "rateb-perms"),
    ("rateb-fix", "rateb-fix"),
    ("rateb-live", "rateb-live"),
    ("rateb-rebrand", "rateb-rebrand"),
    ("rateb-cms", "rateb-cms"),
    ("rateb-purge", "rateb-purge"),
    ("rateb-which", "rateb-which"),
    ("rateb-mobile", "rateb-mobile"),
    ("rateb-op-proof", "rateb-op-proof"),
    ("rateb-nav", "rateb-nav"),
    ("rateb-qr", "rateb-qr"),
    ("rateb_html", "rateb_html"),
    ("rateb_global", "rateb_global"),
    ("rateb-php74", "rateb-php74"),
    ("admin_", "admin_"),
    ("RATEB", "RATEB"),
]


def should_process(path: Path) -> bool:
    if path.name == ".htaccess":
        return True
    if path.suffix.lower() in ALLOW_EXT or path.name.endswith(".env.example"):
        return True
    return False


def main() -> None:
    changed: list[str] = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            path = Path(dirpath) / fn
            if not should_process(path):
                continue
            try:
                text = path.read_text(encoding="utf-8", errors="replace")
            except OSError:
                continue
            orig = text
            for old, new in REPLACEMENTS:
                text = text.replace(old, new)
            if text != orig:
                path.write_text(text, encoding="utf-8", newline="\n")
                changed.append(str(path.relative_to(ROOT)))
    print(f"Updated {len(changed)} files")
    for p in sorted(changed):
        print(p)


if __name__ == "__main__":
    main()
