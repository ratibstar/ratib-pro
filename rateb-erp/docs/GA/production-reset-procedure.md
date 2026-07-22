# Production Reset Procedure — RATEB ERP v1.0 GA

**Script:** `bin/reset-production.php`  
**Status:** Generated and validated (structure). **Not executed** in this session.

## Purpose

After enterprise validation on the official development database (`admin_rateb_erp`), reset all business data before GA go-live while keeping:

- Schema + migrations
- Super-admin user(s)
- Roles, permissions, role-permission map
- System settings, email/SMS templates
- CMS marketing content (public site)
- Plans (subscription catalog)

## Safety

| Guard | Behaviour |
|-------|-----------|
| CLI only | HTTP blocked |
| `--confirm=RESET-PRODUCTION` | Required for execution |
| `--dry-run` | Lists row counts, no changes |
| `--validate` | Checks script structure (no DB) |
| FK checks | Disabled only during truncate, re-enabled after |
| Report | JSON log under `storage/logs/reset-production-*.json` |

## Commands (run on server with DB access)

```bash
cd rateb-erp

# 1. Full backup first
php bin/erp-backup.php

# 2. Validate script
php bin/reset-production.php --validate

# 3. Preview
php bin/reset-production.php --dry-run

# 4. Execute (only after backup + review)
php bin/reset-production.php --confirm=RESET-PRODUCTION
```

## What is deleted

- All companies, branches, tenants users (non-super-admin)
- Accounting: journals, COA, invoices, payments, vouchers
- HR: employees, attendance, payroll, leaves
- CRM: customers
- Inventory: items, movements, warehouses
- Procurement: PR, PO, suppliers (transactional)
- Contracts, assets, transfers, approvals
- Audit logs, login activity, notifications, API tokens
- Files under `storage/uploads/` and `storage/rate-limit/`

## What is preserved

See `ProductionResetRunner::PRESERVE_TABLES` in `bin/reset-production.php`.

## Post-reset expectation

- Super-admin can log in
- Empty tenant list (no companies until onboarded)
- Migrations table unchanged
- Application behaves like fresh install on existing schema

## Validation result (local, 2026-06-26)

```
php bin/reset-production.php --validate
→ OK — script structure valid
```

DB dry-run requires connection to `admin_rateb_erp` on the server.
