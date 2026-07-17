# Phase 2.1 — Extraction Abort and Rollback

**Status:** ABORTED · RUNTIME ROLLED BACK · REMOTE DUPLICATE PURGE BLOCKED
**Date:** 2026-07-17
**Binding:** ADR-AL-1, ADR-AL-2, AF-2.1

## STOP gate

The authorized move completed locally and passed per-move local regression.
After production deployment, the mandatory production regression failed:

```text
sync_registered_before_writers: FAIL
mfg_activate_via_hash_mfg: FAIL
identity_runSelfTest: FAIL (identity_api_missing)
```

Production report:

```text
tools/boot-bench/reports/phase-px4-regression-1784315425841.json
```

This is a runtime behavior change. The Phase 2.1 STOP rule therefore triggered.

## Rollback

Commit `1b88339d` is reverted in full:

- V2 Runtime/EventBus/Service Locator implementation restored.
- V2 SQLite, migrations, and vendor implementation restored.
- V2 Identity implementation restored.
- V2 Sync/Queue implementation restored.
- V2 loader, bootstrap, and Service Worker wiring restored.
- Admin-owned extraction files removed from the repository.
- Production fast deploy attempts to purge the aborted shared files because it
  cannot infer repository deletions.

Production runtime behavior is restored. The post-rollback PX4 report passes:

```text
tools/boot-bench/reports/phase-px4-regression-1784316018510.json
```

### Remaining production blocker

The file-manager unlink operation did not remove the deployed extraction copies.
Cache-busted, `no-cache` requests still return HTTP 200 for:

```text
public/assets/offline/shared/runtime/runtime.js
public/assets/offline/shared/db/sqlite-runtime.js
public/assets/offline/shared/vendor/sqlite/index.mjs
public/assets/offline/shared/identity/identity-module.js
public/assets/offline/shared/sync/sync-engine.js
```

These are no longer repository-owned or referenced by V2, but their physical
production copies remain. This is a duplicate-ownership deployment artifact, so
the STOP gate remains active until an authenticated server-side deletion is
verified.

## Ownership after rollback

The pre-Phase-2.1 ownership state is restored:

```text
public/v2 = temporary canonical infrastructure owner pending a safer extraction
Admin shared extraction package = absent from repository; stale production copies remain
Offline V1 = unchanged
Online ERP = unchanged Authentication Authority
```

This does not change ADR-AL-2's required end state. It records that the first
physical extraction attempt did not satisfy the zero-behavior-change gate.

## Regression evidence

Local per-move checks passed before deployment:

- Runtime/EventBus/Service Locator offline bootstrap: PASS
- SQLite offline bootstrap: PASS
- Identity offline bootstrap: PASS
- Sync/Queue offline bootstrap: PASS
- Local PX4 architecture regression: PASS

Production PX4 failed after extraction, so local passes are insufficient to
authorize the move.

After rollback deployment, production regression passed. Closure remains
blocked only by the verified stale production copies.

## Next attempt requirements

No further extraction is authorized by this report. A new authorization must:

1. reproduce the production-only load failure before moving files;
2. prove cross-scope Service Worker/cache behavior on the production origin;
3. retain one canonical owner without relying on unverified forwarding;
4. pass cold online, warm, and immediate-offline production regression before
   declaring ownership transferred;
5. prove that old production owner files are deleted, not merely removed from
   Git.
