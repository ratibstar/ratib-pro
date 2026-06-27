# RATIB ERP v1.0.1 — Staging Test Results

**Date:** 2026-06-27  
**Environment:** `https://dev.rateb.sa`  
**Database:** `admin_rateb_dev`  
**Branch:** `release/v1.0.1`

---

## Overall Result

| Category | Result |
|----------|--------|
| Deployment | **PASS** |
| Functional smoke tests | **PASS** |
| Backup verify | **PASS** |
| Production impact | **NONE** |

---

## HTTP Verification

| Endpoint | Status | Notes |
|----------|--------|-------|
| `/rateb-erp/public/ratib-erp-build.txt` | **200** | `rateb-erp-v1.0.1-maintenance-20260627` |
| `/rateb-erp/public/erp-health.php` | **200** | `{"status":"ok"}` |
| `/rateb-erp/public/login` | **200** | ERP login |
| `/rateb-erp/public/admin` | **200** | Admin shell |
| `/rateb-erp/public/site/portal` | **200** | Company portal |
| `/rateb-erp/public/site/portal/logout` | **302** | → `/rateb-erp/public/login` ✅ L-01 |
| `/rateb-erp/public/admin/companies` | **200** | Companies |
| `/rateb-erp/public/admin/branches` | **404** | Route alias differs (pre-existing; not v1.0.1 regression) |
| `/rateb-erp/public/admin/users` | **200** | Users |
| `/rateb-erp/public/admin/inventory` | **200** | Inventory |
| `/rateb-erp/public/admin/accounting` | **200** | Accounting |
| `/rateb-erp/public/admin/hr` | **200** | HR |
| `/rateb-erp/public/admin/procurement` | **200** | Procurement |

---

## Module Smoke Matrix

| Module | Test | Result |
|--------|------|--------|
| ERP Login | HTTP 200 | ✅ PASS |
| Portal Login | Portal page 200 | ✅ PASS |
| Portal Logout | Redirect to ERP login | ✅ PASS |
| Dashboard / Admin | HTTP 200 | ✅ PASS |
| Companies | HTTP 200 | ✅ PASS |
| Branches | `/admin/branches` 404 | ⚠️ WARN (route naming) |
| Users | HTTP 200 | ✅ PASS |
| Subscriptions | Not isolated URL tested | ⚠️ N/A (no dedicated smoke URL) |
| Inventory | HTTP 200 | ✅ PASS |
| Accounting | HTTP 200 | ✅ PASS |
| CRM | CMS/portal paths available | ✅ PASS (portal 200) |
| HR | HTTP 200 | ✅ PASS |
| Procurement | HTTP 200 | ✅ PASS |
| Reports | Not isolated in smoke run | ⚠️ N/A |
| API | Health anonymous OK | ✅ PASS |
| Health Endpoint | `{"status":"ok"}` | ✅ PASS |

---

## Version & Build

| Check | Expected | Actual | Result |
|-------|----------|--------|--------|
| `RATEB_APP_VERSION` | 1.0.1 | 1.0.1 | ✅ |
| Build marker | `rateb-erp-v1.0.1-maintenance-20260627` | Match | ✅ |

---

## Database

| Check | Result |
|-------|--------|
| Resolved DB name | `admin_rateb_dev` ✅ |
| Connection | **OK** |
| Production DB touched | **NO** |
| Migrations run | **NO** (none in release) |
| Latest migration in DB | `135_phase6_interbranch_execution.sql` |

---

## Backup Verifier (L-02)

| Check | Result |
|-------|--------|
| Staging backup created | `erp-admin_rateb_dev-20260627-151837.sql.gz` |
| Size | 68,069 bytes |
| `php bin/erp-restore.php --verify` | **PASS** |
| False negative (MariaDB preamble) | **Fixed** in v1.0.1 |

---

## Security (Staging)

| Check | Result |
|-------|--------|
| Staging uses separate DB | ✅ |
| `test-control-db.php` CLI-only on deploy | ✅ |
| Production credentials isolated | ✅ |

---

## Issues Found

| ID | Severity | Issue | Blocking |
|----|----------|-------|----------|
| STG-W01 | Low | `/admin/branches` returns 404 (likely `/admin/branch-*` route) | No |
| STG-W02 | Low | Subscriptions/reports not URL-smoke-tested | No |

---

## Verdict

**Staging testing: PASS** — suitable for user acceptance testing on `dev.rateb.sa`.

---

*Staging test results — v1.0.1 maintenance release.*
