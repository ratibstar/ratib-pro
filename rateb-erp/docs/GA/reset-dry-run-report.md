# Production Reset Dry-Run Report

**Mode:** dry-run (no data modified)
**Database:** `admin_rateb-erp`
**Started:** 2026-06-26T23:32:31+03:00
**Finished:** 2026-06-26T23:32:31+03:00
**Probe:** `https://rateb.sa/rateb-erp/public/erp-security-cert.php?enterprise=1&reset_dry_run=1`

## Preserved (never truncated)

- `rateb_migrations`, RBAC (`rateb_permissions`, `rateb_roles`, `rateb_role_permissions`)
- Super-admin users (`rateb_users` where `is_super_admin = 1`)
- System settings, email/SMS templates
- All `rateb_cms_*` marketing/CMS tables

## Users

- Non-super-admin users to delete: **2**

### Preserved super-admins

- id=1 `admin@rateb.sa`
- id=2 `ahmedashrafabdalmonem77@gmail.com`

## Tables to truncate

**Count:** 94 tables

| Table | Rows before | Action |
|-------|------------:|--------|
| `rateb_api_tokens` | 0 | TRUNCATE |
| `rateb_approval_actions` | 6 | TRUNCATE |
| `rateb_approval_instances` | 3 | TRUNCATE |
| `rateb_approval_workflow_steps` | 3 | TRUNCATE |
| `rateb_approval_workflows` | 2 | TRUNCATE |
| `rateb_asset_assignments` | 1 | TRUNCATE |
| `rateb_asset_categories` | 0 | TRUNCATE |
| `rateb_asset_depreciation` | 2 | TRUNCATE |
| `rateb_asset_maintenance` | 1 | TRUNCATE |
| `rateb_assets` | 1 | TRUNCATE |
| `rateb_attendance_records` | 3 | TRUNCATE |
| `rateb_audit_logs` | 336 | TRUNCATE |
| `rateb_bank_accounts` | 6 | TRUNCATE |
| `rateb_bank_statement_lines` | 0 | TRUNCATE |
| `rateb_blood_donors` | 0 | TRUNCATE |
| `rateb_blood_units` | 0 | TRUNCATE |
| `rateb_branch_transfers` | 0 | TRUNCATE |
| `rateb_branches` | 7 | TRUNCATE |
| `rateb_budget_lines` | 0 | TRUNCATE |
| `rateb_cash_vouchers` | 6 | TRUNCATE |
| `rateb_chart_of_accounts` | 179 | TRUNCATE |
| `rateb_companies` | 6 | TRUNCATE |
| `rateb_company_tax_profiles` | 0 | TRUNCATE |
| `rateb_contract_renewals` | 5 | TRUNCATE |
| `rateb_contracts` | 9 | TRUNCATE |
| `rateb_cost_centers` | 2 | TRUNCATE |
| `rateb_cron_health` | 0 | TRUNCATE |
| `rateb_customers` | 0 | TRUNCATE |
| `rateb_device_categories` | 0 | TRUNCATE |
| `rateb_device_service_history` | 1 | TRUNCATE |
| `rateb_device_spare_parts` | 2 | TRUNCATE |
| `rateb_documents` | 67 | TRUNCATE |
| `rateb_employees` | 5 | TRUNCATE |
| `rateb_fiscal_periods` | 3 | TRUNCATE |
| `rateb_hr_departments` | 3 | TRUNCATE |
| `rateb_hr_documents` | 0 | TRUNCATE |
| `rateb_hr_employee_requests` | 0 | TRUNCATE |
| `rateb_hr_fleet` | 2 | TRUNCATE |
| `rateb_hr_holidays` | 1 | TRUNCATE |
| `rateb_hr_loan_types` | 0 | TRUNCATE |
| `rateb_hr_loans` | 0 | TRUNCATE |
| `rateb_hr_payroll_components` | 0 | TRUNCATE |
| `rateb_hr_payroll_structures` | 0 | TRUNCATE |
| `rateb_hr_permission_requests` | 0 | TRUNCATE |
| `rateb_hr_workplaces` | 0 | TRUNCATE |
| `rateb_inventory` | 13 | TRUNCATE |
| `rateb_inventory_audit_lines` | 2 | TRUNCATE |
| `rateb_inventory_audits` | 2 | TRUNCATE |
| `rateb_inventory_batches` | 6 | TRUNCATE |
| `rateb_invoice_lines` | 1 | TRUNCATE |
| `rateb_invoices` | 1 | TRUNCATE |
| `rateb_journal_entries` | 15 | TRUNCATE |
| `rateb_journal_lines` | 18 | TRUNCATE |
| `rateb_leave_balances` | 13 | TRUNCATE |
| `rateb_leave_requests` | 1 | TRUNCATE |
| `rateb_leave_types` | 11 | TRUNCATE |
| `rateb_lims_results` | 0 | TRUNCATE |
| `rateb_lims_samples` | 0 | TRUNCATE |
| `rateb_login_activity` | 53 | TRUNCATE |
| `rateb_login_barcode_pairs` | 1 | TRUNCATE |
| `rateb_medical_devices` | 1 | TRUNCATE |
| `rateb_notification_queue` | 26 | TRUNCATE |
| `rateb_notifications` | 17 | TRUNCATE |
| `rateb_password_resets` | 0 | TRUNCATE |
| `rateb_payments` | 0 | TRUNCATE |
| `rateb_payroll_lines` | 0 | TRUNCATE |
| `rateb_payroll_periods` | 1 | TRUNCATE |
| `rateb_pharmacy_dispenses` | 0 | TRUNCATE |
| `rateb_pharmacy_prescriptions` | 0 | TRUNCATE |
| `rateb_product_categories` | 8 | TRUNCATE |
| `rateb_purchase_invoices` | 3 | TRUNCATE |
| `rateb_purchase_items` | 6 | TRUNCATE |
| `rateb_purchase_orders` | 5 | TRUNCATE |
| `rateb_purchase_request_items` | 9 | TRUNCATE |
| `rateb_purchase_requests` | 7 | TRUNCATE |
| `rateb_remember_tokens` | 2 | TRUNCATE |
| `rateb_rfq` | 5 | TRUNCATE |
| `rateb_stock_movements` | 14 | TRUNCATE |
| `rateb_subscriptions` | 3 | TRUNCATE |
| `rateb_supplier_classifications` | 4 | TRUNCATE |
| `rateb_supplier_comm_timeline` | 50 | TRUNCATE |
| `rateb_supplier_communications` | 9 | TRUNCATE |
| `rateb_supplier_evaluations` | 14 | TRUNCATE |
| `rateb_supplier_payments` | 0 | TRUNCATE |
| `rateb_supplier_quotations` | 3 | TRUNCATE |
| `rateb_suppliers` | 7 | TRUNCATE |
| `rateb_support_tickets` | 1 | TRUNCATE |
| `rateb_tender_comparisons` | 0 | TRUNCATE |
| `rateb_tenders` | 1 | TRUNCATE |
| `rateb_two_factor_backup_codes` | 0 | TRUNCATE |
| `rateb_user_branches` | 1 | TRUNCATE |
| `rateb_user_roles` | 1 | TRUNCATE |
| `rateb_warehouse_transfers` | 1 | TRUNCATE |
| `rateb_warehouses` | 16 | TRUNCATE |

## Upload / cache files

- `/home/admin/domains/rateb.sa/public_html/rateb-erp/storage/uploads`: would remove **64** files
- `/home/admin/domains/rateb.sa/public_html/rateb-erp/storage/rate-limit`: would remove **25** files

## NOT executed

`php bin/reset-production.php --confirm=RESET-PRODUCTION` was **not** run.
Execute only after explicit approval and a verified backup (`php bin/erp-backup.php`).