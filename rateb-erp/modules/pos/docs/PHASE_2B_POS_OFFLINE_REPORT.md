# Phase 2B — POS Offline Completion

**Date:** 2026-07-11  
**Scope:** POS module only — returns, exchanges, suspend/resume, shift open/close, drawer events, conflicts, replay, tests  
**Out of scope:** Inventory, HR, Procurement, Accounting, ERP shell

---

## Repository audit

### Additive / POS-local changes

| Area | Change |
|------|--------|
| Replay adapter | `PosOfflineReplayService` — thin dispatch to existing POS services |
| Batch processor | `PosSyncBatchProcessorService` — uses replay + conflict recording |
| Queue enqueue | `PosSyncQueueService` — defers all domain actions; returns `accepted_keys` / `clearable_keys` |
| Push ack | `PosPushAckContract` + `PosApiController::syncPush` HTTP status from ack |
| Client queue | `pos-offline-sync.js` — selective clear; suspended local store (IDB v4) |
| Register ops | `pos-register-ops.js` — offline return / exchange / suspend / resume |
| Cashier | `pos-register-cashier.js` — offline drawer events |
| Shift gate | `pos-register-tiles.js` — offline `shift_open` |
| Shift pages | `pos-shift-offline.js` + `pos-pages-shell.php` script tags |
| Tests | `PosOfflinePhase2BTest` + expanded runner |

### Explicit non-changes

- No Inventory / HR / Procurement / Accounting module edits
- No duplicated checkout/return/exchange/shift/drawer business logic
- Existing `PosCheckoutService`, `PosReturnService`, `PosExchangeService`, `PosSuspendService`, `PosShiftService`, `PosCashDrawerService` remain the sole domain owners
- Enterprise Offline foundation (`offline/`) unchanged in this phase (POS uses `rateb_pos_sync_*`)

### Architecture compliance

| Rule | Status |
|------|--------|
| POS only | Pass |
| Replay via existing services | Pass |
| No business logic duplication | Pass |
| Conflict handling (version + shift_already_open) | Pass |
| Selective client clear (2A.1 pattern) | Pass |

---

## Test report

**Command:** `php modules/pos/tests/run-offline-sync-tests.php`  
**Result:** **25/25 PASS**

| Suite | Cases | Focus |
|-------|-------|--------|
| `PosOfflineSyncTest` | 4 | Version conflict resolver |
| `PosOfflinePhase2BTest` | 21 | Ack contract, replay validation, client wiring audits, stress (5k ack), multi-terminal conflict codes, queue defer list |

Coverage mapped to requirements:

1. Returns offline queue — client + deferred `process_return` + empty-payload guard  
2. Exchanges offline queue — client + deferred `process_exchange`  
3. Suspend/Resume — client IDB + deferred `suspend` / `resume_suspended`  
4. Shift open/close — gate + pages JS + deferred actions  
5. Cash drawer events — cashier offline queue + `drawer_event`  
6. Conflict handling — resolver + shift conflict normalize + clearable excludes conflicts  
7. Replay via existing services — source audit of `PosOfflineReplayService`  
8. Integration — queue defer + processor + ack keys  
9. Stress — 5000 ack evaluations  
10. Multi-terminal — distinct keys + equal-version reject + `pos_shift_already_open`

---

## Security report

| Control | Status | Notes |
|---------|--------|-------|
| Push `ok` only on accepted/duplicate | Pass | `PosPushAckContract` → 422/503 when none |
| Client never wipes rejected/conflict | Pass | Clears only `clearable_keys` |
| CSRF on sync push | Pass | Existing `requireSessionCsrfOrAbort` |
| No client URL/method trust | Pass | Client strips `url`/`method`/`headers` |
| Tenant scope on queue rows | Pass | `company_id` from session context |
| Domain replay permissions | Inherited | Existing POS services enforce FK/tenant rules |
| Shift multi-terminal race | Pass | `pos_shift_already_open` → conflict row |
| No ERP shell / cross-module offline | Pass | POS sync tables only |

**Residual risks (Medium):**

1. Offline shift open does not unlock the register until sync succeeds (intentional safety).  
2. Offline resume of server-side suspended orders queues replay but does not hydrate cart until online (local drafts do hydrate).  
3. Live DB integration of full sale/return/exchange replay still depends on staging with migrated `rateb_pos_sync_*` tables.

---

## Production readiness score

| Dimension | Score (0–10) | Weight |
|-----------|--------------|--------|
| Functional completeness (POS offline ops) | 8.5 | 25% |
| Architecture / no logic duplication | 9.5 | 20% |
| Sync integrity (ack + selective clear) | 9.0 | 20% |
| Conflict / multi-terminal | 8.0 | 15% |
| Test depth (unit + stress + source integration) | 7.5 | 10% |
| Security posture | 8.5 | 10% |

**Weighted score: 8.6 / 10**

### Gate recommendation

**CONDITIONAL GO** for staging enablement of POS offline sync (existing POS sync path — not the enterprise `RATEB_OFFLINE_ENABLED` flag).

Before production:

1. Staging soak: multi-terminal shift open race + return/exchange queue replay  
2. Confirm `rateb_pos_sync_queue` / `rateb_pos_sync_conflicts` migrated  
3. Monitor conflict open count after first offline peak  
4. Keep enterprise Offline foundation flag **OFF** until Inventory/HR phases are approved separately
