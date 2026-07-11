# Phase 16B — Accounting Offline (Tier-1 Drafts)

**Status:** CLEAR — additive Tier-1 module on Enterprise Offline Foundation v1.1  
**SDK:** remains **14.2.0** (backward compatible; accounting flags + adapter additive)

## Executive Summary

Accounting Offline wraps Phase 16A online services only. Queue contract, IndexedDB v2, ReplayEngine architecture, SW, auth, and RBAC are unchanged aside from additive `module = accounting` branches. All accounting feature flags default **OFF** and require `offline.enabled`.

**Online-only (by design):** posting, period close, reverse, bank reconciliation, payments, invoice auto-posting, ZATCA / government APIs.

## Repository Audit (16A)

| Area | Location | Offline use |
|------|----------|-------------|
| Migration 182 | `migrations/182_accounting_platform_enterprise.sql` | Domain tables + CoA/period soft columns |
| Services | `JournalService`, `AccountingWorkflowService`, `ChartOfAccountsService`, `FiscalPeriodService`, `CurrencyService`, `ExchangeRateService`, `TaxService`, `CostCenterService`, `ProfitCenterService`, `RecurringJournalService`, `OpeningBalanceService` | Replay delegates write drafts; directories read-only |
| Controllers / routes / views | `routes/company.php` + `views/company/accounting/` + journal-entries | Ops allowlist browse + draft form hooks |
| Permissions | `accounting.*` | Server auth unchanged |
| Tests | `tests/accounting/` | Online; offline tests under `offline/tests/` |

## Replay Flow

```
Client adapter enqueue (module=accounting)
  → OfflineQueue (frozen fields)
  → OfflineReplayEngine (additive accounting branch)
  → AccountingOfflineReplayService
  → Phase 16A domain services
  → Database
```

## Queue Mapping

| Action | Sub-flag |
|--------|----------|
| `journal.create` / `journal.update` / `note.create` / `recurring.create` / `opening_balance.create` | `offline.accounting.journals` |
| `workflow.transition` (targets `draft` / `balanced` only) | `offline.accounting.workflow` |
| Read-only master data pull | `offline.accounting.masterdata` |

**Module:** `accounting` — queue field names unchanged (`client_id`, `idempotency_key`, `module`, `action`, `payload`, `occurred_at`, `status`, `retry_count`, `seq`).

## Feature Flags (default OFF)

- `offline.accounting` → `RATEB_OFFLINE_ACCOUNTING`
- `offline.accounting.journals` → `RATEB_OFFLINE_ACCOUNTING_JOURNALS`
- `offline.accounting.workflow` → `RATEB_OFFLINE_ACCOUNTING_WORKFLOW`
- `offline.accounting.masterdata` → `RATEB_OFFLINE_ACCOUNTING_MASTERDATA`

All require `offline.enabled`.

## Files Created

- `offline/server/Services/AccountingOfflineReplayService.php`
- `offline/server/Services/AccountingOfflineTenantGuard.php`
- `offline/server/Services/AccountingOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/accounting-adapter.js`
- `offline/tests/AccountingOfflinePhase16bTest.php`
- `offline/tests/run-accounting-offline-tests.php`
- `offline/docs/PHASE_16B_ACCOUNTING_OFFLINE.md`

## Files Modified (additive)

- Flags, queue, replay engine, conflict resolver, authz, modules, entity-manifest, master-data, cursor, ops allowlist, ops-forms, SDK, background sync, build script, public SDK bundle
- Phase 14 pilot allowlist assertion updated (excludes payroll/payments/zatca; accounting draft browse allowed)

## Architecture Validation

- Adapter → Queue → OfflineReplayEngine → AccountingOfflineReplayService → Phase 16A services → DB
- No duplicated journal validation / posting rules
- Workflow offline denies non-draft targets (`accounting_offline_transition_denied`)

## Security Validation

- Reuses TenantContext, ACTIVE device, offline unlock, RBAC cache, server authorization
- `AccountingOfflineTenantGuard` asserts company / branch / journal / account / period ownership
- Idempotency via `[offline:key]` description marker + queue idempotency key

## Performance Validation

- Respects `client_queue_max`, batch size, retry policy, debounce — no scheduler redesign

## Regression Validation

- Enterprise Baseline v1.2 / Offline Foundation v1.1 / SDK 14.2.0 / IDB v2 markers preserved
- Existing Inv / HR / Proc / Recruitment / Phase 14 suites remain GREEN

## Test Results

```bash
php offline/scripts/build-rateb-offline-bundle.php
php offline/tests/run-accounting-offline-tests.php
```

Target: **100% PASS**.

## Remaining Risks

- Journal form field shapes vary by legacy UI; ops-forms payload is best-effort mapping into Phase 16A draft APIs
- Cost center / CoA delta requires migration 182 soft columns where used (`deleted_at`)
- Full accounting nav remains in `offline_disabled_modules` (drafts via allowlist + SDK only)

## Production / Pilot Readiness

- **Production:** flags OFF — safe to deploy code.
- **Pilot:** enable `offline.enabled` + `offline.accounting` (+ journals / workflow / masterdata as needed) on a single tenant after migration 182.

## Success Criteria

| Criterion | Status |
|-----------|--------|
| Enterprise Baseline v1.2 untouched | ✔ |
| Offline Foundation untouched | ✔ |
| Queue unchanged | ✔ |
| ReplayEngine architecture unchanged (additive branch only) | ✔ |
| SDK backward compatible (14.2.0) | ✔ |
| Accounting logic reused from Phase 16A only | ✔ |
| Feature flags default OFF | ✔ |
| Posting / close / reverse / bank / payments / ZATCA online-only | ✔ |
