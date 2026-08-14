# RATIB ERP — HR Phase E Accounting Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase D `0a5235d5`  
**Audit:** `docs/hr/HR-PHASE-E-ACCOUNTING-AUDIT.md`

---

## Architecture

```text
HrService::postPayroll (status lock)
        │
        ├─ flag OFF → stop (Phase D contract)
        └─ flag ON  → HrPayrollAccountingAdapter
                         → AccountingService::createManualDraft
                         → draft journal (finance posts later)
```

No Payroll2 / Accounting2 / Bank / WPS.

---

## Feature Flag

| Item | Value |
|------|--------|
| Name | `HR_PAYROLL_ACCOUNTING_ENABLED` |
| Mechanism | Env (`HrPayrollAccountingConfig`) |
| **Default** | **OFF** (`false` when unset) |
| Enable values | `true` / `1` / `on` / `yes` |

Account code overrides (optional):

- `HR_PAYROLL_EXPENSE_ACCOUNT_CODE` (default `5020101`)
- `HR_PAYROLL_PAYABLE_ACCOUNT_CODE` (default `20105`)
- `HR_PAYROLL_DEDUCTION_ACCOUNT_CODE` (default `20104`)

---

## Mapping

```text
Dr Salaries & Wages     = Σ(basic + allowances)
Cr Salaries Payable     = Σ(net)
Cr Accrued Expenses     = Σ(deductions)   [if > 0]
```

Sums from `rateb_payroll_lines` only — no payroll recalculation.

---

## Company / Fiscal / Idempotency

| Control | Behavior |
|---------|----------|
| Company | Payroll.company_id must match expected + created journal company |
| Fiscal | `periodBlocksPosting` → fail `period_closed` (no auto-open) |
| Prerequisite | Payroll status must be `posted` |
| Idempotency | Description marker `[HR_PAYROLL_PERIOD:{id}]` |

---

## Transaction / Failure

1. Payroll locked to `posted` first.  
2. Optional draft journal attempted.  
3. Accounting failure → payroll stays posted; audit `payroll_accounting_failed`.  
4. Success → audit `payroll_accounting_posted` with `accounting_reference` (journal id).  
5. Draft journal ≠ ledger posted.

---

## Audit / Reconciliation

| Event | When |
|-------|------|
| `payroll_accounting_attempted` | Before create |
| `payroll_accounting_posted` | Draft created or already exists |
| `payroll_accounting_failed` | Any failure reason |

`HrPayrollIntegrityService::diagnosePeriod` reports flag state + journal marker presence.

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-e-accounting-tests.php` | **CLEAR** (0/17) |
| Phase B | **CLEAR** (0/14) |
| Phase C | **CLEAR** (0/16) |
| Phase D | **CLEAR** (0/17) |
| ESS Phase E leave | **CLEAR** (0/11) |
| ESS Phase C hardening | **CLEAR** (0/10) |

---

## Production Safety

```text
After deploy: HR_PAYROLL_ACCOUNTING_ENABLED remains unset/false
→ postPayroll creates NO journal
→ Phase D: Payroll posted ≠ GL ≠ Bank
```

**Do not enable in production without explicit approval and staging proof.**

---

## Definition of Done

```text
[x] AccountingService audited / reused
[x] No duplicate Accounting engine
[x] Payroll calculation/workflow unchanged
[x] Feature flag defaults OFF
[x] Flag OFF creates no GL
[x] Flag ON path implemented (draft journal) + tested (source/config)
[x] Company + fiscal + mapping + idempotency + failure audit
[x] Reconciliation updated
[x] No Bank/WPS / no destructive migration
[x] Regressions PASS
[x] Documentation updated
```

### Deferred

- Auto-posted (ledger) journals  
- Per-component / cost-center line mapping  
- `source_type=hr_payroll` ENUM  
- Enabling flag in production
