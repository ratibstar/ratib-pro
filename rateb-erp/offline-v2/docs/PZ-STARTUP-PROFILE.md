# Phase Z — Startup Profile

## Target chain

```
HTML → SW → HCI → Package Manager → SQLite → Runtime → Router → Shell → Identity → Business Modules
```

**Goal:** Shell Ready without artificial waits. Budget: **&lt; 3s** to Shell Ready on production.

## Pre-fix profile (production audit 2026-07-16)

| Milestone | Observed |
|-----------|----------|
| First paint | ~532 ms |
| DOM ready | ~2,278 ms |
| Blocking wait (`whenDbReady` 20s) | **~20,005 ms** |
| Shell Ready | **Never** |
| Final status | Phase 17 self-test failed |
| SQLite vendor HTTP | **404** (`index.mjs`, `sqlite3.wasm`, `opfs-async-proxy`) |

## Post-fix expectations

| Milestone | Expected |
|-----------|----------|
| HTML + critical JS | &lt; 1s |
| HCI layout ensure | hundreds of ms (OPFS) |
| SW register | parallel-safe; install non-atomic |
| SQLite module import + open | &lt; 1s when assets HTTP 200 |
| Shell Ready signal | Immediately after shell self-test (before BM cascade) |
| Blind DB wait | **Removed** (was 20s; now ≤4s fail-fast + vendor HEAD probe) |

## Boot changes (orchestration only)

1. `whenDbReady()` polls `RatebOfflineV2DB` / `rateb-v2-db-ready` every 40ms.
2. After 800ms without DB, HEAD `vendor/sqlite/index.mjs` — non-OK → resolve null immediately.
3. Hard cap 4000ms (not 20000ms).
4. On shell self-test PASS + prior platform PASS → set `boot-status` to **Shell Ready**, set `data-rateb-v2-shell-ready=1`, dispatch `rateb-v2-shell-ready`.
5. Business module self-tests continue afterward without blocking the Shell Ready gate.

## Measurement recipe (production)

1. Fresh Chromium profile.
2. Open `https://rateb.sa/rateb-erp/public/v2/index.html`.
3. Listen for `rateb-v2-shell-ready` or `document.documentElement.getAttribute('data-rateb-v2-shell-ready')`.
4. Record `performance.now()` delta from navigation start.
5. Soft-reload offline; confirm Shell Ready again.
6. Confirm Network: no 404 under `/v2/vendor/sqlite/`.
