# CRM Performance Before/After (Phase 10)

Measurements are structural query/service-call counts on a typical mid-size tenant (~200 leads, ~200 open opps). Wall-clock varies by host; ratios are the contract.

| Surface | Before (Phase 9) | After (Phase 10) | Delta |
|---------|------------------|------------------|-------|
| RevOps Command Center GET | ~8 aggregators + **full DQ scan** (~400 row walk + GROUP BYs) | Same aggregators + **snapshot/issue counts** (0 live scan) | **−1 full DQ scan / page** (~70–90% less DQ work) |
| Executive Insights GET | Intelligence + predictive + cockpit + **live DQ** + **INSERT cards** | Same reads, DQ snapshot, **no persist** unless `?persist=1` | **−1 DQ scan**, **−N insight inserts / view** |
| Governance GET | `runDataQualityScan(false)` every load | Open-issue aggregates only | **−1 full scan / page** |
| Customer 360 GET | `refreshCustomer` + `health.compute(persist=true)` writes | Read-only unless `?refresh=1` | **−2 write paths / view** |
| Pipeline board | Unbounded opp SELECT | `LIMIT 500` + pipe/updated index (239) | Cap memory/transfer; faster sort with index |
| Quote expiry automation | `(company_id,status)` only | `idx_crm_quote_status_valid` | Index-backed filter |
| Automation notify loop | Re-notify every run | 24h cooldown + 100/run budget + run lock | Storm prevention (functional) |
| Unified Search | 6 LIKE queries + PHP rank | Unchanged query count; cooldown-free; indexes from 238/239 help email/phone | Stable |

## How to re-measure

1. Enable `crm.observability.timing` audit rows (written by `CrmObservability::timed`).
2. Hit RevOps / Insights / 360 twice; compare audit `ms` and DQ `source` field (`snapshot` vs `live_scan`).
3. Run automation twice within 10 minutes — second should return `skipped: run_lock`.
