# Phase 5 — L2 SPA Router Charter

**Status:** Binding  
**Depends on:** Phase 1–4 APPROVED · L1 `layerApi()`

## In scope

- Route manifest loader (`routes/route-manifest.json`)
- Route registry + client navigation (History API / hash)
- Handler lifecycle: `init` / `mount` / `unmount` / `dispose`
- Navigation guards (`beforeEach`)
- Route lifecycle events via Runtime event bus (`router:*`)
- Deep-link support (`#/path`, `?r=`, `popstate`)
- Zero-network ERP navigation (no PHP/HTML documents)
- Runtime integration only through published L1 APIs
- Self-test `RatebOfflineV2Router.runSelfTest()`

## Forbidden

DOMParser, HTML fetch for screens, PHP routes, document reload, HTML snapshots, SW document routing, V1 nav / `erp-nav-instant.js`, Cache API page storage as SoT.

## Out of scope

L4 Sync, L5 Module SDK, L6 UI design system, Offline V1.
