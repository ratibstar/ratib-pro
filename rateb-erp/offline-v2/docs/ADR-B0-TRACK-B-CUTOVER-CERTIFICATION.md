# ADR-B0 — Track B Cutover Architecture Certification

**Status:** ACCEPTED AS DESIGN · **NO-GO for Track B implementation until gates clear**  
**Date:** 2026-07-18  
**Binding:** ADR-AL-1, ADR-AL-2, AF-2.1, OFAT, PX-Deploy, Track A M0–M5 PASS  
**Mode:** Documentation / architecture certification only — no code, no adapters, no production change  

---

## 1. Executive Summary

Track A finished: Admin-owned `public/assets/offline/platform/` is the sole implementation owner of HCI, Runtime (EventBus + ServiceLocator), SQLite, Identity (+ Business FW support), and Sync (+ Queue). `public/v2` is a migration host with stubs only.

Production Admin ERP still boots **Offline V1 only** (IndexedDB `rateb_erp_offline`, V1 sync queue, V1 sealed warm identity). Platform and Offline V1 are **parallel, non-interchangeable stacks**.

**Certification verdict for B0 design:** Architecture is **sound** if cutover is staged, single-engine-per-document, flag-gated, and kill-switchable.  
**Verdict for starting Track B implementation (B1+):** **NO-GO** until the GO criteria in §17 are all green. Highest risks are queue/identity/storage format divergence and dual-engine corruption.

---

## 2. Current Architecture (production)

```text
Admin ERP (sole frontend — ADR-AL-1)
  views/layouts/main.php
    → Offline V1: rateb-offline*.js / erp-shell-bootstrap.js
    → IndexedDB: rateb_erp_offline (SYNC_QUEUE, …)
    → V1 auth seal / warm identity (offline-auth)
    → pos-sw.js / rateb-offline-sw.js (Admin/POS scope)

POS
  → Offline V1 + POS modules (unchanged by Track A)

Platform (Admin-owned libraries — ADR-AL-2)
  assets/offline/platform/{hci,runtime,db,identity,support,sync}
  → Used today only by public/v2 migration host
  → SQLite OPFS (rateb-offline-v2/) + sync_outbox/inbox
  → module.identity.* (AF-2.1 local cache)

public/v2
  → stubs + host UI for PX4 only — NOT ERP frontend
```

**Invariant today:** Admin document never starts Platform Runtime/Sync. That invariant must survive until CompatGate explicitly allows Platform on Admin.

---

## 3. Target Architecture (post–Track B)

```text
Admin ERP (still sole frontend)
  → Admin adapters only (never import /public/v2)
  → CompatGate
  → Platform: HCI → Runtime(EventBus, ServiceLocator)
                → SQLite → Identity (module.identity.*)
                → Sync(+Queue)

Offline V1
  → retired or read-only archive after cutover gates
  → may remain for EmergencyRollback window

POS
  → separate cutover train (not in Admin B1); must not dual-boot Platform+V1
```

**Hard rule:** On any Admin document, at most **one** of {Offline V1 engine, Platform engine} may be **active** for writes (queue/identity/db). Shadow mode may load Platform read-only for validation only.

---

## 4. State Machine

```text
                    ┌──────────────┐
                    │     BOOT     │  Admin shell + feature flags load
                    └──────┬───────┘
                           ▼
                    ┌──────────────┐
                    │ V1_ACTIVE    │  Default / safe production
                    └──────┬───────┘
                           │ CompatGateEnabled && PlatformEnabled
                           ▼
                    ┌──────────────┐
           ┌───────►│ COMPATGATE   │◄────── EmergencyRollback / fail
           │        └──────┬───────┘
           │               │ allow_shadow | allow_cutover
           │               ▼
           │        ┌──────────────┐
           │        │ PLATFORM_BOOT│  HCI→Runtime→SQLite→Identity→Sync
           │        └──────┬───────┘
           │               ▼
           │        ┌──────────────┐
           │        │ VALIDATION   │  health, schema, identity, queue empty/drained
           │        └──────┬───────┘
           │         pass / │ \ fail → ROLLBACK
           │    ┌──────────┴──────────┐
           │    ▼                     ▼
           │ PLATFORM_SHADOW     CUTOVER
           │ (read-only /         (Platform write authority)
           │  dual-read)               │
           │    │                      ▼
           │    │               OPERATIONAL
           │    │               (single Platform engine)
           │    │                      │
           │    └──────────► ROLLBACK ◄┘ (flag / kill switch / crash policy)
           │                      │
           │                      ▼
           │                 RECOVERY
           │                 (restore V1_ACTIVE; drain/quarantine)
           └──────────────────────┘
```

| State | Writer authority | Notes |
|-------|------------------|-------|
| BOOT | none | Flags + kill-switch probe |
| V1_ACTIVE | Offline V1 only | Production default |
| COMPATGATE | V1 only | Decide next mode |
| PLATFORM_BOOT | none (init) | Must not enqueue |
| VALIDATION | none | PX-like health checks |
| PLATFORM_SHADOW | V1 writes; Platform read-only | Compare parity |
| CUTOVER | Platform only | V1 write path disabled |
| OPERATIONAL | Platform only | Steady state |
| ROLLBACK | freeze writers | Switch flags; no deploy |
| RECOVERY | V1 after verify | Quarantine Platform pending |

---

## 5. CompatGate Design

**Purpose:** Single decision point that prevents dual Runtime / dual Queue / dual Identity / dual SQLite on one Admin document.

**Inputs**

- Feature flags (server + local override)
- Kill switch (`EmergencyRollback`)
- Pending V1 queue depth / dirty flag
- Platform health (Runtime, DB open, Identity publish, Sync registered)
- Multi-tab lease (`BroadcastChannel` / `navigator.locks`)
- Tenant / company context match

**Outputs**

- `mode`: `v1` | `shadow` | `cutover` | `rollback`
- `reason` code for diagnostics
- `assertSingleEngine()` throws if both engines attempt write registration

**Rules (binding)**

1. If `EmergencyRollback` → force `v1` (ROLLBACK → RECOVERY).  
2. If `!PlatformEnabled` → `v1`.  
3. If `PlatformShadow` && `!PlatformCutover` → may enter SHADOW only when V1 queue is finite and Platform boots clean.  
4. If `PlatformCutover` → require empty-or-migrated V1 queue, Identity bridge OK, single-tab lease held.  
5. Never load Platform Sync `start()` while V1 queue writer is active.  
6. Never import `/public/v2/*` from Admin.

---

## 6. Feature Flags

| Flag | Default | Effect |
|------|---------|--------|
| `CompatGateEnabled` | `false` | Gate code path inert; always V1 |
| `PlatformEnabled` | `false` | Allow Platform scripts to load at all |
| `PlatformShadow` | `false` | Read-only Platform boot + parity checks |
| `PlatformCutover` | `false` | Platform becomes write authority |
| `EmergencyRollback` | `false` | Immediate V1-only; ignores other Platform flags |
| `PlatformQueueMigrate` | `false` | Permit V1→Platform outbox migration job |
| `PlatformIdentityBridge` | `false` | Permit enrollment remap V1 seal → Platform identity |
| `PlatformAdminSW` | `false` | Future Admin SW precache of platform (separate train) |

**Resolution order:** `EmergencyRollback` > server remote config > localStorage override (debug only, signed/ops-gated) > build defaults.

---

## 7. Kill Switch

**Name:** `EmergencyRollback`  
**Delivery:** remote config endpoint already reachable by Admin (cookie session), polled at BOOT and on `visibilitychange`; also sticky `localStorage` mirror written when remote says ON.

**Behavior (no deploy)**

1. Set mode `ROLLBACK`.  
2. Stop Platform Sync interval; unregister Platform write hooks.  
3. Do **not** delete SQLite/OPFS or V1 IDB.  
4. Re-enable Offline V1 writers.  
5. Keep user session (PHP/online cookies) — no forced logout.  
6. Quarantine unsynced Platform `sync_outbox` rows (status=`quarantine`) — do not replay into V1 blindly.  
7. Banner: “Offline engine rolled back to V1 — ops notified.”

**Guarantees:** no deploy; no logout; no silent dual-write; no automatic cross-engine queue merge.

---

## 8. Rollback Design

### Instant (kill switch)
Flags only → V1_ACTIVE. Single engine restored.

### Soft (feature flags)
Clear `PlatformCutover` → SHADOW or V1; keep Platform data.

### Hard (git)
Revert Track B commits only (adapters/layout). **Do not** revert Track A platform ownership (ADR-AL-2). PX-Deploy orphans cleaned.

**Single-instance guarantees after rollback**

| Resource | Rule |
|----------|------|
| Runtime / EventBus / ServiceLocator | Platform not started, or started read-only in SHADOW |
| Queue | Exactly one writer: V1 `sync_queue` **or** Platform `sync_outbox` |
| Identity | V1 seal **or** `module.identity.*` — not both as authz source |
| SQLite | Platform DB not used for Admin writes in V1 mode |
| EventBus | No Platform bus subscribers on Admin write path |

---

## 9. Browser Upgrade Strategy

1. Deploy Track B code with all flags **OFF** (CompatGate present, inert).  
2. Enable `CompatGateEnabled` + `PlatformEnabled` + `PlatformShadow` for canary tenants.  
3. Validate shadow parity (see Test Matrix).  
4. Drain V1 queues / run `PlatformQueueMigrate` under flag.  
5. Enable `PlatformCutover` per tenant.  
6. Cache: Admin continues `pos-sw` / `rateb-offline-sw` until `PlatformAdminSW`; do **not** require V2 SW on Admin.  
7. Legacy browsers without OPFS/WASM: CompatGate forces V1 (capability probe).

**Fresh install after cutover:** BOOT → capability OK → may go PLATFORM_BOOT → CUTOVER if flags on for tenant.  
**Upgrade install:** BOOT → V1_ACTIVE → COMPATGATE → staged path.

---

## 10. Data Migration Strategy

| Store | V1 | Platform | Strategy |
|-------|----|----------|----------|
| Business ops cache | IndexedDB | SQLite `entity_row` | **Do not bulk-copy blindly.** Prefer re-warm from Online ERP after cutover; optional curated entity migrate under flag |
| Identity | Sealed warm package (IDB/session) | `identity.*` via enrollment | Bridge: Online enrollment → `applyEnrollment` (AF-2.1). Never copy passwords/tokens |
| Layout / OPFS | N/A | `rateb-offline-v2/` | Created by HCI `ensureLayout` on Platform boot |
| Schema | IDB versioned stores | SQLite migrations | Platform migrations only; no schema change to Online DB |

**Coexistence:** IDB and SQLite may both exist on disk during SHADOW; only one write authority.

---

## 11. Queue Migration Strategy

| Aspect | Rule |
|--------|------|
| Formats | V1 `sync_queue` ≠ Platform `sync_outbox` |
| Safe path | Drain V1 to Online **before** cutover (preferred) |
| Alternate | Explicit mapper V1 record → Platform `enqueue` under `PlatformQueueMigrate`, idempotent keys preserved where possible |
| Forbidden | Running both queues as writers; silent drop of pending V1 items |
| Conflicts | Platform conflict tables only after cutover; V1 conflicts resolved or abandoned with audit |
| Replay | Platform Sync replay only on Platform outbox; V1 replay only on V1 |

**Cutover gate:** `V1 pending == 0` OR migration report `ok` with checksum.

---

## 12. Cache Strategy

- **Admin/POS SW:** unchanged until dedicated B-train; must not precache V2 host.  
- **Platform assets:** served from `assets/offline/platform/*` (already MIME-correct for DB).  
- **Shadow:** Platform scripts loaded with `cache: 'no-cache'` for first canary.  
- **Rollback:** browser may keep Platform files cached; flags prevent execution of write path.  
- **V2 bootstrap v6:** migration host only — irrelevant to Admin cutover.

---

## 13. Multi-tab Strategy

- Use `navigator.locks` (fallback `BroadcastChannel`) key: `rateb-admin-offline-engine`.  
- Only lease holder may enter CUTOVER or run queue migrate.  
- Other tabs remain V1_ACTIVE or read-only until lease + flag refresh.  
- On lease loss → freeze Platform writers → RECOVERY probe.

---

## 14. Failure Recovery

| Failure | Action |
|---------|--------|
| Platform boot fail | Stay/return V1_ACTIVE; log reason |
| Validation fail | ROLLBACK flags soft |
| Crash mid-cutover | On next BOOT, if `PlatformCutover` but Platform unhealthy → auto soft rollback |
| Power loss | Durable: V1 IDB + Platform SQLite; writers decided by flags + queue gate |
| Offline during cutover | Forbid cutover unless queue drained; SHADOW only |
| Online recovery | Resume Platform Sync drain after health OK |
| Kill switch | Immediate V1 writers |
| Multi-tab race | Lease denial → no cutover |
| Session restore | Online ERP session cookies unchanged; Identity re-unlock via Platform or V1 per mode |

---

## 15. Test Matrix (certification)

| Case | Expected |
|------|----------|
| Fresh install + cutover flags | Platform OPERATIONAL; single engine |
| Upgrade from V1 | V1 → gate → shadow → cutover |
| Rollback kill switch | Instant V1; session kept; queues not dual-written |
| Offline install | Capability/offline policy; no cutover if drain required |
| Online install | Full path OK |
| Multi-tab | Single lease; no dual writers |
| Refresh | Same mode restored from flags |
| Power loss | Durable recovery; mode coherent |
| Crash mid-migrate | Quarantine + V1 fallback |
| Slow / no network | Shadow OK; cutover blocked if drain needs network |
| Queue pending | Cutover blocked or migrate flag path |
| Conflict pending | Resolve/abandon before cutover |
| Browser restart | Flags sticky; engine matches |
| Session restore | No logout; identity unlock works |
| Tenant switch | Re-run CompatGate; no cross-tenant queue |
| POS active | POS remains V1 until POS-specific ADR |
| ERP Admin active | Admin path only |
| OPFS unavailable | Force V1 |
| PX-Deploy / PX4 Admin profile | Must exist before B1 |

---

## 16. Remaining Risks

| ID | Risk | Severity | Mitigation |
|----|------|----------|------------|
| R-Q | V1↔Platform queue format mismatch | **Critical** | Drain-first; explicit mapper; cutover gate |
| R-ID | Identity seal ≠ Platform identity store | **Critical** | Online enrollment bridge only; AF-2.1 |
| R-DUAL | Dual engine on one document | **Critical** | CompatGate + lease + kill switch |
| R-POS | POS still V1 while Admin Platform | High | Separate POS cutover ADR |
| R-SW | Admin SW doesn’t precache Platform | Medium | Optional `PlatformAdminSW` train |
| R-PERF | WASM/SQLite startup cost | Medium | Shadow measure; lazy after shell |
| R-TAB | Multi-tab cutover races | High | Locks |
| R-DATA | Blind IDB→SQLite copy corruption | **Critical** | Forbidden; re-warm preferred |

---

## 17. GO / NO-GO Decision

### B0 (this certification)
**GO for design acceptance** — cutover architecture is certified as the binding Track B plan.

### Track B implementation (B1+)
**NO-GO** until **all** are true:

1. Written Admin PX4 / cutover regression profile exists and is runnable.  
2. CompatGate + flag schema approved in an implementation ADR/checklist.  
3. Kill switch remote delivery proven in staging (no deploy).  
4. Queue strategy chosen: drain-first **or** mapper with idempotent proof.  
5. Identity bridge proof: Online enrollment → `module.identity.*` without credentials.  
6. Multi-tab lease proof.  
7. Explicit POS non-scope statement signed (POS stays V1).  
8. Capability probe for OPFS/WASM forces V1 when missing.  
9. Rollback drill executed in staging (kill switch + soft flag).  
10. User/Architecture **authorizes B1** after gates 1–9.

---

## 18. Readiness Score

| Score | Value | Meaning |
|-------|------:|---------|
| **Track A** | **98** | Complete (M5) |
| **B0 design certification** | **86** | Architecture complete; residual risk documented |
| **Track B implementation readiness** | **38** | Blocked on gates 1–10 |

Deductions on B0 design: queue/identity format gap (−8), POS parallel world (−4), Admin SW not yet specified (−2).

---

## Explicit non-actions (honored)

No code, adapters, file moves, SW/Admin/POS/Platform/V1/API/DB/auth changes in B0 beyond this document.
