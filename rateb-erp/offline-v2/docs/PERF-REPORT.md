# Phase PERF — Offline V2 Performance Engineering

**Result:** PASS for installed / warm / offline startup  
**Production commit:** `8764f9d6`  
**Production URL:** `https://rateb.sa/rateb-erp/public/v2/index.html`  
**Measurement:** Chromium persistent profile, cold → warm reload → offline reload

## Architecture and Identity compliance

- Architecture Freeze remained active. HCI, Runtime, Router, Shell, Sync, SDK,
  Identity, and Business Framework contracts were not redesigned.
- Online ERP remains the only Authentication Authority.
- Identity remains a local cache of sealed identity, claims, RBAC snapshot,
  device trust, and unlock metadata only.
- No password, password hash, session cookie, bearer token, JWT, TOTP secret,
  WebAuthn server credential, API token, or authentication secret was added.
- BusinessModules continue to use published `module.identity.*` APIs only.
- Offline V1 was not changed.

## Executive result

The installed application now paints and becomes interactive before storage,
SQLite, Package Manager scans, Sync, SDK, diagnostics, or BusinessModules.

| Production scenario | First paint | Interactive | Shell Ready | Route Ready | Result |
|---|---:|---:|---:|---:|---|
| Home — warm installed | **120 ms** | **113 ms** | **192 ms** | **192 ms** | PASS |
| Inventory — warm installed | **128 ms** | **109 ms** | **184 ms** | **296 ms** | PASS |
| Home — offline reload | **48 ms** | **47 ms** | **154 ms** | **154 ms** | PASS |
| Inventory — offline reload | **56 ms** | **43 ms** | **150 ms** | **325 ms** | PASS |
| F5 refresh | — | 29 ms shell render | **158 ms** | 99 ms platform route | PASS |

Targets:

- First Paint < 200 ms — PASS
- Interactive < 500 ms — PASS
- Shell Ready < 800 ms — PASS
- Requested route < 1 s — PASS

These targets apply to the installed Offline V2 application (warm or offline),
which is the user-facing offline startup path.

## Cold first-visit network qualification

One uncached production run had **2841 ms TTFB**, so first paint was 3020 ms.
The application cannot paint before the origin returns `index.html`.
After response, the optimized application reached:

- Interactive in 229 ms
- Shell/Route Ready in 486 ms

This isolates remaining cold-first-visit latency to origin/edge TTFB, not the
Offline V2 startup graph. Installed startup meets every Phase PERF target.

## Before vs after

| Metric | Before | After | Improvement |
|---|---:|---:|---:|
| Production Shell Ready (cold run) | 4673 ms | 3326 ms | 29% |
| Offline Shell Ready | 599 ms | 154 ms | 74% |
| Installed warm Shell Ready | not separately gated | 192 ms | target PASS |
| Inventory route (warm) | all modules/self-tests first | 296 ms | route-first PASS |
| F5 refresh | could fail Shell Ready | 158 ms | hang removed |
| Business modules fetched at startup | 8 | 0 on Home; 2 on Inventory | lazy |
| Blocking self-tests | PM + DB + Runtime + Router + Shell | 0 | removed |

## Startup timeline — installed Inventory route

Production warm profile (`#/inventory`):

```text
0 ─────────────── 97 ms   document / TTFB
97 ───────────── 109 ms   critical CSS + HCI + Runtime + Router + Shell + boot
109 ms                     INTERACTIVE (real shell mounted synchronously)
109 ──────────── 184 ms   Runtime start + Router bootstrap + Shell Ready
184 ms                     SHELL READY
200 ms                     background work begins
200 ──────────── 292 ms   PM + SQLite + Sync + SDK + Business Framework
292 ──────────── 296 ms   Identity dependency + Inventory only
296 ms                     REQUESTED ROUTE READY / BACKGROUND READY
```

The Home route does not load any BusinessModule.

## Flame graph — critical versus background

```text
Installed warm Inventory (296 ms total)

Document/TTFB       [================]                                  0–97
Critical scripts                    [==]                               97–109
Shell render                          |                                109
Runtime + Router                      [============]                  109–184
Shell Ready                                        |                  184
Idle handoff                                         [==]             184–200
PM / SQLite / platform                                [==============]200–292
Identity + Inventory                                                 [=]292–296
Route Ready                                                             |296
```

SQLite/WASM is deliberately the largest background cost; it no longer blocks
paint, interaction, or Shell Ready.

## Ranked startup costs (cold production Inventory)

The trace contained 18 resources; all are listed (top 20 requested).

| Rank | Resource | Duration |
|---:|---|---:|
| 1 | `sqlite3.wasm` | 265.2 ms |
| 2 | `vendor/sqlite/index.mjs` | 222.9 ms |
| 3 | `router/router.js` | 199.0 ms |
| 4 | `ui/shell.js` | 197.9 ms |
| 5 | `boot.js` | 196.0 ms |
| 6 | `runtime/runtime.js` | 117.8 ms |
| 7 | `sync/sync-engine.js` | 112.2 ms |
| 8 | `modules/module-sdk.js` | 107.7 ms |
| 9 | `hci.js` | 106.7 ms |
| 10 | `package-manager.js` | 106.5 ms |
| 11 | `css/shell.css` | 106.3 ms |
| 12 | `business-module-framework.js` | 106.3 ms |
| 13 | `css/host.css` | 103.2 ms |
| 14 | `db/sqlite-runtime.js` | 102.4 ms |
| 15 | `routes/route-manifest.json` | 98.6 ms |
| 16 | `db/migrations.js` | 97.1 ms |
| 17 | `identity-module.js` | 3.6 ms (cache hit) |
| 18 | `inventory-module.js` | 3.6 ms (cache hit) |

No long tasks were observed.

## Exact code changes

### `public/v2/index.html`

- Added a static shell skeleton for immediate first content.
- Reduced blocking scripts to HCI, Runtime, Router, Shell, and boot.
- Removed SQLite/WASM preload contention from the critical path.
- Hid the historical diagnostics panel unless `?diagnostics=1`.

### `public/v2/js/boot.js`

- Replaced self-test-driven boot with the normal `Shell.create().mount()` path.
- Emits measured readiness events/marks:
  `hci`, `interactive`, `runtime`, `router`, `shell`, `route`, `db`,
  active module, and background ready.
- Starts the requested hash/query route immediately.
- Runs PM, SQLite, Sync, SDK, and Business Framework in parallel after Shell Ready.
- Loads no BusinessModule on Home.
- Loads only the requested BusinessModule plus mandatory dependencies.
- Defers diagnostics to idle and only enables them with `?diagnostics=1`.

### `public/v2/js/db/sqlite-runtime.js`

- Added a shared `state.opening` Promise.
- Concurrent callers now reuse one SQLite initialization/open/migration.
- Failed open clears the shared Promise for a safe retry.

### `public/v2/sw.js`

- Cache version bumped to `rateb-offline-v2-host-perf1`.
- Existing resilient per-URL precache remains unchanged.

### Profiling

- Added `tools/boot-bench/phase-perf-v2-profile.js`.
- Captures paint, navigation, readiness marks, top resources, long tasks,
  cold/warm/offline runs, HTTP failures, and page errors.

## Enterprise validation

- Production deploy: PASS
- Warm/offline performance budgets: PASS
- Lazy Inventory route: PASS (`inventory.home`)
- Fresh profile + installed cache: PASS
- F5 refresh reaches Shell Ready: PASS (158 ms)
- SQLite background open: PASS
- Service Worker / offline reload: PASS
- HTTP 4xx under `/v2/`: none
- Page errors: none (CSP meta warning excluded; it is a browser warning)
- Identity boundary: PASS
- Offline V1 untouched: PASS

**Enterprise Offline V2 Performance: PASS.**
