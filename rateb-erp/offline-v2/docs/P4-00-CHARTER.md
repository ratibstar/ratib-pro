# Phase 4 — L1 Runtime Charter

**Status:** Binding  
**Depends on:** Phase 1–3 APPROVED · HCI · Package Manager · SQLite Runtime

## In scope

- Local Runtime Kernel (`RatebOfflineV2Runtime`)
- Service Locator / DI
- Event bus with error isolation
- Lifecycle: created → starting → ready|degraded → stopping → stopped|failed
- Load active runtime package from HCI `runtime/runtime.pkg` + `runtime.manifest` + `active.json` only
- Health monitoring
- Layer API facade for future L2/L4/L5 (`layerApi()`)
- Zero-network ERP startup (no admin/PHP/V1 SW fetches)
- Self-test `runSelfTest()`

## Out of scope

L2 Router, L4 Sync engine, L5 Module SDK, L6 UI rendering, Offline V1, Package Manager redesign, SQLite architecture redesign.

## Non-touch

`public/assets/offline`, `offline/`, `pos-sw.js`, `rateb-offline-sw.js`, `offline-shell.html`, `erp-nav-instant.js`, Capacitor, `package-manager.js`, `js/db/sqlite-runtime.js` (architecture).
