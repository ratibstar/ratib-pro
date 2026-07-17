# Phase PX2 — Cold Module Performance Audit

**Scope:** Audit only. No fixes, redesign, or business-logic changes.  
**Architecture Freeze:** ACTIVE  
**Measured:** 2026-07-17 against production commit `9c883c60`

## Identity boundary

- Online ERP remains the only Authentication Authority.
- Identity remains a local cache of sealed identity, claims, RBAC snapshot,
  device trust, and unlock metadata only.
- No passwords, password hashes, cookies, bearer tokens, JWTs, TOTP secrets,
  WebAuthn server credentials, API tokens, or authentication secrets were
  collected or introduced.
- BusinessModules continue to use published `module.identity.*` APIs only on
  the normal route path.
- The profiler records normalized SQL text only; bind values are deliberately
  excluded.

## Architecture Conflict — Category B (report only)

Lifecycle mapping found two Identity-boundary conflicts. They are **not**
performance root causes and were **not** fixed in PX2.

1. **Extra Identity entity classes.** `identity-module.js` stores
   `identity.config`, `identity.local_session`, and `identity.security_meta`
   in addition to the five allowed classes (sealed identity, claims, RBAC
   snapshot, device trust, unlock metadata). No credential/secret storage was
   observed; Online ERP remains Authentication Authority. Under the active
   “Identity may store ONLY” rule, the additional entity classes are Category B.
2. **Direct Identity SQL from Inventory helper.**
   `InventoryStore.assertNoIdentityTouch()` executes
   `SELECT … WHERE entity_type LIKE 'identity.%'`. That is direct Identity
   storage access. It is not on the normal cold/warm route path, but it
   violates the published-API-only rule for BusinessModules.

Normal BusinessModule route code otherwise consumes Identity only through
`module.identity.*` services.

## Executive finding

The first-open delay is real, but the target module's code is not the dominant
cost.

Cold module entry pays this shared chain:

```text
Shell → idle handoff → PM/Sync/SDK/Framework → SQLite WASM/open
      → Identity dependency → optional Inventory dependency → target module
      → first Identity reads → route render
```

The second navigation reuses all of the following:

- open SQLite connection and initialized WASM runtime;
- Business Framework and SDK host;
- active module instances;
- each module's `_store` repository object;
- registered Runtime services;
- registered routes and route-handler objects;
- handler `__inited` state;
- contribution indexes and navigation entries;
- event subscriptions;
- Identity in-memory unlock state;
- CRM/HR sequence counters and Accounting unsubscribe handles.

Therefore warm navigation only unmounts the previous route and remounts an
existing handler.

## Method

Tool: `rateb-erp/tools/boot-bench/phase-px2-cold-module-audit.js`

For each module:

1. Create an isolated persistent Chromium profile.
2. Open Home once to install/warm the Offline V2 host and Service Worker.
3. Open a fresh page directly to the module route.
4. Instrument HCI, Runtime, Router, Shell, PM, SQLite, SDK, Business Framework,
   module lifecycle, render handlers, events, and normalized SQL in memory.
5. Capture a Chrome DevTools Protocol CPU profile and precise JS coverage.
6. Navigate to Home and back to the same module without disposing it.
7. Measure the second navigation.

No production asset was modified. The instrumentation exists only in the audit
browser page.

These are single instrumented production samples, not p50/p95 statistics.
CDP CPU sampling and lifecycle wrappers add observer overhead, so absolute
numbers should be treated as bounded audit evidence; cold/warm ratios, ordering,
SQL counts, lifecycle counts, and root-cause attribution are the stronger
signals.

`reference-module.js` is excluded because it is a framework fixture, not an ERP
BusinessModule. All eight production Offline V2 modules were audited.

## Cold versus warm

| Module | Cold navigation | Shell → route | Warm navigation | V2 CPU self-time | Cold SQL | Warm SQL |
|---|---:|---:|---:|---:|---:|---:|
| Identity | 459.6 ms | 306.1 ms | 0.9 ms | 99.4 ms | 2 | 2 |
| Inventory | 324.1 ms | 197.9 ms | 0.3 ms | 67.1 ms | 3 | 0 |
| Procurement | 303.8 ms | 183.1 ms | 0.3 ms | 75.3 ms | 3 | 0 |
| Sales | 439.2 ms | 241.7 ms | 0.2 ms | 80.0 ms | 3 | 0 |
| Accounting | 317.5 ms | 199.4 ms | 0.2 ms | 72.1 ms | 3 | 0 |
| CRM | 341.9 ms | 216.7 ms | 0.2 ms | 78.8 ms | 3 | 0 |
| HR | 335.4 ms | 208.9 ms | 0.2 ms | 76.3 ms | 3 | 0 |
| Manufacturing | **never ready** | — | route absent | 75.3 ms before failure | 0 | 0 |

The cold/warm gap is 360×–2,196× for successful routes. The ratio is large
because warm mounts are sub-millisecond, not because cold module rendering is
hundreds of milliseconds.

## Per-module timelines

Intervals below are measured from navigation start. “SQLite” is the interval
between platform scripts being ready and DB Ready. “Activation” includes
dependency registration/activation, first Identity reads, and route render.

| Module | Shell Ready | Idle | Platform scripts | SQLite | Activation/render | Route Ready |
|---|---:|---:|---:|---:|---:|---:|
| Identity | 153.5 | 7.5 | 25.7 | 245.9 | 27.0 | 459.6 |
| Inventory | 126.2 | 7.9 | 17.6 | 150.4 | 22.0 | 324.1 |
| Procurement | 120.7 | 5.9 | 18.1 | 133.4 | 25.7 | 303.8 |
| Sales | 197.5 | 3.6 | 25.0 | 182.7 | 30.4 | 439.2 |
| Accounting | 118.1 | 6.0 | 19.5 | 144.1 | 29.8 | 317.5 |
| CRM | 125.2 | 2.9 | 23.3 | 166.1 | 24.4 | 341.9 |
| HR | 126.5 | 15.5 | 19.1 | 147.8 | 26.5 | 335.4 |
| Manufacturing | 131.6 | 7.4 | 20.1 | 152.9 | fails before target initialize | — |

## Representative flame graph — Inventory

```text
0 ─────────────────────────────────────────────────────────────── 324 ms

Shell / Runtime / Router     [==========================]          0–126
Idle handoff                                           [==]      126–134
PM + Sync + SDK + BF                                  [====]     134–152
SQLite WASM/open                                           [==============================] 152–302
Identity + Inventory activation + render                                  [====] 302–324
Warm second navigation                                                           | 0.3 ms
```

SQLite/open is approximately 150 ms of Inventory's 198 ms post-shell cold
delay. The target module lifecycle and render are only a few milliseconds.

## Layer breakdown

### Router

- Cold deep links cannot be resolved during initial `router.init()` because the
  module route is not registered yet.
- Router first mounts the builtin fallback route, then boot activates module
  dependencies and navigates again.
- Successful modules therefore pay two route transitions.
- Warm navigation reuses the registered handler and skips handler `init`.

### Identity activation

- Every module declares Identity as a mandatory dependency.
- Identity constructs one `IdentityStore`, exposes nine services, subscribes to
  `sync:enqueued`, mounts contributions, and emits `identity:ready`.
- Identity activation itself is below 2 ms.
- The route subsequently reads session/claims/RBAC. These are the first SQL
  calls seen for every successful non-Identity module.
- The Identity boundary remains correct: no credential authority or credential
  storage was observed.

### SQLite open

- Largest shared cold cost: 133–246 ms in these runs.
- Cold initialization loads/initializes SQLite WASM, opens `hci-persist`, reads
  persisted bytes, and ensures schema state.
- Source inspection of `sqlite-runtime.js` shows the open path can also perform
  two full database export/persist checkpoints (migration/open and install-
  pointer sync). Cost grows with `ratib.sqlite` size even when no schema
  migration is needed.
- `db.open()` is invoked repeatedly:
  - boot background open;
  - Identity `_ensureStore`;
  - Inventory `_ensureStore` when required;
  - target module `_ensureStore`.
- The existing opening/open latch prevents duplicate physical opens, but the
  Promise/API checks are still repeated.
- Warm navigation does not reopen SQLite.

### First SQL

Captured query shape:

```sql
SELECT payload_json
FROM entity_row
WHERE entity_type = ? AND entity_id = ?
```

- Identity route: two reads (session and claims), 2.6 ms total cold and 0.5 ms
  warm.
- Other successful routes: three reads (session, claims, RBAC), 2.7–4.2 ms
  total cold.
- Bind values were intentionally not collected. These are repeated query
  shapes, but source inspection confirms they represent distinct Identity
  records; they are not proof of duplicate-row reads.
- Because profiles are not enrolled, each target route stops at the Identity
  gate. No target-module list/timeline query executes in this evidence.
- Source mapping shows that an enrolled warm revisit would still re-run route
  `mount` SQL (Identity reads plus the module list/report query). Warm is
  instant in this audit because activation/store/handler/`init` survive and
  the Identity gate fails before module SQL — not because mount SQL is
  memoized.

### Repository and cache creation

- Each production module creates exactly one lightweight store object and
  assigns it to `this._store`.
- `_ensureStore()` returns the same object on subsequent calls.
- No per-navigation repository recreation was observed.
- No explicit per-module cache construction (`Map`, cache index, or equivalent)
  was found on the route path.
- The major “cache” effect is lifecycle persistence: SDK registry, module
  instance, store, service locator, route handler, and SQLite state all survive.

### Services

Service closures registered on cold activation:

| Target | Target services | Dependency-path total |
|---|---:|---:|
| Identity | 9 | 9 |
| Inventory | 9 | 18 |
| Procurement | 15 | 33 |
| Sales | 13 | 31 |
| Accounting | 28 | 46 |
| CRM | 25 | 34 |
| HR | 33 | 42 |
| Manufacturing | 23 | expected 41 |

Registration is CPU-cheap (module `onInitialize` under 1 ms each), but it
creates many persistent closures and triggers repeated contribution/nav
indexing.

### Render

- Cold route-handler render: 2.5–3.9 ms.
- Warm render: 0–0.5 ms.
- Rendering is not the cold bottleneck.
- The visible result for Inventory, Procurement, Sales, Accounting, CRM, and HR
  is currently an Identity enrollment error, not a populated module screen.

### Event subscriptions

- Identity adds one persistent `sync:enqueued` observer.
- Accounting adds three persistent domain observers:
  `inventory:movement`, `sales:invoice_posted`, and
  `procurement:grn_posted`.
- Other production modules add no persistent lifecycle subscription on the
  normal path.
- Self-test-only event listeners were excluded.

### Timeline

- CRM, HR, and Manufacturing expose append-only timeline APIs.
- Timeline repositories/queries are not used during cold route activation.
- Timeline contributes service closures only, not measurable cold I/O.

### Diagnostics and health

- `getDiagnostics()` is not called during normal cold navigation.
- Each activated module calls lightweight `reportHealth()` during initialize,
  mount, and activate.
- Health bookkeeping is sub-millisecond per module.
- Runtime's platform health/`verifyLayout`, not module diagnostics, is the
  significant health-related startup cost.

### Sync hooks

- Sync Engine script loads with PM/SDK/Framework background scripts.
- The target modules do not start a pull/push during route activation.
- Identity's observer and Accounting's domain listeners are the only normal
  cold-path hooks found.
- No credential material is enqueued or synchronized.

## Exact root cause by module

### Identity

Cold 459.6 ms; warm 0.9 ms.

- 245.9 ms SQLite interval dominates.
- Creates and retains `IdentityStore`.
- Registers nine published `module.identity.*` services.
- Adds one Sync event subscription.
- Route performs two Identity reads and renders in 3.4 ms.
- Warm still performs two fast reads; all platform/store state survives.

### Inventory

Cold 324.1 ms; warm 0.3 ms.

- 150.4 ms SQLite interval dominates.
- Serial dependency activation: Identity → Inventory.
- Repeated `db.open()` checks: boot + Identity store + Inventory store.
- Creates and retains two store objects and 18 service closures.
- Route performs three Identity reads, then stops at
  `inv_identity_not_enrolled`; Inventory valuation SQL does not run.
- Warm reuses handler/store/services and fails the cached Identity gate
  immediately.

### Procurement

Cold 303.8 ms; warm 0.3 ms.

- 133.4 ms SQLite interval dominates.
- Serial activation: Identity → Inventory → Procurement.
- Four `db.open()` calls reach the same open DB API.
- Creates three stores and 33 service closures.
- Procurement list SQL is not reached because Identity context is absent.
- Warm retains all dependencies and route registration.

### Sales

Cold 439.2 ms; warm 0.2 ms.

- 182.7 ms SQLite plus a slower 197.5 ms shell run dominate this sample.
- Serial activation: Identity → Inventory → Sales.
- Creates three stores and 31 service closures.
- Three Identity reads precede the `sales_identity_not_enrolled` result.
- Sales-order SQL and inventory hooks are not on this cold route.

### Accounting

Cold 317.5 ms; warm 0.2 ms.

- 144.1 ms SQLite interval dominates.
- Serial activation: Identity → Inventory → Accounting.
- Creates three stores and 46 service closures (largest successful path).
- `onActivate` also binds three persistent domain event subscriptions.
- Trial-balance SQL is not reached because Identity context is absent.
- Warm retains `_unsubs`, store, services, and handler.

### CRM

Cold 341.9 ms; warm 0.2 ms.

- 166.1 ms SQLite interval dominates.
- Serial activation: Identity → CRM.
- Creates two stores and 34 service closures.
- Three Identity reads precede `crm_identity_not_enrolled`.
- Timeline and pipeline queries are not executed on this path.
- `_leadSeq`, `_oppSeq`, store, routes, and services survive.

### HR

Cold 335.4 ms; warm 0.2 ms.

- 147.8 ms SQLite interval dominates.
- Serial activation: Identity → HR.
- Creates two stores and 42 service closures.
- Three Identity reads precede `hr_identity_not_enrolled`.
- Employee/timeline SQL is not reached.
- `_empSeq`, store, routes, and services survive.

### Manufacturing

Cold route never becomes ready.

- Boot recognizes path segment `manufacturing`.
- The module publishes metadata ID `mfg` and route `/mfg`.
- Boot registers the instance as `mfg`, then calls framework
  `activate('manufacturing')`.
- Activation fails with “not registered” before Manufacturing
  `onInitialize`, store creation, services, route registration, SQL, or render.
- Dependencies and SQLite still initialize, so the user pays most cold cost and
  remains on Home.
- This is a functional ID/route mismatch, not merely a performance outlier.
- It is reported only; Phase PX2 does not fix it.

## Duplicate initialization

Measured per cold path:

- `Runtime.start()` called three times through Shell, Business Framework, and
  SDK. Later calls are idempotent, but the Promise/API path repeats.
- `HCI.ensureLayout()` observed twice.
- `db.open()` observed:
  - Identity: 2 calls;
  - Inventory/CRM/HR: 3 calls;
  - Procurement/Sales/Accounting: 4 calls.
- SDK `load()` repeats once per dependency in strict serial order.
- `shell.renderNav()` runs 5–16 times as each dependency contributes routes/UI
  and navigation events fire.
- Runtime event-bus `emit()` runs 37–66 times per cold activation chain.

The repeated calls are mostly guarded/idempotent, but they add orchestration,
microtasks, contribution scans, and DOM nav rebuilds.

## TOP bottlenecks

1. Manufacturing ID/route mismatch — route never becomes ready.
2. SQLite WASM/open/hci-persist interval — 133–246 ms.
3. Shell prerequisite (Runtime + Router) — 118–198 ms before module work.
4. Serial dependency activation through SDK `load`.
5. HCI `ensureLayout()` repeated twice.
6. Runtime `start()` invoked three times.
7. Cold SQLite WASM CPU (`index.mjs` + `sqlite3.wasm`) — top CPU frames.
8. Fallback builtin route first, requested module route second.
9. PM/Sync/SDK/Business Framework script load — 18–26 ms.
10. `db.open()` checks repeated 2–4 times.
11. Runtime `verifyLayout` health walk.
12. Runtime package load/hash on Shell path.
13. Repeated Identity session/claims/RBAC query shape.
14. `shell.renderNav()` repeated 5–16 times.
15. 18–46 service closures registered per dependency path.
16. 37–66 Runtime event-bus emissions.
17. One store object created per activated dependency.
18. Identity Sync observer and Accounting's three domain observers.
19. Module health bookkeeping at initialize/mount/activate.
20. Target render (2.5–3.9 ms) — measurable but not material.

## Prioritized optimization plan (recommendations only)

### P0 — Correctness prerequisite

1. Unify Manufacturing selector, module ID, and route contract
   (`manufacturing` versus `mfg`/`/mfg`) in a future approved fix.

### P1 — Remove shared cold tax

2. Keep SQLite/WASM initialized for the installed session before the first
   module click, or start it during idle after Shell.
3. Make Runtime/SDK/Framework start single-flight at the orchestration level,
   not merely idempotent inside each layer.
4. Memoize HCI layout readiness and remove duplicate warm verification from
   route activation.

### P2 — True route-level lazy activation

5. Give Router a module-loader boundary so one click can load/register the
   requested module without host reload or fallback-route flash.
6. Register route metadata before activating repositories/services; activate
   expensive dependencies behind the route skeleton.
7. Load Identity once for the session and reuse its published services for
   every BusinessModule.

### P3 — Reduce repeated module work

8. Batch service registration and defer non-route services until first use.
9. Batch `renderNav` after the full dependency chain instead of rebuilding for
   each dependency.
10. Cache Identity claims/RBAC snapshot in the Identity module's allowed
    in-memory state after unlock; never store credentials or make Identity an
    Authentication Authority.
11. Keep store instances, route handlers, service registrations, and event
    subscriptions alive across navigation — current warm behavior proves this
    is effective.

### P4 — Render perception

12. Render each module's route skeleton before SQLite/dependency activation.
13. Present a deliberate locked/not-enrolled state rather than an error string.
14. Keep diagnostics, health detail, timeline preload, and Sync work outside the
    first route render.

All recommendations preserve AF 2.1: Online ERP remains the sole Authentication
Authority, Identity stores no credentials/secrets, and BusinessModules continue
to consume only `module.identity.*` APIs.

## Audit conclusion

The cold-load problem is primarily a **shared local platform initialization
problem**, not a per-module SQL, repository, cache, render, diagnostics,
timeline, or Sync problem.

For successful modules:

- SQLite + shell/platform account for most of the 304–460 ms cold time.
- Target lifecycle and render are approximately 22–30 ms after DB Ready.
- Actual SQL is only 2.6–4.2 ms in unenrolled profiles.
- Warm navigation is sub-millisecond because lifecycle objects remain alive.

**Architecture Conflict status:** Category B Identity findings reported above;
no remediation in this phase.

**Phase PX2 status:** COMPLETE — audit only, no fixes implemented.
