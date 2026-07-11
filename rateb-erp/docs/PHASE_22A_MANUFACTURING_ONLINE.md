# Phase 22A — Enterprise Manufacturing (MRP) Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline MFG here — deferred to Phase **22B**.  
**Migration:** `migrations/188_manufacturing_platform_enterprise.sql`

## Executive Summary

Phase 22A adds a **tenant-scoped Enterprise Manufacturing Platform (MRP)** on additive `rateb_mfg_*` tables for products, BOM versions, routings, work centers/machines, production & work orders, capacity/calendar/scheduling, material reservation/consumption meta, finished-goods receipt meta, scrap, quality checks, production costs, timeline, assignments, comments, attachment metadata, and tags.

It does **not** replace Inventory, EAM maintenance work orders, Procurement, Projects, or Accounting. Soft links only (`inventory_item_id`, `warehouse_id`, `project_id`, `eam_asset_id`, `cost_center_id`). UI routes live under `/mfg/*` guarded by `rateb_erp_mw('manufacturing', …)`.

## Repository Audit (pre-22A)

| Area | Status |
|------|--------|
| Manufacturing / MRP / BOM / Production Order domain | **Missing → created** (`rateb_mfg_*`) |
| EAM `rateb_eam_work_orders` / manufacturers | **Preserved** (maintenance — not production) |
| Inventory batches/serials / stock movements | **Preserved** (soft-link only; no posting in 22A) |
| Offline Foundation / Queue / Replay / SDK | **Frozen — not modified** |

## Architecture

```
Controllers (thin, company/mfg)
  → Domain services (MfgProduct*, Bom*, ProductionOrder*, …)
  → ManufacturingWorkflowService ONLY for workflow_status
  → Models (Mfg* → rateb_mfg_*)
  → Database
```

Material consumption / FG receipt / scrap / production cost services write **MFG meta ledgers only**. Stock issue/receipt and GL posting are deferred (future integration via Inventory/Accounting services — not embedded here).

## Workflow

**Only via `ManufacturingWorkflowService`:**

- Production / work order: `draft → planned → released → in_progress → quality_check → completed → closed → cancelled → archived`
- Product / BOM / BOM version / routing: `draft → active → obsolete → cancelled → archived`

## Offline readiness (for later Phase 22B)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Product / variant / BOM / version / line CRUD | domain services | YES | Draft/metadata |
| Work center / machine / routing / operations | domain services | YES | |
| Production / work order create + update | domain services | YES | |
| Workflow transition | `ManufacturingWorkflowService` | YES | Must call service |
| Capacity / calendar / schedule | domain services | YES | |
| Reservation / consumption / FG / scrap / QC / cost meta | domain services | YES | Meta only |
| Assignment / comment / tag / attachment meta | domain services | YES | Binary upload **NO** |
| Inventory stock post / GL post / email / SMS | — | **NO** | **ONLINE ONLY / future** |

## RBAC

| Slug | Role |
|------|------|
| `manufacturing.view` | view MFG |
| `manufacturing.create` | create |
| `manufacturing.update` | update |
| `manufacturing.submit` | workflow transitions |
| `manufacturing.bom` | BOM / routings |
| `manufacturing.planning` | capacity / calendar / schedule / work centers |
| `manufacturing.shopfloor` | production & work orders / materials |
| `manufacturing.quality` | quality checks |
| `manufacturing.admin` | admin |
| `manufacturing.manage` | all (implies above) |

Plan module: `manufacturing`.

## Files Created

- `migrations/188_manufacturing_platform_enterprise.sql`
- `app/models/MfgModels.php`
- `app/services/ManufacturingSupport.php`
- `app/services/ManufacturingWorkflowService.php`
- `app/services/ManufacturingTimelineService.php`
- `app/services/ManufacturingDomainServices.php`
- `app/controllers/Company/ManufacturingControllers.php`
- `views/company/mfg/**`
- `tests/manufacturing/*`
- `docs/PHASE_22A_MANUFACTURING_ONLINE.md`

## Files Modified (additive registration only)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/entity-permissions.php`, `config/module-permissions.php`
- `config/permission-labels-{en,ar}.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## Tests

```bash
php tests/manufacturing/run-manufacturing-phase22a-tests.php
```

## Production readiness

1. Run migration `188_manufacturing_platform_enterprise.sql`
2. Enable plan module `manufacturing`
3. Grant `manufacturing.view` / `manufacturing.manage` (seeded to `company-full-access` / `super-admin`)
4. Use `/mfg` for the enterprise platform
5. Future Offline MFG (22B) must default flags OFF — Baseline untouched

## Success criteria

- ONLINE MFG domain complete and multi-tenant
- Workflow only through `ManufacturingWorkflowService`
- Inventory / EAM / Procurement / Offline Foundation unchanged
- Gate tests CLEAR
- Ready for Phase 22B Offline
