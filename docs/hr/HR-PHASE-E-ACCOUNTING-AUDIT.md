# RATIB ERP — HR Phase E0 Accounting Integration Audit

**Status:** COMPLETE (evidence)  
**Date:** 2026-08-14  
**Base:** Phase D `0a5235d5`  
**Decision:** Feature-flagged adapter; **default OFF**; journal mode = **DRAFT** (finance posts later).

---

## 1. AccountingService API (relevant)

| Method | Role for payroll |
|--------|------------------|
| `ensureDefaultAccounts($companyId)` | Provisions Saudi COA including payroll accounts |
| `accountIdByCode($companyId, $code)` | Resolve account id (legacy aliases supported) |
| `createManualDraft(...)` | Creates **draft** journal + lines (balanced) |
| `createPostedEntry(...)` | Creates **posted** journal (used by PO/stock/payment) — **not** Phase E default |
| `periodBlocksPosting($companyId, $date)` | True when fiscal period closed / not open |
| `journalExistsForSource($type, $id)` | Idempotency for source-linked entries |
| `journalIdForSource($type, $id)` | Lookup existing journal id |

**Core rule:** Adapter must call **AccountingService only** — no parallel GL writer.

---

## 2. Existing journal architecture

- Tables: `rateb_journal_entries`, `rateb_journal_lines` (+ optional `cost_center_id`)
- Entry statuses: `draft` → approval flow → `posted` (and reject/void paths)
- `source_type` ENUM: manual, invoice, payment, purchase_order, stock_movement, … (no `hr_payroll` today)
- Auto-posts (PO/stock/payment) use `createPostedEntry` + `entryExists(source_type, source_id)`

**Phase E choice (safer):** use `createManualDraft` so GL is **not** auto-posted to the ledger. Finance still reviews/posts.

**Idempotency without ENUM ALTER:** marker in description  
`[HR_PAYROLL_PERIOD:{id}]` + company-scoped lookup (avoids production ENUM migration).

---

## 3. Chart accounts usable for payroll (default COA)

| Semantic | Code | Name |
|----------|------|------|
| Salary expense | `5020101` (legacy `5300`) | Salaries & Wages |
| Salaries payable | `20105` (legacy `2400`) | Salaries Payable |
| Accrued expenses (deductions bucket) | `20104` (legacy `2300`) | Accrued Expenses |

Configurable overrides via env (not hard-coded only):

```text
HR_PAYROLL_EXPENSE_ACCOUNT_CODE=5020101
HR_PAYROLL_PAYABLE_ACCOUNT_CODE=20105
HR_PAYROLL_DEDUCTION_ACCOUNT_CODE=20104
```

---

## 4. Payroll data available to adapter

From `rateb_payroll_periods` + `rateb_payroll_lines` (ops):

| Field | Use |
|-------|-----|
| `company_id` | Tenant boundary (must match Accounting company) |
| `period_year` / `period_month` | Entry date = last day of month (or period end) |
| `status` | Must be `posted` (or about to be) before GL attempt |
| `branch_id` | Optional journal branch |
| Σ `basic_salary` + Σ `allowances` | Gross / expense debit |
| Σ `deductions` | Credit accrued/deduction account when > 0 |
| Σ `net_salary` | Credit salaries payable |

**Adapter does not recalculate payroll** — sums existing lines only.

---

## 5. Cost centers

- Journal lines support optional `cost_center_id`.
- Ops payroll lines have **no** cost_center_id / department on the line row used by generate.
- Phase E: **no new cost-center hierarchy**; pass `null` unless a future mapping exists.
- Branch may be passed to draft entry when period has `branch_id`.

---

## 6. Feature flag mechanism

No central HR feature-flag table. Existing pattern: **env vars** (e.g. `HybridSyncConfig`, offline flags).

```text
HR_PAYROLL_ACCOUNTING_ENABLED=false   # default
```

Absent / empty / `0` / `false` / `off` → **disabled**.

---

## 7. Integration point

```text
HrService::postPayroll
  → status approved→posted (unchanged)
  → audit posted (gl_posted false when flag OFF)
  → IF flag ON: HrPayrollAccountingAdapter::ensureDraftJournal(period)
       → AccountingService::createManualDraft
```

**Does not** change draft→approved→posted rules.  
**Does not** allow draft→posted.

---

## 8. Failure / rollback model (chosen)

```text
1) Payroll status locked to posted first (Phase D semantics)
2) Optional accounting draft attempted
3) On accounting failure:
     Payroll remains posted
     accounting_status = failed (via payroll audit payload)
     No silent success
4) On success:
     accounting_status = draft_created
     accounting_reference = journal_entry_id
5) On closed fiscal period:
     failed with reason period_closed — no auto-open
```

Rationale: rolling back payroll post would break Phase D lock semantics and company “post” UX. GL is optional additive.

---

## 9. Minimum journal model (flag ON)

```text
Dr  Salaries & Wages (5020101)     = gross (Σ basic + allowances)
Cr  Salaries Payable (20105)       = net   (Σ net_salary)
Cr  Accrued Expenses (20104)       = deductions (Σ deductions)  [if > 0]
```

Balanced: gross = net + deductions.

---

## 10. Gaps / deferred

| Item | Status |
|------|--------|
| Per-component GL mapping | Deferred |
| Cost center per employee/dept | Deferred (no line CC today) |
| Auto-posted (ledger) journals | Explicitly **not** Phase E default |
| Bank / WPS | Out of scope |
| ENUM source_type=`hr_payroll` | Deferred (marker idempotency used instead) |
| Production flag ON | **Forbidden** without separate approval |

---

## 11. Production safety

After deploy: flag remains **OFF** → Phase D contract holds:

```text
Payroll posted ≠ GL posted ≠ Bank transfer
GL: none_expected
```
