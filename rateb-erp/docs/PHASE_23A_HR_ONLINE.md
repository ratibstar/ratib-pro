# Phase 23A — Enterprise Human Resources Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline HRMS here — deferred to Phase **23B**.  
**Migration:** `migrations/189_hr_platform_enterprise.sql`

## Executive Summary

Phase 23A adds a **tenant-scoped Enterprise HRMS** on additive `rateb_hrm_*` tables for departments, positions, grades, locations, org units, employee profiles, documents meta, contacts/dependents/emergency contacts, certifications/licenses, skills/languages, training + history, performance reviews, goals, competencies, disciplinary actions, rewards, transfers, promotions, assignments, notes, comments, timeline, tags, and status history.

It does **not** replace operational HR under `/hr/*` (`rateb_employees`, attendance, leave, payroll). Soft links only (`legacy_employee_id`, `legacy_job_title_id`, `legacy_workplace_id`, `legacy_document_id`). UI routes live under `/hrm/*` guarded by `rateb_erp_mw('hr', …)`.

## Repository Audit (pre-23A)

| Area | Status | Action |
|------|--------|--------|
| Employees / departments / job titles | Exist (`rateb_employees`, `rateb_hr_*`) | **Reuse** ops; soft-link from HRMS |
| Attendance / leave / payroll | Exist | **Never duplicate** |
| Performance / training / employee skills / contracts | Missing in HR | **Create** under `rateb_hrm_*` |
| Recruitment skills/contracts | Exist under recruitment | **Never modify**; soft-link only if needed |
| `rateb_hrm_*` namespace | Free | **Use** |
| Offline Foundation / Queue / Replay / SDK | Frozen | **Not modified** |
| Manufacturing / EPROC / CRM / Projects / Assets | Frozen siblings | **Not modified** |

## Architecture

```
Controllers (thin, company/hrm)
  → Domain services (EmployeeProfile*, Department*, Training*, …)
  → HumanResourcesWorkflowService ONLY for workflow_status
    → Models (Hrm* → rateb_hrm_*)
      → Database
```

Assignment service is named `HrmAssignmentService` to avoid colliding with Recruitment `AssignmentService` (same pattern as `ManufacturingAssignmentService`).

## Workflow

**Only via `HumanResourcesWorkflowService`:**

- Employee: `draft → registered → active → on_leave → suspended → terminated → archived`
- Training: `planned → scheduled → in_progress → completed → cancelled → archived`
- Performance: `draft → submitted → approved → closed → archived`

## Security

- Tenant + branch scoped rows
- `public_uuid` on every table
- Optimistic locking via `version`
- CSRF on mutating forms
- Soft delete (`deleted_at`)
- Permission checks (`hr.view` / `hr.create` / `hr.update` / `hr.delete` / `hr.training` / `hr.performance` / `hr.promotions` / `hr.transfers` / `hr.admin` / `hr.manage`)

## Migration

`189_hr_platform_enterprise.sql` — 30 additive `CREATE TABLE IF NOT EXISTS rateb_hrm_*` + permission seed (`ON DUPLICATE KEY UPDATE`) + role grants for `company-full-access` / `super-admin`. No `ALTER` of legacy HR/attendance/payroll tables.

## Offline readiness (for later Phase 23B)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Profile / department / position CRUD | domain services | YES | Draft/metadata |
| Training create + enroll | `TrainingService` | YES | |
| Performance / goals / competencies | domain services | YES | |
| Promotion / transfer create | domain services | YES | |
| Workflow transition | `HumanResourcesWorkflowService` | YES | Must call service |
| Comments / notes / document meta | domain services | YES | Meta only; no binary upload |
| Attendance / leave / payroll | legacy HR | NO in 23A | Existing Offline HR Phase 4 only |

## Performance

- Paginated lists (`LIMIT`/`OFFSET` + `COUNT`)
- Timeline append-only indexes on `(company_id, created_at)` and entity keys
- Company-scoped queries with `deleted_at IS NULL`

## Regression

Verified untouched markers: SDK **14.2.0**, IndexedDB **DB_VERSION 2**, Offline Queue/Replay architecture, Manufacturing, Recruitment, Accounting, CRM, Projects, Assets, Approval, Procurement, POS, Inventory, legacy `/hr/*`.

## Tests

```bash
php tests/hr/run-hr-phase23a-tests.php
```

Target: **GATE: CLEAR (0/13 failed)**

## Production readiness

1. Apply `migrations/189_hr_platform_enterprise.sql`
2. Confirm sidebar shows **Enterprise HR** (`/hrm/*`) beside legacy HR
3. Pilot with `hr.view` + granular slugs as needed
4. Do **not** enable Offline HRMS until Phase 23B
