#!/usr/bin/env python3
"""Upload to cPanel Fileman: critical files first (mkdir parents), optional bulk."""
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
REMOTE_BASE = os.environ.get("CPANEL_REMOTE_BASE", "/home/admin/public_html").rstrip("/")
HOST = os.environ["CPANEL_HOST"]
PORT = os.environ.get("CPANEL_PORT", "2083")
USER = os.environ["CPANEL_USER"]
TOKEN = os.environ["CPANEL_API_TOKEN"]
WORKERS = int(os.environ.get("CPANEL_FILEMAN_WORKERS", "4"))
SKIP_BULK = os.environ.get("CPANEL_SKIP_BULK", "1").strip() not in ("0", "false", "no")
MAX_BYTES = int(os.environ.get("CPANEL_FILEMAN_MAX_BYTES", str(3 * 1024 * 1024)))
API = f"https://{HOST}:{PORT}/execute"

SKIP_DIRS = {".git", ".github", ".cursor", "node_modules", "archive", ".idea", ".vscode"}
SKIP_SUFFIX = {".md", ".map", ".log", ".zip", ".tar", ".gz", ".7z", ".exe", ".dll", ".pyc"}
BINARY_SUFFIX = {".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".pdf", ".mp4", ".woff", ".woff2", ".ttf", ".eot"}

CRITICAL_ORDER = [
    ".htaccess",
    "public/rateb-build.txt",
    "profile/index.php",
    "pages/about.php",
    "pages/deploy-root.php",
    "pages/company-profile.php",
    "includes/rateb-public-base-url.php",
    "includes/rateb-home-public-nav-bootstrap.php",
    "includes/rateb-home-public-chrome-top.php",
    "includes/rateb-home-public-nav-sync.php",
    "includes/rateb-home-public-footer.php",
    "includes/rateb-profile-nav-guard.php",
    "includes/rateb_html_global_ai_patch.php",
    "includes/rateb-about-profile-data.php",
    "includes/rateb-about-sections.php",
    "js/pages/rateb-profile-nav-guard.js",
    "js/pages/rateb-mega-nav.js",
    "js/pages/rateb-home-nav-chrome.js",
    "js/pages/about-enterprise.js",
    "css/pages/about-enterprise.css",
    "css/pages/home-public.css",
    "css/pages/rateb-mega-nav.css",
    "public/index.php",
    "pages/home.php",
]

_mkdir_cache: set[str] = set()


def api_post(module: str, params: dict) -> dict:
    data = urllib.parse.urlencode(params).encode("utf-8")
    req = urllib.request.Request(
        f"{API}/{module}",
        data=data,
        method="POST",
        headers={
            "Authorization": f"cpanel {USER}:{TOKEN}",
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )
    with urllib.request.urlopen(req, timeout=90) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def ensure_remote_dir(remote_dir: str) -> None:
    if remote_dir in _mkdir_cache or remote_dir == REMOTE_BASE:
        return
    parts = remote_dir[len(REMOTE_BASE) :].strip("/").split("/")
    built = REMOTE_BASE
    for part in parts:
        if not part:
            continue
        built = f"{built}/{part}"
        if built in _mkdir_cache:
            continue
        try:
            api_post("Fileman/mkdir", {"path": built, "permissions": "0755"})
        except Exception:
            pass
        _mkdir_cache.add(built)


def should_skip(rel: str) -> bool:
    parts = rel.split("/")
    if any(p in SKIP_DIRS for p in parts):
        return True
    low = rel.lower()
    return any(low.endswith(s) for s in SKIP_SUFFIX | BINARY_SUFFIX)


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
    ensure_remote_dir(remote_dir)
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
        try:
            payload = api_post(
                "Fileman/save_file_content",
                {"dir": remote_dir, "file": name, "content": content},
            )
            result = payload.get("result", payload) or {}
            if int(result.get("status", payload.get("status", 0)) or 0) == 1:
                return rel, True, ""
            last_err = json.dumps(result)[:180]
        except Exception as exc:  # noqa: BLE001
            last_err = str(exc)[:180]
        if attempt < retries:
            time.sleep(1.0)
    return rel, False, last_err


def main() -> int:
    print(f"dest={REMOTE_BASE} skip_bulk={SKIP_BULK} workers={WORKERS}")
    crit_fail: list[str] = []
    for rel in CRITICAL_ORDER:
        if not (ROOT / rel).is_file():
            print(f"SKIP missing {rel}")
            continue
        print(f"critical {rel} ... ", end="", flush=True)
        _, ok, err = upload_file(rel, retries=3)
        print("OK" if ok else f"FAIL {err}")
        if not ok:
            crit_fail.append(rel)

    if crit_fail:
        print("CRITICAL_FAIL", ",".join(crit_fail))
        return 1

    if SKIP_BULK:
        print("bulk skipped (CPANEL_SKIP_BULK=1) — fast deploy")
        return 0

    rest = collect_rest()
    print(f"bulk {len(rest)} files ...")
    ok = fail = 0
    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        futures = [pool.submit(upload_file, rel) for rel in rest]
        for i, fut in enumerate(as_completed(futures), 1):
            rel, success, err = fut.result()
            if success:
                ok += 1
            else:
                fail += 1
                if fail <= 15:
                    print(f"FAIL {rel} {err}")
            if i % 150 == 0:
                print(f"progress {i}/{len(rest)}")
    print(f"bulk ok={ok} fail={fail}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
