# Phase 2.5 — Remove the Architecture Conflict (Migration Redesign)

**Status:** REDESIGN COMPLETE · Documentation only · No implementation · No file moves · No production change  
**Binding:** ADR-AL-1, ADR-AL-2, AF-2.1, PX-Deploy, Phase 2.3 PASS, Phase 2.4 Conflict (superseded path)  
**Date:** 2026-07-17

---

## Verdict

The Phase 2.4 migration path was **fundamentally wrong**, not missing a patch.

It assumed: *Admin must consume V2-in-place first, then extract.*  
That creates an impossible triangle:

1. Admin must not import `/public/v2`
2. Admin-owned package must not exist yet
3. Extraction must not happen yet

**Replacement architecture:** **Owner-First Atomic Transfer (OFAT)** — invert the dependency arrow, split ownership migration from Admin-page cutover, and transfer one atomic unit per deploy with exactly one implementation owner at all times.

---

## Return summary (authoritative)

| Field | Value |
|-------|--------|
| **New migration architecture** | Owner-First Atomic Transfer (OFAT) — see below |
| **New dependency graph** | Admin-owned platform is root; V2 is shim-only consumer |
| **New ownership model** | Exactly one implementation owner per component; stubs are not owners |
| **New deployment order** | Track A units M0→M5, then optional Track B cutover |
| **New rollback strategy** | Per-unit `git revert` + PX-Deploy orphan purge |
| **Remaining blockers** | Authorization + PX4 harness alignment (non-architectural) |
| **Final readiness score** | **88 / 100** — architecture conflict removed; implementation still gated on explicit authorize |

---

## New migration architecture

### Name

**Owner-First Atomic Transfer (OFAT)**

### What was wrong (do not revive)

| Failed idea | Why it fails |
|-------------|--------------|
| Shadow Admin → load `/public/v2` | Violates “no import from v2”; makes v2 a production dependency of Admin |
| Copy into `shared/` while V2 keeps the real files | Temporary dual ownership (ADR-AL-2); Phase 2.1 failure mode |
| Integrate Admin pages onto V2 Sync/Identity before ownership transfer | Forces dual Offline V1 + V2 engines (second Runtime/Queue) |
| Freeze production / big-bang cutover | Violates continuous deploy + independent rollback |

### Core rules (binding for all future steps)

1. **Invert the arrow.** Allowed: `public/v2` → Admin-owned platform URLs. Forbidden: Admin → `/public/v2`.
2. **Owner first.** The implementation file’s sole home becomes the Admin-owned package in the **same commit** that retires V2 as owner (V2 left only as a thin stub/re-export, or deleted if unused).
3. **No temporary shared ownership.** At no HEAD does Git contain two maintained implementations of Runtime / EventBus / ServiceLocator / Queue / Identity / SQLite / Sync.
4. **Stubs are not owners.** A V2 stub that loads Admin-owned code does not count as ownership of that component.
5. **Two tracks — never conflate.**
   - **Track A — Platform ownership transfer** (clears B1–B4, B6–B8): move infra under Admin-owned package; V2 host remains a *migration test host*, not an ERP frontend.
   - **Track B — Admin page cutover** (clears B5 later): Admin ERP pages switch from Offline V1 to platform APIs only after Track A is complete and a cutover ADR is approved. Track A does **not** start V2 engines inside Admin layouts.
6. **Atomic unit = one deploy.** Each unit is independently deployable, independently rollbackable, must pass PX-Deploy and PX4, and must not freeze production.
7. **Preserve ADR-AL-1.** Admin PHP MVC remains the only production ERP frontend. `public/v2` never gains ERP screens/routes as production UI.
8. **Preserve ADR-AL-2.** After each unit, Admin owns that component; V2 must not remain canonical source.
9. **Preserve AF-2.1.** Identity stays a local cache; Online ERP remains Authentication Authority; consumers use `module.identity.*` only.

### Canonical Admin-owned package (URL space)

Prefer a dedicated platform tree under Admin-owned assets (not a second frontend):

```text
rateb-erp/public/assets/offline/platform/
  hci/
  runtime/          ← Runtime + EventBus + ServiceLocator (one module)
  db/               ← sqlite-runtime + migrations + vendor/sqlite (adjacent)
  identity/
  sync/             ← Sync + Queue (one module)
  support/          ← Business FW (only as required for Identity publish)
  boot/             ← optional Admin platform boot harness (Track A proof; not ERP UI)
```

**Why this path:** ADR-AL-2 explicitly allows `public/assets/offline/*`. There is no requirement for a static `/public/admin/*` SPA. Admin ERP frontend remains the PHP MVC URL space (`/rateb-erp/public/admin/*` product routes via front controller). Platform files are libraries, not a second ERP UI.

**Naming note:** Avoid reviving the failed Phase 2.1 `shared/` leftover narrative; `platform/` marks Admin-owned infra without implying dual ownership.

### How each transfer works (one unit)

```text
BEFORE:  v2/js/.../impl.js     = sole implementation owner
AFTER:   assets/offline/platform/.../impl.js  = sole implementation owner
         v2/js/.../impl.js     = stub only (loads platform URL) OR removed if boot uses platform directly
         v2 boot / SW precache = reference platform URLs (v2 → Admin)
         Admin ERP layouts     = unchanged (still Offline V1) until Track B
```

### How B1–B8 are removed

| ID | Old blocker | How OFAT removes it |
|----|-------------|---------------------|
| **B1** | No legal Admin → V2 load path | Admin never loads V2. Platform lives under Admin-owned URLs. V2 may load platform. |
| **B2** | SQLite vendor path tied to `/v2` | SQLite unit moves `db/` + `vendor/sqlite/` **together**; relative `import.meta.url` stays valid inside platform tree. |
| **B3** | HCI hidden dep | HCI transfers in **M1 with Runtime** (same atomic unit). |
| **B4** | Identity needs Business FW | **M3** moves Identity + minimal `support/` FW required to publish `module.identity.*` in one unit (or FW micro-step immediately before Identity in same release train — still one rollbackable unit boundary). |
| **B5** | Dual V1+V2 engines on Admin pages | **Deferred to Track B.** Track A does not boot platform Sync/Runtime inside Admin layouts. Offline V1 remains Admin production offline until cutover ADR. |
| **B6** | No static `/public/admin` SPA | Platform package under `assets/offline/platform/` is the Admin-owned library home; ERP UI stays PHP MVC. |
| **B7** | SW isolation | **Resolved for M1:** update **only** `public/v2/sw.js` (cache bump + precache platform Runtime/HCI; drop old owner paths). `pos-sw.js` / Admin SW / Offline V1 SW remain untouched. See [M1 SW amendment](./PHASE-M1-SW-MAINTENANCE-AMENDMENT.md). |
| **B8** | Sync soft-coupled to Router/Shell | Sync unit contract: production path = DB + HCI + Runtime only; Router/Shell remain V2-host-only soft deps for self-tests, not Admin platform boot requirements. |

---

## New dependency graph

### Track A (ownership) — target

```text
Admin-owned platform (canonical)
  HCI
    └── Runtime
          ├── EventBus
          └── ServiceLocator
                ├── SQLite (+ vendor WASM, adjacent)
                ├── Sync (+ Queue, same module)
                └── Identity ──published──► module.identity.*
                      └── support/Business FW (Identity publish only)

Migration host public/v2 (NOT ERP frontend)
  stubs / boot / sw  ──loads──►  platform URLs only
  may run PX4 against platform via stubs

Admin ERP pages (PHP MVC) + POS
  └── Offline V1 only   ◄── unchanged during Track A
```

### Forbidden edges (permanent)

```text
Admin ERP / POS / platform  ──X──►  /public/v2/*   (implementation or import)
platform                    ──X──►  second Runtime/EventBus/Locator/Queue/Identity/SQLite/Sync
V2 stub                     ──X──►  local reimplementation (stub must not grow a second engine)
```

### Allowed edges

```text
public/v2 (shim)            ──►  assets/offline/platform/*
Admin ERP (Track B only)    ──►  assets/offline/platform/* via adapters
platform Identity           ──►  Online ERP (enrollment only; AF-2.1)
```

---

## New ownership model

| Component | Owner before OFAT | Owner after its unit | V2 role after unit | Admin ERP pages |
|-----------|-------------------|----------------------|--------------------|-----------------|
| Runtime + EventBus + ServiceLocator + HCI | `public/v2` | `assets/offline/platform/` | stub / loader | V1 only (Track A) |
| SQLite + vendor | `public/v2` | platform `db/` | stub | V1 only |
| Identity (+ FW support) | `public/v2` | platform `identity/` (+ `support/`) | stub | V1 only |
| Sync + Queue | `public/v2` | platform `sync/` | stub | V1 only |
| Offline V1 | `assets/offline/*` | unchanged | N/A | production offline until Track B |
| ERP UI | Admin PHP MVC | Admin PHP MVC | never | sole frontend |

**Ownership test (must pass every unit):**  
`git grep` / tree scan shows **exactly one** non-stub implementation file for that component under platform; V2 file is stub-sized re-export or absent.

**Duplicate test:** starting platform Runtime twice, or V1+platform Sync on the same Admin document during Track A, is a **release policy failure** — Track A CI must assert Admin layouts do not reference platform boot.

---

## New deployment order

Each step = one PR/commit train → push → PX-Deploy green → PX4 green → next step. No production freeze.

| Step | Unit | What transfers (atomic) | PX-Deploy | PX4 | Rollback |
|------|------|-------------------------|-----------|-----|----------|
| **M0** | Migration harness | Platform boot proof path under Admin-owned URL (empty/smoke only) + PX4 target updated to resolve platform; **no** infra move yet | must pass | harness smoke | revert M0 |
| **M1** | Runtime + EventBus + ServiceLocator + **HCI** | Move to `platform/runtime` + `platform/hci`; V2 stubs; **V2 host `sw.js` only**: cache-name bump + precache platform Runtime/HCI + remove old owner paths from precache (see [M1 SW amendment](./PHASE-M1-SW-MAINTENANCE-AMENDMENT.md)). Forbidden: `pos-sw.js`, Admin, V1, SQLite/Identity/Sync | must pass | Runtime/HCI init via stubs on migration host | revert M1 |
| **M2** | SQLite | Move `db/` + `vendor/sqlite/` together; stubs; SW precache | must pass | DB open + integrity | revert M2 |
| **M3** | Identity (+ support FW as required) | Move Identity + minimal FW; publish `module.identity.*`; AF-2.1 scan | must pass | Identity init + service publish | revert M3 |
| **M4** | Sync + Queue | Move sync-engine; single Queue; no Router/Shell hard dep for start | must pass | Sync start + enqueue smoke | revert M4 |
| **M5** | Track A closeout | Prove: zero non-stub infra left under `public/v2/js/{runtime,db,sync,business/identity*}`; ownership report | must pass | full PX4 | revert M5 |
| **B0** | Cutover ADR (docs) | Authorize Admin page use of platform adapters; CompatGate rules | docs | N/A | N/A |
| **B1+** | Admin cutover (future) | Admin layouts load platform via adapters; never `/v2`; V1 retirement plan | must pass | Admin+platform PX4 profile | per-step revert |

**Risk minimization**

- Smallest units already co-located (Runtime trio; Sync+Queue; SQLite+vendor).
- Track A never touches Admin layout offline wiring → no dual-engine risk during ownership moves.
- SW changes limited to **v2 migration host SW precache list**, same commit as the unit that changes URLs.
- POS / `pos-sw.js` / Offline V1 untouched in Track A.

**Independently deployable:** each M-step ships alone.  
**Independently rollbackable:** each M-step has a single revert commit.  
**No freeze:** production Admin ERP continues on V1 throughout Track A.

---

## New rollback strategy

1. **Unit rollback** = `git revert <unit-sha>` on `main` (no force push).
2. **PX-Deploy integrity** deletes orphans introduced by the reverted commit (Admin platform files removed if revert drops them; restored V2 implementation returns via revert).
3. **Verify** after rollback: PX-Deploy green + PX4 green + ownership test (single implementation) + no Admin → `/v2` imports.
4. **Do not** roll back by pointing Admin at `/public/v2`.
5. **Do not** roll back by leaving platform + V2 both as full implementations.
6. **Track A rollback does not affect** Admin Offline V1 (unchanged).
7. **Track B rollback** reverts layout/adapter commits only; platform ownership remains (safer than re-homing infra).

---

## Remaining blockers

Architectural conflict **B1–B8: CLEARED by OFAT** (pending authorized implementation).

| ID | Type | Remaining item | Blocks implementation? |
|----|------|----------------|------------------------|
| **R1** | Process | Explicit user/Architecture authorization to execute Track A (M0+) | Yes — start gate |
| **R2** | Tooling | PX4 / offline-bootstrap must resolve platform URLs (update harness in M0, not a freeze) | Yes — before M1 |
| **R3** | Policy | Track B cutover ADR not written yet | No for Track A; Yes for Admin page switch |
| **R4** | Ops | First platform unit must prove PX-Deploy orphan delete for `assets/offline/platform/**` | Mitigated — PX-Deploy already manages `public/` trees; confirm in M0 |

**No Architecture Conflict remains on the migration design itself.**

---

## Final readiness score

**88 / 100**

| Bucket | Points | Notes |
|--------|-------:|-------|
| Conflict triangle removed (OFAT) | +30 | Invert arrow; owner-first; no dual ownership |
| ADR-AL-1 / ADR-AL-2 / AF-2.1 preserved | +20 | Single ERP frontend; Admin owns infra; Identity boundary |
| Independent deploy/rollback + PX-Deploy/PX4 per step | +20 | M0–M5 design |
| Track A / Track B split clears dual-engine risk | +15 | B5 deferred correctly |
| Implementation not started (correct for this phase) | 0 | Score is architectural readiness |
| Deductions | −7 | R1 authorize + R2 PX4 harness still required before first move |
| Deductions | −5 | Track B still future work (acceptable; not required to clear 2.4 conflict) |

**Interpretation:** Safe to **authorize Track A implementation** under OFAT. Unsafe to revive Phase 2.4 shadow Admin→`/v2` wiring or Phase 2.1 copy-dual-own extraction.

---

## Explicit non-actions (this phase)

- No implementation code  
- No file moves / copies  
- No production modification  
- No `platform/` directory created yet  
- No Service Worker / routing / Admin layout changes  

Next action when authorized: execute **M0** only (harness + PX4 target), then stop for review before **M1**.
