# RATEB ERP v1.0.1 — Merge Checklist

**PR:** `release/v1.0.1` → `main`  
**Date:** 2026-06-27  
**Legend:** PASS · WARNING · FAIL

---

## Branch & History

| Item | Status |
|------|--------|
| Source branch `release/v1.0.1` | **PASS** |
| Target branch `main` | **PASS** |
| Ahead by 2 commits only | **PASS** |
| Linear history (no merge commits) | **PASS** |
| No binary/backup/vendor files | **PASS** |

---

## Runtime

| Item | Status |
|------|--------|
| Backup verifier fix | **PASS** |
| Portal logout redirect | **PASS** |
| Version 1.0.1 | **PASS** |
| Build marker updated | **PASS** |
| No unintended runtime changes | **PASS** |

---

## Config

| Item | Status |
|------|--------|
| `app.php` — version constants only | **PASS** |
| No production URL changes | **PASS** |
| No new ERP env vars | **PASS** |
| `test-control-db.php` hardened | **PASS** |
| `.gitignore` additive only | **PASS** |

---

## Backup

| Item | Status |
|------|--------|
| Verifier improved (MariaDB-safe) | **PASS** |
| Backup scripts unchanged | **PASS** |
| No backup artifacts in PR | **PASS** |

---

## Restore

| Item | Status |
|------|--------|
| `erp-restore.php` unchanged | **PASS** |
| Same verify API contract | **PASS** |

---

## Health

| Item | Status |
|------|--------|
| Health endpoints unchanged | **PASS** |
| Health auth unchanged | **PASS** |

---

## Security

| Item | Status |
|------|--------|
| Hardcoded creds removed | **PASS** |
| No secrets added in diff | **PASS** |
| Auth core unchanged | **PASS** |
| CSP unchanged | **PASS** |
| CSRF unchanged | **PASS** |

---

## Portal

| Item | Status |
|------|--------|
| Logout redirect to ERP login | **PASS** |
| Session destruction intact | **PASS** |
| Routes unchanged | **PASS** |

---

## Billing

| Item | Status |
|------|--------|
| No billing file changes | **PASS** |

---

## Companies

| Item | Status |
|------|--------|
| Tenant isolation unchanged | **PASS** |
| Company routes unchanged | **PASS** |

---

## RBAC

| Item | Status |
|------|--------|
| Middleware unchanged | **PASS** |
| Permission logic unchanged | **PASS** |

---

## Notifications

| Item | Status |
|------|--------|
| No notification changes | **PASS** |

---

## Automation

| Item | Status |
|------|--------|
| Automation health uses verifier (benefit only) | **PASS** |
| Cron scripts unchanged | **PASS** |

---

## Monitoring

| Item | Status |
|------|--------|
| Observability unchanged | **PASS** |

---

## API

| Item | Status |
|------|--------|
| No API changes | **PASS** |
| No contract changes | **PASS** |

---

## HR / CRM / Inventory / Procurement / Subscription

| Item | Status |
|------|--------|
| HR | **PASS** — no changes |
| CRM | **PASS** — no changes |
| Inventory | **PASS** — no changes |
| Procurement | **PASS** — no changes |
| Subscription | **PASS** — no changes |

---

## Version

| Item | Status |
|------|--------|
| `RATEB_APP_VERSION` = 1.0.1 | **PASS** |
| GA docs frozen | **PASS** |

---

## Build Marker

| Item | Status |
|------|--------|
| `rateb-erp-v1.0.1-maintenance-20260627` | **PASS** |

---

## Git Status

| Item | Status |
|------|--------|
| Remote branch exists | **PASS** |
| Local = remote SHA | **PASS** |
| `main` unchanged until merge | **PASS** |
| Workflow drafts not in PR | **PASS** |

---

## Release Docs

| Item | Status |
|------|--------|
| RELEASE-NOTES | **PASS** |
| CHANGELOG | **PASS** |
| KNOWN-ISSUES | **PASS** |
| SECURITY-CHANGES | **PASS** |
| PHASE 1–7 reports | **PASS** |

---

## Deployment Ready

| Item | Status |
|------|--------|
| No migrations | **PASS** |
| No SQL | **PASS** |
| No schema change | **PASS** |
| `deploy.yml` unchanged | **PASS** |
| Merge triggers deploy | **WARNING** — expected; requires operator approval |
| Rollback path defined | **PASS** |

---

## Summary

| Result | Count |
|--------|-------|
| **PASS** | 58 |
| **WARNING** | 1 |
| **FAIL** | 0 |

**Merge checklist:** **PASS** — ready for merge after operator approval.

---

*Merge checklist — v1.0.1 maintenance release.*
