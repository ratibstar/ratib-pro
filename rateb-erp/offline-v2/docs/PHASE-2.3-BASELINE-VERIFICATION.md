# Phase 2.3 — Baseline Verification (Audit Only)

**Status:** ✅ **PASS** — baseline clean, extraction-ready.
**Mode:** Audit only. No files extracted, moved, copied, deleted, or refactored. No UI/route/SW/Runtime/Identity/Sync/SQLite changes.
**Binding:** ADR-AL-1, ADR-AL-2, AF-2.1, PX-Deploy (permanently binding).
**Verified against:** Git `HEAD` == Production (`https://rateb.sa/rateb-erp/public/*`).

---

## 1. Verdict Matrix

| # | Check | Result | Evidence |
|---|-------|--------|----------|
| 1 | Admin is the only production ERP frontend | ✅ PASS¹ | ERP UI = PHP MVC (`rateb-erp/app/controllers/Admin/*` + `views/`) via `public/index.php`. No second ERP application UI. |
| 2 | `public/v2` not referenced by Admin runtime (except approved migration) | ✅ PASS | Zero references to `public/v2` from PHP/Admin/POS runtime. Matches only in `offline-v2/docs/*` and `tools/boot-bench/*`. |
| 3 | No imports from `assets/offline/shared/*` or removed extraction paths | ✅ PASS | Zero code imports. `assets/offline/shared/*` appears only in 3 phase docs; untracked in Git; HTTP 404 in production. |
| 4 | Offline V1 boots | ✅ PASS² | `assets/offline/rateb-offline.min.js` (200), `erp-shell-bootstrap.js` (200); loaded by `views/layouts/main.php`. |
| 5 | Admin boots | ✅ PASS² | `public/index.php` front controller present + served; ERP routed via rewrite. |
| 6 | POS boots | ✅ PASS² | `pos-sw.js` (200) + `assets/pos/*` present. |
| 7 | Runtime initializes | ✅ PASS² | `v2/js/runtime/runtime.js` (200), single owner, boot wiring intact. |
| 8 | Identity initializes | ✅ PASS² | `v2/js/business/identity-module.js` (200), registered first in boot order. |
| 9 | Sync initializes | ✅ PASS² | `v2/js/sync/sync-engine.js` (200), created + started before writers (PX4 contract). |
| 10 | SQLite initializes | ✅ PASS² | `v2/js/db/sqlite-runtime.js` (200), `whenDbReady` gate + `rateb-v2-db-ready`. |
| 11 | EventBus healthy | ✅ PASS | `createEventBus()` in `runtime.js` → exported as `runtime.events`. Single instance. |
| 12 | Service Locator healthy | ✅ PASS | `createServiceLocator()` in `runtime.js` → exported as `runtime.services`. Single instance. |
| 13 | Queue healthy | ✅ PASS | Outbox/`enqueue` inside `sync-engine.js`. Single instance. |
| 14 | Service Worker healthy | ✅ PASS | `sw.js` (200) cache `rateb-offline-v2-bootstrap-v2` == `boot.js` expected cache. `pos-sw.js` (200). Consistent post-rollback. |
| 15 | Zero orphan production files (PX-Deploy) | ✅ PASS | PX-Deploy integrity gate green on `a645c1db`; `shared/*` → 404. |
| 16 | Zero duplicate ownership | ✅ PASS | Exactly one tracked copy of each infra file, all under `public/v2`. |
| 17 | Git == Production == HTTP | ✅ PASS | Byte-exact SHA-256 match (runtime.js, sw.js, sync-engine.js) + integrity gate. |
| 18 | No hidden dependency on `public/v2` | ✅ PASS | No non-`v2` code path references `v2` (docs/tools only). |
| 19 | No duplicate Runtime | ✅ PASS | 1 × `runtime.js`. |
| 20 | No duplicate Identity | ✅ PASS | 1 × `identity-module.js`. |
| 21 | No duplicate Sync | ✅ PASS | 1 × `sync-engine.js`. |
| 22 | No duplicate SQLite | ✅ PASS | 1 × `sqlite-runtime.js`. |
| 23 | No duplicate Queue | ✅ PASS | 1 × (inside `sync-engine.js`). |
| 24 | No duplicate EventBus | ✅ PASS | 1 × (inside `runtime.js`). |
| 25 | No duplicate Service Locator | ✅ PASS | 1 × (inside `runtime.js`). |

¹ **Note (not a blocker):** There is no static `public/admin/*` tree. The Admin ERP is a server-rendered PHP MVC application (front controller `public/index.php`, `app/controllers/Admin/*`, `views/`). `public/v2` is a bootable **migration / self-test host**, not a production ERP application UI — permitted as a temporary migration layer under ADR-AL-1.

² **Static + prior-evidence PASS.** Files are present, single-owned, byte-identical to Git, and boot wiring is intact. Live runtime boot was last proven by the PX4 / offline-bootstrap regression suite. A fresh PX4 run is recommended as a **pre-flight** immediately before each extraction unit (see Readiness).

---

## 2. Dependency Graph

### Production stacks (two, by design during migration)

```
Admin ERP (PHP MVC)                 POS
  public/index.php                    modules/pos/views/*
  app/controllers/Admin/*             pos-sw.js
  views/layouts/main.php              assets/pos/js/*
        |                                   |
        +---------------+-------------------+
                        v
        V1 Offline Engine (Admin/POS owned)
        assets/offline/rateb-offline(.min).js
        assets/offline/modules/offline-*.js
        (own storage / sync / auth / rbac)
```

```
V2 Migration Host (temporary infra owner — ADR-AL-2)
  public/v2/index.html + boot.js + sw.js
        |
        v
  Runtime (runtime.js)
   ├── EventBus         (createEventBus  → runtime.events)
   └── Service Locator  (createServiceLocator → runtime.services)
        |
        v  registers services: pm, db, sync
  SQLite (db/sqlite-runtime.js, db/migrations.js, vendor/sqlite)
        |
        v
  Identity (business/identity-module.js)   ← boot order: first business module
        |
        v
  Sync (sync/sync-engine.js)
   └── Queue (outbox / enqueue)            ← created + started before writers
        |
        v
  Business modules (inventory, procurement, sales, accounting, crm, hr, mfg)
```

**Boot order (from `boot.js`):** HCI → Runtime → Shell/mount → SW verify → package-manager → SQLite(`db`) → sync-engine + module-sdk + business-framework → Sync.create/start → Identity → business modules.

### Coupling facts (for extraction unit sizing)
- **Runtime + EventBus + Service Locator are one physical module** (`runtime.js`). They cannot be deployed apart without a refactor (refactor is forbidden now) → they extract as **one atomic unit**.
- **Queue lives inside `sync-engine.js`** → Queue extracts **with Sync** as one unit.

---

## 3. Remaining Blockers

**None (zero STOP conditions).** No hidden dependency and no duplicate ownership of the V2 infra components was discovered.

---

## 4. Hidden Dependencies

**None found.**
- No non-`v2` runtime code imports `public/v2`, `assets/offline/shared/*`, or any removed extraction path.
- `assets/offline/shared/*` is untracked in Git and returns HTTP 404 (PX-Deploy removed the aborted-extraction orphans).

---

## 5. Duplicate Ownership Report

**Infra components (target of extraction): single-owner — CLEAN.**

| Component | Owner (single) | Duplicates |
|-----------|----------------|-----------|
| Runtime | `v2/js/runtime/runtime.js` | none |
| EventBus | `v2/js/runtime/runtime.js` | none |
| Service Locator | `v2/js/runtime/runtime.js` | none |
| SQLite runtime | `v2/js/db/sqlite-runtime.js` | none |
| Identity | `v2/js/business/identity-module.js` | none |
| Sync | `v2/js/sync/sync-engine.js` | none |
| Queue | `v2/js/sync/sync-engine.js` | none |

**Known non-blocking duality (pre-existing, by design):** the V1 offline engine (`assets/offline/*`) and the V2 infra provide overlapping offline capabilities (independent storage/sync/identity layers) for two different runtimes. This is the exact condition the extraction+consolidation program exists to resolve; it is **not** a duplicate of the V2 infra abstractions (V1 has no Runtime/EventBus/Service Locator). Per ADR-AL-2, V2 is the **temporary sole owner** of the infra components until extraction lands them in Admin-owned shared libraries.

---

## 6. Readiness Score for Extraction

**Score: 96 / 100 — READY.**

Deductions:
- **−2** Live runtime boot should be re-proven with a **fresh PX4 run** as a pre-flight before each unit (current PASS for checks 4–10 is static + prior-evidence).
- **−2** Unit granularity is constrained by physical co-location: 6 rule-units collapse to **4 atomic units** (Runtime+EventBus+ServiceLocator together; Queue with Sync) because splitting them requires a forbidden refactor.

No blocker, no STOP condition. Extraction may proceed under a fresh authorization.

---

## 7. Exact Extraction Order (smallest independently deployable + rollbackable units)

Target: Admin-owned shared libraries (`public/assets/offline/shared/*` or another Admin-owned package), each with a temporary `v2` shim, each shipped and reverted independently via PX-Deploy integrity.

| Order | Unit | Physical scope | Depends on | Independently deployable | Independently rollbackable |
|-------|------|----------------|------------|--------------------------|----------------------------|
| **U1** | **Runtime + EventBus + Service Locator** | `runtime.js` | (foundation) | ✅ | ✅ |
| **U2** | **SQLite** | `db/sqlite-runtime.js`, `db/migrations.js`, `vendor/sqlite/*` | U1 (service registration) | ✅ | ✅ |
| **U3** | **Identity** | `business/identity-module.js` | U1, U2 | ✅ | ✅ |
| **U4** | **Sync + Queue** | `sync/sync-engine.js` | U1, U2, U3 | ✅ | ✅ |

**Deviation from the literal 6-item rule list (must be honored):**
- *Service Locator* is **not** a standalone unit — it is inside `runtime.js` and ships inside **U1**.
- *Queue* is **not** a standalone unit — it is inside `sync-engine.js` and ships inside **U4**.
- Making them standalone would require a forbidden refactor. If independent Service-Locator / Queue units are mandatory, that requires a prior **approved refactor ADR** — do **not** attempt during extraction.

### Per-unit deploy contract (all four)
1. Add Admin-owned shared copy + `v2` shim (no behavior change, no route/SW/UI change beyond loader path + SW precache list).
2. Deploy via GitHub Actions fast mode; **PX-Deploy integrity gate must be green** (zero orphans, Git==Prod hash verified).
3. Run PX4 / offline-bootstrap regression on production — must be green.
4. Rollback = revert commit → PX-Deploy removes files introduced by the reverted commit (orphan deletion) → re-verify integrity green.
5. **STOP** and report Architecture Conflict on any: dual ownership, hidden `v2` dependency, integrity failure, or PX4 regression.

---

## 8. Evidence Appendix

- **PX-Deploy green run:** `a645c1db` — Actions run `29608459018` (`conclusion=success`), artifact `deploy-orphan-report`.
- **Orphan removal:** `assets/offline/shared/{runtime,sync,vendor/sqlite}` → HTTP 404.
- **Byte-exact Git==HTTP:** `runtime.js` (17502 B), `sw.js` (7211 B), `sync-engine.js` (40333 B) — SHA-256 MATCH.
- **SW cache consistency:** `sw.js` `CACHE = 'rateb-offline-v2-bootstrap-v2'` == `boot.js` `expectedCache`.
- **Single-owner scan:** `git ls-files` returns exactly one of each infra file, all under `public/v2`.
