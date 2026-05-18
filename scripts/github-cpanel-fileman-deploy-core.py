#!/usr/bin/env python3
"""
cPanel Fileman deploy — same save_file_content API as run #858/#860.
fast mode: FAST_FILES baseline + any commit-changed paths under DEPLOY_ALLOW_PREFIXES.
critical/all on manual full sync only.
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

# Paths auto-uploaded when changed in the pushed commit (see build_file_list).
DEPLOY_ALLOW_PREFIXES = (
    "includes/",
    "pages/",
    "control-panel/",
    "js/",
    "css/",
    "api/",
    "config/env/",
    "public/",
)
DEPLOY_ALLOW_FILES = frozenset({
    ".htaccess",
    "index.php",
    "ratib-profile-fix.php",
})
DEPLOY_DENY_PREFIXES = (
    "Designed/",
    ".git/",
    ".github/",
    ".cursor/",
    "archive/",
    "node_modules/",
)
FAST_DEPLOY_CHANGED_CAP = 40

# ~1–2 min on push: always-sync core + build marker LAST.
FAST_FILES = [
    ".htaccess",
    "index.php",
    "includes/ratib-public-base-url.php",
    "includes/ratib_html_global_ai_patch.php",
    "includes/ratib-mega-nav-config.php",
    "includes/ratib-mega-nav-resolve.php",
    "includes/ratib-mega-nav-resolve.fallback.php",
    "includes/ratib-mega-nav-render.php",
    "includes/ratib-home-public-nav-bootstrap.php",
    "includes/ratib-home-public-footer.php",
    "includes/ratib-enterprise-trust-home.php",
    "includes/ratib-public-deploy-ensure.php",
    "pages/about.php",
    "pages/home.php",
    "includes/ratib-about-sections.php",
    "includes/ratib-about-profile-data.php",
    "includes/ratib-home-public-chrome-top.php",
    "includes/ratib-home-public-nav-sync.php",
    "includes/ratib-profile-nav-guard.php",
    "css/pages/about-enterprise.css",
    "js/pages/ratib-profile-nav-guard.js",
    "js/pages/ratib-mega-nav.js",
    "js/pages/home-page.js",
    "css/pages/home-public.css",
    "css/pages/ratib-mega-nav.css",
    "pages/deploy-root.php",
    "pages/ratib-which-page.php",
    "pages/ratib-purge-cache.php",
    "includes/ratib-page-stamp.php",
    "includes/ratib-profile-force-same-tab.php",
    "public/ratib-build.txt",
    "control-panel/includes/control/public-marketing-urls.php",
    "control-panel/includes/control/sidebar.php",
    "control-panel/includes/control/registration-requests-content.php",
    "control-panel/pages/control/control-hub.php",
    "control-panel/pages/control-support-chats.php",
    "control-panel/pages/control-agencies.php",
    "control-panel/includes/control/layout-wrapper.php",
    "control-panel/includes/control/client-platform-nav.php",
]

CRITICAL = [
    ".htaccess",
    "public/ratib-build.txt",
    "pages/about.php",
    "pages/deploy-root.php",
    "pages/company-profile.php",
    "includes/ratib-public-base-url.php",
    "includes/ratib-mega-nav-config.php",
    "includes/ratib-mega-nav-resolve.php",
    "includes/ratib-mega-nav-resolve.fallback.php",
    "includes/ratib-mega-nav-render.php",
    "includes/ratib-home-public-nav-bootstrap.php",
    "includes/ratib-enterprise-trust-home.php",
    "includes/ratib-public-deploy-ensure.php",
    "includes/ratib-home-public-chrome-top.php",
    "includes/ratib-home-public-nav-sync.php",
    "includes/ratib-home-public-footer.php",
    "includes/ratib-profile-nav-guard.php",
    "js/pages/ratib-mega-nav.js",
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
    "control-panel/includes/control/public-marketing-urls.php",
    "control-panel/includes/control/sidebar.php",
    "control-panel/includes/control/registration-requests-content.php",
    "control-panel/pages/control/control-hub.php",
    "control-panel/pages/control-support-chats.php",
    "control-panel/pages/control-agencies.php",
]

CRITICAL_SET = set(CRITICAL) | set(FAST_FILES)

MUST_OK = [
    ".htaccess",
    "public/ratib-build.txt",
    "pages/about.php",
    "pages/home.php",
    "includes/ratib-mega-nav-resolve.php",
    "includes/ratib-mega-nav-resolve.fallback.php",
    "js/pages/ratib-profile-nav-guard.js",
]

_print_lock = Lock()


def is_auto_deploy_path(path: str) -> bool:
    if not path or path.startswith("."):
        return False
    for deny in DEPLOY_DENY_PREFIXES:
        if path.startswith(deny) or f"/{deny}" in f"/{path}/":
            return False
    if path in DEPLOY_ALLOW_FILES:
        return True
    return any(path.startswith(prefix) for prefix in DEPLOY_ALLOW_PREFIXES)


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
        with urllib.request.urlopen(req, timeout=45) as r:
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


def git_changed_paths() -> set[str]:
    sha = os.environ.get("GITHUB_SHA", "").strip()
    if not sha:
        return set()
    try:
        out = subprocess.check_output(
            ["git", "diff-tree", "--no-commit-id", "-r", "--name-only", sha],
            text=True,
        )
        return {line.strip() for line in out.splitlines() if line.strip()}
    except Exception:
        return set()


def build_file_list(mode: str) -> tuple[list[str], int]:
    """Return ordered file list and parallel worker count."""
    if mode == "all":
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
        return files, 3

    if mode == "critical":
        return list(CRITICAL), 3

    # fast (default on push): baseline FAST_FILES + any changed deployable paths in this commit
    marker = FAST_FILES[-1]
    core = [f for f in FAST_FILES if f != marker]
    seen = set(core)
    seen.add(marker)
    extras: list[str] = []
    for path in sorted(git_changed_paths()):
        if path in seen:
            continue
        if not is_auto_deploy_path(path):
            continue
        if not os.path.isfile(path):
            continue
        extras.append(path)
        seen.add(path)
        if len(extras) >= FAST_DEPLOY_CHANGED_CAP:
            break
    files = core + extras + [marker]
    if extras:
        print(
            f"fast deploy: +{len(extras)} commit-changed file(s): {', '.join(extras[:8])}"
            + (" …" if len(extras) > 8 else ""),
            flush=True,
        )
    return files, 2


def run_uploads(files: list[str], remote_base: str, workers: int) -> tuple[int, int, set[str]]:
    total = len(files)
    ok = 0
    fail = 0
    succeeded: set[str] = set()
    done = 0

    existing = [f for f in files if os.path.isfile(f)]
    for rel in files:
        if rel in existing:
            continue
        done += 1
        pct = done * 100 // total if total else 100
        print(f"[{done}/{total}] {pct}% SKIP missing {rel}", flush=True)

    if not existing:
        return ok, fail, succeeded

    if workers <= 1:
        n = done
        for rel in existing:
            n += 1
            pct = n * 100 // total if total else 100
            print(f"[{n}/{total}] {pct}% upload {rel} ... ", end="", flush=True)
            _, success, err = upload_one(rel, remote_base)
            if success:
                print("OK", flush=True)
                ok += 1
                succeeded.add(rel)
            else:
                print("FAIL", flush=True)
                fail += 1
                if err:
                    print(err[:200], flush=True)
        return ok, fail, succeeded

    with ThreadPoolExecutor(max_workers=workers) as pool:
        futures = [pool.submit(upload_one, rel, remote_base) for rel in existing]
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
    return ok, fail, succeeded


def main() -> int:
    root = os.path.dirname(os.path.abspath(__file__))
    os.chdir(os.path.join(root, ".."))
    remote_base = os.environ.get("CPANEL_REMOTE_BASE", "/home/outratib/public_html")
    mode = os.environ.get("CPANEL_DEPLOY_MODE", "fast")
    files, workers = build_file_list(mode)
    total = len(files)

    print(
        f"deploy mode={mode} files={total} parallel={workers} dest={remote_base}",
        flush=True,
    )
    ok, fail, succeeded = run_uploads(files, remote_base, workers)
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
