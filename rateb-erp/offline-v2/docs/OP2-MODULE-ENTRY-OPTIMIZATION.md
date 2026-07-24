# Phase OP2 — Module Entry Optimization

**Status:** Implemented  
**Date:** 2026-07-24  
**Mode:** Minimal safe changes (no business rules / schema / sync protocol / POS checkout / auth / RBAC)

## Goal

Reduce offline module entry from ~2.5s to &lt;500–800ms perceived.

## Changes

| # | Change | File |
|---|--------|------|
| 1 | BusinessModule deep-links use `scheduleImmediate` (no 1200ms idle) | `public/v2/js/boot.js` |
| 2 | Lazy navigate stubs/wrapper installed at Shell Ready | `boot.js` |
| 3 | `Promise.all` for DB API + SDK/Framework + compat scripts | `boot.js` |
| 4 | Identity gate before Sync/target modules | `boot.js` |
| 5 | ActiveSync deferred until enqueue (lazy proxy in SDK) | `boot.js`, `module-sdk.js` |
| 6 | Inventory scripts preload in parallel; pending shell before dep activate | `boot.js` |
| 7 | POS multi-script loads via `Promise.all` | `boot.js` |

## Preserved

- Activation order: Identity → Inventory (if dep) → target (serial)
- RBAC / Identity published APIs
- Sync protocol (only defer instance start)
- POS checkout / business rules / schemas

## Benchmark

```bash
cd rateb-erp/tools/boot-bench
node phase-op2-module-entry-bench.js
```

Marks: `shell-ready`, `identity-ready`, `gate-visible`, `route-ready`, `module-ready`
