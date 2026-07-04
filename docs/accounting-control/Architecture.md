# Architecture

```
Browser → AccountingControlController (ERP session + CSRF)
        → AccountingControlService / Phase7Service / ExportService
        → Existing engines (read-only or delegated writes with confirm)
        → admin_rateb tables
```

## Layer rules

| Layer | Role |
|-------|------|
| Views + JS | Dashboards, charts, exports, i18n |
| Controller | Routes, permissions, API proxy |
| Admin services | SQL read queries, payload parsing |
| Engines | Business logic (unchanged in Phase 7) |

## Data flow

- **Events:** `accounting_events`
- **Replay:** `AccountingReplayEngine` via service
- **Projections:** snapshot tables + `ProjectionRepository`
- **Consolidation:** consolidated_* tables
- **Drift:** `accounting_drift_reports`
- **Reconciliation:** `accounting_reconciliation_reports`, `accounting_correction_log`
- **Integrity:** golden ledger resolver + evidence packs
