# P17-00 — Phase 17 Manufacturing (MRP) Audit (Enterprise Report)

**Status:** COMPLETE (evidence only)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Implementation:** NONE — STOP after audit  
**Scope:** ONLINE ERP Manufacturing (`rateb-erp/` Phase 22A `/mfg/*`) — **NOT** Offline V1 as SoT, **NOT** Offline V2 BM (no Manufacturing BM yet)

---

## Executive verdict

Online Manufacturing is a **single additive stack** (Phase 22A):

1. **Enterprise MFG** (`/mfg/*`) — `rateb_mfg_*` products, BOM versions, routings, work centers/machines, production & work orders, capacity/calendar/schedules, material reservation/consumption **meta**, FG receipt **meta**, scrap, quality checks, production costs **meta**, timeline, assignments.
2. **Branded “MRP”** but **no MRP engine** — no BOM explosion, no netting, no planned-order generation, no demand from sales/forecast.
3. **Soft links only** to Inventory / EAM / Projects / cost centers — **no stock posting, no GL**.
4. **Sibling QMS** (`/qms/*`) soft-links `mfg_quality_check_id` — does not own MFG QC.
5. **EAM work orders** are **maintenance**, not production.

Surface is **MVC + session RBAC**, not a JSON Manufacturing API. Offline V2 has **no Manufacturing BusinessModule**. Offline V1 Phase 22B wraps online services (flags OFF) — frozen reference only.

Offline V2 Manufacturing BM must:

- Own **manufacturing documents only** (`mfg.*` entities).
- Depend on **identity** + **inventory** (mandatory); optional **procurement** / **sales** / **accounting** via published APIs only.
- **Never** own inventory balances, accounting journals, procurement/sales docs, CRM, HR, or authentication.
- Issue/receipt stock **only** via `module.inventory.*`.

---

## 1. BOMs / Bill of Materials

| Item | Evidence |
|------|----------|
| Tables | `rateb_mfg_boms`, `rateb_mfg_bom_versions`, `rateb_mfg_bom_lines` — `migrations/188_manufacturing_platform_enterprise.sql` |
| Services | `BomService`, `BomVersionService`, `BomLineService` — `ManufacturingDomainServices.php` |
| Routes | `GET/POST …/mfg/boms*`, `POST …/boms/{id}/transition` — `routes/modules/ops.php` |
| Perms | `manufacturing.bom`, `manufacturing.create` / `submit` |
| Soft links | Line `inventory_item_id`; product/variant refs |
| Workflow | `draft → active → obsolete → cancelled → archived` |

**Gap:** No explode / multi-level rollup / where-used report service.

---

## 2. Work Centers / Machines / Routing

| Item | Evidence |
|------|----------|
| Tables | `rateb_mfg_work_centers`, `rateb_mfg_machines`, `rateb_mfg_routings`, `rateb_mfg_routing_operations` |
| Services | `WorkCenterService`, `MachineService`, `RoutingService`, `RoutingOperationService` |
| Routes | `…/mfg/work-centers`, `…/mfg/routings*` — **no dedicated `/mfg/machines` HTTP route** |
| Soft links | WC `warehouse_id`; Machine `eam_asset_id`; Op setup/run/queue minutes |
| Perms | `manufacturing.planning` (WC), `manufacturing.bom` (routings) |

---

## 3. Work Orders / Production Orders

| Item | Evidence |
|------|----------|
| Tables | `rateb_mfg_production_orders`, `rateb_mfg_work_orders` |
| Services | `ProductionOrderService`, `MfgWorkOrderService` |
| Routes | `…/mfg/production-orders*`, `…/mfg/work-orders*` + `/transition` |
| Qty fields | `qty_planned`, `qty_completed`, `qty_scrap` |
| Perms | `manufacturing.shopfloor` |
| **Distinct from** | EAM `rateb_eam_work_orders` (maintenance) |

---

## 4. MRP planning / requirements

| Item | Evidence |
|------|----------|
| True MRP (explode/net/plan) | **Missing** — no MRP run table/service/route |
| Closest substitutes | Manual material reservation; capacity/calendar/schedule CRUD; PO with BOM refs |
| Naming | Docs brand “MRP Platform”; implementation = manufacturing domain foundation |

**Verdict:** Requirements planning is **manual meta**, not algorithmic MRP.

---

## 5. Material issue / consumption

| Item | Evidence |
|------|----------|
| Tables | `rateb_mfg_material_reservations`, `rateb_mfg_material_consumptions` |
| Services | `MaterialReservationService`, `MaterialConsumptionService` |
| Behavior | **Meta-only — inventory stock posting deferred** |
| Soft links | `inventory_item_id`, `warehouse_id`, optional batch/serial |

---

## 6. Production receipt / finished goods

| Item | Evidence |
|------|----------|
| Table | `rateb_mfg_finished_goods_receipts` |
| Service | `FinishedGoodsReceiptService::create` — meta-only; increments PO `qty_completed` |
| Scrap | `rateb_mfg_scrap_records` / `ScrapRecordingService` |
| Stock SoT | **Not written** (Inventory owns stock per AF 2.1.1) |

---

## 7. Shop floor / operations

| Item | Evidence |
|------|----------|
| Gate | `manufacturing.shopfloor` on PO/WO |
| Operations | `rateb_mfg_routing_operations` + WO `routing_operation_id` |
| Schedule / assign | `rateb_mfg_schedules`, `rateb_mfg_assignments` (`assignee_user_id`) |
| Gap | No shop-floor terminal / time-clock / auto-consume on op complete |

---

## 8. Quality checks

| Stack | Evidence |
|-------|----------|
| MFG QC | `rateb_mfg_quality_checks` — `QualityCheckService` — `/mfg/quality` |
| Workflow stage | PO/WO includes `quality_check` |
| QMS sibling | `191_quality_management_platform.sql` — soft-link `mfg_quality_check_id` |

---

## 9. Capacity planning

| Item | Evidence |
|------|----------|
| Tables | `rateb_mfg_capacity_plans`, `rateb_mfg_production_calendar`, `rateb_mfg_schedules` |
| Routes | `…/mfg/capacity`, `…/calendar`, `…/schedules` |
| Gap | No auto-booking from WO run minutes; no finite capacity solver |

---

## 10. Costing / manufacturing costs

| Item | Evidence |
|------|----------|
| Table | `rateb_mfg_production_costs` |
| Service | `ProductionCostService` — **meta-only; GL deferred**; `accounting_ref` string only |
| WC | `cost_per_hour` stored; **no auto rollup** |

---

## 11. Permissions

Seeded in `188_…sql`:

| Slug | Intent |
|------|--------|
| `manufacturing.view` | view / reports |
| `manufacturing.create` / `update` / `submit` | CRUD / workflow |
| `manufacturing.bom` | BOM / routings |
| `manufacturing.planning` | capacity / calendar / schedule / WC |
| `manufacturing.shopfloor` | PO / WO / materials |
| `manufacturing.quality` | QC / scrap |
| `manufacturing.admin` / `manage` | admin bundles |

Route gate: `rateb_erp_mw('manufacturing', '<perm>', …)`.

---

## 12. APIs

**No dedicated online REST Manufacturing API** under `api/`.

Surface = HTML form POST on `mfg/…`.

Offline V1 delta (not Online SoT): `/api/v1/offline/delta/mfg_*_directory`.

---

## 13. Database

| Migration | Role |
|-----------|------|
| `migrations/188_manufacturing_platform_enterprise.sql` | All `rateb_mfg_*` + `manufacturing.*` perms |
| `191_quality_management_platform.sql` | QMS sibling soft-link |

### `rateb_mfg_*` (26 tables)

products, product_variants, boms, bom_versions, bom_lines, work_centers, machines, routings, routing_operations, production_orders, work_orders, capacity_plans, production_calendar, schedules, material_reservations, material_consumptions, finished_goods_receipts, scrap_records, quality_checks, production_costs, timeline, assignments, comments, attachments_meta, tags, entity_tags, status_history

---

## 14. Services

| File | Role |
|------|------|
| `app/services/ManufacturingDomainServices.php` | Domain services (god-file) |
| `ManufacturingWorkflowService.php` | Sole `workflow_status` writer |
| `ManufacturingSupport.php` | Tenant/UUID/version/actor |
| `ManufacturingTimelineService.php` | Append-only timeline |
| Controllers / models | `ManufacturingControllers.php`, `MfgModels.php` |
| Tests / docs | `tests/manufacturing/ManufacturingPhase22ATest.php`, `docs/PHASE_22A_MANUFACTURING_ONLINE.md` |

---

## 15. Workflow

**Only via `ManufacturingWorkflowService`:**

```
Product / BOM / BOM version / Routing:
  draft → active → obsolete|cancelled → archived

Production order / Work order:
  draft → planned → released → in_progress → quality_check → completed → closed → archived
  (+ cancelled paths)
```

Side effects: optimistic `version`; `rateb_mfg_status_history`; timeline.

---

## 16. Reports

`GET …/mfg/reports` / dashboard — `boardCounts` + recent timeline only. **Not analytic** (no OEE / cost variance / scrap %).

---

## 17. Notifications

**None dedicated** for MFG transitions, shortage, QC fail, or schedule slip.

---

## 18. Integration boundaries

| System | Behavior |
|--------|----------|
| **Identity** | `TenantContext`; assignees; RBAC `manufacturing.*` |
| **Inventory** | Soft `inventory_item_id`, `warehouse_id`; **no stock movements** |
| **Procurement** | **None** |
| **Sales** | **None** |
| **Accounting** | `accounting_ref` / `cost_center_id` metadata only — **no auto GL** |
| **EAM** | Machine `eam_asset_id` soft-link |
| **Projects** | PO `project_id` soft-link |
| **QMS** | Soft-link from QMS → MFG QC |
| **HR / CRM** | **None** |

### Offline V2 published APIs (deps)

| Module | Use |
|--------|-----|
| Identity | **Mandatory** |
| Inventory | **Mandatory** — stock issue & FG receipt via inventory APIs only |
| Procurement | Optional — shortage → PR events |
| Sales | Optional — demand / MTO signals |
| Accounting | Optional — cost ref events; MFG never posts GL |

---

## 19. Sync boundaries

### Offline V1 (flags default OFF) — reference only

| Phase | Doc | Scope |
|-------|-----|-------|
| 22B | `offline/docs/PHASE_22B_MANUFACTURING_OFFLINE.md` | Drafts + workflow; meta material/FG/scrap/QC/cost |

**Rejected offline:** inventory posting, GL, payments, approvals, notifications, binary uploads.

### Offline V2 implication

| May sync | Must NOT sync |
|----------|---------------|
| Product / BOM / routing / WC drafts | Passwords / tokens |
| PO / WO drafts + workflow transitions | Inventory balances as MFG SoT |
| Reservation / consumption / FG / scrap **meta** | GL journals |
| QC drafts, cost meta, timeline | Binary attachment bytes |
| | Sales / procurement / CRM / HR ownership |

---

## Required BusinessModule surface (future — not this phase)

### Suggested entity prefix

`mfg.*` — product, bom, bom_version, bom_line, work_center, machine, routing, routing_operation, production_order, work_order, capacity_plan, schedule, material_reservation, material_consumption, finished_goods_receipt, scrap_record, quality_check, production_cost, timeline, status_history

### Suggested APIs (`module.mfg.*`)

`upsertProduct` · `transitionProduct` · `upsertBom` / `upsertBomVersion` / `upsertBomLine` · `upsertWorkCenter` · `upsertRouting` · `createProductionOrder` / `transitionProductionOrder` · `createWorkOrder` / `transitionWorkOrder` · `createMaterialReservation` · `createMaterialConsumption` · `createFinishedGoodsReceipt` · `createScrap` · `createQualityCheck` · `createProductionCost` · `listTimeline` · stock issue/receipt **via** `module.inventory.*` · `getDiagnostics` / `runSelfTest`

### Suggested DTOs

`ProductDraftDTO` · `BomDraftDTO` · `BomLineDTO` · `ProductionOrderDTO` · `WorkOrderDTO` · `WorkflowTransitionDTO` · `MaterialReservationDTO` · `MaterialConsumptionDTO` · `FinishedGoodsReceiptDTO` · `QualityCheckDTO` · `ProductionCostDTO` · `InventoryItemRefDTO` · `WarehouseRefDTO`

### Suggested events

`mfg:ready` · `mfg:production_order_transitioned` · `mfg:material_reserved` · `mfg:material_consumed` · `mfg:fg_received` · `mfg:scrap_recorded` · `mfg:quality_checked` · `mfg:shortage_detected` · `mfg:cost_recorded`

### Suggested permissions

`manufacturing.view|create|update|submit|bom|planning|shopfloor|quality|admin|manage`

---

## Dependency graph

```
identity (mandatory)
    ↑
mfg BM
    ├──→ inventory (mandatory): item/warehouse; stock issue & FG receipt
    ├──→ procurement (optional): shortage → PR events
    ├──→ sales (optional): demand / MTO signals
    └──→ accounting (optional): cost ref — never own GL

Peers (soft refs only): qms, eam, projects

FORBID owning: inventory qty SoT, GL, sales docs, PO docs, CRM, HR, auth
```

---

## Reusable components

- Additive `rateb_mfg_*` domain + soft-link pattern  
- Sole workflow authority `ManufacturingWorkflowService`  
- Soft-delete + `public_uuid` + company/branch + optimistic `version`  
- Timeline + status_history append models  
- Permission matrix `manufacturing.*`  
- Meta-only material/FG/scrap/cost ledgers until inventory/GL ports exist  
- Explicit separation from EAM maintenance WOs  

---

## Non-reusable components

- PHP Model + Session/`TenantContext` / `rateb_erp_mw` / Bootstrap  
- Controllers / views / CSRF form posts  
- Direct SQL; god-file `ManufacturingDomainServices.php`  
- Offline V1 queue/adapters/flags/SDK  
- Hardcoded report boards as sync SoT  

---

## Risks

| ID | Severity | Risk |
|----|----------|------|
| R1 | Critical | **No real MRP** despite “MRP” branding |
| R2 | Critical | Material/FG “posted” **does not post inventory** — dual truth risk |
| R3 | High | Cost `posted` / `accounting_ref` implies GL but **never posts** |
| R4 | High | No Sales/Procurement demand bridge |
| R5 | Medium | Incomplete UI (machines / BOM lines / material create) |
| R6 | Medium | Capacity not auto-linked to routing run minutes |
| R7 | Medium | No notifications for QC fail / shortage / overdue WO |
| R8 | Info | Offline V1 wraps online — V2 must not inherit V1 queue |
| R9 | Info | Offline V2 has **no** Manufacturing BM yet |

---

## Missing abstractions

1. **MfgWorkflowPort** — sole transition for product/BOM/routing/PO/WO  
2. **BomPort** — versioned BOM + lines (+ future explode)  
3. **ProductionOrderPort** / **WorkOrderPort**  
4. **MaterialLedgerPort** — reservation/consumption meta only  
5. **InventoryIssuePort** / **InventoryReceiptPort** — call inventory BM  
6. **CapacityPort** — capacity/calendar/schedule  
7. **QualityCheckPort** — MFG QC; optional emit to QMS  
8. **CostLedgerPort** — meta + optional accounting event  
9. **MrpRunPort** (future) — explode/net/plan — currently absent  
10. Clear rule: MFG never writes inventory qty / GL / sales / procurement / CRM / HR  

---

## Recommended Manufacturing BusinessModule architecture

1. **Charter** MFG BM — docs only; mandatory `identity` + `inventory`; optional procurement/sales/accounting.  
2. Local `mfg.*` storage — never SQL into other modules.  
3. Implement **MfgWorkflowPort** + **BomPort** + **ProductionOrderPort** first.  
4. Material/FG **meta** next; stock only via `module.inventory.*`.  
5. Capacity + QC + cost meta.  
6. Defer true MRP engine until inventory + sales/procurement event contracts exist.  
7. Auth/RBAC via `module.identity.*` only.  
8. Do **not** lift Offline V1 adapters as SoT.  
9. Evidence + **STOP**.

---

## Architecture conflict check

No Platform or existing BusinessModule modification is required for this **audit**.  
If future implementation requires changing Platform / Identity / Inventory / Procurement / Sales / Accounting / CRM / HR — **STOP** and raise Architecture Conflict.

---

## Phase boundary

**Phase 17 Manufacturing (MRP) Audit: COMPLETE**  
**Do NOT implement Manufacturing BusinessModule in this phase.**  
**STOP.**
