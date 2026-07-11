# Enterprise Baseline v1.2 — Development Policy (Mandatory)

**Architecture:** `RATEB-ENTERPRISE-BASELINE` **v1.2**  
**Applies to:** all RATEB ERP work after 2026-07-11  
**Authority:** Architecture Freeze — non-negotiable for agents and humans

---

## 1. Purpose

Protect the certified Offline Foundation v1.1 + Recruitment Online (15A) + Recruitment Offline (15B) stack from accidental redesign.

---

## 2. Absolute Prohibitions

1. **No Queue redesign** — do not rename fields, change key paths, or invent a second queue.
2. **No Replay redesign** — keep `OfflineReplayEngine` as dispatcher; do not replace with a new engine.
3. **No SDK breaking changes** — do not remove/rename `RatebOffline.*` public methods; bump only with explicit major plan.
4. **No Service Worker redesign** — keep ERP/POS coexistence; never add `clients.claim` to ERP SW; never intercept `/pos/*`.
5. **No IndexedDB breaking schema** — `DB_VERSION` 2 store set is frozen; additive stores require a versioned upgrade plan + tests.
6. **No Authentication redesign** — offline unlock stays local vault; never create PHP sessions offline.
7. **No RBAC redesign** — UI cache only; server authorization remains authoritative.
8. **No Master Data redesign** — read-only deltas; field allowlists required.
9. **No Inventory / HR / Procurement / Recruitment / POS redesign** — extend additively; do not rewrite existing adapters/replay services for style.
10. **No API or database breaking changes** under this baseline.
11. **No duplicated business logic** in offline replay, adapters, or ops-forms.

---

## 3. Mandatory Affirmative Rules

1. **Only additive modules** — new code paths, flags, ops entries, adapters.
2. **Only additive migrations** — `CREATE TABLE IF NOT EXISTS` / additive columns; no destructive rewrite of frozen tables.
3. **Replay MUST reuse domain services** — thin `{Module}OfflineReplayService` only.
4. **Feature flags default OFF** — every new capability; require `offline.enabled` unless an existing documented exception (e.g. monitoring independence).
5. **Tenant + branch guards** — every write replay asserts ownership before domain call.
6. **Conflict resolver extension** — add `resolve{Module}()`; do not replace LWW core.
7. **Tests required** — new module gate runner; existing phases must remain GREEN.
8. **Ops pages allowlisted** — never cache/enqueue arbitrary ERP pages.
9. **Respect sync policy** — `client_queue_max`, batch size, retries, debounce; no parallel scheduler invention.
10. **Document** — phase report under `offline/docs/` or `docs/` before production enablement.

---

## 4. Compatibility Rules

1. Clients with older bundles must not break when new flags appear (additive `mergeFlags`).
2. Server must safely `skip` / `reject` disabled or unknown module actions.
3. Idempotency keys remain authoritative for duplicate suppression.
4. Public SDK version string remains **14.2.0** until a deliberate baseline bump (v1.3+).

---

## 5. Change Control

| Change type | Allowed? | Gate |
|-------------|----------|------|
| New Tier-1 offline module (flags OFF) | Yes | Pattern + tests |
| New flag key | Yes | Default OFF |
| Rename queue field | **No** | — |
| Change IDB store keyPath | **No** | — |
| Rewrite ReplayEngine | **No** | — |
| Bugfix inside frozen module (behavior-preserving) | Yes | Regression GREEN |
| Enable flag in production | Ops only | Pilot checklist |

---

## 6. Agent / Contributor Checklist

Before merging:

- [ ] No edits under frozen redesign categories above  
- [ ] New offline writes go through queue + domain services  
- [ ] Flags default OFF  
- [ ] Regression suite GREEN (foundation + affected modules)  
- [ ] Phase doc updated  

**Reference:** `ENTERPRISE_BASELINE_V1_2_CERTIFICATION.md`
