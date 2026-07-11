# Phase 21B — Enterprise Procurement Offline (Tier-1 drafts)

**Status:** Implemented (additive Tier-1 offline)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (additive `RatebOffline.procurementEnterprise()` only)  
**IndexedDB:** **DB_VERSION=2** — **NOT bumped**

## Executive Summary

Phase 21B adds Enterprise Procurement (EPROC) offline drafts as module `procurement_enterprise`. Client enqueues via `RatebOffline.procurementEnterprise()` → Offline Queue → ReplayEngine → `ProcurementEnterpriseOfflineReplayService` → Phase 21A services only. Distinct from legacy module `procurement` (PR/PO/RFQ). Flags default OFF. No delete, payments, approvals, notifications, email/SMS, binary uploads, or government APIs.

## Architecture

```
RatebOffline.procurementEnterprise()
  → Offline Queue (module = procurement_enterprise)
  → OfflineReplayEngine
  → ProcurementEnterpriseOfflineReplayService
  → Phase 21A EPROC services
  → Database (rateb_eproc_*)
```

## Supported queue actions

| Action | Flag gate |
|--------|-----------|
| `supplier_profile.*` / `qualification.*` / `risk.create` / `scorecard.create` / `portal_invite.create` / `collaboration.create` | `offline.procurement_enterprise.suppliers` |
| `tender.create` / `bid.create` / `bid_compare.create` | `offline.procurement_enterprise.tenders` |
| `contract.create` | `offline.procurement_enterprise.contracts` |
| `workflow.transition` | `offline.procurement_enterprise.workflow` |
| `assignment.create` / `comment.create` / `note.create` | parent `offline.procurement_enterprise` |
| Master-data pull | `offline.procurement_enterprise.masterdata` |

## Feature flags (default OFF)

- `offline.procurement_enterprise` → `RATEB_OFFLINE_PROCUREMENT_ENTERPRISE`
- `offline.procurement_enterprise.suppliers` → `RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS`
- `offline.procurement_enterprise.tenders` → `RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_TENDERS`
- `offline.procurement_enterprise.contracts` → `RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_CONTRACTS`
- `offline.procurement_enterprise.workflow` → `RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_WORKFLOW`
- `offline.procurement_enterprise.masterdata` → `RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_MASTERDATA`

All require `offline.enabled`.

## Conflicts

`OfflineConflictResolverService::resolveProcurementEnterprise()` — additive status-drift + version rules.

## Files created

- `offline/server/Services/ProcurementEnterpriseOfflineReplayService.php`
- `offline/server/Services/ProcurementEnterpriseOfflineTenantGuard.php`
- `offline/server/Services/ProcurementEnterpriseOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/procurement-enterprise-adapter.js`
- `offline/tests/ProcurementEnterpriseOfflinePhase21bTest.php`
- `offline/tests/run-procurement-enterprise-offline-tests.php`
- `offline/docs/PHASE_21B_PROCUREMENT_ENTERPRISE_OFFLINE.md`

## Tests

```bash
php offline/tests/run-procurement-enterprise-offline-tests.php
php tests/procurement/run-procurement-phase21a-tests.php
php offline/tests/run-approval-offline-tests.php
```

## Production readiness

1. Confirm migration `187_procurement_enterprise_platform.sql` applied
2. Rebuild offline bundle: `php offline/scripts/build-rateb-offline-bundle.php`
3. Pilot: enable `offline.enabled` + `offline.procurement_enterprise` (+ sub-flags) — all default OFF
4. Legacy `offline.procurement` (PR/PO/RFQ) remains separate and untouched
