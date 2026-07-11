# RATEB ERP — Enterprise Offline Layer

Additive offline-first foundation. Does **not** modify existing APIs, schema, UI, or business logic.

## Structure

```
offline/
├── config/          Feature flags, sync policy, entity manifest
├── client/          Browser SDK (IndexedDB, connectivity, queue, transport)
├── server/          PHP services + OfflineSyncApiController
├── migrations/      Additive SQL (also mirrored under rateb-erp/migrations/)
├── tests/           Unit / integration / stress tests
└── docs/            Architecture + phase reports
```

## Feature flags

| Flag | Default | Purpose |
|------|---------|---------|
| `offline.enabled` | `false` | Master switch |
| `offline.pos.complete` | `true` when master on | POS T0 bridge |
| `offline.inventory.movements` | `false` | Tier 1 Inventory Offline (Phase 3) |
| `offline.hr.attendance` | `false` | Tier 1 HR Offline (Phase 4) |
| `offline.read_cache` | `false` | Tier 2 ERP shell SW + chrome snapshot (Phase 10) |
| `offline.auth.unlock` | `false` | Tier 3 local shell unlock PIN/WebAuthn (Phase 11) |
| `offline.rbac.cache` | `false` | Tier 3 RBAC/nav manifest cache (Phase 12; UI only) |

Enable via env:

```bash
RATEB_OFFLINE_ENABLED=1
RATEB_OFFLINE_INVENTORY_MOVEMENTS=1
RATEB_OFFLINE_HR_ATTENDANCE=1
RATEB_OFFLINE_READ_CACHE=1
RATEB_OFFLINE_AUTH_UNLOCK=1
RATEB_OFFLINE_RBAC_CACHE=1
```

## ERP Shell Offline (Phase 10)

When `offline.enabled` + `offline.read_cache` are ON, `views/layouts/main.php` injects the SDK + `erp-shell-bootstrap.js`, which registers `rateb-offline-sw.js` (never intercepts `/pos/*`, never caches API/HTML auth bodies). Shell chrome is stored in IndexedDB `snapshots` (`erp_shell_chrome`). Flags OFF → zero layout/SW behavior.

## ERP Offline Auth (Phase 11)

Requires master + `read_cache` + `auth.unlock`. Vault store `auth_vault` in `rateb_erp_offline` (DB_VERSION 2). Local unlock overlay only — no PHP session offline. Queue flush blocked while `sessionNeedsReauth`.

## ERP Offline RBAC (Phase 12)

Requires master + `read_cache` + `auth.unlock` + `rbac.cache`. Manifest stored as snapshot kind `erp_rbac` (TTL + `rbac_version`). UI/nav only — never replaces server authorization.
## API (additive)

- `GET  /api/v1/offline/status`
- `POST /api/v1/offline/push`
- `POST /api/v1/offline/process`
- `GET  /api/v1/offline/conflicts`
- `POST /api/v1/offline/conflicts/{id}/resolve`
- `GET  /api/v1/offline/delta/{entity}` — `inventory_catalog`, `employee_directory`

## Inventory Offline (Phase 3)

Client: `RatebOffline.inventory()` → enqueue movement / stock count / warehouse transfer + catalog delta pull.

## HR Offline (Phase 4)

Client: `RatebOffline.hr()` → enqueue attendance / bulk / leave draft + employee directory pull.

Server replay uses existing HR domain (`AttendanceRecord`, `LeaveRequest`, `HrService::bootstrapTenant`).  
**Not included:** payroll, approvals, financial posting.

## Tests

```bash
php offline/tests/run-offline-foundation-tests.php
php offline/tests/run-inventory-offline-tests.php
php offline/tests/run-hr-offline-tests.php
php offline/tests/run-queue-durability-tests.php
php offline/tests/run-phase45-integration-validation.php
```
