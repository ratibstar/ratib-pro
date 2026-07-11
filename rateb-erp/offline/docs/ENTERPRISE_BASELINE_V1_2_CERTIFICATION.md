# RATEB ERP — Enterprise Baseline v1.2
# Official Architecture Freeze & Certification

**Architecture ID:** `RATEB-ENTERPRISE-BASELINE`  
**Architecture Version:** **v1.2**  
**Certification Date:** 2026-07-11  
**Predecessor:** Enterprise Offline Foundation **v1.1**  
**Final Decision:** **APPROVED FOR ARCHITECTURE FREEZE**

This document is the permanent architectural reference. All future development MUST build **above** this baseline. Frozen contracts MUST NOT be redesigned.

---

## 1. Executive Summary

Enterprise Baseline v1.2 certifies the stacked delivery of:

| Layer | Version / Phase | Role |
|-------|-----------------|------|
| Offline Foundation | v1.1 | Queue, ReplayEngine, SDK, IDB v2, SW, Auth, RBAC, Master Data |
| Daily Ops Pilot | Phase 14 / 14.2 | Ops allowlist, GRN additive |
| Recruitment Online | Phase 15A | Domain services (sole business logic) |
| Recruitment Offline | Phase 15B | Additive Tier-1 module |

**No business features were added in this certification phase.**  
**No frozen subsystem was redesigned.**

---

## 2. Architecture Freeze Matrix

| Field | Value |
|-------|--------|
| **Architecture ID** | `RATEB-ENTERPRISE-BASELINE` |
| **Architecture Version** | `v1.2` |
| **Offline Foundation** | `v1.1` (frozen; unchanged by certification) |
| **SDK Bundle** | `14.2.0` |
| **IndexedDB** | `rateb_erp_offline` / `DB_VERSION = 2` |
| **Recruitment Online** | Phase 15A / migration `181` |
| **Recruitment Offline** | Phase 15B / flags default OFF |

### Frozen Components

| Component | Freeze rule |
|-----------|-------------|
| Offline SDK public API | Additive only; no renames / removals |
| Queue field contract | Never rename / redesign enqueue |
| OfflineReplayEngine architecture | Additive module branches only |
| Service Workers (`rateb-offline-sw.js`, `pos-sw.js`) | Coexistence rules frozen; no `clients.claim` on ERP SW |
| IndexedDB store layout (v2) | No breaking schema; additive stores need new DB_VERSION + migration plan |
| Offline Authentication (unlock vault) | Local unlock only; no PHP session offline |
| Offline RBAC UI cache | Cache only; never replaces server authz |
| Master Data delta policy | Read-only; allowlist + field maps |
| Inventory / HR / Procurement / Recruitment / POS offline adapters | Pattern frozen; extend via new modules |
| Feature flag keys (existing) | Keys immutable; new keys additive, default OFF |
| Recruitment online domain services | Sole business logic for recruitment |

### Supported Modules (Baseline v1.2)

| Tier | Modules |
|------|---------|
| T0 | POS |
| T1 | Inventory, HR, Procurement (+ GRN), Recruitment |
| T2 | ERP Shell (`offline.read_cache`) |
| T3 | Platform (auth unlock, RBAC cache, master data) |

### Unsupported Offline (by design)

Payroll approvals, leave approvals, procurement approvals, payments, accounting posting, government submission, binary attachment upload offline, generic form-post of non-allowlisted pages.

### Extension Rules

1. New domain capability → **online domain service first**.
2. Offline → thin adapter + queue action + replay service that **delegates only** to domain services.
3. New flag key → default **OFF**; require `offline.enabled` (except monitoring independence already established).
4. New migrations → **additive only**.
5. Tests + gate runner required before merge.

### Compatibility Rules

1. SDK major surface remains `RatebOffline` at **14.2.0** until a deliberate, documented major bump.
2. Clients must tolerate unknown flag keys (mergeFlags additive).
3. Server must reject unknown modules/actions or skip safely — never crash the queue worker.
4. Dual SW: ERP SW never owns `/pos/*`.

### Forbidden Changes

- Queue redesign / field renames  
- ReplayEngine redesign  
- SDK breaking API  
- SW redesign / claiming POS clients  
- IDB breaking schema without versioned migration  
- Duplicating business rules inside offline replay  
- Flags defaulting ON  
- API / DB breaking changes under this baseline  

---

## 3. Version Matrix (no skew)

| Artifact | Certified version | Evidence |
|----------|-------------------|----------|
| Architecture | **v1.2** | This document |
| Offline Foundation | **v1.1** | Frozen under v1.2 |
| SDK / Bundle | **14.2.0** | `offline/client/core/sdk.js`, `public/assets/offline/rateb-offline.js` |
| IndexedDB | **DB_VERSION 2** | `offline/client/db/schema.js` |
| Offline SQL | Migrations **175–179** (+ warehouse `180` / offline `004`) | `migrations/`, `offline/migrations/` |
| Recruitment Online | Migration **181** | `migrations/181_recruitment_platform.sql` |
| Sync policy | `client_queue_max=500`, `batch_size=50`, `max_retries=5` | `offline/config/sync-policy.php` |

**Version skew check:** SDK source, public bundle header, and Phase 15B tests all require **14.2.0** + IDB **2** — **PASS**.

---

## 4. Contract Certification

### Queue Contract (FROZEN)

Required fields (never rename):

`client_id`, `idempotency_key`, `module`, `action`, `payload`, `occurred_at`, `status`, `retry_count`, `seq`

### Replay Contract (FROZEN)

```
Queue row → OfflineReplayEngine → {Module}OfflineReplayService → Domain Service → DB
```

Statuses: `synced` | `conflict` | `failed` | `skipped`

### SDK Public API (FROZEN surface; additive OK)

`RatebOffline.init`, `mergeFlags`, `flags`, `version`, `is*Enabled`, `queue`, `transport`, `connectivity`, `pos`, `inventory`, `hr`, `procurement`, `recruitment`, `opsForms`, `shell`, `auth`, `rbac`, `masterData`, `schema`, `deltaPull`

### Offline Adapter API Pattern (FROZEN)

`isActive`, `enqueue*`, optional `pull*Directory`, `sync` — **no business validation** in adapters.

### Feature Flag Keys (FROZEN set + additive)

See `offline/config/feature-flags.php`. All write/module flags default **false**.

### Service Worker Contract (FROZEN)

- ERP SW: assets + allowlisted ops pages; never `/pos/*`; no API/HTML-auth cache; no `clients.claim`
- POS SW: remains authoritative for POS

### IndexedDB Store Layout (FROZEN v2)

`sync_queue`, `sync_meta`, `entity_cache`, `catalog_index`, `form_drafts`, `snapshots`, `conflicts`, `cursors`, `auth_vault`

### Replay Dispatch Pattern (FROZEN)

Additive `if ($module === '…')` branches in `OfflineReplayEngine` / `OfflineQueueService::processPending`.

### Tenant Guard Pattern (FROZEN)

`{Module}OfflineTenantGuard` — company / branch / entity ownership before domain call.

### Conflict Resolver Pattern (FROZEN)

`OfflineConflictResolverService::resolve*` — server-newer LWW + module-specific expected status/qty checks.

---

## 5. Security Certification

| Control | Status |
|---------|--------|
| Tenant isolation (`company_id`) | PASS — guards + TenantContext |
| Branch isolation | PASS — branch_mismatch guards |
| ACTIVE device policy | PASS — device registry / push gates |
| Offline unlock | PASS — vault local only; no PHP session |
| RBAC UI cache | PASS — never replaces server authz |
| Master Data read-only | PASS — redacted fields |
| Replay authorization | PASS — flags + authz + domain services |
| Queue validation | PASS — sanitizer strips url/method/headers |
| Audit / monitoring | PASS — ops monitoring flag-gated |

**Security Score: 9.2 / 10** (residuals: WebAuthn COSE completeness; notes-marker idempotency is soft)

---

## 6. Performance Certification

| Control | Certified value |
|---------|-----------------|
| `client_queue_max` | 500 |
| Server batch | 50 |
| Max retries / backoff | 5 / `[30,60,120,300,600]` |
| Scheduler | Existing replay scheduler only (no new scheduler) |
| Delta sync | Cursor-based; client-owned cursor |
| Cache TTL | catalog 24h / entity 12h (policy) |

**Performance Score: 9.0 / 10** (residual: long-lived IDB growth needs ops TTL prune in pilot)

---

## 7. Regression Certification (2026-07-11)

| Gate | Result |
|------|--------|
| Foundation | **26/26** |
| Inventory | **33/33** |
| HR | **30/30** |
| Procurement | **31/31** |
| GRN 14.2 | **CLEAR 0/13** |
| Recruitment Offline 15B | **25/25** |
| ERP Shell 10 | **CLEAR 0/17** |
| Auth 11 | **CLEAR 0/14** |
| RBAC 12 | **CLEAR 0/17** |
| Master Data 13 | **CLEAR 0/18** |
| Pilot 14 | **CLEAR 0/12** |
| Phase 13.1 | **CLEAR 0/13** |
| Queue durability | **15/15** |
| Recruitment Online 15A | **CLEAR** (foundation-contract assertion aligned to v1.2) |

**Regression Score: GREEN — all required gates CLEAR**

---

## 8. Technical Debt Register

| ID | Severity | Item | Blocks production? |
|----|----------|------|--------------------|
| TD-V12-001 | Medium | Dual SW coexistence (ERP + POS) requires careful registration order | No |
| TD-V12-002 | Medium | WebAuthn COSE / credential edge cases in offline unlock | No (flag OFF) |
| TD-V12-003 | Medium | Notes/reason marker idempotency (`[offline:key]`) is soft, not a dedicated column | No |
| TD-V12-004 | Medium | Transport RS allowlist historically POS-centric; Tier-1 uses explicit adapters | No |
| TD-V12-005 | Low | Passport online API create-only; offline `passport.update` maps to create | No |
| TD-V12-006 | Low | No countries master-data table for recruitment | No |
| TD-V12-007 | Low | Skills/languages delta without `updated_at` (id cursor) | No |
| TD-V12-008 | Low | IDB growth under long pilot without prune job | No for canary |

**Critical / High debt blocking production under flags OFF:** **None**

---

## 9. Readiness Scores

| Dimension | Score | Notes |
|-----------|-------|-------|
| Architecture | **9.6 / 10** | Clear Tier-1 pattern proven across Inv/HR/Proc/Rec |
| Security | **9.2 / 10** | Tenant/branch/device/flags solid |
| Performance | **9.0 / 10** | Limits certified; prune ops residual |
| Offline Readiness | **9.4 / 10** | Foundation + Tier-1 complete; flags OFF safe |
| Enterprise Readiness | **9.3 / 10** | Baseline freeze + policies published |
| Pilot Readiness | **APPROVED** | Enable flags per tenant after migrations |
| Production Readiness (code) | **APPROVED** | Ship with flags **OFF** |
| Production Readiness (full offline pilot) | **CONDITIONAL** | Requires explicit flag enable + soak |

---

## 10. Future Module Roadmap (recommended order)

1. **Accounting drafts (read/browse + journal draft enqueue)** — high value; must reuse existing accounting services; never post offline.  
2. **CRM / Customers write Tier-1** — customer directory already in master data; natural extension.  
3. **Projects / Tasks drafts** — operational, low payment risk.  
4. **Assets / Maintenance work-order drafts** — field ops benefit from offline.  
5. **Recruitment expansion** — agency CRUD offline, richer passport update API online first, then offline.  
6. **Approvals / Payments / Gov submission** — **last**; require new online-first contracts; remain unsupported offline until explicitly designed.

**Why this order:** Prefer modules that already have domain services and draft-safe semantics; defer money movement and irreversible government actions.

---

## 11. Repository Evidence

| Path | Role |
|------|------|
| `offline/docs/ENTERPRISE_BASELINE_V1_2_CERTIFICATION.md` | This certification |
| `offline/docs/ENTERPRISE_BASELINE_V1_2_DEVELOPMENT_POLICY.md` | Mandatory rules |
| `offline/docs/ENTERPRISE_BASELINE_V1_2_EXTENSION_GUIDE.md` | How to add modules |
| `offline/docs/PHASE_15B_RECRUITMENT_OFFLINE.md` | 15B evidence |
| `docs/PHASE_15A_RECRUITMENT_ONLINE.md` | 15A evidence |
| `offline/config/feature-flags.php` | Frozen flag keys |
| `offline/config/modules.php` | Tier + operations registry |
| `offline/config/sync-policy.php` | Performance limits |
| `offline/client/core/sdk.js` | SDK 14.2.0 |
| `offline/client/db/schema.js` | IDB v2 |
| `offline/tests/run-*-tests.php` | Regression gates |

---

## 12. Final Decision

**Enterprise Baseline v1.2 is CERTIFIED and FROZEN.**

- Future development MUST be **additive**.  
- Frozen contracts MUST NOT be modified.  
- Every new capability MUST ship with a Feature Flag default **OFF**.  
- Offline Replay MUST reuse online domain services only.

**Signed status:** APPROVED FOR ARCHITECTURE FREEZE — Baseline v1.2
