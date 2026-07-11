# Offline Activation — Blockers & Fixes

**Date:** 2026-07-12  
**Goal:** Make existing Offline platform run on this PC (no new features).  
**Status after activation:** flags **ON** via project-root `.env` — verify script **ACTIVATION: READY**.

---

## What prevented Offline today

| # | Exact file | Exact line(s) | Exact reason | Exact fix |
|---|------------|---------------|--------------|-----------|
| 1 | `rateb-erp/offline/config/feature-flags.php` | L11–94 (`'offline.enabled' => false`, all module defaults `false`) | Master + all module offline flags default **OFF** by design | Set env overrides (designed path). **Done:** project-root `.env` with `RATEB_OFFLINE_*=1` |
| 2 | Project-root `.env` | **missing** before this activation | `dotenv_bridge` never saw `RATEB_OFFLINE_*` | **Created** `c:\Users\انا\Documents\ratibprogram\.env` with full offline block |
| 3 | `config/env/dotenv_bridge.php` | L116–120 | Only loads keys with prefix `RATEB_OFFLINE_` (or allowlist) from `.env` | No code change needed — put vars under that prefix (**done**) |
| 4 | `rateb-erp/views/layouts/main.php` | L393–395 | SDK + `erp-shell-bootstrap.js` + SW register only inject when `isReadCacheEnabled()` | Enable `RATEB_OFFLINE_ENABLED=1` + `RATEB_OFFLINE_READ_CACHE=1` (**done**) |
| 5 | `rateb-erp/views/layouts/main.php` | L449–453 | Auth enroll/unlock bootstrap only when `isAuthUnlockEnabled()` | `RATEB_OFFLINE_AUTH_UNLOCK=1` (**done**) |
| 6 | `rateb-erp/views/layouts/main.php` | L456–460 | RBAC cache bootstrap only when `isRbacCacheEnabled()` | `RATEB_OFFLINE_RBAC_CACHE=1` (**done**) |
| 7 | `rateb-erp/offline/server/Services/OfflineFeatureFlagService.php` | L427–429 | Cold requires auth.unlock + `offline.auth.cold` | `RATEB_OFFLINE_AUTH_COLD=1` (**done**) |
| 8 | `rateb-erp/offline/server/Services/OfflineBackgroundSync.php` | L29–53 | “Background sync” returns `disabled: true` when master OFF | Same as #1 — master ON (**done**). **Note:** there is **no** flag named `offline.background` |
| 9 | `rateb-erp/offline/config/feature-flags.php` | (no key) | Flags `offline.sync` / `offline.background` **do not exist** | Sync/replay = `offline.enabled` + `/api/v1/offline/push|process`. Do not invent new flags |
| 10 | `rateb-erp/offline/config/feature-flags.php` | (no `offline.warehouse*`) | Warehouse has **no** dedicated offline flag/adapter | Use `RATEB_OFFLINE_INVENTORY_MOVEMENTS=1` + `RATEB_OFFLINE_MASTER_DATA=1` (**done**) |
| 11 | `rateb-erp/offline/config/ops-page-allowlist.php` | L18+ `paths` | Offline **browse** is allowlist-only even with `pilot.ops_pages` | Navigate allowlisted paths; add path to this file only if a needed page is missing (existing config, not a new feature) |
| 12 | `rateb-erp/offline/server/Services/OfflineQueueService.php` | ~L73 / L133 `migration_required` | Push/replay fails if `rateb_offline_*` tables missing | Run migrations `175`–`180`, `194` on ERP DB if status returns `migration_required` |
| 13 | Device registry | `177`/`179`/`194` + enroll APIs | Queue/replay require trusted/active device after auth unlock | Online login → enroll PIN once while online before cold/warm disconnect |
| 14 | Live browser cache | N/A (runtime) | SW + Cache Storage empty until first online visit with flags ON | Open ERP **online once** after activation so SW installs and pages warm into cache |

---

## What was activated (this PC)

File: `C:\Users\انا\Documents\ratibprogram\.env` (gitignored)

Includes: master, read_cache, auth.unlock, auth.cold, rbac.cache, master_data, pilot.ops_pages, monitoring, identity secret, POS, inventory, HR/HRMS, procurement (+ enterprise + GRN), recruitment, accounting, CRM, projects, assets, approval, manufacturing, payroll, quality, documents, BI.

Verify:

```bash
php rateb-erp/offline/scripts/verify-offline-activation.php
```

Result: **ACTIVATION: READY (0 issues)** — all listed flags ON; SDK/SW/shell/manifest present.

---

## How to use Offline now

### Warm Offline
1. Restart PHP / web server (so `.env` is loaded into the process).
2. Login online.
3. Browse modules you need (warms SW cache + allowlisted pages).
4. Enroll offline PIN (auth unlock UI / enroll API).
5. Disconnect Wi‑Fi → continue → queue → reconnect → replay (`/api/v1/offline/process` or background process when online).

### Cold Offline
1. After warm enroll at least once with `RATEB_OFFLINE_AUTH_COLD=1`.
2. Close browser, disconnect network, open ERP / `offline-shell.html`.
3. PIN unlock → local session only (no PHP session) → cached pages → queue → reconnect → replay.

### Kill-switch
```env
RATEB_OFFLINE_ENABLED=0
```

---

## Remaining operational checks (not code gaps)

| Check | How |
|-------|-----|
| Migrations applied | ERP DB has `rateb_offline_sync_queue` etc.; else apply `migrations/175_*` … `194_*` |
| SW registered | DevTools → Application → Service Workers → `rateb-offline-sw.js` |
| IndexedDB | `rateb_erp_offline` DB_VERSION 2 |
| Cache Storage | `rateb-erp-coexist-v1` + SW caches after first online pass |
| Replay | Online + authenticated; call process or rely on reconnect flush |

---

## Explicit non-flags

| User asked | Repository fact |
|------------|-----------------|
| `offline.sync` | **Does not exist** — use `offline.enabled` |
| `offline.background` | **Does not exist** — `OfflineBackgroundSync` uses master flag |
| Warehouse module flag | **Does not exist** — inventory + master_data |
