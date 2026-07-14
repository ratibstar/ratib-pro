# Phase OA — Offline SDK Split — Enterprise Report

**Date:** 2026-07-14  
**Scope:** Load timing only — architecture, APIs, sync/replay/queue/auth/RBAC algorithms unchanged  
**Pre-condition:** [PHASE_OA_DEPENDENCY_GRAPH.md](./PHASE_OA_DEPENDENCY_GRAPH.md) — **SAFE TO SPLIT** (cycles are SOFT)

---

## 1. Dependency graph (summary)

| Artifact | Location |
|----------|----------|
| Full JSON graph | `tools/boot-bench/reports/oa-dependency-graph.json` |
| Human report | `offline/docs/PHASE_OA_DEPENDENCY_GRAPH.md` |

- **35 exported globals** (unchanged names)
- **147 soft edges**, **0 hard circular load deps**
- Cycles (queue↔connectivity↔replay, sdk↔adapters, auth↔rbac) resolved via **bootstrap loader** only — no logic duplication

---

## 2. What changed (WHEN, not HOW)

| Item | Change |
|------|--------|
| Entry script | `offline-bootstrap.js` (**18,040 B** &lt; 20 KB) |
| Modules | `public/assets/offline/modules/*` (35 files, built from `offline/client/**`) |
| IndexedDB | Singleton `dbPromise` in `schema.js` — open once |
| Boot sequence | storage critical → idle: queue/network/replay/sync/sdk/shell |
| Auth/crypto | Loaded only when unlock required (offline-shell / flags) |
| Domain adapters | Lazy / path click / ops-forms idle |
| Monolith | Still built as `rateb-offline.js` for certification / fallback — **not** on critical layout path |

### Unchanged

Sync Engine, Replay Engine, Queue Engine, Hybrid Runtime, IDB schema version/stores, Auth/RBAC algorithms, SW scope model, controllers, routes, SQL.

---

## 3. Before / After benchmarks

Source: `tools/boot-bench/reports/oa-before-after-1784062350103.json` (Chrome headless, local assets)

| Metric | Before (monolith) | After (OA) | Target |
|--------|-------------------|------------|--------|
| Critical startup JS | 389,493 B | **18,040 B** | &lt;40 KB |
| Bootstrap size | n/a | **18,040 B** | &lt;20 KB |
| Time to interactive (post-init) | full SDK parse ~34–110 ms wall | **~21 ms** to Schema+booted | shell before heavy |
| IDB `open()` count (startup) | N opens via `withStore` | **1** singleton | once |
| Auth on normal admin boot | Yes (in monolith) | **No** until unlock | Phase 7 |
| Queue/Replay/Connectivity | Parse at start | **After idle** | Phase 4 |
| Globals after idle | All | Queue+Conn+Replay+`isCrmEnabled` | API parity |

Critical-path download reduction: **~95.4%** (389 KB → 18 KB).

---

## 4. New boot sequence

```
offline-bootstrap.js
  → RatebOffline.init()
  → ensure(storage) [+ auth/rbac only if unlock required]
  → sdk:ready / interactive
  → requestIdleCallback
       → queue + network + replay + sync + sdk helpers
       → shell capture + forms + diagnostics
  → on path click → domain adapter module
```

---

## 5. Regression gates (status)

| Gate | Status | Notes |
|------|--------|-------|
| Offline APIs / globals | PASS (structural) | Same names; OA merges sdk helpers onto bootstrap |
| IndexedDB schema | PASS | DB_VERSION 2, same stores; singleton only |
| Auth load deferral | PASS (bench) | Auth false on OA normal boot |
| Queue/Replay/Sync after idle | PASS (bench) | Present after idle window |
| SW precache | UPDATED | bootstrap + storage/auth/rbac modules |
| Offline shell | UPDATED | bootstrap → ensure auth/rbac → hosts |
| Layout critical path | UPDATED | loads `offline-bootstrap.js` |
| Monolith certification tests | PASS path | `rateb-offline.js` still fully concatenated |
| Full prod E2E unlock | PENDING env | Needs valid prod credentials |

Rebuild: `php offline/scripts/build-rateb-offline-bundle.php`

---

## 6. Rollback plan

1. In `views/layouts/main.php`, restore lazy script to `rateb_asset('offline/rateb-offline.js')`.
2. In `public/offline-shell.html`, restore serial load of `rateb-offline.js`.
3. Redeploy `main.php` + `offline-shell.html` (+ optional revert SW URL list).
4. Monolith file remains built — no data migration required.

---

## 7. Files touched

- `offline/client/db/schema.js` — IDB singleton  
- `offline/client/core/sdk.js` — shared flags + OA merge  
- `offline/client/adapters/shell-adapter.js` — shell HTML script tag  
- `offline/scripts/build-rateb-offline-bundle.php` — emit modules  
- `public/assets/offline/offline-bootstrap.js` — NEW  
- `public/assets/offline/modules/*` — NEW (generated)  
- `views/layouts/main.php`, `public/offline-shell.html`  
- `erp-shell-bootstrap.js`, `erp-offline-full-warm.js`  
- `public/pos-sw.js`, `public/rateb-offline-sw.js`  
- Docs: `PHASE_OA_DEPENDENCY_GRAPH.md`, this report  

---

## 8. Verdict

**Phase OA IMPLEMENTED — safe split proven, critical path &lt;20 KB bootstrap, heavy work idle/lazy, IDB singleton active, APIs preserved.**
