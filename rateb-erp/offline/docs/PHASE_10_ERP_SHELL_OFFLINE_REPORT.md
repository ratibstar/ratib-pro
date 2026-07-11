# Phase 10 — ERP Shell Offline (Tier 2)

**Date:** 2026-07-11  
**Scope:** ERP shell survivability only — service worker, sanitized chrome snapshot, layout bootstrap  
**Out of scope:** Auth vault, RBAC cache, master-data deltas, accounting, POS, Tier-1 queue/replay/guards  
**Flags:** `offline.enabled` + `offline.read_cache` (both default **OFF**)

---

## Delivered

| Item | Location |
|------|----------|
| `isReadCacheEnabled()` | `OfflineFeatureFlagService` |
| ERP SW (upgrade stub) | `public/rateb-offline-sw.js` |
| Shell adapter | `offline/client/adapters/shell-adapter.js` (+ SDK bundle) |
| Bootstrap | `public/assets/offline/erp-shell-bootstrap.js` |
| Static fallback | `public/offline-shell.html` |
| Layout gate | `views/layouts/main.php` (inject only when read_cache ON) |
| Tests | `offline/tests/ErpShellOfflinePhase10Test.php` |

## Behavior

| Flags | Behavior |
|-------|----------|
| OFF (default) | No scripts, no SW registration from ERP layout |
| ON | Register SW; init SDK; capture sanitized chrome to `snapshots` |

## Safety

- `/pos/*` — SW does not `respondWith` (no POS interference in handler)
- `/api/*` — never cached; offline JSON `{ok:false,offline:true}`
- HTML documents — network-only; never Cache API for authenticated pages
- CSRF — stripped before IndexedDB snapshot
- Login POST — not intercepted for caching

## Explicit non-changes

- No schema migrations
- No Tier-1 replay/queue/guard edits
- No POS file edits
- No auth/RBAC/delta work
