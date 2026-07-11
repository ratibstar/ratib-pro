# Phase 27A — Enterprise Business Intelligence & Analytics Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline BI here — deferred to Phase **27B**.  
**Migration:** `migrations/193_business_intelligence_platform.sql`

## Executive Summary

Phase 27A adds a **tenant-scoped Enterprise BI & Analytics platform** on additive `rateb_bi_*` tables for dashboards, widgets, KPIs/snapshots, saved reports, report runs, datasets/soft-links, drilldowns, trends, forecast metadata, alerts, schedules, exports, analytics scopes (company/branch/department), comments, timeline, status history, audit logs, favorites, and tags.

It **soft-links only** to CRM, Projects, Accounting, HR, Payroll, Manufacturing, Assets, Procurement, Quality, Documents, POS, Inventory, and Recruitment via `source_module` / `linked_module` metadata — never ALTERs those modules. UI routes live under `/bi/*` guarded by `rateb_erp_mw('bi', …)`.

## Repository Audit (pre-27A)

| Area | Status | Action |
|------|--------|--------|
| `rateb_bi_*` / `/bi/*` | Absent | **Greenfield** |
| Legacy `reports` / AnalyticsReports | Exist | **Preserved** — parallel route |
| ERP operational modules | Exist | Soft-link metadata only |
| Offline Foundation / Queue / Replay / SDK / IndexedDB | Frozen | **Not modified** |

## Architecture

```
Controllers (thin, company/bi)
  → Domain services (BiDashboard*, BiKpi*, BiReport*, …)
  → BusinessIntelligenceWorkflowService ONLY for workflow_status
    → Models (Bi* → rateb_bi_*)
      → Database
```

## Workflow

**Only via `BusinessIntelligenceWorkflowService` (dashboard / report / kpi):**

`draft → published → archived`

## Security

- Tenant + branch scoped rows
- `public_uuid` on every table
- Optimistic locking via `version`
- CSRF on mutating forms
- Soft delete (`deleted_at`)
- Permissions: `bi.view`, `bi.create`, `bi.update`, `bi.publish`, `bi.export`, `bi.admin`, `bi.manage`

## Migration

`193_business_intelligence_platform.sql` — 21 additive `CREATE TABLE IF NOT EXISTS rateb_bi_*` + permission seed + role grants. No `ALTER` of CRM/Projects/Accounting/HR/Payroll/MFG/Assets/Procurement/Quality/DMS/POS/Inventory/Recruitment tables.

## Offline readiness (for later Phase 27B)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Dashboard / KPI / Report create | domain services | YES | Starts `draft` |
| Workflow transition | `BusinessIntelligenceWorkflowService` | YES | Must call service |
| Widget / dataset / alert / schedule | domain services | YES | Metadata |
| Export request | `BiExportService` | YES | Metadata only |
| Soft-link modules | N/A | NO mutate | Metadata refs only |
| Offline adapter / replay | N/A | Deferred 27B | Not shipped in 27A |

## Performance

- Paginated lists (`LIMIT`/`OFFSET` + `COUNT`)
- Timeline append-only indexes
- Company-scoped queries with `deleted_at IS NULL`
- Workflow status indexes on dashboards / reports / KPIs
- No heavy multi-module joins

## Regression

- Legacy `reports` routes unchanged
- Offline Foundation markers (`DB_VERSION = 2`, SDK `14.2.0`) intact
- No modifications to Queue, Replay, Service Worker, Auth, or RBAC core

## Tests

```bash
php tests/bi/run-business-intelligence-phase27a-tests.php
```

Target: **13/13 PASS**

## Production Readiness

- Additive migration with idempotent `CREATE TABLE IF NOT EXISTS`
- Permission seeds with `ON DUPLICATE KEY UPDATE`
- Thin controllers + domain services
- Workflow authority centralized
- EN/AR translations and sidebar navigation wired
- Ready for migration apply on production after deploy
