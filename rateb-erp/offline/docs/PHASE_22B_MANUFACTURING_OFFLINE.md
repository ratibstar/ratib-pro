# Phase 22B — Enterprise Manufacturing Offline (Tier-1 drafts)

**Status:** Implemented (additive Tier-1 offline)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (additive `RatebOffline.manufacturing()` only)  
**IndexedDB:** **DB_VERSION=2** — **NOT bumped**

## Executive Summary

Phase 22B adds Enterprise Manufacturing offline drafts as module `manufacturing`. Client enqueues via `RatebOffline.manufacturing()` → Offline Queue → ReplayEngine → `ManufacturingOfflineReplayService` → Phase 22A services only. Distinct from EAM `WorkOrderService` (assets maintenance). Flags default OFF. No delete, inventory posting, GL posting, payments, approvals, notifications, email/SMS, binary uploads, or government APIs.

## Architecture

```
RatebOffline.manufacturing()
  → Offline Queue (module = manufacturing)
  → OfflineReplayEngine
  → ManufacturingOfflineReplayService
  → Phase 22A MFG services (BomService, RoutingService, ProductionOrderService, MfgWorkOrderService, …)
  → Database (rateb_mfg_*)
```

## Supported queue actions

| Action | Flag gate |
|--------|-----------|
| `bom.create` / `bom.update` / `routing.create` / `routing.update` | `offline.manufacturing.production` |
| `production_order.create` / `production_order.update` | `offline.manufacturing.production` |
| `work_order.create` / `work_order.update` | `offline.manufacturing.production` |
| `material_reservation.create` / `material_consumption.create` | `offline.manufacturing.production` |
| `finished_goods.create` / `scrap.create` | `offline.manufacturing.production` |
| `workflow.transition` | `offline.manufacturing.workflow` |
| `quality_check.create` | `offline.manufacturing.quality` |
| `cost.create` / `assignment.create` / `comment.create` / `note.create` | parent `offline.manufacturing` |
| Master-data pull (`mfg_product_directory`, `mfg_work_center_directory`, status catalogs) | `offline.manufacturing.masterdata` |

## Rejected actions

`delete`, inventory posting, GL posting, payments, approvals, notifications, email, SMS, government, binary uploads.

## Feature flags (default OFF)

- `offline.manufacturing` → `RATEB_OFFLINE_MANUFACTURING`
- `offline.manufacturing.production` → `RATEB_OFFLINE_MANUFACTURING_PRODUCTION`
- `offline.manufacturing.workflow` → `RATEB_OFFLINE_MANUFACTURING_WORKFLOW`
- `offline.manufacturing.quality` → `RATEB_OFFLINE_MANUFACTURING_QUALITY`
- `offline.manufacturing.masterdata` → `RATEB_OFFLINE_MANUFACTURING_MASTERDATA`

All require `offline.enabled`.

## Security

- `ManufacturingOfflineTenantGuard` — company + branch isolation on BOM/PO/WO/routing
- Idempotency via `[offline:key]` in `notes` on create (BOM, production order, work order)
- Server-authoritative replay; offline cache is UI-only
- No auth/RBAC redesign

## Conflicts

`OfflineConflictResolverService::resolveManufacturing()` — additive status-drift + version rules.

## Files created

- `offline/server/Services/ManufacturingOfflineReplayService.php`
- `offline/server/Services/ManufacturingOfflineTenantGuard.php`
- `offline/server/Services/ManufacturingOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/manufacturing-adapter.js`
- `offline/tests/ManufacturingOfflinePhase22bTest.php`
- `offline/tests/run-manufacturing-offline-tests.php`
- `offline/docs/PHASE_22B_MANUFACTURING_OFFLINE.md`

## Files modified (additive wiring)

- `offline/config/feature-flags.php`
- `offline/config/modules.php`
- `offline/config/entity-manifest.php`
- `offline/config/master-data-entities.php`
- `offline/config/ops-page-allowlist.php`
- `offline/server/Services/OfflineFeatureFlagService.php`
- `offline/server/Services/OfflineReplayEngine.php`
- `offline/server/Services/OfflineQueueService.php`
- `offline/server/Services/OfflineConflictResolverService.php`
- `offline/server/Services/OfflineAuthorizationService.php`
- `offline/server/Services/OfflineBackgroundSync.php`
- `offline/server/Services/OfflineCursorService.php`
- `offline/server/Services/ErpOfflineMasterDataPolicy.php`
- `offline/client/core/sdk.js`
- `offline/client/adapters/ops-forms-adapter.js`
- `offline/scripts/build-rateb-offline-bundle.php`
- `public/assets/offline/rateb-offline.js` (+ `.min.js`)
- `app/services/ManufacturingDomainServices.php` — `MfgWorkOrderService::update()` for `work_order.update` replay

## Tests

```bash
php offline/tests/run-manufacturing-offline-tests.php
php tests/manufacturing/run-manufacturing-phase22a-tests.php
php offline/tests/run-procurement-enterprise-offline-tests.php
php offline/tests/run-approval-offline-tests.php
```

Target: **26/26 PASS** (Phase 22B gate).

## Production readiness

1. Confirm migration `188_manufacturing_platform_enterprise.sql` applied
2. Rebuild offline bundle: `php offline/scripts/build-rateb-offline-bundle.php`
3. Pilot: enable `offline.enabled` + `offline.manufacturing` (+ sub-flags) — all default OFF
4. EAM `eam/work-orders` (assets) and MFG `mfg/work-orders` remain separate modules
