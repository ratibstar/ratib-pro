# Phase 2 — Shared Infrastructure Extraction

**Status:** ANALYSIS COMPLETE · PHYSICAL EXTRACTION STOPPED
**Date:** 2026-07-17  
**Binding ADRs:** [ADR-AL-1](./ADR-AL-1-SINGLE-ERP-FRONTEND.md), [ADR-AL-2](./ADR-AL-2-SHARED-INFRASTRUCTURE-OWNERSHIP.md)  
**Identity boundary:** [AF-2.1-SECURITY-BOUNDARY](./AF-2.1-SECURITY-BOUNDARY.md)

---

## Phase constraints (observed)

| Constraint | Observed |
|------------|----------|
| No new ERP features | Honored |
| No Admin UI / routes / SW changes | Honored |
| No screen migration / no `public/v2` removal | Honored |
| Zero behavior / UI / route / schema changes | Honored |
| Preserve Offline V1, Online ERP, AF-2.1 | Honored |
| Extraction only | **Stopped before file moves** — see §6 STOP gate |

Physical extraction of the seven components under the current constraint set would either:

1. **Change load paths** → requires `index.html` / `boot.js` / `v2/sw.js` updates (SW change forbidden; behavior risk), or  
2. **Copy into Admin while leaving V2 owners** → **duplicate ownership** (ADR-AL-2 violation; STOP required).

Therefore Phase 2 delivers analysis only. No code was moved, copied, or rewritten.

---

## 1. Dependency graph

### 1.1 Platform stack (V2 — current canonical owners)

```text
HCI (js/hci.js)
  └─ Runtime (js/runtime/runtime.js)
       ├─ EventBus        (createEventBus → RatebOfflineV2Runtime.events)
       ├─ Service Locator (createServiceLocator → RatebOfflineV2Runtime.services)
       ├─ Package Manager (js/package-manager.js) [out of Phase 2 scope]
       ├─ SQLite          (js/db/sqlite-runtime.js + migrations.js + vendor/sqlite/*)
       │    └─ Sync / Queue (js/sync/sync-engine.js; queue = sync_outbox/inbox)
       ├─ Module SDK      (js/modules/module-sdk.js) [out of Phase 2 scope]
       ├─ Business FW     (js/business/business-module-framework.js) [out of Phase 2 scope]
       └─ Identity        (js/business/identity-module.js)
            └─ ERP BusinessModules (inventory, procurement, sales, accounting, crm, hr, manufacturing)
                 [OUT OF SCOPE — do not extract / do not migrate screens]
```

### 1.2 Hard edges

| From | To | Edge type |
|------|----|-----------|
| Runtime | HCI | Package/root storage (`rateb-offline-v2/`) |
| Runtime | EventBus | Co-located factory; same file |
| Runtime | Service Locator | Co-located factory; same file |
| Boot | Runtime, SQLite, Sync, Identity | Dynamic load + `start()` / `open()` / register |
| SQLite | Vendor WASM / OPFS | Durable DB `database/ratib.sqlite` |
| Sync | SQLite | `sync_*` tables + `entity_row` apply |
| Queue | Sync | **Not standalone** — outbox/inbox inside Sync |
| Identity | SQLite + Runtime services | `identity.*` entity types via published APIs only |
| BusinessModules | `module.identity.*` | AF-2.1 mandatory; no direct vault/SQL |

### 1.3 Extraction coupling (must move together)

| Bundle | Components | Reason |
|--------|------------|--------|
| **RSE** | Runtime + Service Locator + EventBus | Single file `runtime.js`; split without interface = behavior risk |
| **DB** | SQLite runtime + migrations + vendor WASM | Atomic open/migrate contract |
| **SQ** | Sync + Queue | Queue has no separate module |
| **ID** | Identity module | Depends on Runtime services + SQLite; AF-2.1 wrappers |

**Implicit dependency not in the seven-name list but required for RSE/DB:** HCI (`js/hci.js`). Extraction plan must include HCI in the Admin-owned package or Runtime will break.

### 1.4 Admin / Offline V1 parallel stack (MUST NOT merge in Phase 2)

```text
views/layouts/main.php
  └─ public/assets/offline/*  (generated from offline/client — certified V1)
       ├─ RatebOffline / offline-bootstrap / erp-shell-bootstrap
       ├─ RatebOfflineEvents
       ├─ RatebOfflineAuthLock / LocalSession / RbacCache
       ├─ RatebOfflineSchema (IndexedDB rateb_erp_offline v2)
       ├─ RatebOfflineQueue / DeltaPull / ReplayScheduler
       └─ pos-sw.js caches V1 assets (DO NOT MODIFY)
```

V1 and V2 are **parallel infrastructures**, not the same implementation. Phase 2 must not replace or rewrite V1.

---

## 2. Extraction plan

### 2.1 Target ownership root (Admin-owned)

Preferred new package (does **not** exist yet; create only when extraction is authorized):

```text
rateb-erp/public/assets/offline/shared/
  runtime/     ← Runtime + Service Locator + EventBus (+ HCI if bundled)
  db/          ← sqlite-runtime + migrations + vendor/sqlite
  sync/        ← sync-engine (includes Queue)
  identity/    ← identity-module (AF-2.1 preserved)
```

**Do not** place extracted sources under `public/assets/offline/modules/` — that tree is Offline V1 build output from `offline/client`.

### 2.2 Safe extraction sequence (future authorized phase)

| Step | Action | Gate |
|------|--------|------|
| E0 | Freeze V2 infra APIs (current symbols) | Done via AF-2.1 / AL-1 / AL-2 |
| E1 | Create `assets/offline/shared/**` by **move** (not copy) of RSE files | Requires path rewiring authorization |
| E2 | Leave thin re-export stubs at old `public/v2/js/...` paths that load Admin-owned URLs | Zero API change for consumers |
| E3 | Move DB + vendor; stub `sqlite-runtime.js` | No schema change |
| E4 | Move Sync (Queue follows); stub `sync-engine.js` | No outbox schema change |
| E5 | Move Identity; stub `identity-module.js` | Preserve `module.identity.*` + `assertNoSecrets` |
| E6 | Update V2 loaders / precache to shared URLs | **Requires SW change authorization** — blocked in this Phase 2 |
| E7 | Prove single owner: only `shared/` edited thereafter; V2 stubs are non-canonical | ADR-AL-2 |

### 2.3 Explicitly out of Phase 2

- Router (`js/router`), Shell (`js/ui`), `index.html`, `sw.js`, route manifests  
- BusinessModules other than Identity  
- Offline V1 (`offline/client`, `assets/offline/modules`, `rateb-offline.js`)  
- Online ERP auth / PHP Admin UI  
- Schema migrations beyond identical file move  
- Merging V1 IndexedDB with V2 SQLite  

### 2.4 Phase 2 execution result

| Item | Result |
|------|--------|
| Code moved | **No** |
| Code copied | **No** (would create dual ownership) |
| SW / routes / Admin UI touched | **No** |
| Deliverables 1–6 | **Yes** (this document) |

---

## 3. Ownership map

| Component | Current canonical owner | Current path | Post-extraction owner (target) | Temporary V2 role |
|-----------|-------------------------|--------------|--------------------------------|-------------------|
| Runtime | `public/v2` | `js/runtime/runtime.js` | Admin `assets/offline/shared/runtime/` | Consumer stub only |
| Service Locator | `public/v2` (inside Runtime) | same file | Admin shared/runtime | Consumer stub only |
| EventBus | `public/v2` (inside Runtime) | same file | Admin shared/runtime | Consumer stub only |
| SQLite | `public/v2` | `js/db/*` + `vendor/sqlite/*` | Admin `shared/db/` | Consumer stub only |
| Sync | `public/v2` | `js/sync/sync-engine.js` | Admin `shared/sync/` | Consumer stub only |
| Queue | `public/v2` (inside Sync) | sync-engine + migrations | Admin `shared/sync/` | Consumer stub only |
| Identity | `public/v2` | `js/business/identity-module.js` | Admin `shared/identity/` | Consumer stub only |
| HCI (required) | `public/v2` | `js/hci.js` | Admin shared (with Runtime) | Consumer stub only |

| Parallel stack | Owner | Extraction action |
|----------------|-------|-------------------|
| Offline V1 SDK / Queue / AuthLock / IDB | `offline/client` → generated `assets/offline/modules` | **Preserve ZERO TOUCH** |
| Online ERP Authentication Authority | Online ERP / PHP session | **Preserve** — Identity never becomes authority |
| Admin UI / routes / `pos-sw.js` | Admin | **No Phase 2 changes** |

**Single-implementation rule:** after extraction, exactly one maintained body of source per component under Admin shared. V2 must not remain the edit locus.

---

## 4. Duplicate implementations

### 4.1 Concept duplicates (parallel stacks — do not unify in Phase 2)

| Concept | V2 implementation | Admin / Offline V1 implementation | Compatible? |
|---------|-------------------|-----------------------------------|-------------|
| Runtime | `RatebOfflineV2Runtime` | `RatebOffline` + bootstraps | **No** — different lifecycle/DI |
| Identity | `RatebOfflineV2Identity` + SQLite `identity.*` | `RatebOfflineAuthLock` + IDB `auth_vault` | **No** — different storage & API |
| Local DB | SQLite/OPFS `ratib.sqlite` | IndexedDB `rateb_erp_offline` v2 | **No** |
| Sync | `RatebOfflineV2Sync` SQLite outbox/inbox | DeltaPull + Transport + Replay | **No** — different server contract |
| Queue | `sync_outbox` inside Sync | `RatebOfflineQueue` / `sync_queue` | **No** — frozen V1 contract |
| EventBus | `RatebOfflineV2Runtime.events` | `RatebOfflineEvents` | **No** — separate channels |
| Service Locator | `RatebOfflineV2Runtime.services` | *(none)* | V2-only |

### 4.2 Source duplicates that are not Phase 2 targets

- `rateb-offline.js` / `rateb-offline.min.js` embed V1 modules — certified bundle, leave untouched.  
- `assets/offline/modules/*` are **generated copies** of `offline/client` — V1 ownership, not V2 extraction targets.

### 4.3 Intra-V2 co-location (not duplicates)

- Service Locator + EventBus live inside Runtime → one implementation, two APIs.  
- Queue lives inside Sync → one implementation.

### 4.4 Dual-ownership risk if Phase 2 copied files

Copying V2 infra into `assets/offline/shared/` **without deleting/stubbing** V2 owners would create two maintained trees for the same symbols → **ADR-AL-2 violation → STOP** (this is why Phase 2 did not copy).

---

## 5. Compatibility report

### 5.1 Production ERP (Admin)

| Surface | Impact of Phase 2 analysis | Impact if premature move without SW auth |
|---------|----------------------------|------------------------------------------|
| `/rateb-erp/public/admin/*` | None | None (Admin does not load V2 globals today) |
| Offline V1 assets | None | Must remain byte-compatible |
| `pos-sw.js` | Untouched | Must remain untouched per Phase 2 |
| Online ERP auth | Untouched | Untouched |

**Finding:** Admin production does not currently consume `RatebOfflineV2*` symbols. Extracting V2 libraries into Admin-owned paths does not auto-wire Admin; wiring is a later phase.

### 5.2 V2 migration / offline bootstrap surface

| Surface | Compatibility requirement |
|---------|---------------------------|
| Global symbols | `RatebOfflineV2Runtime`, `RatebOfflineV2DB`, `RatebOfflineV2Sync`, `RatebOfflineV2Identity`, `RatebOfflineV2ActiveSync` must keep identical public API |
| SQLite schema | No migration churn; same `MIGRATIONS` / table set |
| OPFS root | Keep `rateb-offline-v2/` |
| Identity services | Keep `module.identity.*` names + secret rejection |
| Precache | `v2/sw.js` currently caches V2-relative paths; relocating without SW update breaks cold offline boot |

### 5.3 AF-2.1 Identity boundary

| Rule | Status |
|------|--------|
| Online ERP = Authentication Authority | Preserved |
| Identity stores sealed/claims/RBAC/device/unlock meta only | Preserved in plan |
| No passwords / hashes / cookies / JWT / TOTP / API tokens | Must remain in extracted Identity |
| BusinessModules use published APIs only | Unchanged |

### 5.4 Schema

| Change | Phase 2 |
|--------|---------|
| SQLite migrations | **None** |
| IndexedDB V1 | **None** |
| Entity type renames | **None** |

---

## 6. Regression report & STOP gate

### 6.1 Regressions introduced by this Phase 2

| Area | Result |
|------|--------|
| Runtime behavior | **Unchanged** (no code moves) |
| UI / routes / SW | **Unchanged** |
| Offline V1 | **Unchanged** |
| Online ERP | **Unchanged** |
| Identity boundary | **Unchanged** |
| Duplicate ownership | **Not created** |

### 6.2 Predicted regressions if extraction proceeded under current bans

| Action | Predicted failure | Severity |
|--------|-------------------|----------|
| Move files; leave SW precache on old paths | Cold offline boot fails / precache verify fails | **Critical** |
| Copy to Admin shared; keep editing V2 | Dual canonical owners | **Architecture violation** |
| Point V2 stubs to shared URLs without SW update | Offline first-launch miss | **Critical** |
| Merge Identity into V1 AuthLock | Second identity authority / storage split | **AF-2.1 + V1 freeze violation** |
| Replace V1 Queue with V2 outbox | Breaks certified replay contract | **Critical** |
| Split Runtime / EventBus / Locator without tests | Service registration order / event loss | **High** |

### 6.3 STOP decision

```text
STOP — Phase 2 physical extraction is blocked.
```

**Reasons (any one is sufficient):**

1. Updating consumers to Admin-owned URLs requires Service Worker / precache changes — **forbidden in this phase**.  
2. Copying without retiring V2 ownership creates **duplicate ownership** — **forbidden by ADR-AL-2 and this phase’s stop rule**.  
3. Any non-stub path change risks **runtime behavior change** — **forbidden**.

### 6.4 Authorization needed to continue extraction (next phase)

Before any file move:

1. Explicit authorization to update `public/v2` loaders **and** `public/v2/sw.js` precache lists (or an equivalent zero-break cache strategy).  
2. Commit to **move + stub** (single owner), never long-lived copy.  
3. Regression pack: offline cold boot, Runtime.start, DB open, Sync enqueue, Identity unlock/enroll, AF-2.1 secret scan.  
4. Confirm Offline V1 remains untouched and unwired to V2 symbols.

---

## Deliverable checklist

| # | Deliverable | Location |
|---|-------------|----------|
| 1 | Dependency graph | §1 |
| 2 | Extraction plan | §2 |
| 3 | Ownership map | §3 |
| 4 | Duplicate implementations | §4 |
| 5 | Compatibility report | §5 |
| 6 | Regression report | §6 |

**No feature work. No Admin UI. No routes. No SW. No screen migration. No `public/v2` removal. No cleanup. No optimization. Extraction analysis only.**
