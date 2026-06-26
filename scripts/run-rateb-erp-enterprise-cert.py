#!/usr/bin/env python3
"""Run RATEB ERP enterprise certification on production (official dev DB)."""
from __future__ import annotations

import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


def http_get_json(url: str, *, timeout: int = 180) -> tuple[int, dict | str]:
    req = urllib.request.Request(
        url,
        method="GET",
        headers={"Cache-Control": "no-cache", "Pragma": "no-cache"},
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            try:
                return int(resp.status), json.loads(body)
            except json.JSONDecodeError:
                return int(resp.status), body
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        try:
            return int(exc.code), json.loads(body)
        except json.JSONDecodeError:
            return int(exc.code), body
    except Exception as exc:
        return 0, str(exc)


def cert_post(
    site: str,
    token: str,
    action: str,
    *,
    db_name: str = "",
    seed: bool = False,
    timeout: int = 600,
) -> tuple[int, dict | str]:
    url = site.rstrip("/") + "/rateb-erp/public/enterprise-cert-run.php"
    headers = {
        "X-Rateb-Migrate-Token": token,
        "Cache-Control": "no-cache",
        "Content-Type": "application/x-www-form-urlencoded",
    }
    if db_name:
        headers["X-Rateb-Erp-Db-Name"] = db_name
    if seed:
        headers["X-Rateb-Cert-Confirm"] = "ENTERPRISE-SEED"
    data = urllib.parse.urlencode({"action": action}).encode("utf-8")
    req = urllib.request.Request(url, data=data, method="POST", headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            try:
                return int(resp.status), json.loads(body)
            except json.JSONDecodeError:
                return int(resp.status), body
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        try:
            return int(exc.code), json.loads(body)
        except json.JSONDecodeError:
            return int(exc.code), body
    except Exception as exc:
        return 0, str(exc)


def write_markdown(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    print(f"Wrote {path}", flush=True)


def format_enterprise_report(suite: dict, *, db: str, site: str) -> str:
    passed = int(suite.get("passed") or 0)
    failed = int(suite.get("failed") or 0)
    total = int(suite.get("total") or 0)
    lines = [
        "# Enterprise Final Pass Report",
        "",
        f"**Generated:** {suite.get('generated_at', 'n/a')}",
        f"**Site:** {site}",
        f"**Database:** {db or 'server default'}",
        f"**Probe:** `{site}/rateb-erp/public/erp-security-cert.php?enterprise=1`",
        "",
        "## Summary",
        "",
        "| Metric | Value |",
        "|--------|------:|",
        f"| **Passed** | {passed} |",
        f"| **Failed** | {failed} |",
        f"| **Total** | {total} |",
        "| **Target** | All PASS (≥29 with live DB) |",
        "",
        f"## Result: {'✅ PASS' if failed == 0 and total >= 29 else '❌ FAIL'}",
        "",
    ]
    if suite.get("error"):
        lines += [f"**Error:** {suite['error']}", ""]
    for suite_name, s in (suite.get("suites") or {}).items():
        lines += [f"### {suite_name}", "", "| Test | Status | Reason |", "|------|--------|--------|"]
        for t in s.get("tests") or []:
            status = "PASS" if t.get("passed") else "FAIL"
            lines.append(f"| {t.get('name', '?')} | {status} | {t.get('reason') or ''} |")
        lines.append("")
    return "\n".join(lines)


def format_reset_report(reset: dict, *, db: str, site: str) -> str:
    report = reset.get("report") or {}
    tables = report.get("tables") or {}
    users = report.get("users") or {}
    files = report.get("files") or []
    lines = [
        "# Production Reset Dry-Run Report",
        "",
        "**Mode:** dry-run (no data modified)",
        f"**Database:** {report.get('database') or db or 'n/a'}",
        f"**Started:** {report.get('started_at', 'n/a')}",
        f"**Finished:** {report.get('finished_at', 'n/a')}",
        f"**Probe:** `{site}/rateb-erp/public/erp-security-cert.php?enterprise=1&reset_dry_run=1`",
        "",
        "## Preserved (never truncated)",
        "",
        "- `rateb_migrations`, RBAC (`rateb_permissions`, `rateb_roles`, `rateb_role_permissions`)",
        "- Super-admin users (`rateb_users` where `is_super_admin = 1`)",
        "- System settings, email/SMS templates",
        "- All `rateb_cms_*` marketing/CMS tables",
        "",
        "## Users",
        "",
        f"- Non-super-admin users to delete: **{users.get('deleted_non_admin', 0)}**",
        "",
        "### Preserved super-admins",
        "",
    ]
    for admin in users.get("preserved_super_admins") or []:
        lines.append(f"- id={admin.get('id')} `{admin.get('email', '')}`")
    lines += [
        "",
        "## Tables to truncate",
        "",
        f"**Count:** {len(tables)} tables",
        "",
        "| Table | Rows before | Action |",
        "|-------|------------:|--------|",
    ]
    for table, info in sorted(tables.items()):
        if isinstance(info, dict):
            lines.append(f"| `{table}` | {info.get('before', '?')} | {info.get('action', 'TRUNCATE')} |")
    lines += ["", "## Upload / cache files", ""]
    for entry in files:
        if isinstance(entry, dict):
            path = entry.get("path", "")
            would = entry.get("would_remove", entry.get("removed", 0))
            lines.append(f"- `{path}`: would remove **{would}** files")
    lines += [
        "",
        "## NOT executed",
        "",
        "`php bin/reset-production.php --confirm=RESET-PRODUCTION` was **not** run.",
        "Execute only after explicit approval and a verified backup.",
    ]
    return "\n".join(lines)


def fetch_security_cert(site: str, *, reset_dry_run: bool = False, attempts: int = 4) -> tuple[int, dict | str]:
    base = site.rstrip("/") + "/rateb-erp/public/erp-security-cert.php?enterprise=1"
    if reset_dry_run:
        base += "&reset_dry_run=1"
    base += "&_=" + str(int(time.time()))
    last: tuple[int, dict | str] = (0, "")
    for attempt in range(1, attempts + 1):
        if attempt > 1:
            time.sleep(8)
        code, body = http_get_json(base, timeout=180)
        last = (code, body)
        print(f"--- erp-security-cert attempt {attempt} (HTTP {code}) ---", flush=True)
        if code == 200 and isinstance(body, dict) and body.get("enterprise_suite"):
            return code, body
    return last


def suite_ok(suite: dict) -> bool:
    passed = int(suite.get("passed") or 0)
    failed = int(suite.get("failed") or 0)
    total = int(suite.get("total") or 0)
    return failed == 0 and total >= 29 and passed == total


def main() -> int:
    site = os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL", "https://rateb.sa")
    site = site.rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    db_name = os.environ.get("RATEB_ERP_DB_NAME", "")
    do_seed = os.environ.get("RATEB_ENTERPRISE_SEED") == "1"
    repo_root = Path(__file__).resolve().parent.parent
    docs = repo_root / "rateb-erp" / "docs" / "GA"

    if do_seed:
        if not token:
            print("::warning::Enterprise seed skipped — no RATEB_ERP_MIGRATE_TOKEN", flush=True)
        else:
            print("==> enterprise seed (token endpoint)", flush=True)
            code, seed_resp = cert_post(site, token, "seed", db_name=db_name, seed=True, timeout=900)
            print(json.dumps(seed_resp, indent=2) if isinstance(seed_resp, dict) else seed_resp, flush=True)
            if code >= 400 or (isinstance(seed_resp, dict) and not seed_resp.get("ok")):
                print("::error::Enterprise seed failed", flush=True)
                return 1

    print("==> enterprise test + reset dry-run (erp-security-cert probe)", flush=True)
    code, cert = fetch_security_cert(site, reset_dry_run=True)
    if not isinstance(cert, dict):
        print(f"::error::Invalid cert response: {cert}", flush=True)
        return 1

    suite = cert.get("enterprise_suite") or {}
    reset = cert.get("reset_dry_run") or {}
    resolved_db = str((reset.get("report") or {}).get("database") or db_name or "")

    print(json.dumps({"enterprise_suite": suite, "reset_dry_run_keys": list(reset.keys())}, indent=2), flush=True)

    write_markdown(docs / "enterprise-final-pass-report.md", format_enterprise_report(suite, db=resolved_db, site=site))

    if not suite_ok(suite):
        passed = int(suite.get("passed") or 0)
        total = int(suite.get("total") or 0)
        failed = int(suite.get("failed") or 0)
        err = suite.get("error") or ""
        print(
            f"::error::Enterprise tests {passed}/{total} (failed={failed}) — need all PASS, min 29 total"
            + (f" — {err}" if err else ""),
            flush=True,
        )
        return 1

    print(f"Enterprise tests {suite.get('passed')}/{suite.get('total')} PASS", flush=True)

    if not reset.get("report"):
        print("::error::Reset dry-run missing from erp-security-cert response", flush=True)
        return 1

    write_markdown(docs / "reset-dry-run-report.md", format_reset_report(reset, db=resolved_db, site=site))

    if token:
        print("==> erp backup (token endpoint)", flush=True)
        code, backup_resp = cert_post(site, token, "backup", db_name=db_name, timeout=900)
        print(json.dumps(backup_resp, indent=2) if isinstance(backup_resp, dict) else backup_resp, flush=True)
        if code >= 400 or (isinstance(backup_resp, dict) and not backup_resp.get("ok")):
            print("::warning::ERP backup reported failure — review output", flush=True)
    else:
        print("::warning::ERP backup skipped — no RATEB_ERP_MIGRATE_TOKEN", flush=True)

    print("Enterprise certification complete — all tests PASS, reset dry-run validated", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
