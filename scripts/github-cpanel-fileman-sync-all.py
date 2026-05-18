#!/usr/bin/env python3
"""Upload entire project to cPanel public_html via Fileman API (parallel)."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
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
WORKERS = int(os.environ.get("CPANEL_FILEMAN_WORKERS", "10"))
API_URL = f"https://{HOST}:{PORT}/execute/Fileman/save_file_content"

SKIP_DIR_PARTS = {
    ".git",
    ".github",
    ".cursor",
    "node_modules",
    "archive",
    ".idea",
    ".vscode",
}

SKIP_FILE_SUFFIXES = {".md", ".map", ".log", ".zip", ".tar", ".gz"}

CRITICAL = {
    ".htaccess",
    "public/ratib-build.txt",
    "pages/about.php",
    "js/pages/ratib-profile-nav-guard.js",
    "profile/index.php",
}


def should_skip(rel_posix: str) -> bool:
    parts = rel_posix.split("/")
    if any(p in SKIP_DIR_PARTS for p in parts):
        return True
    low = rel_posix.lower()
    if low.endswith(SKIP_FILE_SUFFIXES):
        return True
    return False


def collect_files() -> list[str]:
    out: list[str] = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT).as_posix()
        if should_skip(rel):
            continue
        out.append(rel)
    out.sort(key=lambda r: (r not in CRITICAL, r))
    return out


def upload_file(rel: str) -> tuple[str, bool, str]:
    full = ROOT / rel
    remote_dir = f"{REMOTE_BASE}/{os.path.dirname(rel)}"
    if os.path.dirname(rel) in ("", "."):
        remote_dir = REMOTE_BASE
    name = os.path.basename(rel)
    try:
        raw = full.read_bytes()
        content = raw.decode("utf-8")
    except UnicodeDecodeError:
        content = raw.decode("latin-1")
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
        with urllib.request.urlopen(req, timeout=60) as resp:
            body = resp.read().decode("utf-8", errors="replace")
        payload = json.loads(body)
        result = payload.get("result", payload) or {}
        ok = int(result.get("status", payload.get("status", 0)) or 0) == 1
        return rel, ok, "" if ok else body[:200]
    except Exception as exc:  # noqa: BLE001
        return rel, False, str(exc)[:200]


def main() -> int:
    files = collect_files()
    print(f"sync-all files={len(files)} workers={WORKERS} dest={REMOTE_BASE}")
    ok = 0
    fail = 0
    critical_fail: list[str] = []

    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        futures = {pool.submit(upload_file, rel): rel for rel in files}
        done = 0
        for fut in as_completed(futures):
            rel, success, err = fut.result()
            done += 1
            if success:
                ok += 1
                if done <= 5 or done % 50 == 0 or rel in CRITICAL:
                    print(f"OK [{done}/{len(files)}] {rel}")
            else:
                fail += 1
                print(f"FAIL [{done}/{len(files)}] {rel} {err}")
                if rel in CRITICAL:
                    critical_fail.append(rel)

    print(f"Summary ok={ok} fail={fail} total={len(files)}")
    if critical_fail:
        print("CRITICAL FAIL:", ", ".join(critical_fail))
        return 1
    if ok < max(1, len(files) // 2):
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
