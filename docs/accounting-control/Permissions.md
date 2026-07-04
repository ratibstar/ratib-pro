# Permissions

Migration: `rateb-erp/migrations/151_accounting_control_permissions.sql`

| Slug | Screens |
|------|---------|
| `accounting.dashboard` | Dashboard, Settings, Timeline, Notifications |
| `accounting.events` | Event Store |
| `accounting.replay` | Replay Center |
| `accounting.audit` | Audit Center |
| `accounting.projections` | Projections |
| `accounting.consolidation` | Consolidation |
| `accounting.drift` | Drift Detection |
| `accounting.reconciliation` | Reconciliation |
| `accounting.integrity` | Financial Integrity |
| `accounting.system_health` | System Health, Diagnostics |

Granted to `company-full-access` and `hq_admin` by default.

Sidebar entry requires `accounting.dashboard`.
