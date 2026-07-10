# Enterprise Offline Architecture — Phase 2A Implementation Notes

See chat Phase 1 architecture document for full design.

## Phase 2A delivered

- `offline/` folder structure
- Client SDK (`public/assets/offline/rateb-offline.js`)
- IndexedDB schema `rateb_erp_offline`
- Connectivity + Queue + Transport
- Feature flags (master default OFF)
- Additive SQL: `rateb_offline_*` tables
- `OfflineSyncApiController` at `/api/v1/offline/*`
- Unit tests under `offline/tests/`

## Not in Phase 2A

- Inventory / HR / Procurement / ERP shell sync
- UI script injection (no layout changes)
- Business logic replay beyond `offline.ack`
