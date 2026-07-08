#!/usr/bin/env python3
"""Run RATEB Platform Catalog migrations on production after deploy."""
from __future__ import annotations

import os
import stat
import subprocess
import sys
import tempfile
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


def ssh_migrate() -> tuple[int, str]:
    key = (os.environ.get("DEPLOY_SSH_PRIVATE_KEY") or os.environ.get("SSH_PRIVATE_KEY") or "").strip()
    host = os.environ.get("DEPLOY_SSH_HOST") or os.environ.get("SSH_HOST") or ""
    user = os.environ.get("DEPLOY_SSH_USER") or os.environ.get("SSH_USER") or ""
    if not key or not host or not user:
        return 0, "ssh skipped (no credentials)"

    remote_base = (
        os.environ.get("DEPLOY_REMOTE_BASE")
        or os.environ.get("CPANEL_SFTP_REMOTE_DIR")
        or "/home/admin/domains/rateb.sa/public_html"
    ).rstrip("/")
    catalog_dir = f"{remote_base}/rateb-platform-catalog"
    remote_cmd = f"cd '{catalog_dir}' && php bin/migrate.php 2>&1"

    if "\\n" in key and "\n" not in key:
        key = key.replace("\\n", "\n")
    fd, key_path = tempfile.mkstemp(suffix=".pem")
    os.close(fd)
    with open(key_path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(key)
        if not key.endswith("\n"):
            handle.write("\n")
    os.chmod(key_path, stat.S_IRUSR | stat.S_IWUSR)

    ssh_port = os.environ.get("DEPLOY_SSH_PORT") or os.environ.get("SSH_PORT") or "22"
    cmd = [
        "ssh",
        "-i",
        key_path,
        "-p",
        ssh_port,
        "-o",
        "BatchMode=yes",
        "-o",
        "IdentitiesOnly=yes",
        "-o",
        "StrictHostKeyChecking=accept-new",
        f"{user}@{host}",
        remote_cmd,
    ]
    try:
        proc = subprocess.run(cmd, text=True, capture_output=True, timeout=300)
        out = (proc.stdout or "") + (proc.stderr or "")
        return proc.returncode, out.strip()
    finally:
        try:
            os.remove(key_path)
        except OSError:
            pass


def migration_body_ok(body: str) -> bool:
    lines = [ln.strip() for ln in body.strip().splitlines() if ln.strip()]
    return bool(lines and lines[-1] == "OK")


def main() -> int:
    site = os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL", "https://rateb.sa")
    site = site.rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""

    if token:
        print(
            f"migrate auth: workflow secret + server token (site={site})",
            flush=True,
        )
        endpoints = [
            "/control-panel/api/control/platform-catalog-migrate-run.php",
            "/rateb-platform-catalog/public/run-migrations.php",
        ]
        last_body = ""
        for path in endpoints:
            code, body = http_migrate(site, token, path)
            last_body = body
            print(f"--- {path} (HTTP {code}) ---", flush=True)
            print(body, flush=True)
            if migration_body_ok(body):
                print("RATEB Platform Catalog migrations completed", flush=True)
                return 0
            if code == 403 and body.strip() == "Forbidden":
                continue
            if code == 404:
                continue
            if code == 0 and is_transient_network_error(body):
                continue

        if "Access denied" in last_body and "platform_catalog" in last_body:
            print(
                "::warning::Platform Catalog migrations skipped — grant admin_rateb full access to "
                "admin_rateb_platform_catalog in DirectAdmin (or set RATEB_PLATFORM_CATALOG_DB_USER/PASS in .env)",
                flush=True,
            )
            return 0

    code, body = ssh_migrate()
    print("--- rateb-platform-catalog/bin/migrate.php (SSH) ---", flush=True)
    print(body, flush=True)
    if code == 0 and "ssh skipped" in body:
        print("::warning::Platform Catalog migrations skipped — no credentials", flush=True)
        return 0
    if migration_body_ok(body):
        print("RATEB Platform Catalog migrations completed", flush=True)
        return 0
    if "Access denied" in body and "platform_catalog" in body:
        print(
            "::warning::Platform Catalog migrations skipped — grant admin_rateb full access to "
            "admin_rateb_platform_catalog in DirectAdmin (or set RATEB_PLATFORM_CATALOG_DB_USER/PASS in .env)",
            flush=True,
        )
        return 0
    if code != 0 or "Migration failed:" in body or "ERROR:" in body or "Refusing to run catalog migrations" in body:
        print("::error::Platform Catalog migration failed", flush=True)
        return 1

    print("RATEB Platform Catalog migrations completed", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
