# Phase 4.6.2 — Enterprise Staging Soak

**Date:** 2026-07-11  
**Host:** `https://dev.rateb.sa` / DB `admin_rateb_dev`  
**Harness:** `offline/tests/run-phase462-staging-soak.php`  
**Flags:** Process-local only for soak window — **`.env` remains without `RATEB_OFFLINE_*`**  
**Result:** **CONDITIONAL GO** for Procurement Offline (Critical 0 / High 0)

---

## Scenario matrix

| # | Scenario | Result |
|---|----------|--------|
| 1 | Inventory Offline | **PASS** — push + stock_movement replay `synced`; idempotent re-push → conflict |
| 2 | HR Offline | **PASS** — attendance create `synced` + row persisted; leave draft `synced` |
| 3 | Queue durability | **PASS** — SDK `removeMany` / no `Stores.clear(QUEUE)`; clearable excludes conflict/rejected |
| 4 | Browser refresh recovery | **PASS** — model keeps rejected/conflict/pending |
| 5 | Network disconnect/reconnect | **PASS** — pending retained while master OFF; synced after reconnect |
| 6 | Power-loss recovery | **PASS** — mid-queue rows retained; client atomic delete path present |
| 7 | Replay ordering | **PASS** — FIFO `ORDER BY created_at ASC, id ASC` |
| 8 | Background synchronization | **PASS** — `OfflineBackgroundSync` processed ≥1 with Inv/HR flags |
| 9 | Multi-branch synchronization | **PASS** — tenant deny + company5 branch 5 vs 6 isolation |
| 10 | Long-running queue | **PASS** — 500 pending → 500 processed, 0 left |
| 11 | Performance under load | **PASS** — see metrics |
| 12 | Zero Data Loss | **PASS** |
| — | POS multi-terminal / sale / devices | **SKIPPED** — `modules/pos` absent (non-failing per rules) |

**Checks:** 46/46 passed

---

## Findings

### Critical
- None

### High
- None

### Medium
- **B-POS-001** — POS scenarios blocked by missing `modules/pos` on staging (explicitly non-failing).
- **M-SOAK-24H** — Continuous ≥24h soak not executed in this run (long queue 500-item drain used instead).
- Carried residuals (unchanged): M-DEVICE-001, M-WEBAUTHN-001, M-IDEM-001, M-DUAL-001, M-TRANSPORT-001

### Low
- None new from this run

---

## Zero Data Loss
**PASS (staging evidenced)**

- Server queue rows survive disconnect / mid-enqueue
- Ack contract: only accepted+duplicate clearable
- Deployed SDK: Phase 4.5.1 durable delete-by-key (no clear-all flush)
- No unknown queue statuses after soak

---

## Performance metrics (live staging)

| Metric | Value |
|--------|-------|
| Long queue enqueue (500) | ~513 ms (~760 items/s) |
| Long queue process (500) | ~341 ms |
| Pending after enqueue | 500 |
| Pending final | 0 |
| Ack/conflict CPU 200 cycles | ~0.5 ms |
| Inventory movement | synced |
| HR attendance | synced |

---

## Compatibility note (deployed during soak)

Staging ERP core lacks `Database::liveTableHasColumn()`. Additive offline helper `OfflineSchema::hasColumn()` was deployed so Inv/HR services work on v1.0.1 without modifying Core ERP. **Not a new product feature** — staging compatibility shim inside `offline/`.

---

## Production readiness
**CONDITIONAL**

- Inv/HR enterprise offline soak evidenced on staging
- Feature flags remain **OFF** in `.env`
- POS not on this staging host
- 24h continuous soak still recommended before production enablement

---

## GO / NO-GO for Procurement Offline

**CONDITIONAL GO**

| Gate | Status |
|------|--------|
| Critical | 0 |
| High | 0 |
| Inv/HR soak | PASS |
| ZDL | PASS |
| POS on this host | Blocked (Medium, non-failing) |
| Flags OFF post-soak | PASS |

Procurement Offline may proceed to planning/implementation under the standing architecture rules (additive, flags default OFF). Complete POS staging deploy + optional 24h soak before production flag enablement.
