# Deployment

## Auto-deploy paths (GitHub Actions fast mode)

- `app/Accounting/Admin/**`
- `rateb-erp/views/admin/accounting-control/**`
- `rateb-erp/public/assets/accounting-control/**`
- `rateb-erp/app/controllers/Admin/AccountingControlController.php`
- `rateb-erp/routes/web.php`, `company.php`
- `rateb-erp/config/lang/ar.php`, `en.php`

## Manual

- Enterprise migrations: `config/migrations/` on `admin_rateb`
- ERP permissions: `rateb-erp/migrations/151_accounting_control_permissions.sql`

## Build marker

Update `rateb-erp/public/ratib-erp-build.txt` and `RATEB_ASSET_BUILD` in `rateb-erp/config/app.php` after asset changes.

## Verify

1. `ratib-erp-build.txt` on production
2. `/accounting-control/diagnostics` → overall PASS or WARN
3. `/accounting-control/health` → schema checks ✓
