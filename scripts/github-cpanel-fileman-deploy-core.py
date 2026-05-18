#!/usr/bin/env python3
"""
Sequential cPanel Fileman upload — same API as run #858.
One Python process (faster than 24x bash+python); no parallel (cPanel rejects #859).
"""
from __future__ import annotations

import json
import os
import sys
import urllib.parse
import urllib.request

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


def remote_dir(remote_base: str, rel: str) -> str:
    parent = os.path.dirname(rel)
    if not parent or parent == ".":
        return remote_base
    return f"{remote_base}/{parent}"


def upload_one(rel: str, remote_base: str) -> tuple[bool, str]:
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
        return False, str(e)
    try:
        d = json.loads(body)
    except json.JSONDecodeError:
        return False, body[:200]
    rblock = d.get("result", d) or {}
    st = int(rblock.get("status", d.get("status", 0)) or 0)
    if st == 1:
        return True, ""
    return False, body[:200]


def main() -> int:
    root = os.path.dirname(os.path.abspath(__file__))
    os.chdir(os.path.join(root, ".."))
    remote_base = os.environ.get("CPANEL_REMOTE_BASE", "/home/outratib/public_html")
    mode = os.environ.get("CPANEL_DEPLOY_MODE", "critical")
    files = list(CRITICAL)
    if mode == "all":
        import subprocess

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
                "-size",
                "-3M",
            ],
            text=True,
        )
        files = sorted({line[2:] for line in out.splitlines() if line.startswith("./")})

    total = len(files)
    ok = 0
    fail = 0
    succeeded: set[str] = set()
    n = 0
    print(f"deploy mode={mode} files={total} dest={remote_base}", flush=True)

    for rel in files:
        n += 1
        pct = n * 100 // total if total else 100
        if not os.path.isfile(rel):
            print(f"[{n}/{total}] {pct}% SKIP missing {rel}", flush=True)
            continue
        print(f"[{n}/{total}] {pct}% upload {rel} ... ", end="", flush=True)
        success, err = upload_one(rel, remote_base)
        if success:
            print("OK", flush=True)
            ok += 1
            succeeded.add(rel)
        else:
            print("FAIL", flush=True)
            fail += 1
            if err:
                print(err[:200], flush=True)

    print(
        f"\n========== Summary: ok={ok} fail={fail} total={total} "
        f"({(ok * 100 // total) if total else 0}% success) ==========",
        flush=True,
    )
    need = sum(1 for m in MUST_OK if os.path.isfile(m))
    must_hit = sum(1 for m in MUST_OK if m in succeeded)
    return 0 if must_hit >= need else 1


if __name__ == "__main__":
    sys.exit(main())
