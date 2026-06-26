#!/usr/bin/env python3
"""Generate GA enterprise + reset reports from erp-security-cert JSON snapshot."""
from __future__ import annotations

import json
import sys
from datetime import datetime, timezone
from pathlib import Path


def main() -> int:
    repo = Path(__file__).resolve().parent.parent
    src = repo / "rateb-erp" / "docs" / "GA" / "_cert-json-latest.json"
    if not src.is_file():
        print(f"Missing {src}", file=sys.stderr)
        return 1

    data = json.loads(src.read_text(encoding="utf-8-sig"))
    suite = data.get("enterprise_suite") or {}
    reset = data.get("reset_dry_run") or {}
    report = reset.get("report") or {}
    site = "https://rateb.sa"
    db = report.get("database") or "admin_rateb-erp"
    docs = repo / "rateb-erp" / "docs" / "GA"
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")

    passed = int(suite.get("passed") or 0)
    failed = int(suite.get("failed") or 0)
    total = int(suite.get("total") or 0)
    ok = failed == 0 and passed >= 29

    ent_lines = [
        "# Enterprise Final Pass Report",
        "",
        f"**Generated:** {now}",
        f"**Site:** {site}",
        f"**Database:** `{db}`",
        f"**Probe:** `{site}/rateb-erp/public/erp-security-cert.php?enterprise=1`",
        "",
        "## Summary",
        "",
        "| Metric | Value |",
        "|--------|------:|",
        f"| **Passed** | {passed} |",
        f"| **Failed** | {failed} |",
        f"| **Total** | {total} |",
        "| **Target** | All PASS (31 with live DB connected) |",
        "",
        f"## Result: {'✅ PASS' if ok else '❌ FAIL'}",
        "",
    ]
    for name, s in (suite.get("suites") or {}).items():
        ent_lines += [f"### {name}", "", "| Test | Status | Reason |", "|------|--------|--------|"]
        for t in s.get("tests") or []:
            st = "PASS" if t.get("passed") else "FAIL"
            ent_lines.append(f"| {t.get('name', '?')} | {st} | {t.get('reason') or ''} |")
        ent_lines.append("")
    (docs / "enterprise-final-pass-report.md").write_text("\n".join(ent_lines), encoding="utf-8")

    users = report.get("users") or {}
    tables = report.get("tables") or {}
    files = report.get("files") or []
    reset_lines = [
        "# Production Reset Dry-Run Report",
        "",
        "**Mode:** dry-run (no data modified)",
        f"**Database:** `{db}`",
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
        reset_lines.append(f"- id={admin.get('id')} `{admin.get('email', '')}`")
    reset_lines += [
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
            reset_lines.append(
                f"| `{table}` | {info.get('before', '?')} | {info.get('action', 'TRUNCATE')} |"
            )
    reset_lines += ["", "## Upload / cache files", ""]
    for entry in files:
        if isinstance(entry, dict):
            path = entry.get("path", "")
            would = entry.get("would_remove", entry.get("removed", 0))
            reset_lines.append(f"- `{path}`: would remove **{would}** files")
    reset_lines += [
        "",
        "## NOT executed",
        "",
        "`php bin/reset-production.php --confirm=RESET-PRODUCTION` was **not** run.",
        "Execute only after explicit approval and a verified backup (`php bin/erp-backup.php`).",
    ]
    (docs / "reset-dry-run-report.md").write_text("\n".join(reset_lines), encoding="utf-8")
    print(f"Enterprise: {passed}/{total} — reports written to {docs}")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
