#!/usr/bin/env python3
"""
cPanel Fileman deploy — text via save_file_content; images/SVG via upload_files (multipart).
fast mode: FAST_FILES baseline + any commit-changed paths under DEPLOY_ALLOW_PREFIXES.
critical/all on manual full sync only.
"""
from __future__ import annotations

import json
import mimetypes
import os
import secrets
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
    "public/profile-media/",
    "assets/images/government/",
    "assets/images/diagrams/",
    "assets/images/about-ratib-command.png",
    "assets/images/program-preview-pipeline.svg",
    "assets/images/program-preview-workers.svg",
    "assets/images/program-preview-finance.svg",
    "uploads/ratib_cms_media/",
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

# save_file_content percent-encoded bodies break cPanel JSON serializer on PNG/large files.
BINARY_EXTENSIONS = frozenset({
    ".png",
    ".jpg",
    ".jpeg",
    ".gif",
    ".webp",
    ".ico",
    ".svg",
    ".woff",
    ".woff2",
    ".ttf",
    ".eot",
    ".pdf",
})


def is_binary_upload(rel: str) -> bool:
    return os.path.splitext(rel)[1].lower() in BINARY_EXTENSIONS


def fileman_upload_dir(abs_dir: str) -> str:
    """upload_files expects dir relative to account home (e.g. public_html/public)."""
    marker = "/public_html"
    idx = abs_dir.find(marker)
    if idx >= 0:
        return "public_html" + abs_dir[idx + len(marker) :]
    return abs_dir


def build_multipart_body(
    fields: dict[str, str],
    files: list[tuple[str, str, bytes, str]],
) -> tuple[bytes, str]:
    boundary = secrets.token_hex(16)
    bnd = boundary.encode("ascii")
    chunks: list[bytes] = []
    for key, val in fields.items():
        chunks.append(b"--" + bnd + b"\r\n")
        chunks.append(
            f'Content-Disposition: form-data; name="{key}"\r\n\r\n'.encode("utf-8")
        )
        chunks.append(val.encode("utf-8") + b"\r\n")
    for field, filename, content, mime in files:
        chunks.append(b"--" + bnd + b"\r\n")
        chunks.append(
            (
                f'Content-Disposition: form-data; name="{field}"; filename="{filename}"\r\n'
                f"Content-Type: {mime}\r\n\r\n"
            ).encode("utf-8")
        )
        chunks.append(content + b"\r\n")
    chunks.append(b"--" + bnd + b"--\r\n")
    return b"".join(chunks), f"multipart/form-data; boundary={boundary}"


def mime_for_filename(name: str) -> str:
    guessed, _ = mimetypes.guess_type(name)
    return guessed or "application/octet-stream"


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
    "pages/site-content-media.php",
    "public/cms-media.php",
    "pages/home.php",
    "control-panel/pages/control/site-content.php",
    "includes/ratib-about-sections.php",
    "includes/ratib-about-profile-data.php",
    "includes/ratib-operational-proof-data.php",
    "includes/ratib-operational-proof-render.php",
    "includes/ratib-public-cms.php",
    "includes/site-content.php",
    "includes/site-content-profile-data.php",
    "includes/site-content-public-data.php",
    "api/site-content-media.php",
    "css/pages/operational-proof.css",
    "public/cms-bundle-about.png",
    "public/cms-bundle-pipeline.svg",
    "public/cms-bundle-workers.svg",
    "public/cms-bundle-finance.svg",
    "public/cms-bundle-gov-control.png",
    "public/cms-bundle-gov-inspections.png",
    "public/cms-bundle-gov-tracking.png",
    "public/cms-bundle-gov-onboarding.png",
    "public/cms-bundle-diagram-workflow.svg",
    "public/cms-bundle-diagram-onboarding.svg",
    "public/cms-bundle-diagram-deployment.svg",
    "public/cms-bundle-diagram-tenant.svg",
    "public/cms-bundle-diagram-events.svg",
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
    "pages/partner-portal-login.php",
    "pages/architecture.php",
    "pages/security-compliance.php",
    "pages/procurement-legal.php",
    "js/pages/about-enterprise.js",
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
    "public/cms-media.php",
    "public/cms-bundle-gov-control.png",
    "pages/about.php",
    "pages/home.php",
    "includes/site-content.php",
    "includes/ratib-mega-nav-resolve.php",
    "includes/ratib-mega-nav-resolve.fallback.php",
    "js/pages/ratib-profile-nav-guard.js",
]

_print_lock = Lock()
_mkdir_cache: set[str] = set()


def api_post_raw(module: str, data: bytes, content_type: str) -> dict:
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    req = urllib.request.Request(
        f"https://{host}:{port}/execute/{module}",
        data=data,
        method="POST",
        headers={
            "Authorization": f"cpanel {user}:{token}",
            "Content-Type": content_type,
        },
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def api_post(module: str, params: dict) -> dict:
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    data = urllib.parse.urlencode(params).encode("utf-8")
    req = urllib.request.Request(
        f"https://{host}:{port}/execute/{module}",
        data=data,
        method="POST",
        headers={
            "Authorization": f"cpanel {user}:{token}",
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def uapi_ok(payload: dict) -> bool:
    rblock = payload.get("result", payload) or {}
    st = int(rblock.get("status", payload.get("status", 0)) or 0)
    return st == 1


def upload_files_ok(payload: dict) -> bool:
    if uapi_ok(payload):
        return True
    data = payload.get("data")
    if not isinstance(data, dict):
        rblock = payload.get("result", payload) or {}
        data = rblock.get("data") if isinstance(rblock, dict) else None
    if isinstance(data, dict) and int(data.get("succeeded", 0) or 0) >= 1:
        return True
    return False


def ensure_remote_dir(remote_dir: str, remote_base: str) -> None:
    """Create nested remote dirs (required before save_file_content for public/profile-media/...)."""
    remote_dir = remote_dir.rstrip("/")
    remote_base = remote_base.rstrip("/")
    if remote_dir in _mkdir_cache or remote_dir == remote_base:
        return
    suffix = remote_dir[len(remote_base) :].strip("/")
    if suffix == "":
        _mkdir_cache.add(remote_dir)
        return
    built = remote_base
    for part in suffix.split("/"):
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


def upload_binary_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if not os.path.isfile(rel):
        return rel, False, "missing"
    abs_dir = remote_dir(remote_base, rel)
    name = os.path.basename(rel)
    ensure_remote_dir(abs_dir, remote_base)
    with open(rel, "rb") as f:
        raw = f.read()
    body, ctype = build_multipart_body(
        {
            "dir": fileman_upload_dir(abs_dir),
            "overwrite": "1",
        },
        [("file-1", name, raw, mime_for_filename(name))],
    )
    try:
        payload = api_post_raw("Fileman/upload_files", body, ctype)
    except Exception as e:
        return rel, False, str(e)
    if upload_files_ok(payload):
        return rel, True, ""
    rblock = payload.get("result", payload) or {}
    return rel, False, json.dumps(rblock)[:200]


def upload_text_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if not os.path.isfile(rel):
        return rel, False, "missing"
    dir_path = remote_dir(remote_base, rel)
    name = os.path.basename(rel)
    ensure_remote_dir(dir_path, remote_base)
    with open(rel, "rb") as f:
        raw = f.read()
    try:
        content = raw.decode("utf-8")
    except UnicodeDecodeError:
        content = raw.decode("latin-1")
    try:
        payload = api_post(
            "Fileman/save_file_content",
            {"dir": dir_path, "file": name, "content": content},
        )
    except Exception as e:
        return rel, False, str(e)
    if uapi_ok(payload):
        return rel, True, ""
    rblock = payload.get("result", payload) or {}
    return rel, False, json.dumps(rblock)[:200]


def upload_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if is_binary_upload(rel):
        return upload_binary_one(rel, remote_base)
    return upload_text_one(rel, remote_base)


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
    workers = 2
    if any(is_binary_upload(f) for f in files):
        workers = 1
    return files, workers


def run_uploads(files: list[str], remote_base: str, workers: int) -> tuple[int, int, set[str]]:
    total = len(files)
    ok = 0
    fail = 0
    succeeded: set[str] = set()
    done = 0

    existing = sorted(
        [f for f in files if os.path.isfile(f)],
        key=lambda p: p.count("/"),
    )
    for rel in files:
        if rel in existing:
            continue
        done += 1
        pct = done * 100 // total if total else 100
        print(f"[{done}/{total}] {pct}% SKIP missing {rel}", flush=True)

    if not existing:
        return ok, fail, succeeded

    remote_dirs = sorted({remote_dir(remote_base, rel) for rel in existing}, key=lambda d: d.count("/"))
    for d in remote_dirs:
        ensure_remote_dir(d, remote_base)

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
    if fail > 0:
        return 1
    return 0 if must_hit >= need else 1


if __name__ == "__main__":
    sys.exit(main())
