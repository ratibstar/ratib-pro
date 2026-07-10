# Phase 2A.1 — Blocking Fixes Report

**Date:** 2026-07-11  
**Scope:** Resolve every Critical and High finding from the Independent Enterprise Audit  
**Phase 2B:** Not started

## Verdict

| Gate | Result |
|------|--------|
| Critical findings resolved | **Yes** |
| High findings resolved | **Yes** |
| Feature flag default OFF | **Yes** |
| Additive-only / no business logic changes | **Yes** |
| Existing POS/ERP APIs unchanged | **Yes** |
| Regression tests | **26/26 offline PASS** + POS unit suites PASS |
| **Approve Phase 2B?** | **Conditional Yes** — only after production re-review of 2A.1; still no Inventory/HR/Procurement work |

## Fixes mapped to audit findings

| # | Finding | Fix |
|---|---------|-----|
| C1 | `ok:true` + full client queue wipe | `OfflinePushAckContract`: `ok` only when `accepted+duplicate > 0`; HTTP 422/403/503 otherwise |
| H1 | Clear rejected/conflict items | Client flush removes only `clearable_keys` (`accepted_keys` ∪ `duplicate_keys`) |
| H2 | Unvalidated `branch_id` | `OfflineBranchGuard` + `BranchAccessService::canAccessBranch` |
| H3 | `requireAuthOrAbort` no-op | Aborts with 401 when no company context |
| H4 | Actor `user_id=0` on Bearer | `userId()` prefers `TenantContext::apiUserId()` |
| H5 | No authz on process/resolve | `requireSyncManageOrAbort` via `OfflineAuthorizationService` (`pos.sync.manage` / pos ability / unrestricted token) |
| H6 | Client URL/method trust | `OfflinePayloadSanitizer` + client normalize strips `url`/`method`/`headers` |

## New / updated components

- `offline/server/Services/OfflinePushAckContract.php`
- `offline/server/Services/OfflinePayloadSanitizer.php`
- `offline/server/Services/OfflineBranchGuard.php`
- `offline/server/Services/OfflineAuthorizationService.php`
- `OfflineSyncApiController.php` (hardened)
- `OfflineQueueService.php` (key lists + sanitizer)
- `offline/client/sync/queue-manager.js` + rebuilt `public/assets/offline/rateb-offline.js`

## Push acknowledgement contract (additive fields)

Response shape (existing counters preserved):

```json
{
  "ok": false,
  "result": {
    "accepted": 0,
    "duplicate": 0,
    "conflict": 1,
    "rejected": 1,
    "accepted_keys": [],
    "duplicate_keys": [],
    "conflict_keys": ["…"],
    "rejected_keys": ["…"],
    "clearable_keys": []
  }
}
```

- `ok: true` + 200 only if at least one accepted or duplicate  
- Client may clear **only** `clearable_keys`  
- Rejected and conflict keys remain in IndexedDB  

## Tests

```bash
php offline/tests/run-offline-foundation-tests.php
# 26/26 passed (includes 13 Phase 2A.1 regression cases)
```

POS offline / cart / checkout / security / blocking-fixes: PASS (no regressions).

## Phase 2B readiness

**May begin** only for scoped Tier-0/1 design work **after** confirming:

1. Migrations `175–177` applied in target env before enabling flag  
2. `RATEB_OFFLINE_ENABLED` remains unset/false until ops sign-off  
3. No client-URL replay is introduced in 2B (sanitizer must remain)  
4. Branch + sync-manage gates stay mandatory for mutating admin endpoints  

**Do not** implement Inventory/HR/Procurement sync until a fresh audit of 2A.1 on a staging enablement pass.
