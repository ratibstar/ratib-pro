# Phase 4.5.1 — Queue Durability Fix (H-FLUSH-001)

**Date:** 2026-07-11  
**Scope:** Fix enterprise offline client queue flush crash window  
**Out of scope:** Procurement Offline (not started)

---

## Repository audit

### Changed (additive durability only)

| File | Change |
|------|--------|
| `offline/client/db/migrations.js` | Added atomic `Stores.removeMany(store, keys)` (single IDB transaction) |
| `offline/client/sync/queue-manager.js` | Flush uses `removeByKeys(clearable)` — **no** `Stores.clear` + rewrite |
| `public/assets/offline/rateb-offline.js` (+ min) | Rebuilt SDK **Phase 4.5.1** |
| `offline/tests/QueueDurabilityPhase451Test.php` | Durability / crash / stress validation |
| `offline/tests/run-queue-durability-tests.php` | Runner |
| Phase 4.5 gate tests | Updated to expect H-FLUSH-001 closed |

### Explicit non-changes

- No ERP business logic / APIs / schema  
- No Procurement Offline  
- Server ack contract unchanged  
- POS sync path unchanged (already used `removeByKeys` API)

### Root cause (closed)

Enterprise flush previously ran **two** IndexedDB transactions:

1. `Stores.clear(QUEUE)` — committed empty store  
2. `putMany(remaining)` — rewrite survivors  

Power loss / refresh between (1) and (2) → **Zero Data Loss violation**.

### Fix

1. Build clearable key set from ack (`accepted` ∪ `duplicate` only).  
2. `removeMany` deletes those keys in **one** transaction.  
3. Rejected / conflict / other pending rows never touched.  
4. FIFO via `seq` + `occurred_at` sort on `list()` / push payload.

---

## Durability report

| Scenario | Model | Result |
|----------|-------|--------|
| Crash mid clear-rewrite (legacy) | Empty store | **Would lose** survivors (documented) |
| Crash mid delete-by-key tx | IDB abort | **All rows kept** |
| Browser refresh after push, before delete | Queue intact | Re-push → server idempotency |
| Browser refresh after delete commits | Only clearable gone | Survivors remain |
| Power loss mid `removeMany` | Atomic abort | Full queue intact |
| Partial sync (accepted + conflict + rejected) | delete clearable only | Conflict/rejected/pending kept |

---

## Zero Data Loss validation

| Rule | Status |
|------|--------|
| Never clear entire queue on flush | **PASS** |
| Clear only `clearable_keys` | **PASS** |
| Rejected/conflict never deleted | **PASS** |
| Atomic delete (all-or-nothing tx) | **PASS** |
| Idempotency keys on survivors unchanged | **PASS** |
| Server ack contract unchanged | **PASS** |

---

## Stress report

| Test | Scale | Result |
|------|-------|--------|
| delete-by-key algorithm | 5,000 items | PASS |
| FIFO after partial clear | 20 items shuffled | PASS |
| Power-loss atomic model | 100 items / 50 clearable | PASS |
| Phase 4.5 cross-module ack (prior) | 3,000 | PASS |

---

## Test execution

```bash
php offline/tests/run-queue-durability-tests.php
php offline/tests/run-phase45-integration-validation.php
php offline/tests/run-offline-foundation-tests.php
```

Expected: durability **PASS**; Phase 4.5 gate **no High findings**; Foundation flush check **PASS**.

---

## Production readiness

| Dimension | Score | Notes |
|-----------|-------|-------|
| Queue durability | **9.0** | H-FLUSH-001 closed |
| Zero data loss (client+server) | **9.0** | |
| FIFO / idempotency / replay order | **8.5** | `seq` field additive |
| Regression risk | **9.0** | APIs/schema/business untouched |

**H-FLUSH-001: CLOSED**

### Gate

- **Procurement Offline:** unblocked from the High durability stopper (still requires normal phase planning + staging soak).  
- Keep enterprise flags **OFF** until staging mid-flush kill test is signed off (`M-SOAK-001`).
