# Phase 25A — Enterprise Quality Management Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline QMS here — deferred to Phase **25B**.  
**Migration:** `migrations/191_quality_management_platform.sql`

## Executive Summary

Phase 25A adds a **tenant-scoped Enterprise Quality Management System (QMS)** on additive `rateb_qms_*` tables for programs, plans, standards, checklists/items, inspections, results, defects, nonconformities, root causes, corrective/preventive actions, audits/findings, complaints, supplier quality, training, documents meta, comments, assignments, timeline, status history, and tags.

It does **not** replace Manufacturing shop-floor QC (`rateb_mfg_quality_checks` / `QualityCheckService`) or EAM inspections. Soft links only (`mfg_quality_check_id`, `eam_inspection_id`, `legacy_supplier_id`, `eproc_profile_id`). UI routes live under `/qms/*` guarded by `rateb_erp_mw('quality', …)`.

## Repository Audit (pre-25A)

| Area | Status | Action |
|------|--------|--------|
| MFG quality checks | Exist (`rateb_mfg_quality_checks`, `QualityCheckService`) | **Never ALTER**; soft-link via `mfg_quality_check_id`; QMS uses `QualityInspectionService` |
| EAM inspections / checklists | Exist | **Never modify**; soft-link via `eam_inspection_id` |
| Procurement / suppliers | Exist | Soft-link `legacy_supplier_id` / `eproc_profile_id` only |
| HR training | Exist | Soft-link metadata only |
| `rateb_qms_*` / `/qms/*` | Absent | **Greenfield** additive namespace |
| Offline Foundation / Queue / Replay / SDK / IndexedDB | Frozen | **Not modified** |

## Architecture

```
Controllers (thin, company/qms)
  → Domain services (QualityPlan*, QualityInspection*, QmsCorrectiveAction*, …)
  → QualityWorkflowService ONLY for workflow_status
    → Models (Qms* → rateb_qms_*)
      → Database
```

## Workflow

**Only via `QualityWorkflowService`:**

**Inspection / Audit:**  
`planned → scheduled → in_progress → completed → approved → archived`

**Corrective / Preventive action:**  
`draft → assigned → in_progress → verified → closed → archived`

## Security

- Tenant + branch scoped rows
- `public_uuid` on every table
- Optimistic locking via `version`
- CSRF on mutating forms
- Soft delete (`deleted_at`)
- Permissions: `quality.view`, `quality.create`, `quality.update`, `quality.inspect`, `quality.audit`, `quality.corrective`, `quality.preventive`, `quality.admin`, `quality.manage`

## Migration

`191_quality_management_platform.sql` — 23 additive `CREATE TABLE IF NOT EXISTS rateb_qms_*` + permission seed (`ON DUPLICATE KEY UPDATE`) + role grants for `company-full-access` / `super-admin`. No `ALTER` of MFG/EAM/procurement/HR/payroll tables.

## Offline readiness (for later Phase 25B)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Plan / standard / checklist create | domain services | YES | Master data |
| Inspection create | `QualityInspectionService` | YES | Starts `planned` |
| Workflow transition | `QualityWorkflowService` | YES | Must call service |
| Defect / NC / CAPA create | domain services | YES | |
| Audit / complaint / supplier quality | domain services | YES | |
| Comments / document meta | domain services | YES | Meta only |
| MFG `QualityCheckService` | Manufacturing | NO in 25A | Soft-link only |
| Offline adapter / replay | N/A | Deferred 25B | Not shipped in 25A |

## Performance

- Paginated lists (`LIMIT`/`OFFSET` + `COUNT`)
- Timeline append-only indexes on `(company_id, created_at)` and entity keys
- Company-scoped queries with `deleted_at IS NULL`
- Workflow status indexes on inspections / CAPA / audits
- No heavy multi-module joins

## Regression

Verified untouched markers: SDK **14.2.0**, IndexedDB **DB_VERSION 2**, Offline Queue/Replay architecture, Manufacturing, Assets/EAM, Procurement, Projects, Payroll, Accounting, CRM, HR/HRMS, Recruitment, POS, Inventory.

## Tests

```bash
php tests/quality/run-quality-phase25a-tests.php
```

Target: **GATE CLEAR (0/13 failed)**.

## Production Readiness

- Additive migration only; run `191_quality_management_platform.sql` on deploy
- Assign `quality.*` permissions / enable `quality` company module
- Sidebar + EN/AR labels wired
- No Offline Foundation / SDK / IndexedDB changes
- Soft-links only to MFG/EAM — existing shop-floor and asset inspection flows unchanged
