# Phase 2.1 — Extraction Abort and Rollback

**Status:** ABORTED · ROLLED BACK
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
- Production fast deploy explicitly purges the aborted shared files because it
  cannot infer repository deletions.

## Ownership after rollback

The pre-Phase-2.1 ownership state is restored:

```text
public/v2 = temporary canonical infrastructure owner pending a safer extraction
Admin shared extraction package = absent
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

After rollback deployment, production regression must pass before closure.

## Next attempt requirements

No further extraction is authorized by this report. A new authorization must:

1. reproduce the production-only load failure before moving files;
2. prove cross-scope Service Worker/cache behavior on the production origin;
3. retain one canonical owner without relying on unverified forwarding;
4. pass cold online, warm, and immediate-offline production regression before
   declaring ownership transferred.
