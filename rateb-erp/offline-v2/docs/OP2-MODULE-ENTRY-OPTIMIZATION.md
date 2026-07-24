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

### Measured (production, commit `fc94dec2`, identity gate)

| Module | Shell | Identity ready | Gate visible | Route ready | ActiveSync |
|--------|------:|---------------:|-------------:|------------:|:----------:|
| Inventory | 707 | 1671 | 1672 | 1673 | no |
| Sales | 523 | 1497 | 1497 | 1498 | no |
| HR | 532 | 1478 | 1478 | 1479 | no |
| Accounting | 538 | 1448 | 1449 | 1450 | no |
| POS | 522 | 1481 | 1482 | 1482 | no |

**Before (OP1 audit):** Route Ready ~2500 ms (incl. ≤1200 ms idle)  
**After OP2:** Route Ready ~1450–1670 ms · post-shell gap ~950 ms · stubs early · Sync deferred  

Regressions: none (`phase-op2-module-entry-1784902999789.json`).
