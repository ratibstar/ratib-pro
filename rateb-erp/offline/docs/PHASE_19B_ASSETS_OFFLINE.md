# Phase 19B — Enterprise Assets & Maintenance Offline (Tier-1)

**Status:** Implemented (additive Tier-1)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified** (Queue / ReplayEngine / SDK / SW / IDB schema frozen)  
**SDK:** **14.2.0** (additive `RatebOffline.assets()` only)  
**Online foundation:** Phase 19A (`rateb_eam_*` + domain services)

## Executive Summary

Phase 19B adds Assets & Maintenance offline drafts as module `assets`. Client enqueues via `RatebOffline.assets()` → Offline Queue → ReplayEngine → `AssetOfflineReplayService` → Phase 19A services only. Flags default OFF. No binary uploads, deletes, payments, approvals, email/SMS, or government APIs.

## Architecture

```
RatebOffline.assets()
  → Offline Queue (module = assets)
  → OfflineReplayEngine
  → AssetOfflineReplayService
  → Phase 19A domain services
  → Database
```

## Queue actions

| Action | Flag |
|--------|------|
| `asset.create` / `asset.update` / `assignment.create` / `transfer.create` / `comment.create` / `activity.create` / `note.create` | `offline.assets` |
| `maintenance_request.create` / `maintenance_plan.create` / `work_order.create` | `offline.assets.maintenance` |
| `workflow.transition` | `offline.assets.workflow` |
| `inspection.create` / `checklist.create` / `meter_reading.create` | `offline.assets.inspections` |
| Master-data pull | `offline.assets.masterdata` |

## Feature flags (default OFF)

- `offline.assets` → `RATEB_OFFLINE_ASSETS`
- `offline.assets.maintenance` → `RATEB_OFFLINE_ASSETS_MAINTENANCE`
- `offline.assets.workflow` → `RATEB_OFFLINE_ASSETS_WORKFLOW`
- `offline.assets.inspections` → `RATEB_OFFLINE_ASSETS_INSPECTIONS`
- `offline.assets.masterdata` → `RATEB_OFFLINE_ASSETS_MASTERDATA`

Requires `offline.enabled`.

## Master data (read-only directories)

Categories, Manufacturers, Locations, Asset Models, Maintenance Plans (+ static status catalogs). Reuses existing cursor engine. No new IndexedDB stores. No `DB_VERSION` bump.

## Security

Tenant/branch guard (`AssetOfflineTenantGuard`). Existing Auth + RBAC. Offline cache UI-only. Server authorization authoritative. Idempotency via existing `[offline:key]` notes marker.

## Conflicts

`OfflineConflictResolverService::resolveAssets()` — additive status-drift + version rules. No redesign.

## Files created

- `offline/server/Services/AssetOfflineReplayService.php`
- `offline/server/Services/AssetOfflineTenantGuard.php`
- `offline/server/Services/AssetOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/assets-adapter.js`
- `offline/tests/AssetsOfflinePhase19bTest.php`
- `offline/tests/run-assets-offline-tests.php`
- `offline/docs/PHASE_19B_ASSETS_OFFLINE.md`

## Files modified (additive only)

Flags, modules, entity-manifest, master-data-entities, ops allowlist, FeatureFlagService, QueueService, ReplayEngine, ConflictResolver, Authorization, BackgroundSync, CursorService, ErpOfflineMasterDataPolicy, SDK, ops-forms-adapter, build script, public bundles.

## Tests

```bash
php offline/tests/run-assets-offline-tests.php
```

## Production readiness

1. Confirm migration **185** applied (19A).
2. Rebuild offline bundle: `php offline/scripts/build-rateb-offline-bundle.php`
3. Pilot: enable `offline.enabled` + `offline.assets` (+ sub-flags) — all default OFF.
4. Attachment upload remains ONLINE ONLY.
