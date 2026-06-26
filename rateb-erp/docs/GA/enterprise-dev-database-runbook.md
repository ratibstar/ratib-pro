# Enterprise Tests — Official Dev Database

**Database:** `admin_rateb_erp` (official pre-GA development)  
**Date:** 2026-06-26

## Configuration

Set on the server (`.env` or shell):

```bash
export RATEB_ERP_DB_NAME=admin_rateb_erp
export RATEB_OFFICIAL_DEV_DB=1
export RATEB_ENV=development
```

## Run enterprise seed

```bash
cd rateb-erp
RATEB_OFFICIAL_DEV_DB=1 RATEB_ENTERPRISE_SEED=1 php bin/enterprise-seed/run.php
```

Re-run batches for large targets (journals, invoices, stock movements):

```bash
RATEB_OFFICIAL_DEV_DB=1 php bin/enterprise-seed/run.php --only=journal_entries,invoices,stock_movements
```

## Run enterprise tests

```bash
RATEB_OFFICIAL_DEV_DB=1 php bin/enterprise-test/run.php
RATEB_OFFICIAL_DEV_DB=1 php bin/enterprise-test/run.php --json
```

**Target:** 29/29 PASS (infrastructure includes reset script check).

## Local auditor result (no DB)

```
TOTAL: 16/29 passed
13 failures: database unavailable (connection refused)
```

## Fixes applied for test accuracy

| Fix | File |
|-----|------|
| Allow official dev DB with `RATEB_OFFICIAL_DEV_DB=1` | `bin/enterprise-seed/guard.php` |
| Inter-branch GL check per company (1350+2150) | `bin/enterprise-test/EnterpriseTestRunner.php` |
| Backfill 1350/2150 after seed companies | `bin/enterprise-seed/EnterpriseSeeder.php` |
| Reset script infrastructure test | `bin/enterprise-test/EnterpriseTestRunner.php` |

## Next step

Run the commands above **on the cPanel server** where `admin_rateb_erp` is reachable, then attach `enterprise-test/run.php --json` output to GA certification.
