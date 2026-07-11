# Phase 24A — Enterprise Payroll Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline Payroll here — deferred to Phase **24B**.  
**Migration:** `migrations/190_payroll_platform_enterprise.sql`

## Executive Summary

Phase 24A adds a **tenant-scoped Enterprise Payroll Platform** on additive `rateb_payroll_*` tables for salary structures/components, earning/deduction types, employee salary assignments, cycles, run periods, batches, items, payslips, overtime, bonuses, commissions, loans/installments, advances, reimbursements, settlements, adjustments, notes, comments, timeline, attachments meta, status history, assignments, and audit.

It does **not** replace operational payroll under `/hr/payroll` (`rateb_payroll_periods`, `rateb_payroll_lines`, `rateb_hr_payroll_*`). Soft links only (`hrm_employee_profile_id`, `legacy_employee_id`, `legacy_payroll_period_id`, `attendance_ref`, `leave_ref`). No auto GL posting. UI routes live under `/payroll/*` guarded by `rateb_erp_mw('payroll', …)`.

## Repository Audit (pre-24A)

| Area | Status | Action |
|------|--------|--------|
| Legacy payroll periods/lines | Exist (`rateb_payroll_periods`, `rateb_payroll_lines`) | **Never ALTER**; soft-link via `legacy_payroll_period_id` |
| HR payroll components/structures | Exist (`rateb_hr_payroll_*`) | **Never duplicate** |
| HRMS employees | Exist (`rateb_hrm_employee_profiles`) | **Soft-link** via `hrm_employee_profile_id` |
| Attendance / leave | Exist | **Never modify**; `attendance_ref` / `leave_ref` metadata only |
| Accounting GL | Exist | **Never auto-post**; `accounting_post_ref` metadata only |
| `rateb_payroll_*` enterprise namespace | New additive tables | **Use** (run periods named `rateb_payroll_run_periods` to avoid legacy `rateb_payroll_periods` collision) |
| Offline Foundation / Queue / Replay / SDK | Frozen | **Not modified** |

## Architecture

```
Controllers (thin, company/payroll)
  → Domain services (PayrollStructure*, PayrollBatch*, PayrollCalculation*, Loan*, …)
  → PayrollWorkflowService ONLY for batch workflow_status
    → Models (Payroll* → rateb_payroll_*)
      → Database
```

## Workflow

**Only via `PayrollWorkflowService` (batch entity):**

`draft → prepared → calculated → reviewed → approved → posted → closed → archived`

## Security

- Tenant + branch scoped rows
- `public_uuid` on every table
- Optimistic locking via `version`
- CSRF on mutating forms
- Soft delete (`deleted_at`)
- Permissions: `payroll.view`, `payroll.create`, `payroll.calculate`, `payroll.review`, `payroll.approve`, `payroll.post`, `payroll.admin`, `payroll.manage`

## Migration

`190_payroll_platform_enterprise.sql` — 26 additive `CREATE TABLE IF NOT EXISTS rateb_payroll_*` + permission seed (`ON DUPLICATE KEY UPDATE`) + role grants for `company-full-access` / `super-admin`. No `ALTER` of legacy payroll/attendance/leave/accounting tables.

## Offline readiness (for later Phase 24B)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Batch create / item add | `PayrollBatchService`, `PayrollCalculationService` | YES | Draft/calculate path |
| Workflow transition | `PayrollWorkflowService` | YES | Must call service |
| Payslip issue | `PayrollPayslipService` | YES | |
| Loans / advances / overtime | domain services | YES | |
| Comments / document meta | `PayrollCommentService`, `PayrollDocumentMetaService` | YES | Meta only |
| Legacy hr/payroll | `HrService` | NO in 24A | Existing paths only |
| Auto GL | N/A | NO | Explicitly rejected |

## Performance

- Paginated lists (`LIMIT`/`OFFSET` + `COUNT`)
- Timeline append-only indexes on `(company_id, created_at)` and entity keys
- Company-scoped queries with `deleted_at IS NULL`
- Batch workflow index on `(company_id, workflow_status, deleted_at)`

## Regression

Verified untouched markers: SDK **14.2.0**, IndexedDB **DB_VERSION 2**, Offline Queue/Replay architecture, HRMS, Accounting, Manufacturing, Recruitment, CRM, Projects, Assets, Approval, Procurement, POS, Inventory, legacy `/hr/payroll`.

## Tests

```bash
php tests/payroll/run-payroll-phase24a-tests.php
```

Gate: **CLEAR (0/13 failed)** — baseline, migration, services, workflow, RBAC, routes, views, docs.

## Production Readiness

1. Apply migration `190_payroll_platform_enterprise.sql` on target environment.
2. Grant `payroll.*` permissions via existing RBAC matrix.
3. Pilot on `/payroll/dashboard` — legacy `/hr/payroll` remains available.
4. Phase 24B offline remains separate; no SDK/IDB changes in 24A.
