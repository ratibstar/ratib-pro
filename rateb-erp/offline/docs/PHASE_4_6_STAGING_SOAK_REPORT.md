# Phase 4.6 — Enterprise Staging Soak Validation

**Date:** 2026-07-11 (re-run after staging availability claim)  
**Result:** **NO-GO for Procurement Offline**

## Staging environment (live evidence)

| Check | Result |
|-------|--------|
| Host | `https://dev.rateb.sa` |
| `RATEB_ENV` | `staging` (from staging `.env`) |
| ERP DB | `admin_rateb_dev` |
| DB connect | **OK** (SSH + Bootstrap) |
| Build marker | `rateb-erp-v1.0.1-maintenance-20260627` |
| Health | `GET /rateb-erp/public/erp-health.php` → `{"status":"ok"}` |
| Login / admin | HTTP 200 |
| `rateb-erp/offline/` on server | **ABSENT** |
| `public/assets/offline/rateb-offline.js` | **404** |
| Offline HTTP APIs | **404** |
| Offline tables (175–179 / POS sync) | **ALL MISSING** |
| Latest applied migration | `135_phase6_interbranch_execution.sql` (2026-06-26) |
| Local MySQL | Not used (validation targeted remote staging) |
| SSH | `admin@167.233.71.107` (deploy key) |

### Offline table probe (`admin_rateb_dev`)

```
DB=admin_rateb_dev
DB_CONNECT=OK
TABLE_MISSING rateb_offline_sync_queue
TABLE_MISSING rateb_offline_conflicts
TABLE_MISSING rateb_offline_entity_cursors
TABLE_MISSING rateb_offline_devices
TABLE_MISSING rateb_pos_sync_queue
TABLE_MISSING rateb_pos_sync_conflicts
```

## Pre-soak unit evidence (local; not a substitute for staging soak)

| Suite | Result |
|-------|--------|
| Foundation | 26/26 PASS |
| Inventory Offline | 33/33 PASS |
| HR Offline | 30/30 PASS |
| Queue durability 4.5.1 | 15/15 PASS |
| Phase 4.5 gate | 19/19 — Critical 0, High 0 |
| POS Offline sync/auth | 42/42 PASS |

## Scenario matrix (staging)

| # | Scenario | Status |
|---|----------|--------|
| 1 | 24-hour soak | **NOT RUN** — offline stack not on staging |
| 2 | Multi-terminal POS | **NOT RUN** |
| 3 | Multi-branch synchronization | **NOT RUN** |
| 4 | Inventory synchronization | **NOT RUN** |
| 5 | HR attendance synchronization | **NOT RUN** |
| 6 | Browser crash recovery | Unit model only (4.5.1) — **not live** |
| 7 | Power-loss recovery | Unit model only (4.5.1) — **not live** |
| 8 | Network disconnect/reconnect | **NOT RUN** |
| 9 | Queue durability (live) | Unit PASS — **not live** |
| 10 | Conflict resolution (live) | Unit PASS — **not live** |
| 11 | Replay ordering (live) | Unit PASS — **not live** |
| 12 | Performance under sustained load | **NOT RUN** |

## Findings

### Critical
- None in application code from this attempt.

### High
- **H-SOAK-001:** Staging DB is reachable, but required Phase 4.6 live scenarios (incl. 24h soak, multi-terminal/branch, Inv/HR sync, crash/power/network recovery, load) were **not executed** because the offline runtime is not present on staging.
- **H-DEPLOY-001:** Staging still on `v1.0.1-maintenance-20260627` with **no** `rateb-erp/offline/` tree and **no** offline SDK asset — cannot exercise POS/Inv/HR offline against staging.
- **H-MIG-001:** Offline / POS sync tables absent; migrations stop at `135_*`. Migrations 175–179 (and POS sync schema) not applied on `admin_rateb_dev`.

### Medium
- **M-SOAK-001:** Live staging soak still required once deploy + migrations land.
- **M-DEVICE-001:** Unknown device status allows unlock until cached.
- **M-WEBAUTHN-001:** Full COSE signature verify deferred.
- **M-IDEM-001:** Idempotency via notes `LIKE` markers.
- **M-DUAL-001:** Dual offline paths (POS vs enterprise).
- **M-TRANSPORT-001:** Transport RS allowlist POS-centric.
- **M-STAGING-500:** Site root `https://dev.rateb.sa/` returns HTTP 500 (login/admin/health OK) — staging shell instability.

### Low
- Ops: staging host exists (`dev.rateb.sa`) but offline program not promoted to that environment.

## Zero Data Loss verification
**CONDITIONAL (unit-proven / staging-unproven)**  
H-FLUSH-001 closed in 4.5.1 under unit crash/power-loss models. Live mid-flush, reconnect, multi-terminal durability on staging **not demonstrated**.

## Performance metrics
| Metric | Result |
|--------|--------|
| Sustained load (staging) | **Not measured** |
| 24h soak error rate | **N/A** |
| Push/replay latency p50/p95 | **N/A** |
| Queue backlog under load | **N/A** |

Local stress (unit only): Inv/HR ack/conflict sanitizer stresses and 5k delete-by-key durability models **PASS** — not staging performance evidence.

## Security review (Phase 4.6 staging scope)
| Item | Status |
|------|--------|
| Staging isolated from `admin_rateb-erp` | **PASS** (`admin_rateb_dev`) |
| Offline flags on staging | Not evaluable (code absent); must remain OFF until soak sign-off |
| Authz / tenant guards | Unit PASS; live staging **not exercised** |
| Device / WebAuthn residuals | Medium (M-DEVICE-001, M-WEBAUTHN-001) |
| Secrets in report | Redacted (`RATEB_ERP_DB_PASS=***`) |

## Production readiness
**Not ready** for enterprise offline production enablement. Staging host/DB exist, but offline code + schema + live soak evidence are missing.

## Go / No-Go for Procurement Offline
**NO-GO**

### Required to flip to GO
1. Deploy offline program (POS + Inventory + HR + SDK) to `dev.rateb.sa` (or designated staging).
2. Apply offline migrations 175–179 (+ POS sync tables) on `admin_rateb_dev` only.
3. Enable offline flags **only on staging** for the soak window.
4. Execute scenarios 1–12 with evidence (including ≥24h soak).
5. Re-issue Phase 4.6 with Critical=0, High=0 on **live** results.
