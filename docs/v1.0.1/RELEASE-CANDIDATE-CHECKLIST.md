# RATEB ERP v1.0.1 — Release Candidate Checklist

**Date:** 2026-06-27  
**Branch:** `release/v1.0.1`  
**Audit:** Phase 6 — Final release candidate

Legend: **PASS** · **WARNING** · **FAIL**

---

## Runtime

| Item | Status | Notes |
|------|--------|-------|
| Only intended files modified | **PASS** | 6 files; all map to v1.0.1 tasks |
| No accidental modifications | **PASS** | Diff limited to maintenance scope |
| PHP syntax valid | **PASS** | All 4 changed `.php` files pass `php -l` |
| No debug/TODO left in changes | **PASS** | Grep clean on changed PHP |
| No temporary files in scope | **PASS** | None staged or in commit list |

---

## Config

| Item | Status | Notes |
|------|--------|-------|
| Version bump only (`app.php`) | **PASS** | `1.0.0` → `1.0.1` |
| Asset build string updated | **PASS** | `20260627-v1.0.1-maintenance` |
| No production URL changes | **PASS** | Host/SITE_URL unchanged in diff |
| No new ERP env vars required | **PASS** | Runtime config unchanged |
| `test-control-db.php` hardened | **PASS** | CLI-only; env-based creds |

---

## Backup

| Item | Status | Notes |
|------|--------|-------|
| Verifier logic improved | **PASS** | 256KB scan; MariaDB/MySQL headers |
| Backup command scripts unchanged | **PASS** | `erp-backup-verify.php` not in diff |
| No backup artifacts committed | **PASS** | `.gitignore` excludes local `.sql.gz` |
| False negative fix (L-02) | **PASS** | Addresses GA risk register item |

---

## Restore

| Item | Status | Notes |
|------|--------|-------|
| `erp-restore.php` unchanged | **PASS** | Same CLI interface |
| `--verify` flag behavior | **PASS** | Uses same `verifyBackupFile()` API |
| Import logic unchanged | **PASS** | No restore regression |
| Return contract compatible | **PASS** | `valid`, `error`, `size`, `path` |

---

## Health

| Item | Status | Notes |
|------|--------|-------|
| Health probe endpoints | **PASS** | No health files in diff |
| `AutomationHealthService` | **PASS** | Unchanged; benefits from verifier fix |
| Health auth token gate | **PASS** | No regression |

---

## Security

| Item | Status | Notes |
|------|--------|-------|
| Hardcoded password removed | **PASS** | SEC-M01 resolved |
| No secrets in commit diff | **PASS** | Verified |
| Auth core unchanged | **PASS** | `Auth.php` not modified |
| CSP unchanged | **PASS** | No CSP files in diff |
| CSRF unchanged | **PASS** | Portal CSRF validation intact |
| Logout security intact | **PASS** | Session + remember-me + audit |

---

## Portal

| Item | Status | Notes |
|------|--------|-------|
| Logout redirect (L-01) | **PASS** | `rateb_url('login')` |
| Route unchanged | **PASS** | `/site/portal/logout` same |
| Header logout links | **PASS** | Unchanged |
| Profile CSRF | **PASS** | Unchanged |

---

## Billing

| Item | Status | Notes |
|------|--------|-------|
| Billing services | **PASS** | No billing files in diff |
| N-Genius config | **PASS** | No payment URL changes |
| Invoice/subscription logic | **PASS** | Unchanged |

---

## Companies

| Item | Status | Notes |
|------|--------|-------|
| Tenant isolation | **PASS** | `TenantContext` unchanged |
| Company routes | **PASS** | No route diff |
| Branch context | **PASS** | Unchanged |

---

## RBAC

| Item | Status | Notes |
|------|--------|-------|
| Middleware | **PASS** | No middleware diff |
| Permission checks | **PASS** | Unchanged |
| Admin/company gates | **PASS** | Unchanged |

---

## Notifications

| Item | Status | Notes |
|------|--------|-------|
| Notification queue | **PASS** | No changes |
| Portal notifications page | **PASS** | Controller method unchanged |

---

## Automation

| Item | Status | Notes |
|------|--------|-------|
| Cron health integration | **PASS** | Uses verifier via `AutomationHealthService` |
| Migration 023 check | **PASS** | Unchanged in service |
| Automation hardening | **PASS** | No regression |

---

## Monitoring

| Item | Status | Notes |
|------|--------|-------|
| Observability endpoints | **PASS** | No changes |
| Cron warnings | **PASS** | Backup verify warnings now accurate |

---

## API

| Item | Status | Notes |
|------|--------|-------|
| API routes/handlers | **PASS** | No API changes |
| Rate limiting | **PASS** | Unchanged |
| Mobile auth | **PASS** | Unchanged |

---

## Version

| Item | Status | Notes |
|------|--------|-------|
| `RATEB_APP_VERSION` | **PASS** | `1.0.1` |
| Release notes version | **PASS** | Documented |
| GA docs frozen | **PASS** | `rateb-erp/docs/GA/` untouched |

---

## Build Marker

| Item | Status | Notes |
|------|--------|-------|
| `ratib-erp-build.txt` | **PASS** | `rateb-erp-v1.0.1-maintenance-20260627` |
| Fast-deploy sync | **PASS** | File in deploy path |
| Asset cache bust | **PASS** | `RATEB_ASSET_BUILD` updated |

---

## Git Status

| Item | Status | Notes |
|------|--------|-------|
| Branch correct | **PASS** | `release/v1.0.1` |
| Changes uncommitted | **WARNING** | Expected — awaiting operator commit |
| Untracked workflow drafts | **WARNING** | Exclude from commit |
| `origin/main...HEAD` empty | **WARNING** | All delta in working tree (expected) |

---

## Release Docs

| Item | Status | Notes |
|------|--------|-------|
| Release notes present | **PASS** | `RELEASE-NOTES-v1.0.1.md` |
| Changelog present | **PASS** | `CHANGELOG-v1.0.1.md` |
| Migration notes (no schema) | **PASS** | Documented |
| Security changes doc | **PASS** | Present |
| Known issues doc | **PASS** | Present |
| Phase 1–6 audit trail | **PASS** | Complete |

---

## Deployment Ready

| Item | Status | Notes |
|------|--------|-------|
| No migrations required | **PASS** | |
| Rollback path defined | **PASS** | v1.0.0 @ e64c37b3 |
| Downtime expected | **PASS** | None |
| Operator approval pending | **WARNING** | Do not commit until approved |

---

## Summary Counts

| Result | Count |
|--------|-------|
| **PASS** | 58 |
| **WARNING** | 4 |
| **FAIL** | 0 |

**Overall checklist: PASS** (warnings are process-state only, not blockers)

---

*Release candidate checklist — v1.0.1 maintenance.*
