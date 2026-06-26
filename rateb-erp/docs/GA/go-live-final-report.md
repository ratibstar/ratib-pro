# RATIB ERP v1.0 — Go-Live Final Report

**Report date:** 2026-06-27  
**Code freeze:** ACTIVE — no code changes unless critical production defect  
**Application:** RATIB ERP `1.0.0`  
**Production host:** `https://rateb.sa`  
**ERP database:** `admin_rateb-erp`  
**Build marker:** `20260626-csp-cdnjs-fix`  
**Enterprise probe:** `https://rateb.sa/rateb-erp/public/erp-security-cert.php?enterprise=1`

---

## Executive decision

# ❌ NOT YET READY FOR GO-LIVE

Pre-go-live **certification** (security + enterprise tests + reset dry-run preview) is **complete**.

The **go-live execution checklist** (backup → restore proof → production reset → post-reset validation) is **not complete**. Do **not** run `reset-production.php --confirm=RESET-PRODUCTION` until items 1–2 pass and explicit written approval is recorded.

When all checklist items pass, update this document and change the decision to:

**✅ RATIB ERP v1.0 READY FOR GO-LIVE**

---

## Pre-freeze certification (completed)

| Check | Result | Evidence |
|-------|--------|----------|
| Security Phase 6 | ✅ PASS | `critical: 0`, `high: 0`, `open_findings: []` |
| Enterprise suite (live DB) | ✅ **31/31 PASS** | `enterprise_suite.failed: 0` (latest: `2026-06-26T23:55:00+03:00`) |
| Reset dry-run (preview) | ✅ VALIDATED | 94 business tables; preserves documented below |
| UI CSP (Font Awesome) | ✅ FIXED | `65768dca` — `cdnjs.cloudflare.com` allowed in CSP |
| Production reset executed | ❌ **NOT RUN** | By design — awaiting approval |

Related reports:

- `rateb-erp/docs/GA/enterprise-final-pass-report.md`
- `rateb-erp/docs/GA/reset-dry-run-report.md`
- `rateb-erp/docs/GA/production-reset-procedure.md`

---

## Checklist 1 — Backup

| Requirement | Status | Notes |
|-------------|--------|-------|
| Run `php bin/erp-backup.php` | ⏳ **PENDING** | Must run on server (CLI + `mysqldump`) |
| Backup completed successfully | ⏳ PENDING | |
| Archive readable | ⏳ PENDING | |
| Database dump valid | ⏳ PENDING | Use `php bin/erp-restore.php --verify <file>` |
| Location documented | ✅ DOCUMENTED | See below |

### Documented backup location

On the production server:

```
/home/admin/domains/rateb.sa/public_html/rateb-erp/storage/backups/
```

Expected artifacts per run:

| File pattern | Contents |
|--------------|----------|
| `erp-{db_name}-{Ymd-His}.sql.gz` | Full MySQL dump (gzip) |
| `erp-files-{Ymd-His}.tar.gz` | `storage/uploads/` archive (if uploads exist) |

### Server commands (operator)

```bash
cd /home/admin/domains/rateb.sa/public_html/rateb-erp
php bin/erp-backup.php
# Verify latest dump:
php bin/erp-restore.php --verify storage/backups/erp-admin_rateb-erp-YYYYMMDD-HHMMSS.sql.gz
```

Alternative (deploy token):

```bash
curl -X POST "https://rateb.sa/rateb-erp/public/enterprise-cert-run.php" \
  -H "X-Rateb-Migrate-Token: $RATEB_ERP_MIGRATE_TOKEN" \
  -d "action=backup"
```

**Gate:** Do not proceed to reset until backup exit code is `0` and `--verify` passes.

---

## Checklist 2 — Restore verification

| Requirement | Status | Notes |
|-------------|--------|-------|
| Restore latest backup to temporary DB | ⏳ **PENDING** | Requires separate DB or staging schema |
| Database integrity confirmed | ⏳ PENDING | |
| File integrity confirmed | ⏳ PENDING | Extract `erp-files-*.tar.gz` if used |
| Application boots successfully | ⏳ PENDING | Point temp `.env` at restored DB |

### Recommended procedure

1. Create temporary database (e.g. `admin_rateb_erp_restore_test`).
2. Restore dump:
   ```bash
   php bin/erp-restore.php storage/backups/erp-admin_rateb-erp-YYYYMMDD-HHMMSS.sql.gz
   ```
   (Set `RATEB_ERP_DB_NAME` to temp DB for restore only.)
3. Run `php bin/enterprise-test/run.php --json` against temp DB.
4. Drop temp DB after verification.

**Gate:** Do not continue if restore verification fails.

---

## Checklist 3 — Reset dry run

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Execute `--dry-run` | ✅ **DONE** | Live probe + CLI script validated |
| Tables scheduled for deletion reviewed | ✅ | **94** business tables |
| Preserved tables reviewed | ✅ | See preserve list |
| Super-admin accounts preserved | ✅ | 2 accounts |
| System settings preserved | ✅ | `rateb_system_settings` in preserve list |
| Migrations preserved | ✅ | `rateb_migrations` in preserve list |
| CMS preserved | ✅ | All `rateb_cms_*` tables |
| No unexpected tables | ✅ | Only `rateb_*` business tables in wipe order |

### Live dry-run probe

```
GET https://rateb.sa/rateb-erp/public/erp-security-cert.php?enterprise=1&reset_dry_run=1
```

Latest snapshot (2026-06-26):

| Item | Value |
|------|------:|
| Database | `admin_rateb-erp` |
| Tables to truncate | 94 |
| Non-super-admin users to delete | 2 |
| Super-admins preserved | `admin@rateb.sa`, `ahmedashrafabdalmonem77@gmail.com` |

### Preserved (never truncated)

- `rateb_migrations`
- RBAC: `rateb_permissions`, `rateb_roles`, `rateb_role_permissions`, `rateb_plans`
- `rateb_system_settings`, `rateb_email_templates`, `rateb_sms_templates`
- All `rateb_cms_*` marketing/CMS tables
- `rateb_users` where `is_super_admin = 1`

### Server CLI (equivalent)

```bash
php bin/reset-production.php --dry-run
```

Full table-level detail: `rateb-erp/docs/GA/reset-dry-run-report.md`

---

## Checklist 4 — Production reset

| Requirement | Status |
|-------------|--------|
| Explicit approval received | ❌ **NOT RECEIVED** |
| `--confirm=RESET-PRODUCTION` executed | ❌ **NOT EXECUTED** |

### Command (DO NOT RUN without approval)

```bash
php bin/reset-production.php --confirm=RESET-PRODUCTION
```

### Capture template (fill after execution)

| Field | Value |
|-------|-------|
| Start time | _TBD_ |
| End time | _TBD_ |
| Execution log | `rateb-erp/storage/logs/reset-production-{Ymd-His}.json` |
| Tables truncated | _TBD_ |
| Users deleted (non-admin) | _TBD_ |
| Files removed under uploads | _TBD_ |
| Errors | _TBD_ |

---

## Checklist 5 — Post-reset verification

| Check | Status |
|-------|--------|
| Login works | ⏳ N/A until after reset |
| Super Admin works | ⏳ N/A |
| Dashboard loads | ⏳ N/A |
| `rateb_migrations` intact | ⏳ N/A |
| Settings intact | ⏳ N/A |
| Roles intact | ⏳ N/A |
| Languages intact | ⏳ N/A |
| Permissions intact | ⏳ N/A |
| No business transactions remain | ⏳ N/A |

### Post-reset verification commands

```bash
# After reset — on server
php bin/enterprise-test/run.php --json
# Expect infrastructure + schema tests PASS; company/financial tests may show empty DB (expected)

# Manual UI checks:
# - Login as admin@rateb.sa
# - Dashboard, settings, roles, permissions pages load
# - Companies list empty (0 rows)
# - CMS marketing site still renders
```

---

## Checklist 6 — Release confirmation summary

| Phase | Status |
|-------|--------|
| 1. Backup | ⏳ PENDING — operator action on server |
| 2. Restore verification | ⏳ PENDING — temp DB required |
| 3. Reset dry run | ✅ COMPLETE |
| 4. Production reset | ❌ BLOCKED — no approval |
| 5. Post-reset validation | ⏳ N/A |
| 6. Final declaration | ❌ **NOT READY FOR GO-LIVE** |

---

## Remaining issues

| ID | Severity | Issue | Action |
|----|----------|-------|--------|
| GL-01 | **Blocker** | Pre-reset backup not executed in this session | Run `erp-backup.php` on server; verify with `--verify` |
| GL-02 | **Blocker** | Restore to temp environment not proven | Complete checklist item 2 |
| GL-03 | **Blocker** | Production reset not approved/executed | Await explicit approval |
| GL-04 | Low | `enterprise-ga-final-certification.md` outdated | Update after go-live execution (doc only) |
| GL-05 | Info | DB name hyphen vs underscore | Server uses `admin_rateb-erp`; both names refer to same ERP DB in docs |

No critical production code defects open at code freeze. UI icon issue (CSP) resolved in `65768dca`.

---

## Code freeze notice

From this point:

- **No code changes** unless a **critical production issue** is discovered.
- Any fix requires a **new release version** (v1.0.1+).
- Documentation updates for go-live execution are permitted.

---

## Approval record (fill before reset)

| Role | Name | Date | Signature / ticket |
|------|------|------|---------------------|
| Product owner | | | |
| Technical lead | | | |
| DBA / Ops | | | |

**Reset approval phrase:** `RESET-PRODUCTION`

---

*Generated as part of RATIB ERP v1.0 go-live checklist. Update this file after each checklist step completes.*
