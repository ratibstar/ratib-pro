# RATIB ERP — HR Phase D Payroll Correctness Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase C `1ae1a629`  
**Audit:** `docs/hr/HR-PHASE-D-PAYROLL-AUDIT.md`

---

## D0 — Current Payroll Flow

```text
Attendance (absent only)
 → batch payroll inputs
 → rateb_payroll_lines (draft)
 → oversight approve
 → company post (status lock)
 → GL: none
 → Bank transfer: none
```

Live engine remains `HrService` on `rateb_payroll_periods` / `rateb_payroll_lines`. No Payroll2.

---

## D1 — Attendance → Payroll Inputs

| Item | Result |
|------|--------|
| Source | `rateb_attendance_records` where `status='absent'` |
| Period | Calendar month `BETWEEN` first/last day |
| Leave days | `status='leave'` — **not** deducted |
| Batch | `batchAbsenceDaysByEmployee` / structures / loans — same formula |
| Outside period | Excluded by BETWEEN |

---

## D2 — Calculation Integrity

```text
net = max(0, salary_base + allowances - (struct_deductions + loans + absent*(salary_base/30)))
```

| Item | Result |
|------|--------|
| Salary source | `rateb_employees.salary_base` at generate time |
| Enterprise overlay | Not used by ops generate |
| Effective dating | Snapshot at generate — no historical ops salary table (**deferred**) |
| Duplicate lines | Skip if employee line already exists for period |

---

## D3 — State Machine

| Transition | Result |
|------------|--------|
| draft → approved | PASS (oversight) |
| approved → posted | PASS (company) |
| draft → posted | DENY |
| posted → post again | Idempotent no-op (no second audit) |
| Tenant mismatch approve/post | DENY |

---

## D4 — Payroll / GL / Transfer separation

| State | Meaning |
|-------|---------|
| Payroll `posted` | Period **locked** |
| GL | **Not created** (`gl_posted: false` in audit) |
| Bank transfer | **Not created** (`bank_transfer: false`) |

UI/lang updated: `payroll_posted`, `post_payroll`, `payroll_posted_status_note` (EN/AR) + banner on payroll show.

---

## D5 — Audit + Reconciliation

| Item | Detail |
|------|--------|
| Audit events | `calculated`, `approved`, `posted` (+ flags) |
| Reconciliation | `HrPayrollIntegrityService::diagnosePeriod` — read-only sums + expected GL/transfer = none |

---

## D6 — Tests + Regression

| Suite | Result |
|-------|--------|
| `run-hr-phase-d-tests.php` | **CLEAR** (0/17 failed) |
| `run-hr-phase-b-security-tests.php` | **CLEAR** (0/14 failed) |
| `run-hr-phase-c-security-tests.php` | **CLEAR** (0/16 failed) |
| `run-ess-phase-e-leave-tests.php` | **CLEAR** (0/11 failed) |
| `run-ess-phase-c-hardening-tests.php` | **CLEAR** (0/10 failed) |

### Definition of Done

```text
[x] Payroll flow fully traced
[x] Attendance inputs verified
[x] Payroll period boundaries verified
[x] Leave → Payroll interaction verified (leave ≠ absent)
[x] Salary source verified
[x] Salary effective dating verified (generate-time snapshot; historical deferred)
[x] Duplicate salary effects checked (line skip)
[x] Payroll transitions verified
[x] draft → posted remains blocked
[x] Approval/posting tenant isolation verified
[x] Payroll posting idempotency verified
[x] Payroll ≠ GL documented + UI clarified
[x] Payroll ≠ Bank Transfer documented + UI clarified
[x] Accounting failure N/A (no GL call)
[x] Transfer failure N/A (no transfer)
[x] Payroll audit verified
[x] Read-only reconciliation available
[x] No destructive migration / No Payroll2 / No engine rewrite
[x] Phase B/C + ESS regressions PASS
[x] Phase D tests PASS
[x] Documentation updated
```

### Deferred

- Unpaid leave still writes `leave` (same as paid) — leave redesign  
- Ops historical / effective-dated salary for generate  
- Phase E `PayrollAccountingAdapter` + feature flag  
- Bank / WPS transfer workflow  
- Late / OT payroll inputs  

---

## Recommended Next Phase

**Phase E — Accounting adapter (flagged OFF)** for optional GL via `AccountingService` only.
