#!/usr/bin/env python3
"""Upload project to cPanel: critical files first, then all code files in parallel."""
from __future__ import annotations

import json
import os
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REMOTE_BASE = os.environ.get("CPANEL_REMOTE_BASE", "/home/outratib/public_html").rstrip("/")
HOST = os.environ["CPANEL_HOST"]
PORT = os.environ.get("CPANEL_PORT", "2083")
USER = os.environ["CPANEL_USER"]
TOKEN = os.environ["CPANEL_API_TOKEN"]
WORKERS = int(os.environ.get("CPANEL_FILEMAN_WORKERS", "6"))
MAX_BYTES = int(os.environ.get("CPANEL_FILEMAN_MAX_BYTES", str(2 * 1024 * 1024)))
API_URL = f"https://{HOST}:{PORT}/execute/Fileman/save_file_content"

SKIP_DIRS = {".git", ".github", ".cursor", "node_modules", "archive", ".idea", ".vscode"}
SKIP_SUFFIX = {".md", ".map", ".log", ".zip", ".tar", ".gz", ".7z", ".exe", ".dll", ".pyc"}
BINARY_SUFFIX = {".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".pdf", ".mp4", ".woff", ".woff2", ".ttf", ".eot"}

# Uploaded first, sequentially — must all succeed.
CRITICAL_ORDER = [
    ".htaccess",
    "public/ratib-build.txt",
    "profile/index.php",
    "pages/about.php",
    "pages/home.php",
    "pages/deploy-root.php",
    "pages/company-profile.php",
    "includes/ratib-public-base-url.php",
    "includes/ratib-home-public-nav-bootstrap.php",
    "includes/ratib-home-public-chrome-top.php",
    "includes/ratib-home-public-nav-sync.php",
    "includes/ratib-home-public-footer.php",
    "includes/ratib-profile-nav-guard.php",
    "includes/ratib_html_global_ai_patch.php",
    "includes/ratib-about-profile-data.php",
    "includes/ratib-about-sections.php",
    "js/pages/ratib-profile-nav-guard.js",
    "js/pages/ratib-mega-nav.js",
    "js/pages/ratib-home-nav-chrome.js",
    "js/pages/about-enterprise.js",
    "js/pages/home-page.js",
    "css/pages/about-enterprise.css",
    "css/pages/home-public.css",
    "css/pages/ratib-mega-nav.css",
    "public/index.php",
]


def should_skip(rel: str) -> bool:
    parts = rel.split("/")
    if any(p in SKIP_DIRS for p in parts):
        return True
    low = rel.lower()
    if any(low.endswith(s) for s in SKIP_SUFFIX):
        return True
    if any(low.endswith(s) for s in BINARY_SUFFIX):
        return True
    return False


def collect_rest() -> list[str]:
    critical_set = set(CRITICAL_ORDER)
    rest: list[str] = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT).as_posix()
        if rel in critical_set or should_skip(rel):
            continue
        if path.stat().st_size > MAX_BYTES:
            continue
        rest.append(rel)
    return sorted(rest)


def upload_file(rel: str, retries: int = 2) -> tuple[str, bool, str]:
    full = ROOT / rel
    if not full.is_file():
        return rel, False, "missing locally"
    if full.stat().st_size > MAX_BYTES:
        return rel, False, "too large"
    remote_dir = f"{REMOTE_BASE}/{os.path.dirname(rel)}"
    if os.path.dirname(rel) in ("", "."):
        remote_dir = REMOTE_BASE
    name = os.path.basename(rel)
    try:
        raw = full.read_bytes()
        try:
            content = raw.decode("utf-8")
        except UnicodeDecodeError:
            content = raw.decode("latin-1")
    except OSError as exc:
        return rel, False, str(exc)

    last_err = ""
    for attempt in range(retries + 1):
        data = urllib.parse.urlencode({"dir": remote_dir, "file": name, "content": content}).encode("utf-8")
        req = urllib.request.Request(
            API_URL,
            data=data,
            method="POST",
            headers={
                "Authorization": f"cpanel {USER}:{TOKEN}",
                "Content-Type": "application/x-www-form-urlencoded",
            },
        )
        try:
            with urllib.request.urlopen(req, timeout=90) as resp:
                body = resp.read().decode("utf-8", errors="replace")
            payload = json.loads(body)
            result = payload.get("result", payload) or {}
            if int(result.get("status", payload.get("status", 0)) or 0) == 1:
                return rel, True, ""
            last_err = body[:180]
        except Exception as exc:  # noqa: BLE001
            last_err = str(exc)[:180]
        if attempt < retries:
            time.sleep(1.5)
    return rel, False, last_err


def main() -> int:
    print(f"sync dest={REMOTE_BASE} workers={WORKERS} max_bytes={MAX_BYTES}")

    crit_fail: list[str] = []
    for rel in CRITICAL_ORDER:
        if not (ROOT / rel).is_file():
            print(f"SKIP critical missing {rel}")
            continue
        print(f"critical {rel} ... ", end="", flush=True)
        _, ok, err = upload_file(rel, retries=3)
        if ok:
            print("OK")
        else:
            print(f"FAIL {err}")
            crit_fail.append(rel)

    if crit_fail:
        print("CRITICAL FAIL:", ", ".join(crit_fail))
        return 1

    rest = collect_rest()
    print(f"bulk upload {len(rest)} code files ...")
    ok = 0
    fail = 0
    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        futures = [pool.submit(upload_file, rel) for rel in rest]
        for i, fut in enumerate(as_completed(futures), 1):
            rel, success, err = fut.result()
            if success:
                ok += 1
            else:
                fail += 1
                if fail <= 20:
                    print(f"FAIL {rel} {err}")
            if i % 100 == 0:
                print(f"progress {i}/{len(rest)} ok={ok} fail={fail}")

    print(f"Summary critical=OK bulk_ok={ok} bulk_fail={fail} bulk_total={len(rest)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
