# Phase 19A — Enterprise Assets & Maintenance Platform (ONLINE FOUNDATION)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline. No Queue / Replay / SDK / SW / IDB changes.  
**Migration:** `migrations/185_assets_platform_enterprise.sql`

## Executive Summary

Phase 19A adds a **tenant-scoped Enterprise Assets & Maintenance (EAM)** module on additive `rateb_eam_*` tables. It does **not** replace the legacy fixed-assets register (`rateb_assets`, `/assets`, `AssetsController`, `AssetDeviceWorkflowService`). UI routes live under `/eam/*` guarded by `rateb_erp_mw('assets', …)`.

## Repository Audit (pre-19A)

| Area | Status |
|------|--------|
| Legacy `rateb_assets` / assignments / maintenance | **Preserved** (untouched) |
| Enterprise WO / meter / insurance / checklist platform | **Missing → created** (`rateb_eam_*`) |
| Offline Assets | Deferred to **Phase 19B** |
| Offline Foundation / Queue / Replay / SDK | **Frozen — not modified** |

## Architecture

```
Controllers (thin, company/eam)
  → Domain services (Asset*, Maintenance*, WorkOrder*, …)
  → AssetWorkflowService ONLY for workflow_status
  → Models (Eam* → rateb_eam_*)
  → Database
```

Binary uploads reuse existing `DocumentService` (ONLINE ONLY). `AssetDocumentMetaService` stores metadata links only.

## Workflow

**Asset (only via `AssetWorkflowService`):**  
`draft → registered → active → maintenance → retired → disposed → archived`

**Maintenance request / work order (same map via `AssetWorkflowService`):**  
`new → approved → scheduled → in_progress → completed → closed`

## Offline readiness (for 19B later)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Asset create/update (non-status) | `AssetService` | YES | Draft/metadata |
| Asset workflow transition | `AssetWorkflowService` | YES | Must call service |
| Category / location / model CRUD | `AssetCategoryService`, `AssetLocationService`, … | YES | Master-data style |
| Assignment / transfer complete | `AssetAssignmentService`, `AssetTransferService` | YES | Tenant-scoped |
| Maintenance request / plan create | `MaintenanceRequestService`, `MaintenancePlanService` | YES | |
| Request / WO workflow | `AssetWorkflowService` | YES | |
| Work order create / parts consumption meta | `WorkOrderService` | YES | Qty meta only |
| Inspection / checklist / meter | `InspectionService`, `ChecklistService`, `MeterReadingService` | YES | |
| Warranty / insurance meta | `WarrantyService`, `InsuranceService` | YES | |
| Activity / comment / timeline | `AssetActivityService`, `AssetCommentService`, `AssetTimelineService` | YES | |
| Document meta link | `AssetDocumentMetaService` | PARTIAL | Meta yes; binary **NO** |
| Binary attachment upload | `DocumentService` | **NO** | **ONLINE ONLY** |
| Depreciation posting / GL | — | **NO** | Accounting coupling |
| Cross-tenant / admin ops | — | **NO** | Unsupported offline |

## RBAC

| Slug | Role |
|------|------|
| `assets.view` | view EAM |
| `assets.create` | create |
| `assets.update` | update + workflow |
| `assets.delete` | soft-delete |
| `assets.assign` | assignments |
| `assets.transfer` | transfers |
| `assets.maintenance` | plans / requests / WOs / calendar |
| `assets.inspection` | inspections / checklists |
| `assets.admin` | admin |
| `assets.manage` | all (implies above) |

## Files Created

- `migrations/185_assets_platform_enterprise.sql`
- `app/models/EamModels.php`
- `app/services/AssetSupport.php`
- `app/services/AssetWorkflowService.php`
- `app/services/AssetTimelineService.php`
- `app/services/AssetDomainServices.php`
- `app/services/AssetActivityServices.php`
- `app/controllers/Company/AssetPlatformControllers.php`
- `views/company/eam/**`
- `tests/assets/*`
- `docs/PHASE_19A_ASSETS_ONLINE.md`

## Files Modified (additive)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/entity-permissions.php`
- `config/permission-labels-{en,ar}.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## Tests

```bash
php tests/assets/run-assets-phase19a-tests.php
```

## Production readiness

1. Run migration `185_assets_platform_enterprise.sql`
2. Ensure plan module `assets` is enabled for the tenant
3. Grant `assets.view` / `assets.manage` (seeded to `company-full-access` / `super-admin`)
4. Use `/eam` for the enterprise platform; legacy `/assets` remains for the fixed-assets register
5. Phase 19B may wrap these services — Offline flags must default OFF — Baseline untouched

## Success criteria

- ONLINE EAM domain complete and multi-tenant
- Workflow only through `AssetWorkflowService`
- Legacy assets + Offline Foundation unchanged
- Gate tests CLEAR
