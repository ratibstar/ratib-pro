# Phase 2.4 — Shadow Integration (NO FILE MOVES)

**Status:** Documentation only · **Architecture Conflict on live wiring** · Design contract READY  
**Mode:** DO NOT IMPLEMENT · DO NOT REFACTOR · DO NOT MOVE/COPY/RENAME/DELETE · DO NOT create `shared/` · DO NOT touch Service Workers · DO NOT touch Production routing/URLs/builds  
**Binding:** ADR-AL-1, ADR-AL-2, AF-2.1, PX-Deploy, Phase 2.3 PASS  
**Date:** 2026-07-17

---

## Return summary (authoritative)

| Field | Value |
|-------|--------|
| **Readiness score** | **42 / 100** — adapter/interface design complete; **live Admin consumption of V2 is UNSAFE under Phase 2.4 constraints** |
| **Blockers** | See §Blockers (Architecture Conflict) |
| **Dependency graph** | See §Dependency graph |
| **Adapter list** | See §Adapter list |
| **Compatibility matrix** | See §Compatibility matrix |
| **Recommended implementation order** | See §Recommended implementation order — **blocked until Architecture Conflict cleared** |

---

## Architecture Conflict (STOP)

**STOP — live shadow integration cannot proceed without violating Phase 2.4 rules.**

To make Admin consume V2 Runtime / SQLite / Identity / Sync *today*, without extracting files, Admin would have to:

1. **Load scripts from `/rateb-erp/public/v2/*`**, which violates Task 5 (“No direct import from /public/v2”), **or**
2. **Copy/create Admin-owned paths** (`shared/` or equivalent), which this phase explicitly forbids.

Therefore:

- **Design / contract validation:** ALLOWED (this document).
- **Runtime wiring in Admin / Production:** **FORBIDDEN until a later authorized phase** that either (a) extracts to Admin-owned URLs (Phase 2.5+) or (b) formally approves a temporary migration import path via ADR (not present).

No unsafe dependency was “integrated.” Conflict is reported; extraction/implementation remains stopped.

---

## Readiness score

**42 / 100**

| Bucket | Score | Notes |
|--------|------:|-------|
| Published API surfaces exist (Runtime, DB, Identity, Sync) | +25 | Stable globals + `module.identity.*` services |
| Single ownership today (no duplicate V2 infra) | +20 | Phase 2.3 PASS |
| Adapter contracts fully specified | +20 | See §Adapter list / §Interfaces |
| Live Admin→V2 without `/v2` import or file move | **0** | Impossible under Phase 2.4 constraints → Architecture Conflict |
| Safe coexistence with Offline V1 without second Runtime/Queue | −10 | Dual-stack collision risk if both engines start |
| Hidden deps (HCI, Business FW, relative WASM imports) documented | +7 | Not optional; must be in next phase scope |
| SW / URL / production routing unchanged (honored) | +10 | Constraint compliance |

---

## Blockers

| ID | Severity | Blocker | Why unsafe |
|----|----------|---------|------------|
| **B1** | **STOP** | No legal load path for Admin → V2 under Phase 2.4 | Scripting `/public/v2/...` = direct import (Task 5). Creating `shared/` = forbidden. No third path. |
| **B2** | **STOP** | SQLite vendor resolution is path-relative to `public/v2` | `sqlite-runtime.js` imports `../../vendor/sqlite/index.mjs` via `import.meta.url`. Admin cannot open DB without that URL tree or a copy. |
| **B3** | HIGH | HCI is a hard hidden dependency | Sync throws `sync_hci_missing`; SQLite uses `RatebOfflineV2HCI` for OPFS layout `rateb-offline-v2/`. Not in the six-name list; required for any real consume. |
| **B4** | HIGH | Identity requires BusinessModule framework + Runtime locator | Publishes `module.identity.*` only after `onInitialize` via Business FW `exposeService`. Admin cannot use “published APIs only” without FW + Runtime already live. |
| **B5** | HIGH | Dual offline stacks (V1 IndexedDB vs V2 SQLite) | Admin already boots Offline V1 (`rateb-offline*.js`). Starting V2 Runtime/Sync/Queue inside Admin without a coexistence gate creates **second Runtime / EventBus / ServiceLocator / Queue** risk (Tasks 7–10). |
| **B6** | MED | Admin has no static `/public/admin/*` SPA tree | Production Admin is PHP MVC (`public/index.php` + `views/` + `app/controllers/Admin/*`). Adapters must target Admin-owned package (`assets/offline/...`), not a nonexistent Admin SPA. |
| **B7** | MED | Service Worker isolation | V2 SW scope is `/v2/`; Admin/POS use `pos-sw.js` / `rateb-offline-sw.js`. Shadow load of V2 assets into Admin pages has no precache contract; changing SW is forbidden this phase. |
| **B8** | LOW | Sync soft-couples to V2 host globals | Self-test / verify paths expect `RatebOfflineV2PM`, Router, Shell. Production Sync path needs DB+HCI+Runtime; Admin must not pull UI shell as a hard dep. |

**No Category B (AF-2.1) credential-boundary breach found in current code.** Conflict is structural (load path / dual stack), not secret storage.

---

## Dependency graph

### Current production (as-is — two parallel stacks)

```text
Admin ERP (PHP MVC)                         POS
  public/index.php                            modules/pos/*
  views/layouts/main.php                      pos-sw.js
        │                                           │
        └──────────────────┬────────────────────────┘
                           ▼
              Offline V1 (Admin-owned today)
              assets/offline/rateb-offline(.min).js
              modules/offline-*.js  (IndexedDB, V1 sync queue, V1 auth seal)
```

```text
V2 migration host (temporary infra owner — ADR-AL-2)
  public/v2/index.html → boot.js → sw.js (scope ./ under /v2/)
        │
        ▼
  HCI (js/hci.js)  ←── hidden hard dep
        │
        ▼
  Runtime (runtime.js)
   ├── EventBus          → RatebOfflineV2Runtime.events
   └── Service Locator   → RatebOfflineV2Runtime.services
        │
        ├── SQLite (db/sqlite-runtime.js + migrations + vendor/sqlite/*)
        │     └── relative WASM/OPFS via HCI root rateb-offline-v2/
        ├── Sync + Queue (sync/sync-engine.js)
        │     └── requires DB + HCI + Runtime.events
        └── Identity (business/identity-module.js)
              └── requires Business FW + Runtime + SQLite
              └── publishes module.identity.*
```

### Target shadow model (design only — not wired)

```text
Admin page / Admin-owned package
        │
        │  ONLY through adapters (never import /public/v2/*)
        ▼
  ┌─────────────────────────────────────────┐
  │ AdminCompatGate                         │
  │  - refuse dual V2+V1 engine start       │
  │  - single Runtime / Bus / Locator / Queue│
  └─────────────────────────────────────────┘
        │
        ├── AdminRuntimeAdapter  → Runtime + EventBus + ServiceLocator
        ├── AdminHciAdapter      → HCI (required)
        ├── AdminSqliteAdapter   → RatebOfflineV2DB
        ├── AdminIdentityAdapter → module.identity.* ONLY
        └── AdminSyncAdapter     → Sync instance API (enqueue/start/…)
```

**Edge that must never exist:** `views/` / `assets/offline/modules/*` → direct `.../public/v2/...` script or module URL.

---

## Ownership graph

| Component | Canonical owner today | Admin consumer may… | Forbidden |
|-----------|----------------------|---------------------|-----------|
| Runtime + EventBus + ServiceLocator | `public/v2/js/runtime/runtime.js` | Call via AdminRuntimeAdapter after Admin owns a copy | Second `RatebOfflineV2Runtime` / second bus / second locator |
| HCI | `public/v2/js/hci.js` | Via AdminHciAdapter (must move with Runtime/DB) | Parallel OPFS roots / duplicate HCI |
| SQLite | `public/v2/js/db/*` + `vendor/sqlite` | Via AdminSqliteAdapter | Direct SQL on `identity.*`; second DB open of same vault without gate |
| Identity | `public/v2/js/business/identity-module.js` | Via `module.identity.*` only | Direct vault / identity SQL / credential store |
| Sync + Queue | `public/v2/js/sync/sync-engine.js` | Via AdminSyncAdapter on **one** Sync instance | Second Sync/Queue; credential sync |
| Offline V1 | `public/assets/offline/*` | Continue as production Admin offline until cutover ADR | Merge V1+V2 engines in one page without CompatGate |
| ERP UI | Admin PHP MVC | Unchanged | Second ERP frontend under `public/v2` |

---

## Runtime initialization graph (V2 — required order)

```text
1. HCI available (RatebOfflineV2HCI)
2. Runtime script → single RatebOfflineV2Runtime (events + services)
3. Runtime.start() / health (as needed)
4. SQLite module import → open() → register services 'db'
5. Sync factory create() → start() → register services 'sync'  (before writers)
6. Business framework start
7. Identity create → register → activate → expose module.identity.*
8. Only then: Admin adapters may call published surfaces
```

**Admin V1 order today (must not be silently replaced):** layout loads `rateb-offline` / `erp-shell-bootstrap` → V1 IndexedDB offline SDK. Shadow must not start steps 1–7 on Admin pages until CompatGate + legal Admin-owned URLs exist.

---

## Execution order graph (future authorized implementation)

```text
Phase A (this doc)     Design adapters/interfaces ── DONE
Phase B (blocked)      Clear B1: Admin-owned URLs via extraction OR approved ADR import path
Phase C                Deploy U1 Runtime+Bus+Locator (+ HCI) under Admin-owned path
Phase D                Adapters only; Admin loads Admin URLs — never /v2/
Phase E                U2 SQLite → AdminSqliteAdapter
Phase F                U3 Identity → AdminIdentityAdapter (module.identity.*)
Phase G                U4 Sync+Queue → AdminSyncAdapter
Phase H                CompatGate: V1 offline coexistence / cutover policy
```

---

## Hidden dependencies

| Hidden dep | Required by | Risk if ignored |
|------------|-------------|-----------------|
| **HCI** (`RatebOfflineV2HCI`, OPFS layout `rateb-offline-v2/`) | SQLite, Sync, Runtime storage | Immediate hard fail (`sync_hci_missing` / DB open fail) |
| **BusinessModule framework** | Identity `exposeService` → `module.identity.*` | No published Identity APIs without FW |
| **Relative vendor/sqlite ES module graph** | SQLite | Broken without same URL adjacency or import-map (URL change = forbidden here) |
| **migrations.js + schema version** | SQLite / Sync (`REQUIRED_SCHEMA`) | Sync refuse / integrity fail |
| **Single active Sync instance** | Queue semantics | Duplicate Queue if Admin creates second Sync |
| **Online ERP session cookies** | Identity `fetchEnrollmentFromOnline` | Enrollment bridge only; Online remains Authentication Authority |
| **V1 Offline engine still production-critical** | Admin/POS pages | Dual-stack second Runtime/Queue if V2 also started |

---

## Adapter list (required — design only)

| Adapter | Consumes | Exposes to Admin | Must NOT |
|---------|----------|------------------|----------|
| **AdminRuntimeAdapter** | Single `RatebOfflineV2Runtime` | `start`, `shutdown`, `getState`, `events.on/emit`, `services.get/has/list` | Construct second Runtime; reimplement EventBus/Locator |
| **AdminHciAdapter** | Single `RatebOfflineV2HCI` | Layout/quota/persist byte API needed by DB/Sync | Own parallel OPFS app root |
| **AdminSqliteAdapter** | Single `RatebOfflineV2DB` | `open`, `exec`, `integrityCheck`, schema version | Query `identity.*`; open second durable DB for same tenant without policy |
| **AdminIdentityAdapter** | `module.identity.*` services only | `session`, `unlock`, `lock`, `claims`, `rbac`, `device`, `enrollBridge`, `applyEnrollment`, `securityScan` | Touch vault/SQL; store credentials; call Online login/token mint |
| **AdminSyncAdapter** | Single Sync instance from `RatebOfflineV2Sync.create` | `start`, `stop`, `enqueue`, `syncOnce`, `push`, `pull`, `getStatus` | Create second Sync/Queue; enqueue auth secrets |
| **AdminCompatGate** | V1 + V2 presence flags | Allow/deny V2 boot on Admin pages | Allow both engines fully active without ADR |

---

## Required interfaces (contracts)

### IAdminRuntime
- `getState(): string`
- `events: { on, off, emit }`
- `services: { get, has, list, register? }` — Admin modules should prefer `get/has` only
- `start() / shutdown()`

### IAdminSqlite
- `open(): Promise<{ mode, schemaVersion }>`
- `exec(sql, bind?): Promise<…>`
- `integrityCheck(): Promise<{ ok, … }>`

### IAdminIdentity (AF-2.1)
- Only mirrors `module.identity.*` service handles listed in Adapter list
- Explicit deny: passwords, hashes, cookies, bearer, JWT, TOTP, WebAuthn server creds, API tokens

### IAdminSync
- `enqueue(mutation)`, `start(opts)`, `stop()`, `syncOnce()`, `getStatus()`
- Queue is **not** a separate interface — it is Sync.enqueue/outbox

### IAdminCompat
- `canStartV2Infra(): { ok, reason }`
- `assertSingleRuntime()` / `assertSingleQueue()`

---

## Compatibility layers (required)

| Layer | Purpose |
|-------|---------|
| **Global rename façade (optional later)** | Map Admin-stable names (`RatebAdminOffline.*`) → underlying single V2 globals without Admin importing `/v2` paths |
| **V1 coexistence gate** | Keep Offline V1 as Admin production offline until cutover; refuse starting V2 Sync/Runtime on same document without policy |
| **Service name stability** | Admin always calls `module.identity.*` / adapter methods — never `IdentityStore` / entity SQL |
| **Schema compat** | Sync `REQUIRED_SCHEMA` must match AdminSqliteAdapter opened DB |
| **No SW merge layer in 2.4** | V2 SW and Admin/POS SW remain separate; no scope change |

---

## Compatibility matrix

| Consumer \ Provider | V2 Runtime | V2 SQLite | V2 Identity APIs | V2 Sync/Queue | Offline V1 | `/public/v2` URLs |
|---------------------|:----------:|:---------:|:----------------:|:-------------:|:----------:|:-----------------:|
| Admin ERP pages (today) | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ forbidden |
| Admin via adapters + Admin-owned URLs (future) | ✅ | ✅ | ✅ | ✅ | ⚠ gated | ❌ still forbidden |
| Admin via adapters loading `/v2/*` (2.4) | ⛔ Conflict | ⛔ | ⛔ | ⛔ | ✅ | ⛔ Task 5 |
| V2 host `boot.js` | ✅ | ✅ | ✅ | ✅ | ❌ N/A | ✅ (owner) |
| BusinessModules | via Runtime | via services | **only** identity APIs | via Sync | ❌ | N/A |

Legend: ✅ allowed · ❌ not used · ⚠ coexistence policy required · ⛔ Architecture Conflict under Phase 2.4

---

## Rollback plan (for future implementation phases)

1. **Docs-only (this phase):** revert commit containing this document; no production effect.
2. **If adapters are later added under Admin-owned paths:** `git revert` → PX-Deploy orphan deletion removes new files → integrity gate must stay green.
3. **Never rollback by leaving dual copies** (V2 owner + Admin copy both live) — that is ADR-AL-2 duplicate ownership.
4. **Never “rollback” by pointing Admin at `/public/v2`** — reintroduces Task 5 violation.
5. **V1 Offline remains the Admin safety net** until an explicit cutover ADR retires it.

---

## Recommended implementation order

**Blocked until B1–B5 cleared by an authorized extraction (or ADR-approved temporary import path).**

When unblocked, smallest safe order (matches Phase 2.3 atomic units):

1. **AdminCompatGate** (policy only — no second engine)
2. **U1 + HCI** under Admin-owned URL → **AdminRuntimeAdapter** + **AdminHciAdapter**
3. **U2 SQLite** → **AdminSqliteAdapter**
4. **U3 Identity** (+ Business FW as required support, not ERP UI) → **AdminIdentityAdapter** (`module.identity.*` only)
5. **U4 Sync+Queue** → **AdminSyncAdapter** (single instance)
6. PX4 / offline-bootstrap + PX-Deploy integrity after each unit
7. Independent rollback per unit via git revert + PX-Deploy orphan purge

---

## Task checklist (Phase 2.4)

| # | Task | Result |
|---|------|--------|
| 1 | Admin consume Runtime via adapter only | **Design PASS** · Live **BLOCKED (B1)** |
| 2 | Admin consume SQLite via adapter only | **Design PASS** · Live **BLOCKED (B1/B2)** |
| 3 | Admin consume Identity via published APIs only | **Design PASS** · Live **BLOCKED (B1/B4)** |
| 4 | Admin consume Sync via published APIs only | **Design PASS** · Live **BLOCKED (B1/B3)** |
| 5 | No direct import from `/public/v2` | **PASS** (nothing wired) |
| 6 | No duplicate ownership | **PASS** (unchanged) |
| 7–10 | No second Runtime/EventBus/ServiceLocator/Queue | **PASS** (unchanged); would FAIL if live dual-boot attempted |
| 11–20 | Graphs, adapters, interfaces, compat, blockers, rollback | **PASS** (this document) |

---

## Explicit non-actions (honored)

- No file moves / copies / renames / deletes  
- No URL / SW / build / production routing changes  
- No `shared/` created  
- No code generation / implementation / refactor  
- No Production touch beyond documentation commit (docs path; deploy integrity unaffected)
