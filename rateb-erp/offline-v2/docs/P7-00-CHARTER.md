# Phase 7 — L4 Sync Engine Charter

**Status:** Binding  
**Depends on:** Phase 1–6 APPROVED · L3 schema · L1 `layerApi` / events · HCI reachability

## In scope

- SQLite outbox / inbox processing
- Push / pull pipeline (pluggable transport; default local-loop for sealed self-tests)
- Conflict detection + resolution framework (LWW / server_wins / client_wins / manual)
- Transaction ordering (created_at) and replay via outbox
- Retry queue with exponential backoff (`available_at`, `attempts`)
- Sync checkpoints (`sync_checkpoint`)
- Resume after interruption (`start` drains pending when online)
- Background sync orchestration (interval + `window.online`)
- Network awareness through HCI `getReachability()` only (plus browser online signal for reconnect)
- Runtime event integration (`sync:*`)
- Package / schema compatibility verification
- Audit logging (`sync_audit`)
- Zero-network operation while offline; auto-sync on reconnect

## Forbidden

IndexedDB ERP storage, Cache API business data, PHP page fetch, document reload, DOMParser, Offline V1, redesign of HCI / PM / SQLite / Runtime / Router / Shell (APIs only), L5 Module SDK.
