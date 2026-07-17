# Phase 2.1 — Shared Infrastructure Extraction Report

**Status:** COMPLETE
**Date:** 2026-07-17
**Binding:** [ADR-AL-1](./ADR-AL-1-SINGLE-ERP-FRONTEND.md), [ADR-AL-2](./ADR-AL-2-SHARED-INFRASTRUCTURE-OWNERSHIP.md)
**Predecessor:** [Phase 2 analysis](./PHASE-2-SHARED-INFRASTRUCTURE-EXTRACTION.md)
**Identity boundary:** [AF-2.1](./AF-2.1-SECURITY-BOUNDARY.md)

---

## Result

The seven authorized shared infrastructure components now have exactly one
canonical implementation under the Admin-owned package:

```text
rateb-erp/public/assets/offline/shared/
```

`public/v2` consumes the Admin-owned implementations. Its former implementation
paths contain compatibility stubs only.

No Admin layout, Admin UI, route, BusinessModule behavior, Identity behavior,
Sync behavior, Queue behavior, SQLite logic, API, or database schema was changed.

---

## 1. Moved files

### Required order 1–3: Runtime, EventBus, Service Locator

These three components are one atomic implementation and were moved together:

```text
FROM public/v2/js/runtime/runtime.js
TO   public/assets/offline/shared/runtime/runtime.js
```

The canonical body is byte-identical to the pre-extraction Git blob:

```text
6665caff6dec9372d8a4c144bcf2e89d1ea4ede6
```

### Required order 4: SQLite

```text
FROM public/v2/js/db/sqlite-runtime.js
TO   public/assets/offline/shared/db/sqlite-runtime.js

FROM public/v2/js/db/migrations.js
TO   public/assets/offline/shared/db/migrations.js

FROM public/v2/vendor/sqlite/*
TO   public/assets/offline/shared/vendor/sqlite/*
```

Only the two relative vendor import URLs in `sqlite-runtime.js` changed:

```text
../../vendor/sqlite/*  →  ../vendor/sqlite/*
```

The runtime logic, API, migration list, schema target, SQL, OPFS path, fallback,
and persistence behavior are unchanged. `migrations.js` is byte-identical:

```text
841321c645ee5e6e16706ab593d5f2e2b8abe4e1
```

### Required order 5: Identity

```text
FROM public/v2/js/business/identity-module.js
TO   public/assets/offline/shared/identity/identity-module.js
```

The canonical body is byte-identical:

```text
ac0d0284955454551c487aaf7c77f50137f956eb
```

AF-2.1 `assertNoSecrets`, `module.identity.*` published APIs, storage classes,
unlock metadata, enrollment bridge, and Online ERP Authentication Authority
boundary are unchanged.

### Required order 6–7: Sync, Queue

Queue is an internal Sync component (`sync_outbox` / `sync_inbox`), so both moved
atomically:

```text
FROM public/v2/js/sync/sync-engine.js
TO   public/assets/offline/shared/sync/sync-engine.js
```

The canonical body is byte-identical:

```text
be9c513b6707f2212bf764970f293562cb490fa6
```

Enqueue, retry, backoff, checkpoint, conflict, push/pull, and queue schemas are
unchanged.

---

## 2. Compatibility stubs

The following V2 paths now contain only non-canonical forwarding stubs:

| Former implementation path | Canonical target |
|----------------------------|------------------|
| `public/v2/js/runtime/runtime.js` | `assets/offline/shared/runtime/runtime.js` |
| `public/v2/js/db/sqlite-runtime.js` | `assets/offline/shared/db/sqlite-runtime.js` |
| `public/v2/js/db/migrations.js` | `assets/offline/shared/db/migrations.js` |
| `public/v2/js/business/identity-module.js` | `assets/offline/shared/identity/identity-module.js` |
| `public/v2/js/sync/sync-engine.js` | `assets/offline/shared/sync/sync-engine.js` |

The active V2 boot path loads canonical Admin-owned files directly; stubs exist
only for temporary path compatibility. SQLite stubs are ES-module re-exports.
Classic-script stubs inject the canonical script and are not used by the active
loader.

No implementation signatures (`createEventBus`, `createServiceLocator`,
`RUNTIME_VERSION`, `IDENTITY_VERSION`, `assertNoSecrets`, `SYNC_VERSION`,
`createSync`, `DB_API_VERSION`, `MIGRATIONS`) remain under `public/v2`.

---

## 3. Updated wiring

### Loader/bootstrap

- `public/v2/index.html` loads canonical Runtime directly.
- `public/v2/js/boot.js` imports canonical SQLite directly.
- `boot.js` loads canonical Identity and Sync directly.
- SQLite vendor probe points to the Admin-owned vendor package.
- Runtime service registration and public global names remain unchanged.

### Service Worker

`public/v2/sw.js`:

- precaches canonical Admin-owned Runtime, SQLite, vendor, Identity, and Sync;
- retains compatibility stubs in precache;
- permits controlled V2 clients to read `/assets/offline/shared/` from cache;
- keeps Admin routes and Offline V1 behavior excluded;
- advances the atomic bootstrap cache to
  `rateb-offline-v2-bootstrap-v3`.

`public/v2/js/boot.js` verifies that same cache identifier.

### Production deletion

Fast deploy cannot infer deleted repository files. The deployment core now
explicitly removes the former `public/v2/vendor/sqlite/*` files from production.
Former JavaScript owner paths are overwritten by their stubs.

---

## 4. Remaining references

### Runtime references (expected)

- V2 consumers still call the unchanged globals:
  `RatebOfflineV2Runtime`, `.events`, and `.services`.
- Active source paths in `index.html`, `boot.js`, and `sw.js` point to
  `assets/offline/shared`.

### Former path references (expected only)

- Service Worker entries for the five compatibility stubs.
- Stub comments and forwarding URLs.
- Historical evidence/charter documents that describe the pre-extraction state.

### Former vendor path

- No active code or precache reference points to `public/v2/vendor/sqlite`.
- Production purge removes the old vendor files.

### Duplicate ownership check

Exactly one source body was found for each extracted component, all under
`public/assets/offline/shared`. Offline V1 remains a separate certified system
under `offline/client` / generated `public/assets/offline/modules`; it was not
modified or merged.

---

## 5. Dependency graph

```text
public/v2/index.html
  ├─ public/v2/js/hci.js                         (not extracted; existing dependency)
  └─ Admin shared/runtime/runtime.js
       ├─ EventBus                               (same canonical Runtime body)
       ├─ Service Locator                        (same canonical Runtime body)
       └─ unchanged RatebOfflineV2Runtime API

public/v2/js/boot.js
  ├─ Admin shared/db/sqlite-runtime.js
  │    ├─ Admin shared/db/migrations.js
  │    └─ Admin shared/vendor/sqlite/*
  ├─ Admin shared/sync/sync-engine.js
  │    └─ Queue (sync_outbox / sync_inbox)
  └─ Admin shared/identity/identity-module.js
       └─ module.identity.* only (AF-2.1)

public/v2/sw.js
  └─ precaches Admin shared canonical files + V2 compatibility stubs
```

Ownership:

```text
Admin shared package = canonical implementation owner
public/v2            = temporary consumer + compatibility stubs
Offline V1           = preserved, separate, unchanged
Online ERP           = Authentication Authority, unchanged
```

---

## 6. Regression results

### Per-move gates

| Move gate | Regression | Result |
|-----------|------------|--------|
| Runtime + EventBus + Service Locator | Fresh install → immediate offline boot | PASS |
| SQLite + migrations + vendor | Fresh install → immediate offline DB/bootstrap | PASS |
| Identity | Fresh install → immediate offline Identity/module bootstrap | PASS |
| Sync + Queue | Fresh install → immediate offline Sync/module bootstrap | PASS |

Reports:

```text
tools/boot-bench/reports/offline-bootstrap-regression-1784314906499.json
tools/boot-bench/reports/offline-bootstrap-regression-1784314957033.json
tools/boot-bench/reports/offline-bootstrap-regression-1784314992986.json
tools/boot-bench/reports/offline-bootstrap-regression-1784315023588.json
```

### Full architecture regression

```text
tools/boot-bench/reports/phase-px4-regression-1784315125522.json
```

Result: **PASS**

Verified:

- Sync registered before BusinessModule writers.
- Queue enqueue works through unchanged Sync API.
- Identity self-test and fresh published session/RBAC behavior pass.
- Identity lock/unlock boundary remains intact.
- Existing module activation and ownership-prefix guards pass.

### Static gates

- JavaScript syntax (classic scripts): PASS.
- Python deploy script compilation: PASS.
- Git whitespace/error check: PASS.
- Duplicate implementation signature scan: PASS.
- Runtime, Identity, Sync, and migrations Git-blob equality: PASS.

---

## 7. Constraint compliance

| Constraint | Result |
|------------|--------|
| UI / Admin layout changes | None |
| Route changes | None |
| Feature / BusinessModule changes | None |
| Identity / Sync / Queue behavior changes | None |
| SQLite logic / schema changes | None |
| API changes | None |
| Offline V1 changes | None |
| Online ERP changes | None |
| Canonical owners | Exactly one per extracted component |
| `public/v2` implementation ownership | None for extracted components |

Phase 2.1 completes the authorized extraction without triggering the STOP gate.
