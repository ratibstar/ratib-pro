# RATIB ERP — HR Phase D0 Payroll Flow Audit

**Status:** COMPLETE (evidence only — no engine rewrite)  
**Date:** 2026-08-14  
**Base:** Phase C commit `1ae1a629`  
**Live engine:** `HrService::generatePayrollLines` / `approvePayroll` / `postPayroll`  
**Forbidden:** Payroll2, AccountingService redesign, auto production recalculation

---

## 1. End-to-end flow (actual)

```text
Attendance (rateb_attendance_records)
        │  status IN present|late|absent|leave
        ▼
Payroll Input (inline in generatePayrollLines)
        │  ONLY status = 'absent' counted (÷30 of salary_base)
        ▼
Payroll Lines (rateb_payroll_lines)  [status of period still draft]
        ▼
Payroll Calculation (same method — formula below)
        ▼
Payroll Draft (rateb_payroll_periods.status = draft)
        ▼
Oversight Approval → approved
        ▼
Company Posting → posted   ★ status flip ONLY
        ▼
Accounting / GL            ✗ NOT CALLED
        ▼
Payment / Bank Transfer    ✗ NOT IMPLEMENTED for ops payroll
```

---

## 2. Stage matrix

| Stage | Controller | Service | Table | Status | Actor | Company scope | Audit | Accounting | Payment |
|-------|------------|---------|-------|--------|-------|---------------|-------|------------|---------|
| Attendance raw | Hr attendance CRUD / ESS | `HrService` | `rateb_attendance_records` | present/late/absent/leave | company / ESS | `company_id` | generic CRUD | none | none |
| Approved leave → attendance | oversight leave approve | `HrService::applyApprovedLeave` | writes `status=leave` rows | leave | oversight | company on leave | leave audit path | none | none |
| Generate lines | `HrPayrollController::generate` | `generatePayrollLines` | `rateb_payroll_lines` | period must be `draft` | company `hr-payroll` | period.company_id | `calculated` | none | none |
| Approve | oversight (company approve blocked) | `approvePayroll` | `rateb_payroll_periods` | draft→approved | oversight | tenant guard | `approved` | none | none |
| Post | `HrPayrollController::post` | `postPayroll` | `rateb_payroll_periods` | approved→posted | company | tenant guard | `posted` | **none** | **none** |
| Enterprise payroll overlay | `/admin/payroll/*` | `PayrollDomainServices` | `rateb_payroll_*` | separate workflow | company | company_id | timeline | metadata `accounting_post_ref` only | none |
| GL | — | `AccountingService` | journals | — | — | — | — | **not wired to ops HR** | — |
| Bank transfer | — | — | — | — | — | — | — | — | **no ops payroll transfer** |

---

## 3. Actual calculation formula (ops)

```text
basic        = rateb_employees.salary_base          (at generate time)
allowances   = Σ active structure components type=allowance (fixed or % of basic)
struct_ded   = Σ active structure components type≠allowance
loan_ded     = Σ installment_amount of active loans
                 where paid_installments < installments_count
                 AND start_date <= period_end
absent_days  = COUNT attendance WHERE status='absent'
                 AND date BETWEEN period_start AND period_end
absent_ded   = (basic / 30) * absent_days
deductions   = struct_ded + loan_ded + absent_ded
net          = max(0, basic + allowances - deductions)
```

**Not used by ops generator:** overtime, lateness, unpaid leave flag, enterprise `rateb_payroll_employee_salary`, salary effective-dated history, sanctions.

---

## 4. Attendance → Payroll input

| Input | Source | Payroll effect |
|-------|--------|----------------|
| Absence | `rateb_attendance_records.status = 'absent'` in period | Deduct basic/30 per day |
| Present / late | attendance | **No** payroll effect |
| Leave (auto from approved leave) | attendance `status='leave'` | **No** deduction (not counted as absent) |
| Unpaid leave type | leave type `paid` flag | **Ignored** by `applyApprovedLeave` and payroll — gap |
| Overtime / lateness | — | **Not calculated** |
| Outside period dates | BETWEEN start/end of calendar month | Excluded |

**Period boundaries:** `period_year`/`period_month` → `YYYY-MM-01` … last day of month. No timezone conversion.

**Query pattern (pre-Phase D):** N+1 — one absence COUNT per employee. Completeness: all `active` employees in company; skips if line already exists (idempotent per employee).

---

## 5. Leave → Attendance → Payroll

| Leave Type paid? | Attendance effect | Payroll effect (actual) |
|------------------|-------------------|-------------------------|
| Any approved leave | Creates `leave` day if no row exists | No absence deduction |
| Rejected leave | No attendance write | No effect |
| Unpaid (type.paid=0) | Still `leave` status | **Same as paid** — **gap / deferred** |

---

## 6. Salary source & effective dating

| Source | Used by ops generate? |
|--------|----------------------|
| `rateb_employees.salary_base` | **YES** |
| Structure components | **YES** |
| `rateb_payroll_employee_salary` | **NO** |
| Phase C salary audit effective_date | Audit metadata only — **not** read by generate |

**Effective dating behavior (actual):** Snapshot at generate time. August payroll uses whatever `salary_base` is when Generate is clicked. A change effective 2026-09-01 is **not** enforced by the engine — operators must generate August before changing September salary (process control). Historical salary table for ops: **missing**.

---

## 7. State machine (ops periods)

| From | Action | To | Allowed? |
|------|--------|-----|----------|
| (create) | store | draft | yes |
| draft | generate lines | draft | yes (adds lines) |
| draft | approve | approved | yes (oversight) |
| approved | post | posted | yes (company) |
| draft | post | — | **DENIED** (`payroll_not_approved`) |
| posted | post again | — | throws (not approved) — **pre-fix: not soft-idempotent** |
| posted | approve | — | DENIED (not draft) |
| posted | generate | — | DENIED (not draft) |

Statuses in schema/UI: primarily `draft|approved|posted`. No separate `rejected`/`cancelled` on ops periods in live path.

---

## 8. Posted ≠ GL ≠ Bank (critical)

| Claim | Reality |
|-------|---------|
| Payroll `posted` | Period locked for regenerate; lines frozen by process |
| GL journal created | **False** — `postPayroll` does not call `AccountingService` |
| Bank transfer created | **False** — no ops payroll transfer workflow |
| Enterprise `accounting_post_ref` | Metadata string only; “No auto GL posting” in `PayrollSupport` |

**Risk:** Arabic/English copy “ترحيل / posted” can be read as accounting post. Phase D must clarify UI/lang without adding GL.

---

## 9. Confirmed gaps (in Phase D scope)

| ID | Gap | Safe fix? |
|----|-----|-----------|
| D1-batch | N+1 absence/structure/loan queries | Yes — batch load, **same formula** |
| D3-idempotent | Second `post` throws instead of no-op | Yes — early return if already `posted` |
| D4-copy | `payroll_posted` implies GL | Yes — clarify lang + UI note |
| D5-recon | No read-only totals/GL/transfer check | Yes — diagnostic service |
| D2-effective | No ops historical salary | **Document only** — needs schema (Phase E+/contracts) |
| D1-unpaid | Unpaid leave = paid leave for payroll | **Document only** — rule change out of scope |
| D4-GL | No GL adapter | **Deferred Phase E** |
| D4-transfer | No bank transfer | **Deferred** |

---

## 10. Explicit non-goals

- No Payroll2 / new calculation engine  
- No AccountingService changes  
- No auto recalculation of posted periods  
- No destructive migrations  
- No leave/attendance redesign
