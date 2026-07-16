# Phase 2 — L7 Package Manager Charter

**Status:** Binding  
**Depends on:** Phase 1 L0 VERIFIED COMPLETE · DR-0001 · P1-00A

## In scope

- HCI write policy for package ingest, updates/, slots/, runtime activate
- Immutable `packages/*` (create-if-absent only)
- Slots `slot-a` | `slot-b` | `slot-c`
- State machine: ingest → stage → verify → activate → rollback
- Never modify active slot contents; never modify packages in place
- Enterprise self-test via `RatebOfflineV2PM.runSelfTest()`

## Out of scope

L3 SQLite schema, L1 runtime loader/service locator, L2 router, L4 sync, L5 modules, L6 UI shell, Offline V1.

## Non-touch

`public/assets/offline`, `offline/`, `pos-sw.js`, `rateb-offline-sw.js`, `offline-shell.html`, `erp-nav-instant.js`, Capacitor, branch appliance.
