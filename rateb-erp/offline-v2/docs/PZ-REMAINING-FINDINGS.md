# Phase Z — Remaining Findings

## Resolved by this phase

- Missing `public/v2/vendor/sqlite/*` on production (gitignore + commit + deploy).
- SW `cache.addAll` atomic failure on 404 / `./` directory URL.
- 20-second `whenDbReady` blind wait.
- Risk of WASM corruption if uploaded as text via Fileman `save_file_content`.

## Residual / follow-up (non-blocking for Shell Ready)

| Finding | Severity | Notes |
|---------|----------|-------|
| Sync / Module SDK / BM-framework host self-tests FAIL on some fresh profiles | Low | Shell Ready + SQLite + SW already PASS; not a startup blocker |
| PM self-test fails offline (`pm_cannot_stage_active_slot`) | Low | Expected without network staging; Shell Ready allowed when PM API present |
| Full Phase 17 BM self-test cascade still serial after Shell Ready | Low | Intentional host certification |
| Stale SW clients | Low | Cache id `rateb-offline-v2-host-pz`; old `rateb-offline-v2-host-*` deleted on activate |
| Offline V1 PWA vs Offline V2 host confusion (`/admin` blank) | Info | Different products; V1 not in Phase Z scope |

## Out of scope (architecture freeze)

- Redesigning SQLite Runtime / HCI / Identity / Sync / BM modules
- New ERP features
- Offline V1 changes

## Category B

None opened by Phase Z.

## Enterprise gate

**PASS** — production Shell Ready 1828 ms; offline Shell Ready 659 ms (`af09d26f`).
