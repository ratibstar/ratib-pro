# Enterprise Validation Report — GA Security Blockers

**Generated:** 2026-06-26  
**Command:** `php bin/enterprise-test/run.php`  
**Execution time:** ~233 ms (local CLI, no DB)

## Summary

| Metric | Value |
|--------|------:|
| **Passed** | 15 |
| **Failed** | 13 |
| **Skipped (DB-dependent)** | 13 |
| **Total** | 28 |
| **Target** | 24/24 on Staging DB |

## Result: NOT 24/24 — DB unavailable locally

## Suite breakdown

| Suite | Passed | Total |
|-------|-------:|------:|
| branch_isolation | 1 | 6 |
| financial | 2 | 4 |
| transfers | 1 | 6 |
| api_security | 2 | 3 |
| infrastructure | **9** | **9** |

## Infrastructure (all passed — evidence)

```
[PASS] erp-health probe exists
[PASS] erp-backup script exists
[PASS] erp-restore script exists
[PASS] Migration 135 file exists
[PASS] Enterprise seed guard exists
[PASS] Health endpoint has no session impersonation
[PASS] Document barcode tenant gate present
[PASS] SecurityHeaders helper exists
[PASS] ApiRateLimiter helper exists
```

## Failed tests (all: `database unavailable`)

- rateb_user_branches / rateb_branches tables
- Branch-scoped branch_id columns
- HQ / branch manager roles
- Inter-branch GL 1350/2150
- Company financial data
- rateb_branch_transfers + failed status + branch_transfer journal source
- Audit / notifications tables
- rateb_api_tokens table

## Required next step for 24/24

On Staging with `RATEB_ENV=staging` and ERP DB reachable:

```bash
php migrations/run.php
php bin/enterprise-seed/run.php   # optional volume
php bin/enterprise-test/run.php
```

Expected: **28/28** (infrastructure expanded from 5→9 tests in this release).
