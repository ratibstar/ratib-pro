# Phase 6 — Enterprise Monitoring & Offline Operations

**Date:** 2026-07-11  
**Scope:** Read-only offline ops dashboards + monitoring API  
**Out of scope:** New ERP business modules, write actions, schema/API/business-logic changes  
**Flag:** `offline.monitoring` default **false** (`RATEB_OFFLINE_MONITORING`) — independent of master

---

## Repository audit

### Additive / offline-local

| Area | Change |
|------|--------|
| Feature flag | `offline/config/feature-flags.php` — `offline.monitoring=false` |
| Metrics | `OfflineMonitoringService` — aggregates queue, devices, conflicts, retries, replay, audit, worker, alerts, performance, readiness |
| API | `OfflineMonitoringApiController` + `offline-monitoring-api.php` — GET-only `/api/v1/offline/monitoring*` |
| Web UI | `OfflineOpsDashboardController` + `offline-web.php` — `offline/ops`, `offline/monitoring` |
| Views | `views/offline/ops/{index,disabled,forbidden}.php` |
| Wiring | `routes/api.php` + `routes/company.php` require monitoring/web route files |
| Tests | `OfflineMonitoringPhase6Test` — 18 checks |

### Explicitly unchanged

- Existing offline push / process / conflict resolve contracts
- ERP business modules (POS / Inventory / HR / Procurement replay logic)
- Database schema (no Phase 6 migrations)
- Client SDK (monitoring is server/ops)

### Coverage map (12 areas)

| # | Requirement | Surface |
|---|-------------|---------|
| 1 | Offline Monitoring Dashboard | Web `offline/ops` + API overview |
| 2 | Queue Health Monitor | `queueHealth` + UI section |
| 3 | Device Status | `deviceStatus` + UI |
| 4 | Synchronization Metrics | `synchronizationMetrics` + UI |
| 5 | Conflict Dashboard | `conflictDashboard` + UI |
| 6 | Retry Dashboard | `retryDashboard` + UI |
| 7 | Replay History | `replayHistory` + UI |
| 8 | Offline Audit Logs | Derived from queue errors / conflicts / devices |
| 9 | Background Worker Metrics | Flag + backlog + last-hour synced |
| 10 | Alerting | Threshold alerts in-dashboard (no email/SMS) |
| 11 | Performance Metrics | Success rate / throughput estimates |
| 12 | Production Readiness Dashboard | Score + checklist |

---

## Security audit

| Check | Result |
|-------|--------|
| `offline.monitoring` default OFF | **PASS** |
| Monitoring independent of master (ops can view backlog when sync OFF) | **PASS** |
| Web routes gated `pos.sync.manage` | **PASS** |
| API requires auth + `canManageSync` + monitoring flag | **PASS** |
| Monitoring API GET-only (no process/resolve/retry writes) | **PASS** |
| Controllers / service contain no mutating SQL | **PASS** |
| Tenant scoped via `company_id` | **PASS** |
| Existing offline write APIs unmodified | **PASS** |
| No new permission migration (reuses `pos.sync.manage`) | **PASS** |

**Residual (accepted):** In-dashboard alerts only — no pager/email channel in Phase 6.

---

## Monitoring report

### Surfaces

- **Web:** company app routes `offline/ops` and `offline/monitoring` (same read-only dashboard).
- **API:**  
  - `GET /api/v1/offline/monitoring`  
  - `GET /api/v1/offline/monitoring/queue`  
  - `GET /api/v1/offline/monitoring/devices`  
  - `GET /api/v1/offline/monitoring/conflicts`  
  - `GET /api/v1/offline/monitoring/alerts`  
  - `GET /api/v1/offline/monitoring/readiness`

### Alert thresholds

| Code | Severity | Condition |
|------|----------|-----------|
| `MIGRATION_REQUIRED` | critical | Offline tables missing |
| `QUEUE_FAILED_HIGH` | high | failed ≥ 25 |
| `QUEUE_DEPTH_HIGH` | high | depth ≥ 500 |
| `CONFLICTS_OPEN` | medium | open ≥ 20 |
| `RETRY_HOTSPOTS` | medium | retry_count ≥ 3 items ≥ 10 |
| `STALE_DEVICES` | low | active unseen ≥ 7d ≥ 5 |
| `MASTER_OFF_WITH_PENDING` | medium | master OFF + pending > 0 |

### Enablement

```env
RATEB_OFFLINE_MONITORING=1
```

Does **not** enable sync/replay. Master and module flags remain separate.

### Tests

| Suite | Result |
|-------|--------|
| Monitoring Phase 6 | **18/18 PASS** |
| Foundation | **26/26 PASS** |
| Phase 4.5 integration gate | **19/19 PASS** (Critical 0, High 0) |

Runner: `php offline/tests/run-offline-monitoring-tests.php`

---

## Production readiness score

| Dimension | Score | Notes |
|-----------|------:|-------|
| Additive architecture | 10/10 | No business/API/schema breaks |
| Read-only guarantee | 10/10 | GET + SELECT aggregates only |
| Authz / tenant scope | 9/10 | Reuses sync manage; no new RBAC slug |
| Ops coverage (12 areas) | 9.5/10 | All surfaces present; alerts in-UI only |
| Flag safety | 10/10 | Default OFF; independent of master |
| Test coverage | 9/10 | Unit/source gates; live soak optional |
| Observability depth | 8.5/10 | Derived audit (no dedicated audit table) |
| External alerting | 7/10 | Thresholds only; no email/SMS/webhook |

### Overall: **9.0 / 10 — GO (flag-gated)**

**Verdict:** Phase 6 is production-ready to deploy **with `RATEB_OFFLINE_MONITORING` left OFF** until ops enable it per environment. Enabling monitoring does not turn on offline sync.

**Enable when:** Staging/prod offline tables migrated (175–179) and operators with `pos.sync.manage` need visibility.

**Do not enable:** As a substitute for soak of POS/Inv/HR/Procurement sync flags — monitoring is visibility only.
