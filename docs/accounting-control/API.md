# API Reference

Base: `{app}/accounting-control/api/{resource}`

Headers for POST: `X-CSRF-Token`, `Content-Type: application/json`

## GET resources

| Resource | Params | Returns |
|----------|--------|---------|
| `dashboard` | `company_id` | KPI cards + charts |
| `section` | `section`, filters | Section KPI cards |
| `events` | filters, `page`, `export=csv` | Paginated events |
| `replay` | filters, `detail=1` | Queue + history + stats |
| `audit` | filters | Logs + evidence |
| `projections` | `type`, `detail=1`, filters | Parsed rows + history |
| `consolidation` | `type`, `detail=1`, filters | Hierarchy + eliminations |
| `drift` | `detail=1`, filters | Breakdown + actions |
| `reconciliation` | `detail=1`, filters | Workflow + timeline |
| `integrity` | `detail=1`, filters | Full integrity center |
| `search` | `q` (min 2 chars) | Cross-resource results |
| `timeline` | filters | Unified activity feed |
| `notifications` | — | Recent alerts |
| `diagnostics` | — | PASS/WARN/FAIL checks |
| `health` | — | Pipeline health |
| `settings` | — | Feature flags |

## POST resources

| Resource | Body | Action |
|----------|------|--------|
| `replay` | `confirm:1`, filters | Execute replay |
| `projections` | `action:rebuild`, `confirm:1` | Rebuild snapshots |
| `consolidation` | `confirm:1`, filters | Run consolidation |
| `drift` | filters | Detect drift |
| `reconciliation` | filters or `action:execute` | Reconcile / execute correction |

## Site REST (alternate)

`api/accounting/{events,replay,audit,projections,consolidation,drift,reconciliation,integrity}.php`
