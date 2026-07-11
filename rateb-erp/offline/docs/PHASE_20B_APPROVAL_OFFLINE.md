# Phase 20B — Enterprise Approval Offline (Tier-1 drafts)

**Status:** Implemented (additive Tier-1 offline)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (additive `RatebOffline.approvals()` only)  
**IndexedDB:** **DB_VERSION=2** — **NOT bumped**

## Executive Summary

Phase 20B adds Approval offline drafts as module `approval`. Client enqueues via `RatebOffline.approvals()` → Offline Queue → ReplayEngine → `ApprovalOfflineReplayService` → Phase 20A services only. Flags default OFF. No final approve/reject, escalate, notifications, attachments, email/SMS, payments, or government APIs.

## Architecture

```
RatebOffline.approvals()
  → Offline Queue (module = approval)
  → OfflineReplayEngine
  → ApprovalOfflineReplayService
  → Phase 20A Approval* services
  → Database (rateb_eap_*)
```

## Supported queue actions

| Action | Flag gate |
|--------|-----------|
| `approval_request.create` / `approval_request.update` / `comment.create` / `delegation.create` / `note.create` | `offline.approval` (+ `.requests` for create/update) |
| `workflow.transition` (submit/cancel/archive paths only) | `offline.approval.workflow` |
| Master-data pull | `offline.approval.masterdata` |

Final decisions (`approved` / `rejected`), escalate, notification send, and attachment binaries remain **ONLINE ONLY**.

## Feature flags (default OFF)

- `offline.approval` → `RATEB_OFFLINE_APPROVAL`
- `offline.approval.requests` → `RATEB_OFFLINE_APPROVAL_REQUESTS`
- `offline.approval.workflow` → `RATEB_OFFLINE_APPROVAL_WORKFLOW`
- `offline.approval.masterdata` → `RATEB_OFFLINE_APPROVAL_MASTERDATA`

All require `offline.enabled`.

## Master data (read-only)

Templates, chains, stages, rules, delegation lists, static status catalog — via existing cursor engine. No DB_VERSION bump.

## Conflicts

`OfflineConflictResolverService::resolveApproval()` — additive status-drift + version rules. No redesign.

## Files created

- `offline/server/Services/ApprovalOfflineReplayService.php`
- `offline/server/Services/ApprovalOfflineTenantGuard.php`
- `offline/server/Services/ApprovalOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/approval-adapter.js`
- `offline/tests/ApprovalOfflinePhase20bTest.php`
- `offline/tests/run-approval-offline-tests.php`
- `offline/docs/PHASE_20B_APPROVAL_OFFLINE.md`

## Tests

```bash
php offline/tests/run-approval-offline-tests.php
php tests/approval/run-approval-phase20a-tests.php
php offline/tests/run-assets-offline-tests.php
```

## Production readiness

1. Confirm migration `186_approval_platform_enterprise.sql` applied
2. Rebuild offline bundle: `php offline/scripts/build-rateb-offline-bundle.php`
3. Pilot: enable `offline.enabled` + `offline.approval` (+ sub-flags) — all default OFF
4. Server authorization remains authoritative; offline cache is UI only
