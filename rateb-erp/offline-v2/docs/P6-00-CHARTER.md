# Phase 6 — L6 UI Shell Charter

**Status:** Binding  
**Depends on:** Phase 1–5 APPROVED · L1 `layerApi` · L2 Router

## In scope

- Client-rendered shell: header, sidebar, workspace, footer
- Theme service (light/dark)
- Layout state (sidebar collapse, loading, error)
- Navigation from Router route list
- Loading + error boundaries
- Toast host + dialog/overlay host
- Desktop-first responsive CSS
- Runtime/Router integration via published APIs only
- Self-test `RatebOfflineV2Shell.runSelfTest()`

## Forbidden

PHP rendering, HTML snapshots, DOMParser, document reload, SW UI routing, V1 UI reuse, L4 Sync, L5 Module SDK.
