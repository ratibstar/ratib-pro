# Phase 4 — Enterprise Financial Operating System Flow

```
Accounting Event
       ↓
Event Store (accounting_events)          ← immutable source log
       ↓
AccountingEventPipeline
       ├── Idempotency check
       ├── Audit log
       ↓
AccountingGateway (UNCHANGED)
       ↓
Adapters → legacy DB writes (rateb_* | financial_* | control_* | ledger_*)
       ↓
AccountingProjectionHook (NEW)           ← async-safe, optional
       ↓
AccountingProjectionEngine (NEW)
       ├── AccountingNormalizer (read all systems)
       ├── AccountingReportService
       ↓
Materialized Snapshots (NEW, immutable when period closed)
       ├── accounting_trial_balance_snapshots
       ├── accounting_balance_sheet_snapshots
       ├── accounting_profit_loss_snapshots
       └── accounting_cashflow_snapshots
       ↓
AccountingConsolidationEngine (HQ VIEW)
       ├── accounting_consolidated_trial_balance
       ├── accounting_consolidated_balance_sheet
       └── accounting_consolidated_profit_loss
       ↓
CFO Reports / Drift Detection / Period Close
```

## Period Close

```
AccountingPeriodCloser::closePeriod()
  1. Generate all snapshots
  2. Run consolidation
  3. Record accounting_period_closures (status=closed)
  4. Future snapshot writes blocked for that period
```

## Drift Detection

```
AccountingDriftDetector::detectDrift()
  Compare: accounting_events ↔ idempotency ↔ trial balance totals
  Output: accounting_drift_reports
```

## Rebuild (never deletes event store)

```
AccountingSnapshotRebuilder::rebuild()
  1. Read accounting_events (processed)
  2. Re-normalize all 4 systems
  3. Replace snapshot tables only
```
