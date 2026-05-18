#!/usr/bin/env python3
"""
cPanel Fileman deploy — same save_file_content API as run #856/#858.
Parallel uploads (default 4) for speed; same [N/TOTAL] % log lines.
DO NOT replace with github-cpanel-fileman-sync-all.py (API 404).
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock

CRITICAL = [
    ".htaccess",
    "public/ratib-build.txt",
    "pages/about.php",
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
    "pages/home.php",
]

MUST_OK = [
    ".htaccess",
    "public/ratib-build.txt",
    "pages/about.php",
    "js/pages/ratib-profile-nav-guard.js",
]

_print_lock = Lock()


def remote_dir(remote_base: str, rel: str) -> str:
    parent = os.path.dirname(rel)
    if not parent or parent == ".":
        return remote_base
    return f"{remote_base}/{parent}"


def upload_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if not os.path.isfile(rel):
        return rel, False, "missing"
    dir_path = remote_dir(remote_base, rel)
    name = os.path.basename(rel)
    with open(rel, "rb") as f:
        raw = f.read()
    try:
        content = raw.decode("utf-8")
    except UnicodeDecodeError:
        content = raw.decode("latin-1")
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    url = f"https://{host}:{port}/execute/Fileman/save_file_content"
    data = urllib.parse.urlencode(
        {"dir": dir_path, "file": name, "content": content}
    ).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=data,
        method="POST",
        headers={
            "Authorization": f"cpanel {user}:{token}",
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            body = r.read().decode("utf-8", errors="replace")
    except Exception as e:
        return rel, False, str(e)
    try:
        d = json.loads(body)
    except json.JSONDecodeError:
        return rel, False, body[:200]
    rblock = d.get("result", d) or {}
    st = int(rblock.get("status", d.get("status", 0)) or 0)
    if st == 1:
        return rel, True, ""
    return rel, False, body[:200]


def list_all_files() -> list[str]:
    out = subprocess.check_output(
        [
            "find",
            ".",
            "-type",
            "f",
            "!",
            "-path",
            "./.git/*",
            "!",
            "-path",
            "./.github/*",
            "!",
            "-path",
            "./.cursor/*",
            "!",
            "-path",
            "./node_modules/*",
            "!",
            "-path",
            "./archive/*",
            "!",
            "-name",
            "*.md",
            "!",
            "-name",
            "*.map",
            "!",
            "-name",
            "*.log",
            "!",
            "-name",
            "*.zip",
            "!",
            "-name",
            "*.png",
            "!",
            "-name",
            "*.jpg",
            "!",
            "-name",
            "*.jpeg",
            "!",
            "-name",
            "*.gif",
            "!",
            "-name",
            "*.webp",
            "!",
            "-name",
            "*.ico",
            "!",
            "-name",
            "*.pdf",
            "!",
            "-name",
            "*.woff",
            "!",
            "-name",
            "*.woff2",
            "!",
            "-name",
            "*.ttf",
            "!",
            "-name",
            "*.mp4",
            "-size",
            "-3M",
        ],
        text=True,
    )
    files = sorted({line[2:] for line in out.splitlines() if line.startswith("./")})
    return files


def main() -> int:
    os.chdir(os.path.dirname(os.path.abspath(__file__)) + "/..")
    remote_base = os.environ.get("CPANEL_REMOTE_BASE", "/home/outratib/public_html")
    mode = os.environ.get("CPANEL_DEPLOY_MODE", "critical")
    workers = max(1, min(8, int(os.environ.get("CPANEL_UPLOAD_PARALLEL", "4"))))

    files = list_all_files() if mode == "all" else list(CRITICAL)
    total = len(files)
    to_upload: list[str] = []
    ok = 0
    fail = 0
    done = 0
    succeeded: set[str] = set()

    print(f"deploy mode={mode} files={total} parallel={workers} dest={remote_base}")

    for rel in files:
        if os.path.isfile(rel):
            to_upload.append(rel)
            continue
        done += 1
        pct = done * 100 // total if total else 100
        print(f"[{done}/{total}] {pct}% SKIP missing {rel}", flush=True)

    # Smaller files first so % climbs quickly; home.php (largest) tends to finish last.
    to_upload.sort(key=lambda p: (os.path.getsize(p), p))

    with ThreadPoolExecutor(max_workers=workers) as pool:
        futures = {
            pool.submit(upload_one, rel, remote_base): rel for rel in to_upload
        }
        for fut in as_completed(futures):
            rel, success, err = fut.result()
            done += 1
            pct = done * 100 // total if total else 100
            line = f"[{done}/{total}] {pct}% upload {rel} ... {'OK' if success else 'FAIL'}"
            with _print_lock:
                print(line, flush=True)
                if not success and err:
                    print(err[:200], flush=True)
            if success:
                ok += 1
                succeeded.add(rel)
            else:
                fail += 1

    print(
        f"\n========== Summary: ok={ok} fail={fail} total={total} "
        f"({(ok * 100 // total) if total else 0}% success) =========="
    )
    need = sum(1 for m in MUST_OK if os.path.isfile(m))
    must_hit = sum(1 for m in MUST_OK if m in succeeded)
    return 0 if must_hit >= need else 1


if __name__ == "__main__":
    sys.exit(main())
