# Enterprise Offline Architecture — Implementation Notes

See chat Phase 1 architecture document for full design.

## Phase 2A delivered

- `offline/` folder structure
- Client SDK (`public/assets/offline/rateb-offline.js`)
- IndexedDB schema `rateb_erp_offline`
- Connectivity + Queue + Transport
- Feature flags (master default OFF)
- Additive SQL: `rateb_offline_*` tables
- `OfflineSyncApiController` at `/api/v1/offline/*`
- Unit tests under `offline/tests/`

## Phase 3 delivered (Inventory Tier 1)

- `InventoryOfflineReplayService` → `StockMovementService` / `InventoryWorkflowService`
- Stock movement / stock count / warehouse transfer queue actions
- Inventory catalog delta pull (`inventory_catalog`)
- Conflict: version LWW + `quantity_changed`
- Client `RatebOfflineInventoryAdapter`
- Flag `offline.inventory.movements` default **OFF**

## Phase 4 delivered (HR Tier 1)

- `HrOfflineReplayService` → attendance / bulk / leave drafts (no approvals/payroll)
- Employee directory delta pull (`employee_directory`)
- Conflict: version LWW + `status_changed`
- Client `RatebOfflineHrAdapter`
- Flag `offline.hr.attendance` default **OFF**

## Not implemented yet

- Procurement sync
- ERP shell read cache
- UI script injection into ERP layouts (optional)
- Offline payroll / leave approvals
