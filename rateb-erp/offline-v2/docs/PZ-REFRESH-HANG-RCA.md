# Refresh Hang Investigation — Offline V2

**Identity boundary:** Online ERP = only Authentication Authority. Identity stores sealed identity / claims / RBAC / device trust / unlock metadata only. No credentials. No change to Identity in this fix.

## Root cause

| Field | Value |
|-------|--------|
| **Exact file** | `rateb-erp/public/v2/js/package-manager.js` |
| **Exact function** | `runSelfTest()` → first `stageInstall('slot-a', …)` |
| **Boot gate** | `rateb-erp/public/v2/js/boot.js` — `shellOk` required `pmRes.ok` |
| **Error** | `pm_cannot_stage_active_slot` |

### Why first load works

1. OPFS `runtime/active.json` has no active slot (or inactive).
2. PM self-test stages `slot-a` → activate → stage `slot-b` → activate → rollback → **leaves `activeSlot = slot-a`**.
3. Shell Ready fires.

### Why Refresh (F5) fails

1. Navigation type = `reload`; HCI / DB / Runtime / Router / Shell all PASS.
2. PM self-test again calls `stageInstall('slot-a')` while **`slot-a` is still active** from the previous boot.
3. Throws `pm_cannot_stage_active_slot` → `pmRes.ok = false`.
4. Boot treats PM fail as fatal for Shell Ready → status becomes **`Phase 17 self-test failed`**, `data-rateb-v2-shell-ready` never set.
5. Shell self-test still mounts then disposes; dark host + failed status reads as a black / broken refresh.

### Startup timeline after Refresh (measured)

| Mark | ms (reload) | Result |
|------|-------------|--------|
| boot-start | 76 | OK |
| layout-ensured / verified | 127–152 | PASS |
| sw-registered | 153 | PASS |
| pm-selftest-done | 265 | **FAIL** `pm_cannot_stage_active_slot` |
| db-selftest-done | 297 | PASS |
| runtime / router / shell marks | 389–574 | PASS (shell mark fires even when shellOk false) |
| Shell Ready attribute | — | **never** |
| Final boot-status | — | `Phase 17 self-test failed` |

SW install/activate/fetch: not the hang. Package restore / SQLite reconnect / Router remount: OK.

## Minimal fix

1. **`package-manager.js` `runSelfTest`** — choose primary/secondary slots from `getActive()` so the first `stageInstall` never targets the current active slot (refresh-idempotent).
2. **`boot.js`** — Shell Ready requires PM **API present**, not PM self-test PASS (self-test is certification that mutates durable state).
3. **`sw.js`** — bump cache id to `rateb-offline-v2-host-pz2` so clients pick up new boot/PM.

## Validation

After deploy: cold load → Shell Ready → F5 → Shell Ready again (`data-rateb-v2-shell-ready=1`).
