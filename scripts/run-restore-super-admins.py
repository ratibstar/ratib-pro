#!/usr/bin/env python3
"""Invoke token-gated super-admin restore on production (auth recovery only)."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request


def main() -> int:
    site = (os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL") or "https://rateb.sa").rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    if not token:
        print("::warning::Super-admin restore skipped — no RATEB_ERP_MIGRATE_TOKEN", flush=True)
        return 0

    auto = "--auto" in sys.argv or os.environ.get("RATEB_RESTORE_SUPER_ADMINS_AUTO") == "1"
    url = f"{site}/rateb-erp/public/restore-super-admins.php"
    headers = {"X-Rateb-Migrate-Token": token}

    forensic = _request("GET", url, headers)
    print("==> super-admin forensic", flush=True)
    print(json.dumps(forensic, indent=2), flush=True)

    if not forensic.get("ok"):
        print("::error::Forensic probe failed", flush=True)
        return 1

    report = (forensic.get("report") or {}).get("forensic") or {}
    super_count = int(report.get("super_admin_count") or 0)
    accounts = report.get("accounts") or {}
    needs_restore = super_count < 1 or any(not (a or {}).get("exists") for a in accounts.values())

    if not needs_restore and not os.environ.get("RATEB_FORCE_SUPER_ADMIN_RESTORE"):
        print(f"Super-admin auth OK (count={super_count}) — restore not required", flush=True)
        return 0

    if auto or os.environ.get("RATEB_FORCE_SUPER_ADMIN_RESTORE") == "1":
        print("==> restoring super-admin accounts", flush=True)
        restore_headers = {
            **headers,
            "X-Rateb-Restore-Confirm": "RESTORE-SUPER-ADMINS",
        }
        result = _request("POST", url, restore_headers)
        print(json.dumps(result, indent=2), flush=True)
        if not result.get("ok"):
            print("::error::Super-admin restore failed", flush=True)
            return 1
        after = ((result.get("report") or {}).get("forensic_after") or {})
        if int(after.get("super_admin_count") or 0) < 1:
            print("::error::Super-admin restore completed but count still zero", flush=True)
            return 1
        print("Super-admin restore OK", flush=True)
        return 0

    print("::warning::Super-admin restore needed but auto mode off", flush=True)
    return 0


def _request(method: str, url: str, headers: dict[str, str]) -> dict:
    req = urllib.request.Request(url, method=method, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            body = resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        try:
            return json.loads(body)
        except json.JSONDecodeError:
            return {"ok": False, "error": body, "status": e.code}
    try:
        return json.loads(body)
    except json.JSONDecodeError:
        return {"ok": False, "error": body}


if __name__ == "__main__":
    sys.exit(main())
