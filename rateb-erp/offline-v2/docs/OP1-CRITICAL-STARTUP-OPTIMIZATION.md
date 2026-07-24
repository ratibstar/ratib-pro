# Phase OP1 — Critical Startup Optimization

**Status:** Implemented  
**Date:** 2026-07-24  
**Mode:** Implementation (no business-logic changes)

## Goal

Make the Offline V2 ERP UI visible immediately. Move Runtime HCI/package/health,
SQLite, Identity, PM/SDK/Sync, and extension router manifests off the shell
critical path.

## Architecture (target)

```text
Shell.mount()
  → Immediate paint (interactive skeleton)
  → Runtime.start() critical only (register services)
  → Router builtins (no manifest wait)
  → Shell Ready / Interactive
  → Background: Runtime deferred (ensureLayout / package / health)
  → Background: SQLite on first DB request
  → Background: Identity when unlock / authenticated module route needs it
  → Background: PM / Sync / SDK only when package / sync / module requested
```

## Changes by file

| File | Change |
|------|--------|
| `public/assets/offline/platform/runtime/runtime.js` | Split `start()` into critical (services) + deferred (layout/package/health). Added `whenFullyReady()`. |
| `public/assets/offline/platform/hci/hci.js` | `ensureLayout()` memoized per session + `localStorage` warm skip; full walk only on first install / LAYOUT_ID change. |
| `public/v2/js/ui/shell.js` | Paint DOM first; await critical Runtime only; defer `whenFullyReady()`. |
| `public/v2/js/router/router.js` | Inline builtin routes; load extension manifest after paint (`deferManifest`). |
| `public/assets/offline/platform/db/sqlite-runtime.js` | `register()` no longer warms WASM; open remains on first DB request; mark `rateb-v2-sqlite-ready`. |
| `public/v2/js/boot.js` | Home skips PM/Sync/SDK/DB; modules get DB+SDK+Sync lazily; Identity only via `bootstrapIdentityReadiness` after shell. |

## Preserved

- Offline certification paths
- Sync enqueue correctness (Sync registers when a module is requested)
- Identity boundary (published APIs only; Online ERP remains Authentication Authority)
- RBAC / POS checkout / controllers / services / schemas unchanged

## Benchmark

Tool: `rateb-erp/tools/boot-bench/phase-op1-startup-bench.js`

Marks:

- `rateb-v2-shell-paint`
- `rateb-v2-interactive-ready`
- `rateb-v2-shell-ready`
- `rateb-v2-runtime-ready` (critical)
- `rateb-v2-runtime-fully-ready` (deferred)
- `rateb-v2-sqlite-ready` (first open)
- `rateb-v2-identity-ready` (first Identity activation)

### Before (PX2/PZ warm Inventory / Home)

| Metric | Before |
|--------|--------|
| First Paint | ~48 ms |
| Shell Ready | 66–206 ms (blocked on full Runtime.start) |
| Interactive | ~21 ms (attr set early; shell mount still waited) |
| Runtime Ready | Coupled to Shell Ready |
| SQLite | Opened/warmed on Home background (~45 ms+) |
| Identity | Activated on module routes after platform load |
| Home background complete | ~353 ms |

### After (measured — production, commit `6af8a23d`)

Evidence: `tools/boot-bench/reports/phase-op1-startup-1784901197799.json`

| Metric | After |
|--------|--------|
| Shell Ready | **22.1 ms** (was 66 ms) |
| Interactive | 21.1 ms |
| Runtime Ready (critical) | 21.2 ms |
| SQLite on Home | **null** (not opened) |
| Identity on Home | **null** (not activated) |
| PM/Sync/SDK on Home | Deferred until module/package/sync request |
