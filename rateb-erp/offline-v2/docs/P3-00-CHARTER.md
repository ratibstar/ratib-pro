# Phase 3 — L3 SQLite Runtime Charter

**Status:** Binding  
**Depends on:** Phase 1 + Phase 2 APPROVED · P1-00A · HCI

## In scope

- SQLite engine (`@sqlite.org/sqlite-wasm` vendored under `public/v2/vendor/sqlite/`)
- ERP database file **only** at `database/ratib.sqlite` (via HCI path contract)
- Migrations framework + schema versioning
- Integrity check (`PRAGMA integrity_check`)
- Backup / restore foundations (`backups/` via HCI)
- Install-pointer mirror table compatible with L7 `runtime/active.json`
- Foundation tables for later L4 (`sync_outbox`, `sync_inbox`, `entity_row`) — **no sync engine**

## Modes

1. **opfs** — `sqlite3.oo1.OpfsDb` when available  
2. **hci-persist** — in-memory SQLite + serialize/deserialize through HCI (fallback; no COOP headers required)

## Out of scope

L1 Runtime Loader, L2 Router, L4 Sync engine, L5 Module SDK, L6 UI, Offline V1, Package Manager redesign.

## Non-touch

`public/assets/offline`, `offline/`, `pos-sw.js`, `rateb-offline-sw.js`, `offline-shell.html`, `erp-nav-instant.js`, Capacitor.
