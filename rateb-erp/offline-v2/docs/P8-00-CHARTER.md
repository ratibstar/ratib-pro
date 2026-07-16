# Phase 8 — L5 Module SDK Charter

**Status:** Binding  
**Depends on:** Phase 1–7 APPROVED · published Runtime / Router / Shell / Sync / PM / DB APIs

## In scope

- Module manifest format (`rateb-offline-v2-module/1`)
- Lifecycle: install → initialize → mount → activate → deactivate → unmount → dispose
- Runtime DI context (runtime, layer, db, sync, router, shell, pm, hci, events, services)
- Service registration (`module.{id}.{name}`)
- Event bus integration
- Route registration via Router published API
- UI / navigation contribution points
- Permission + capability model
- Module configuration
- Version compatibility checks
- Signature verification hooks
- Hot load / unload
- Fault isolation / containment
- Public SDK APIs for future ERP modules (Phase 9+)
- Compatibility with Package Manager (`modules` package type), SQLite, Sync, Shell, Runtime

## Forbidden

Business ERP modules, PHP fetch, DOMParser, document reload, IndexedDB/Cache ERP storage, Offline V1, redesign of L0–L4/L6/L7 layers.
