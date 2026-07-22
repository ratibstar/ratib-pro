# RATEB ERP — Phase AI Accounting Performance Report

**Verdict: PASS** (primary targets met; measured on production company_id=22)

Architecture remained locked: no controller/URL/middleware/RBAC/schema/business-rule changes. Optimizations are request-scoped memoization, schema probe caching, COA map batching, dirty-check updates, and skip-when-complete COA ensure.

---

## Before / After (same audit tool)

| Metric | Before (AH) | After (AI) | Target | Result |
|--------|-------------|------------|--------|--------|
| Controller `index` wall | **180.2 ms** | **38.7 ms** | — | −78.5% |
| `AccountingDashboardService::build` | **151.5 ms** | **36.9 ms** | &lt;60 ms | **PASS** |
| SQL count | **550** | **60** | &lt;150 | **PASS** |
| SQL time | **103.1 ms** | **27.4 ms** | — | −73.4% |
| `SHOW COLUMNS` | **62** | **8** | 1 | Partial* |
| `findCoaByCode` SQL | **130** | **0** | &lt;10 | **PASS** |
| `touchCoaRow` UPDATEs | **117** | **0** | 0 steady | **PASS** |
| `ensureDefaultAccounts` | **27.5 ms / 113 SQL** | **0.73 ms / 1 SQL** | ~0 | **PASS** |
| Duplicate SQL extras | **496** | **9** | — | −487 |

\*62→8: duplicate per-request probes eliminated. Remaining 8 are distinct tables (7 accounting schema probes + 1 BranchService `is_main`), each checked once.

**Stability (2nd run):** build 43.3 ms, SQL 60, SHOW 8, ensure 0.71 ms.

---

## TTFB estimate

| Layer | Before | After (est.) |
|-------|--------|--------------|
| Controller accounting index | 180 ms | 39 ms |
| Savings on hot path | — | **≈141 ms** |
| Browser accounting TTFB (post-AG ~708 ms) | 708 ms | **≈567 ms** if bootstrap/HTML unchanged |

---

## What changed

1. **`Database::liveTableHasColumn`** — load all columns once per table per request (not once per call).
2. **`AccountingBranchScope` / `Model::branchColumnIsSelectable`** — request static caches for branch probes.
3. **`ensureDefaultAccounts`** — if all `DEFAULT_ACCOUNTS` codes exist, return immediately (unless `RATEB_ACCOUNTING_REPAIR_COA=1`).
4. **`findCoaByCode`** — one `SELECT *` COA map per company; reuse `code => row`.
5. **`touchCoaRow`** — skip UPDATE when name / name_ar / is_active unchanged.
6. **`AccountingService`** — request memo for `financialSummary`, `cfoMetrics`, `bankReconciliation`, `vatReport`, AR/AP, P&amp;L.
7. **`AccountingDashboardService::build`** — share `$metrics` with trends/KPIs/alerts; memoize `metrics()`.

---

## Top 20 hotspots (after)

| Rank | Function | SQL count | SQL ms |
|------|----------|-----------|--------|
| 1 | `Database::liveTableHasColumn` | 7 | 8.7 |
| 2 | `Model::executePrepared` | 14 | 4.4 |
| 3 | AccountingService memo closures (aggregates) | 6 | 1.9 |
| 4 | `AccountingDashboardService::trends` | 4 | 1.6 |
| 5 | `AccountingDashboardService::charts` | 3 | 1.5 |
| 6 | `BranchService::branchesTableExists` | 1 | 1.5 |
| 7 | `BranchService::branchesHaveIsMainColumn` | 1 | 1.2 |
| 8–20 | Remaining dashboard SELECTs (AR/AP, VAT, P&amp;L, alerts, recent) | ≤3 each | &lt;1.2 each |

---

## Behavioral guarantees

- Same accounting numbers and alert semantics (alerts unpaid query still uses `sent|draft`, not metrics’ broader unpaid set).
- COA auto-provision still runs on first install / missing codes / `RATEB_ACCOUNTING_REPAIR_COA=1`.
- Normal dashboard never rebuilds or rewrites COA.

---

## Evidence files

- Before: `phase-ah-verdict.json`, `phase-ah-accounting-audit.json`
- After: `phase-ai-accounting-audit-after.json`, `phase-ai-verdict.json`
