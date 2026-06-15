#!/usr/bin/env python3
"""Trigger RATEB ERP migrations on production after deploy."""
from __future__ import annotations

import os
import sys
import urllib.error
import urllib.request


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
    site = os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL", "https://rateb.sa")
    site = site.rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    if not token:
        print("::warning::RATEB ERP migrations skipped — no token", flush=True)
        return 0

    print(
        "migrate auth: workflow secret + server token from deploy bundle upload",
        flush=True,
    )

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
