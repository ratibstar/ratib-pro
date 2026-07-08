#!/usr/bin/env python3
"""Run RATEB Platform Catalog migrations on production via SSH after deploy."""
from __future__ import annotations

import os
import stat
import subprocess
import sys
import tempfile


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


def main() -> int:
    code, body = ssh_migrate()
    print("--- rateb-platform-catalog/bin/migrate.php ---", flush=True)
    print(body, flush=True)
    if code == 0 and "ssh skipped" in body:
        print("::warning::Platform Catalog migrations skipped — no SSH credentials", flush=True)
        return 0
    if code != 0:
        if "Access denied for user 'root'@'localhost'" in body:
            print(
                "::warning::Platform Catalog migrations skipped — set RATEB_PLATFORM_CATALOG_DB_* "
                "or DB_USER/DB_PASS in server .env (CLI must not use root)",
                flush=True,
            )
            return 0
        print("::error::Platform Catalog migration failed", flush=True)
        return 1
    if "Migration failed:" in body or "Refusing to run catalog migrations" in body:
        print("::error::Platform Catalog migration reported failure", flush=True)
        return 1
    print("RATEB Platform Catalog migrations completed", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
