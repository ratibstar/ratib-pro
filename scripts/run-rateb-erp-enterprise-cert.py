#!/usr/bin/env python3
"""Run RATEB ERP enterprise certification on the server (official dev DB)."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


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


def format_enterprise_report(result: dict, *, db: str, site: str) -> str:
    suite_data = result.get("result") or {}
    passed = suite_data.get("passed", 0)
    failed = suite_data.get("failed", 0)
    total = suite_data.get("total", 0)
    lines = [
        "# Enterprise Final Pass Report",
        "",
        f"**Generated:** {suite_data.get('generated_at', 'n/a')}",
        f"**Site:** {site}",
        f"**Database:** {db or 'server default'}",
        "",
        "## Summary",
        "",
        f"| Metric | Value |",
        f"|--------|------:|",
        f"| **Passed** | {passed} |",
        f"| **Failed** | {failed} |",
        f"| **Total** | {total} |",
        f"| **Target** | 29/29 PASS |",
        "",
        f"## Result: {'✅ PASS' if failed == 0 and total >= 29 else '❌ FAIL'}",
        "",
    ]
    suites = suite_data.get("suites") or {}
    for suite_name, suite in suites.items():
        lines.append(f"### {suite_name}")
        lines.append("")
        lines.append("| Test | Status | Reason |")
        lines.append("|------|--------|--------|")
        for t in suite.get("tests") or []:
            status = "PASS" if t.get("passed") else "FAIL"
            reason = t.get("reason") or ""
            lines.append(f"| {t.get('name', '?')} | {status} | {reason} |")
        lines.append("")
    return "\n".join(lines)


def format_reset_report(payload: dict, *, db: str) -> str:
    result = payload.get("result") or {}
    report = result.get("report") or {}
    tables = report.get("tables") or {}
    users = report.get("users") or {}
    files = report.get("files") or []
    lines = [
        "# Production Reset Dry-Run Report",
        "",
        f"**Mode:** dry-run (no data modified)",
        f"**Database:** {report.get('database') or db or 'n/a'}",
        f"**Started:** {report.get('started_at', 'n/a')}",
        f"**Finished:** {report.get('finished_at', 'n/a')}",
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
    lines.extend(["", "## Tables to truncate", "", "| Table | Rows before | Action |", "|-------|------------:|--------|"])
    for table, info in sorted(tables.items()):
        if isinstance(info, dict):
            lines.append(f"| `{table}` | {info.get('before', '?')} | {info.get('action', 'TRUNCATE')} |")
    lines.extend(["", "## Upload / cache files", ""])
    for entry in files:
        if isinstance(entry, dict):
            path = entry.get("path", "")
            would = entry.get("would_remove", entry.get("removed", 0))
            lines.append(f"- `{path}`: would remove **{would}** files")
    errors = report.get("errors") or []
    if errors:
        lines.extend(["", "## Errors", ""])
        for err in errors:
            lines.append(f"- {err}")
    lines.extend([
        "",
        "## NOT executed",
        "",
        "`php bin/reset-production.php --confirm=RESET-PRODUCTION` was **not** run.",
        "Execute only after explicit approval and a verified backup.",
    ])
    return "\n".join(lines)


def main() -> int:
    site = os.environ.get("DEPLOY_SITE_URL") or os.environ.get("CPANEL_SITE_URL", "https://rateb.sa")
    site = site.rstrip("/")
    token = os.environ.get("RATEB_ERP_MIGRATE_TOKEN") or os.environ.get("CPANEL_API_TOKEN") or ""
    db_name = os.environ.get("RATEB_ERP_DB_NAME", "")
    do_seed = os.environ.get("RATEB_ENTERPRISE_SEED") == "1"
    repo_root = Path(__file__).resolve().parent.parent
    docs = repo_root / "rateb-erp" / "docs" / "GA"

    if not token:
        print("::warning::Enterprise certification skipped — no RATEB_ERP_MIGRATE_TOKEN", flush=True)
        return 0

    if do_seed:
        print("==> enterprise seed", flush=True)
        code, seed_resp = cert_post(site, token, "seed", db_name=db_name, seed=True, timeout=900)
        print(json.dumps(seed_resp, indent=2) if isinstance(seed_resp, dict) else seed_resp, flush=True)
        if code >= 400 or (isinstance(seed_resp, dict) and not seed_resp.get("ok")):
            print("::error::Enterprise seed failed", flush=True)
            return 1

    print("==> enterprise test", flush=True)
    code, test_resp = cert_post(site, token, "test", db_name=db_name, timeout=300)
    print(json.dumps(test_resp, indent=2) if isinstance(test_resp, dict) else test_resp, flush=True)
    if not isinstance(test_resp, dict):
        print("::error::Invalid enterprise test response", flush=True)
        return 1

    suite = test_resp.get("result") or {}
    passed = int(suite.get("passed") or 0)
    failed = int(suite.get("failed") or 0)
    total = int(suite.get("total") or 0)
    resolved_db = str(test_resp.get("database") or db_name or "")

    write_markdown(
        docs / "enterprise-final-pass-report.md",
        format_enterprise_report(test_resp, db=resolved_db, site=site),
    )

    if failed > 0 or total < 29:
        print(f"::error::Enterprise tests {passed}/{total} — need 29/29 PASS", flush=True)
        return 1

    print(f"Enterprise tests {passed}/{total} PASS", flush=True)

    print("==> erp backup", flush=True)
    code, backup_resp = cert_post(site, token, "backup", db_name=db_name, timeout=900)
    print(json.dumps(backup_resp, indent=2) if isinstance(backup_resp, dict) else backup_resp, flush=True)
    if code >= 400 or (isinstance(backup_resp, dict) and not backup_resp.get("ok")):
        print("::warning::ERP backup reported failure — review output", flush=True)

    print("==> reset dry-run", flush=True)
    code, reset_resp = cert_post(site, token, "reset-dry-run", db_name=db_name, timeout=300)
    print(json.dumps(reset_resp, indent=2) if isinstance(reset_resp, dict) else reset_resp, flush=True)
    if not isinstance(reset_resp, dict) or not reset_resp.get("ok"):
        print("::error::Reset dry-run failed", flush=True)
        return 1

    write_markdown(
        docs / "reset-dry-run-report.md",
        format_reset_report(reset_resp, db=resolved_db),
    )

    print("Enterprise certification complete — 29/29 PASS, reset dry-run validated", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
