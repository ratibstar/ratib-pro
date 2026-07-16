# Phase Z — Remaining Findings

## Resolved by this phase

- Missing `public/v2/vendor/sqlite/*` on production (gitignore + commit + deploy).
- SW `cache.addAll` atomic failure on 404 / `./` directory URL.
- 20-second `whenDbReady` blind wait.
- Risk of WASM corruption if uploaded as text via Fileman `save_file_content`.

## Residual / follow-up (non-blocking for Shell Ready if assets 200)

| Finding | Severity | Notes |
|---------|----------|-------|
| Full Phase 17 BM self-test cascade still serial after Shell Ready | Low | Intentional host certification; does not block Shell Ready signal |
| `/v2/` vs `/v2/index.html` | Low | Mitigated by `DirectoryIndex`; prefer explicit `index.html` in docs/manifest |
| Large `index.mjs` (~578KB) via text upload | Low | Prefer monitor first deploy; switch to binary if Fileman truncates |
| Stale SW clients | Low | New cache id `rateb-offline-v2-host-pz`; old `rateb-offline-v2-host-*` deleted on activate |
| Offline V1 PWA vs Offline V2 host confusion (`/admin` blank) | Info | Different products; V1 not in Phase Z scope |

## Out of scope (architecture freeze)

- Redesigning SQLite Runtime / HCI / Identity / Sync / BM modules
- New ERP features
- Offline V1 changes

## Category B

None opened by Phase Z.
