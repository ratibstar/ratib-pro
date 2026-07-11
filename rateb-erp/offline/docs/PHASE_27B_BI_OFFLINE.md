# Phase 27B — Enterprise Business Intelligence Offline

**Status:** Core files implemented (Tier-1 drafts, flags default OFF)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (banner note only; contracts unchanged)  
**IndexedDB:** **DB_VERSION = 2** — **NOT bumped**  
**Online prerequisite:** Phase **27A** (`rateb_bi_*`, `Bi*Service`, `BusinessIntelligenceWorkflowService`)

## Executive Summary

Phase 27B adds additive Offline support for Enterprise BI. Client queues via `RatebOffline.bi()` → Offline Queue `module=bi` → `OfflineReplayEngine` → `BiOfflineReplayService` → **Phase 27A services only** → `rateb_bi_*`.

No duplicated business logic. No SQL from Replay. No SDK/IDB/Queue schema redesign. All feature flags **OFF** by default.

## Architecture

```
RatebOffline.bi()
  → Offline Queue (module = bi)
    → OfflineReplayEngine
      → BiOfflineReplayService
        → Phase 27A services ONLY
          → rateb_bi_*
```

## Replay

Delegates only to:

- `BiDashboardService`, `BiKpiService`, `BiReportService`, `BiWidgetService`
- `BiDatasetService`, `BiAlertService`, `BiScheduleService`, `BiExportService`
- `BiTrendService`, `BiForecastService`, `BiAnalyticsScopeService`
- `BusinessIntelligenceWorkflowService` (early statuses only offline)
- `BiTimelineService` (`note.create`)

Rejected: delete, binary, notifications, email/SMS, payments, publish, download.

Offline workflow limited to: `draft`, `archived` (never `published`).

## Queue

**module = `bi`**

Supported: `dashboard.create`, `kpi.create`, `report.create`, `widget.create`, `dataset.create`, `alert.create`, `schedule.create`, `export.create`, `trend.create`, `forecast.create`, `scope.create`, `workflow.transition`, `note.create`.

## Feature Flags (default OFF)

| Flag | Env |
|------|-----|
| `offline.bi` | `RATEB_OFFLINE_BI` |
| `offline.bi.workflow` | `RATEB_OFFLINE_BI_WORKFLOW` |
| `offline.bi.masterdata` | `RATEB_OFFLINE_BI_MASTERDATA` |

Requires `offline.enabled`.

## Security

Tenant + branch guards (`BiOfflineTenantGuard`), existing Auth/RBAC, server-authoritative replay, idempotency via notes markers (`[offline:key]`), offline cache UI-only.

## Tests

```bash
php offline/tests/run-bi-offline-tests.php
```

Target: **~26 PASS** / GATE CLEAR (after parent wires flags / registry / bundle).
