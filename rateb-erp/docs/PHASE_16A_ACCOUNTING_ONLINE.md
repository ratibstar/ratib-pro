# Phase 16A — Enterprise Accounting Platform (ONLINE FOUNDATION)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline:** Do NOT implement Offline. No Queue / Replay / SDK / SW / IDB changes.  
**Migration:** `migrations/182_accounting_platform_enterprise.sql`

## Executive Summary

Phase 16A does **not** rebuild the existing operational GL (already production-shaped via `AccountingService`). It adds an **enterprise domain-service foundation** (15A-style) plus gap capabilities so future Phase 16B Offline Accounting can call reusable services only.

### Repository Audit (pre-16A)

| Area | Status |
|------|--------|
| CoA / Journals / GL / Fiscal / Cost centers / Bank / VAT | Already present |
| Controllers / views / reports | Already present |
| Multi-currency / FX / tax codes / profit centers / recurring / opening OB wizard | **Gaps → filled in 16A** |
| Fine-grained create/update/reverse/close_period/admin permissions | **Added** |

## Architecture

```
Controllers (thin)
  → Domain services (ChartOfAccounts, Journal, Ledger, Fiscal, Workflow, Tax, Currency, FX, Cost/Profit, Recurring, Opening, DocumentMeta)
  → Existing AccountingService (posting / period lock / reports) where already correct
  → Database
```

**Only `AccountingWorkflowService` may change lifecycle states:**  
`draft → balanced → posted → locked|reversed → archived`

## Offline Readiness Matrix

| Operation | Service | Offline Replay Compatible |
|-----------|---------|---------------------------|
| Create/update CoA | `ChartOfAccountsService` | YES |
| Soft-delete CoA | `ChartOfAccountsService::softDelete` | YES |
| Create/update journal draft | `JournalService` | YES |
| Lifecycle transition | `AccountingWorkflowService::transition` | YES (post/reverse use existing AccountingService) |
| Trial balance / account statement | `LedgerService` | YES (read) |
| Fiscal create/close/lock | `FiscalPeriodService` | YES (close is sensitive — flag later) |
| Cost center create/list | `CostCenterService` | YES |
| Profit center create/list | `ProfitCenterService` | YES |
| Currency / FX / tax codes | `CurrencyService` / `ExchangeRateService` / `TaxService` | YES |
| Recurring template + generate draft | `RecurringJournalService` | YES (generate draft only) |
| Opening balances draft | `OpeningBalanceService` | YES |
| Attachment **metadata** link/list | `AccountingDocumentMetaService` | YES (read/meta) |
| Binary attachment upload | `DocumentService` multipart | **NO** |
| Auto-post from invoice/PO/POS | `AccountingService` source postings | **NO** (online system sync) |
| Bank statement CSV import | existing controllers | **NO** |
| Approvals / oversight post | existing oversight flows | **NO** until explicitly designed |

## Files Created

- `migrations/182_accounting_platform_enterprise.sql`
- `app/models/AccountingPlatformModels.php`
- `app/services/AccountingSupport.php`
- `app/services/AccountingDomainServices.php`
- `app/services/AccountingEnterpriseServices.php`
- `app/controllers/Company/AccountingPlatformControllers.php`
- `views/company/accounting/platform-hub.php` + currencies/tax/profit/recurring/opening views
- `tests/accounting/*`
- `docs/PHASE_16A_ACCOUNTING_ONLINE.md`

## Files Modified (additive)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## RBAC (new + existing)

| Slug | Role |
|------|------|
| `accounting.view` | existing |
| `accounting.create` | new |
| `accounting.update` | new |
| `accounting.post` | existing |
| `accounting.reverse` | new |
| `accounting.close_period` | new |
| `accounting.admin` | new (implies all) |
| `accounting.manage` | existing (implies create+update) |

## Validation

- Double-entry balance enforced in `AccountingSupport::assertBalanced` + `JournalService`
- Period lock via `AccountingService::periodBlocksPosting`
- Currency / tax / FX field validation in domain services

## Tests

```bash
php tests/accounting/run-accounting-phase16a-tests.php
```

## Production readiness

1. Run migration `182_accounting_platform_enterprise.sql`
2. Existing accounting UI remains primary for daily GL
3. Use **Accounting platform** hub for currencies / tax / profit / recurring / opening balances
4. Phase 16B may wrap these services — flags default OFF — Baseline untouched

## Success criteria

- ✔ Domain services own new business rules  
- ✔ No Offline / Queue / Replay / SDK / Baseline changes  
- ✔ Future 16B can call these services directly  
