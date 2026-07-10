# RATEB ERP — Enterprise Offline Layer (Phase 2A)

Additive offline-first foundation. Does **not** modify existing APIs, schema, UI, or business logic.

## Structure

```
offline/
├── config/          Feature flags, sync policy, entity manifest
├── client/          Browser SDK (IndexedDB, connectivity, queue, transport)
├── server/          PHP services + OfflineSyncApiController
├── migrations/      Additive SQL (also mirrored under rateb-erp/migrations/)
├── tests/           Unit tests
└── docs/            Architecture reference
```

## Feature flags

| Flag | Default | Purpose |
|------|---------|---------|
| `offline.enabled` | `false` | Master switch |
| `offline.pos.complete` | `true` when master on | POS T0 bridge |
| `offline.inventory.movements` | `false` | Tier 1 (not in 2A) |
| `offline.hr.attendance` | `false` | Tier 1 (not in 2A) |
| `offline.read_cache` | `false` | Tier 2 (not in 2A) |

Enable via env `RATEB_OFFLINE_ENABLED=1` or `offline/config/feature-flags.php`.

## API (additive)

- `GET  /api/v1/offline/status`
- `POST /api/v1/offline/push`
- `POST /api/v1/offline/process`
- `GET  /api/v1/offline/conflicts`
- `POST /api/v1/offline/conflicts/{id}/resolve`
- `GET  /api/v1/offline/delta/{entity}`

## Tests

```bash
php offline/tests/run-offline-foundation-tests.php
```
