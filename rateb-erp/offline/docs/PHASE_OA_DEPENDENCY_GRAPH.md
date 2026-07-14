# Phase OA — Offline SDK Dependency Graph (Pre-Split Gate)

**Date:** 2026-07-14  
**Source:** `public/assets/offline/rateb-offline.js` (concat of `offline/client/**`)  
**Machine evidence:** `tools/boot-bench/reports/oa-dependency-graph.json`

## Exported globals (35)

| Global | Defining section |
|--------|------------------|
| `RatebOfflineSchema` | schema.js |
| `RatebOfflineStores` | migrations.js |
| `RatebOfflineIdempotency` | idempotency.js |
| `RatebOfflineEvents` | event-bus.js |
| `RatebOfflineConnectivity` | connectivity.js |
| `RatebOfflineQueue` | queue-manager.js |
| `RatebOfflineReplayScheduler` | replay-scheduler.js |
| `RatebOfflineDeltaPull` | delta-pull.js |
| `RatebOfflineTransport` | transport.js |
| `RatebOfflinePosAdapter` … `RatebOfflineBiAdapter` | domain adapters |
| `RatebOfflineFormPostAdapter` | form-post-adapter.js |
| `RatebOfflineShellAdapter` | shell-adapter.js |
| `RatebOfflineAuthLock` | auth-lock-adapter.js |
| `RatebOfflineLocalSession` | offline-local-session-adapter.js |
| `RatebOfflineBootstrapManager` | offline-cold-bootstrap-adapter.js |
| `RatebOfflineRbacCache` | rbac-cache-adapter.js |
| `RatebOfflineMasterData` | master-data-adapter.js |
| `RatebOfflineOpsForms` | ops-forms-adapter.js |
| `RatebOffline` | sdk.js |

## Platform dependencies

| Concern | Sections |
|---------|----------|
| IndexedDB | schema, migrations, queue, shell, auth, rbac, master-data |
| Crypto (PBKDF2 / WebAuthn) | auth-lock-adapter only |
| Service Worker / Cache API | shell-adapter, connectivity (indirect) |
| `__RATEB_ERP_SHELL_OFFLINE__` | auth, rbac, shell, sdk consumers |
| webStorage | auth, shell, local-session |
| fetch | connectivity, queue flush, delta-pull, transport |

## Module graph (load-time hard deps)

Must load before dependents:

```
schema.js
  └─ migrations.js
  └─ queue-manager.js
  └─ shell / auth / rbac / master-data (call-time Schema)

event-bus.js, idempotency.js
  └─ queue-manager.js

connectivity.js  ↔  queue-manager.js   (SOFT — methods at event time)
queue-manager.js  ↔  replay-scheduler.js (SOFT)
transport.js → connectivity, queue (SOFT at configure/flush)

adapters → RatebOffline.flags()          (SOFT — guarded)
ops-forms → all domain adapters          (SOFT — guarded)
sdk.js → everything                      (SOFT assign / status)
auth ↔ local-session ↔ rbac              (SOFT — guarded)
```

## Initialization graph (current monolith order)

Identical to `offline/scripts/build-rateb-offline-bundle.php` `$order`  
(schema → … → ops-forms → sdk last).

## Circular dependency report

| Cycle | Class | Resolution |
|-------|-------|------------|
| queue ↔ connectivity ↔ replay | **SOFT** (call-time `root.X &&`) | Bootstrap loads storage→queue→network→replay; no logic dup |
| sdk ↔ every adapter | **SOFT** (`RatebOffline.flags`) | Bootstrap installs flags/init **before** adapters |
| auth ↔ local-session ↔ rbac | **SOFT** | Load auth only when unlock required; rbac after/with auth |
| ops-forms ↔ adapters | **SOFT** | Idle / first form use |

**No HARD circular load dependency** (no module requires another module’s exports at IIFE top-level for assignment).

## Gate decision

**SAFE TO SPLIT** via bootstrap loader that:

1. Publishes `RatebOffline` flags/`init`/`ensure` first  
2. Enforces module load order for hard deps (schema before queue)  
3. Lazy-loads soft-cycle peers  

**Forbidden:** duplicating queue/auth/RBAC logic; changing IDB schema version/stores; changing sync/replay algorithms.
