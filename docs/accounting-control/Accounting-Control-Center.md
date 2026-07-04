# Accounting Control Center

Enterprise admin UI for event-driven accounting (Phase 6–7).

## URL

- Company ERP: `/rateb-erp/public/{app}/accounting-control`
- Admin platform: `/admin/accounting-control`

## Sections (14)

Dashboard, Event Store, Replay, Audit, Projections, Consolidation, Drift, Reconciliation, Integrity, Activity Timeline, Notifications, Settings, System Health, Diagnostics.

## Architecture

- **UI:** `rateb-erp/views/admin/accounting-control/`
- **Assets:** `rateb-erp/public/assets/accounting-control/`
- **Controller:** `AccountingControlController`
- **Read facade:** `AccountingControlService` (Phase 6)
- **Phase 7 dashboards:** `AccountingControlPhase7Service`
- **Exports:** `AccountingControlExportService`
- **Database:** `admin_rateb` via `AccountingConnectionFactory`

Engines (Gateway, Event Store, Replay, Projection, etc.) are **not** modified by this UI layer.

## Permissions

See `Permissions.md`.

## API

JSON proxy: `GET/POST …/accounting-control/api/{resource}`

Resources: `dashboard`, `section`, `events`, `replay`, `audit`, `projections`, `consolidation`, `drift`, `reconciliation`, `integrity`, `health`, `settings`, `search`, `timeline`, `notifications`, `diagnostics`.

Add `?detail=1` on projections, consolidation, drift, reconciliation, integrity, replay for enriched dashboards.

Exports: `?export=csv|excel|json|pdf` on list resources.

## Operations

1. Apply enterprise migrations on `admin_rateb` (Phases 3–5).
2. Set `ACCOUNTING_*=1` in `.env`.
3. Run ERP migration `151_accounting_control_permissions.sql`.
4. Open **Diagnostics** for PASS/WARN/FAIL checklist.
5. Populate data via Event Store → Replay → Projections rebuild.

## Troubleshooting

- Empty dashboards: no events/snapshots yet — rebuild projections.
- Gateway OFF: set `ACCOUNTING_GATEWAY_ENABLED=1` or enable event store.
- Missing `payload` column: schema catchup runs on bootstrap; manual: `public/run-accounting-schema-catchup.php`.
