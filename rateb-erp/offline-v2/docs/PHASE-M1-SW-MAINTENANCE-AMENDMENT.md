# Phase M1 Revision — V2 Host SW Maintenance in Ownership Transfer

**Status:** ACCEPTED architecture amendment · Documentation only  
**Date:** 2026-07-17  
**Supersedes:** M1 execution constraint “No Service Worker changes” (that constraint created an Architecture Conflict with OFAT single-ownership)  
**Does not supersede:** ADR-AL-1, ADR-AL-2, AF-2.1, PX-Deploy, OFAT Track A/B split  

---

## Problem

M1 cannot transfer Runtime/HCI ownership while `public/v2/sw.js` continues to permanently precache the previous V2 implementations under an unchanged cache name (`rateb-offline-v2-bootstrap-v2`) with cache-first fetch. Existing clients would keep running the old owner → **duplicate effective ownership**.

## Decision

**M1 includes ONLY the V2 migration-host Service Worker maintenance required to preserve single ownership.**

| In scope (M1) | Out of scope (forbidden) |
|---------------|--------------------------|
| Runtime + EventBus + ServiceLocator transfer to `platform/runtime/` | SQLite / vendor/sqlite |
| HCI transfer to `platform/hci/` | Identity / Sync / Queue |
| V2 thin stubs (forward-only) | Admin layouts / routes / runtime |
| `public/v2/index.html` script `src` → stubs or platform (migration host only) | Offline V1 |
| `public/v2/sw.js`: cache **version bump**, precache **platform URLs**, **remove** old Runtime/HCI paths | `pos-sw.js`, `rateb-offline-sw.js` |
| | ERP UI / POS / Authentication |

`public/v2` remains a **migration host only**, not a production ERP frontend.

---

## Architecture safety answers

1. **Architecture-safe?** YES — SW change is scoped to the migration host and is the minimum required to retire the old owner from the install cache.
2. **ADR-AL-1 preserved?** YES — Admin remains the only production ERP frontend; no Admin/POS/ERP UI change; v2 stays non-ERP.
3. **ADR-AL-2 preserved?** YES — after M1, Admin-owned `platform/` is the sole Runtime/HCI implementation; v2 stubs are not owners.
4. **OFAT preserved?** YES — one atomic unit, one owner, inverted arrow (`v2` → platform), independently deployable/rollbackable, PX-Deploy + PX4 per step.

---

## Updated M1 execution order

1. **Move** HCI implementation → `public/assets/offline/platform/hci/hci.js` (remove M0 `.gitkeep` or replace).
2. **Move** Runtime (+ EventBus + ServiceLocator) → `public/assets/offline/platform/runtime/runtime.js`.
3. **Replace** `public/v2/js/hci.js` and `public/v2/js/runtime/runtime.js` with **thin stubs only** (load/forward platform URLs; no logic, no second singleton).
4. **Update** `public/v2/index.html` script tags only as needed so the migration host loads stubs (or platform URLs) — still under `/v2/`, not Admin.
5. **Maintain V2 host SW (`public/v2/sw.js`) only:**
   - Bump `CACHE` name (e.g. `…-bootstrap-v3`) so activate deletes the prior owned cache.
   - Remove `./js/hci.js` and `./js/runtime/runtime.js` as **canonical implementation** precache entries if replaced by platform URLs; precache **platform** Runtime/HCI URLs (and stubs if still referenced by `index.html`).
   - Do **not** change SW scope, navigation policy, or Admin/POS workers.
6. **Audit:** exactly one non-stub Runtime/HCI/EventBus/ServiceLocator implementation under `platform/`; V2 files stub-sized; no Admin → `/v2` imports; no SQLite/Identity/Sync movement.
7. **Deploy:** commit → push → PX-Deploy PASS (Git == Production, orphans cleared including any deleted V2 owner paths).
8. **PX4:** PASS against migration host using platform-owned Runtime/HCI via stubs.
9. **STOP** — do not start M2.

### Rollback

`git revert` M1 commit → PX-Deploy restores prior V2 implementations, removes platform Runtime/HCI orphans, restores prior `sw.js` cache name → PX4 PASS.

---

## Explicit non-changes

- `pos-sw.js` / Admin Offline SW: **unchanged**
- Admin PHP MVC / layouts / routes: **unchanged**
- Offline V1: **unchanged**
- SQLite, Identity, Sync, Queue, Business Modules, Authentication, ERP UI: **unchanged**
