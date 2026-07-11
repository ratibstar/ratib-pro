# Enterprise Offline Architecture — Implementation Notes

**Official production target:** `https://rateb.sa/rateb-erp/public/admin/`  
**Production filesystem:** `/home/admin/domains/rateb.sa/public_html/rateb-erp`  
**Production database:** `admin_rateb-erp`  
**Do not use staging** (`dev.rateb.sa` / `admin_rateb_dev`) for production certification.

See Phase 1 architecture document for full design. Flags default **OFF** until Phase 8/9 pilot enablement.

## Phase 2A delivered

- `offline/` folder structure
- Client SDK (`public/assets/offline/rateb-offline.js`)
- IndexedDB schema `rateb_erp_offline`
- Connectivity + Queue + Transport
- Feature flags (master default OFF)
- Additive SQL: `rateb_offline_*` tables (migrations 175–179)
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

## Phase 4.5 / 4.5.1 delivered

- Cross-module integration gate
- Queue durability fix (atomic `removeMany` / delete-by-key flush)

## Phase 5 delivered (Procurement Tier 1)

- `ProcurementOfflineReplayService` → PR / RFQ / PO **drafts only**
- Supplier directory delta (no payments)
- Flag `offline.procurement` default **OFF**
- SDK Phase **5.0.0**

## Phase 6 delivered (Monitoring)

- `OfflineMonitoringService` + ops dashboard (`offline/ops`)
- Read-only monitoring API `/api/v1/offline/monitoring*`
- Flag `offline.monitoring` default **OFF** (independent of master)

## Phase 7 / 7.1 delivered (Certification + Hardening)

- Production certification (local/unit + staging evidence historically separate)
- Hardening: queue-only branch scope, ACTIVE device gate, push ≠ auto-process without Sync Manage

## Phase 8 delivered (Rollout planning)

- Pilot / canary / flag sequence / rollback / DR / ops handbooks  
- See `PHASE_8_PRODUCTION_ROLLOUT.md`

## Not implemented / out of scope

- ERP shell read cache (`offline.read_cache`)
- Offline payroll / leave approvals / procurement approvals / payments / accounting posting
- External pager/email alerting (in-dashboard alerts only)

## Rollback artifact

- `offline/docs/rollback-offline-175-179.sql` — offline tables only; dual approval required on production
