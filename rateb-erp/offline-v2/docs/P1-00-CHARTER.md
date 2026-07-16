# Offline V2 — Phase 1 Charter (P1-00)

**Status:** Binding  
**Decision:** DR-0001 GO  
**Host:** Installed Chromium PWA (sole v2.0 reference)

## In scope (Phase 1 only)

- L0 Host Capability Interface (HCI)
- P1-00A Runtime Root Layout on OPFS
- V2-scoped PWA entry under `/public/v2/`
- Installability SW (V2 scope only; no HTML document routing)
- Quota / persistence / reachability signals
- Host boot stub (local static assets only)
- Secondary-host HCI notes (documentation only)

## Out of scope (later phases)

Package Manager, SQLite schema, Runtime loader, SPA Router, Module SDK, Sync, UI Shell, business modules.

## Non-touch (permanent for Phase 1)

Do not modify Offline V1, `pos-sw.js`, `rateb-offline-sw.js`, `offline-shell.html`, Cache API ERP runtime, IndexedDB ERP stores, `auth_vault`, snapshots, `erp-nav-instant.js`, branch appliance, Capacitor `server.url`.

## Storage

Only P1-00A hierarchy via HCI. No extra top-level directories. No renames.
