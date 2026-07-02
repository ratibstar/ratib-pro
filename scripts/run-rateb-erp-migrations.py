#!/usr/bin/env python3
"""Trigger RATEB ERP migrations on production after deploy."""
from __future__ import annotations

import os
import sys
import time
import urllib.error
import urllib.request


def is_transient_network_error(message: str) -> bool:
    needles = (
        "Temporary failure in name resolution",
        "Name or service not known",
        "timed out",
        "TimeoutError",
        "Connection refused",
        "Connection reset",
        "Errno -3",
        "Errno -2",
        "Errno 110",
        "Errno 111",
        "getaddrinfo failed",
    )
    msg = message or ""
    return any(needle in msg for needle in needles)


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


def http_migrate_with_retry(site: str, token: str, path: str, attempts: int = 5) -> tuple[int, str]:
    last_code = 0
    last_body = ""
    for attempt in range(1, attempts + 1):
        code, body = http_migrate(site, token, path)
        last_code, last_body = code, body
        if code > 0:
            return code, body
        if is_transient_network_error(body) and attempt < attempts:
            wait_s = min(4 * attempt, 20)
            print(
                f"transient network error on {path} (attempt {attempt}/{attempts}); retry in {wait_s}s: {body[:160]}",
                flush=True,
            )
            time.sleep(wait_s)
            continue
        return code, body
    return last_code, last_body


def main() -> int:
    site = os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL", "https://rateb.sa")
    site = site.rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    if not token:
        print("::warning::RATEB ERP migrations skipped — no token", flush=True)
        return 0

    print(
        f"migrate auth: workflow secret + server token from deploy bundle upload (site={site})",
        flush=True,
    )

    endpoints = [
        "/control-panel/api/control/rateb-erp-migrate-run.php",
        "/rateb-erp/public/run-migrations.php",
    ]

    last_body = ""
    last_code = 0
    saw_transient_only = True
    for path in endpoints:
        code, body = http_migrate_with_retry(site, token, path)
        last_body = body
        last_code = code
        print(f"--- {path} (HTTP {code}) ---", flush=True)
        print(body, flush=True)
        if code > 0:
            saw_transient_only = False
        lines = [ln.strip() for ln in body.strip().splitlines() if ln.strip()]
        if lines and lines[-1] == "OK":
            print("RATEB ERP migrations completed", flush=True)
            return 0
        if code == 403 and body.strip() == "Forbidden":
            continue
        if code == 404:
            continue
        if code == 500 and "Refusing ERP migrations on" in body:
            print(f"::warning::{path} skipped — wrong ERP database; fix RATEB_ERP_DB_NAME in server .env", flush=True)
            continue
        if code == 0 and is_transient_network_error(body):
            continue
        saw_transient_only = False

    if "ERROR:" in last_body:
        if "Refusing ERP migrations on" in last_body:
            print("::warning::RATEB ERP migrations skipped — set RATEB_ERP_DB_NAME=admin_rateb-erp in server .env", flush=True)
            return 0
        print("::error::RATEB ERP migration failed", flush=True)
        return 1

    if saw_transient_only and is_transient_network_error(last_body):
        print(
            "::warning::RATEB ERP migrations skipped — CI could not resolve/reach site (transient DNS); deploy continues",
            flush=True,
        )
        return 0

    print("::error::Migration response did not include OK", flush=True)
    return 1


if __name__ == "__main__":
    sys.exit(main())
