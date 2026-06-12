#!/usr/bin/env python3
"""Upload deploy migrate token and trigger RATEB ERP migrations on production."""
from __future__ import annotations

import importlib.util
import os
import sys
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.abspath(__file__))


def load_deploy_core():
    path = os.path.join(ROOT, "github-cpanel-fileman-deploy-core.py")
    spec = importlib.util.spec_from_file_location("deploy_core", path)
    if spec is None or spec.loader is None:
        raise RuntimeError("Cannot load github-cpanel-fileman-deploy-core.py")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def sanitize_host(host: str) -> str:
    host = (host or "").strip()
    for prefix in ("https://", "http://"):
        if host.lower().startswith(prefix):
            host = host[len(prefix) :]
    return host.split("/")[0].strip()


def upload_migrate_token(dc) -> bool:
    token = os.environ.get("CPANEL_API_TOKEN", "")
    remote_base = os.environ.get("CPANEL_REMOTE_BASE", "/home/outratib/public_html").rstrip("/")
    if not token:
        print("::warning::Skip token upload — no CPANEL_API_TOKEN", flush=True)
        return False

    host = sanitize_host(os.environ.get("CPANEL_HOST", ""))
    if host:
        os.environ["CPANEL_HOST"] = host

    local_rel = "rateb-erp/storage/deploy-migrate-token"
    os.makedirs(os.path.dirname(local_rel), exist_ok=True)
    with open(local_rel, "w", encoding="utf-8") as handle:
        handle.write(token)
    try:
        _rel_path, ok, err = dc.upload_text_one(local_rel, remote_base)
    finally:
        try:
            os.remove(local_rel)
        except OSError:
            pass

    if not ok:
        print(f"::warning::Migrate token upload failed: {err}", flush=True)
        return False

    print("migrate token file uploaded", flush=True)
    return True


def http_migrate(site: str, token: str, path: str) -> tuple[int, str]:
    url = site.rstrip("/") + path
    req = urllib.request.Request(
        url,
        data=b"",
        method="POST",
        headers={
            "X-Rateb-Migrate-Token": token,
            "Cache-Control": "no-cache",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            return int(resp.status), body
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        return int(exc.code), body
    except Exception as exc:
        return 0, str(exc)


def main() -> int:
    site = os.environ.get("CPANEL_SITE_URL", "https://out.ratib.sa").rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    if not token:
        print("::warning::RATEB ERP migrations skipped — no token", flush=True)
        return 0

    dc = load_deploy_core()
    upload_migrate_token(dc)

    endpoints = [
        "/control-panel/api/control/rateb-erp-migrate-run.php",
        "/rateb-erp/public/run-migrations.php",
    ]

    last_body = ""
    for path in endpoints:
        code, body = http_migrate(site, token, path)
        last_body = body
        print(f"--- {path} (HTTP {code}) ---", flush=True)
        print(body, flush=True)
        lines = [ln.strip() for ln in body.strip().splitlines() if ln.strip()]
        if lines and lines[-1] == "OK":
            print("RATEB ERP migrations completed", flush=True)
            return 0
        if code == 403 and body.strip() == "Forbidden":
            continue
        if code == 404:
            continue

    if "ERROR:" in last_body:
        print("::error::RATEB ERP migration failed", flush=True)
        return 1

    print("::warning::Migration response did not include OK", flush=True)
    return 1


if __name__ == "__main__":
    sys.exit(main())
