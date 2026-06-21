#!/usr/bin/env python3
"""Start RCC Realtime Hub on production after deploy (HTTP + optional SSH)."""
from __future__ import annotations

import os
import subprocess
import sys
import urllib.error
import urllib.request


def http_start(site: str, token: str, path: str) -> tuple[int, str]:
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
        with urllib.request.urlopen(req, timeout=60) as resp:
            return int(resp.status), resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        return int(exc.code), exc.read().decode("utf-8", errors="replace")
    except Exception as exc:
        return 0, str(exc)


def ssh_start() -> tuple[int, str]:
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
    script = f"{remote_base}/ratib-contact-center/bin/start-realtime-hub.sh"
    port = os.environ.get("RCC_WEBSOCKET_PORT") or "9702"

    import stat
    import tempfile

    if "\\n" in key and "\n" not in key:
        key = key.replace("\\n", "\n")
    fd, key_path = tempfile.mkstemp(suffix=".pem")
    os.close(fd)
    with open(key_path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(key)
        if not key.endswith("\n"):
            handle.write("\n")
    os.chmod(key_path, stat.S_IRUSR | stat.S_IWUSR)

    port_n = int(port)
    remote_cmd = (
        f"pgrep -f rcc-realtime-hub.php >/dev/null || bash '{script}'; "
        f"sleep 1; "
        f"(echo >/dev/tcp/127.0.0.1/{port_n}) >/dev/null 2>&1 && echo OK || echo PORT_CLOSED"
    )
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
        proc = subprocess.run(cmd, text=True, capture_output=True, timeout=45)
        out = (proc.stdout or "") + (proc.stderr or "")
        return proc.returncode, out.strip()
    finally:
        try:
            os.remove(key_path)
        except OSError:
            pass


def main() -> int:
    site = os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL", "https://rateb.sa")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    if not token:
        print("::warning::RCC Realtime Hub skipped — no migrate token", flush=True)
        return 0

    paths = [
        "/control-panel/api/control/rcc-realtime-hub-run.php",
        "/ratib-contact-center/public/run-realtime-hub.php",
    ]
    http_ok = False
    for path in paths:
        code, body = http_start(site, token, path)
        print(f"--- HTTP {path} ({code}) ---", flush=True)
        print(body, flush=True)
        if "OK" in body and "running=yes" in body.replace(" ", ""):
            http_ok = True
            break
        if code == 404:
            continue

    if http_ok:
        print("RCC Realtime Hub started via HTTP", flush=True)
        return 0

    code, body = ssh_start()
    if code:
        print(f"--- SSH start (exit {code}) ---", flush=True)
        print(body, flush=True)
    if "OK" in body:
        print("RCC Realtime Hub started via SSH", flush=True)
        return 0

    print(
        "::warning::RCC Realtime Hub not confirmed on port 9702 — "
        "add cPanel cron (ratib-contact-center/bin/REALTIME-HUB-RUN.txt). "
        "Inbox still works with polling fallback.",
        flush=True,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
