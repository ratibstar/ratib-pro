# RATIB ERP — HR Phase H2 Leave Integrity Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Audit:** `docs/hr/HR-PHASE-H2-LEAVE-AUDIT.md` (`b4af3831`)  
**Base:** Phase H Matrix Governance `12cfcf71`

---

## Objective

Close leave integrity gaps without rewriting payroll, approval, or calendar semantics:

- Paid / unpaid leave → correct payroll input
- Balance consume / restore once
- Overlap + concurrency guards
- Real cancellation + safe undo
- Leave-owned attendance ownership
- ESS uses the same canonical create path

---

## Canonical sources (unchanged)

| Concern | SoT |
|---------|-----|
| Requests | `rateb_leave_requests` |
| Types | `rateb_leave_types.paid` |
| Balances | `rateb_leave_balances` (`remaining = entitled - used`) |
| Employee | `rateb_employees.id` + `company_id` |
| Approval | `ApprovalOversightService` + `HrApprovalMatrixService` |
| Attendance | `rateb_attendance_records` (+ `leave_request_id`) |
| Payroll inputs | Phase D batch inputs into existing `/30` formula |

---

## Product decisions implemented

### D1 — Paid / unpaid

- Canonical flag: `rateb_leave_types.paid` (no second flag).
- On final approve: snapshot → `rateb_leave_requests.paid_snapshot`.
- Attendance for approved leave remains `status=leave` (not `absent`).
- Payroll: `deduct_days = absent_days + unpaid_leave_days` via `batchUnpaidLeaveDaysByEmployee`.
- Unpaid days use **existing** `salary_base/30` — **no HrService payroll rewrite**.

### D2 / D18 — Calendar-day semantics

**Calendar-day semantics intentionally preserved.**  
Inclusive days (e.g. 2026-08-01 → 2026-08-03 = 3).  
**Deferred:** working-day / holiday / weekend exclusion.

### D3 / D15 — Balance

- Pending / rejected → no consumption.
- Approved → consume via approved-only sync.
- ESS + Admin create: `requested days <= remaining` when type has `days_per_year > 0`.
- Uncapped types (`days_per_year` null/0) remain allowed.
- Cancel/undo restore via re-sync of approved totals (never negative invent).

### D4 / D14 — Overlap + concurrency

- Wired into `HrService::createPendingLeaveRequest`.
- Active statuses: `pending` + `approved` only (`rejected`/`cancelled` ignored).
- Employee row `FOR UPDATE` before overlap check + insert.

### D5 / D6 — Cancellation + undo

- `cancelLeave`: pending|approved → cancelled; restore balance once; reverse leave-owned attendance.
- Oversight undo → `undoLeaveApproval` (not bare status reset).
- Idempotent duplicate cancel/undo.

### D7 / D20 — Attendance ownership

Additive migration `249_hr_phase_h2_leave_integrity.sql`:

- `rateb_attendance_records.leave_request_id` (nullable + index)
- `rateb_leave_requests.paid_snapshot` (nullable)

Reversal: `DELETE … WHERE leave_request_id = :lid AND status = 'leave'` only.  
**No** broad `DELETE WHERE status=leave`.

### D8 / D17 — Payroll + posted protection

- Workflow unchanged: draft → approved → posted.
- Cancel/undo **blocked** when leave dates overlap a **posted** payroll period (`leave_cancel_blocked_posted_payroll` / `leave_undo_blocked_posted_payroll`).
- **Limitation (documented):** no automatic payroll correction journal; operator must use existing draft-period regenerate / future reconciliation. Posted lines are never mutated.

### D9 — ESS

- `HrEssLeaveService::apply` → `createPendingLeaveRequest`.
- Cancel API: `POST /api/v1/hr/leave/requests/{id}/cancel`.
- Client cannot set `company_id` / `employee_id` / paid / approver / stage.

### D10 — Matrix

Unchanged decide path: Oversight → matrix gate → domain finalizers. No ApprovalEngine2.

### D11 / D12 — Notifications + audit

- Submit: `notifyPendingSubmission`.
- Final approve / reject / cancel: `notifyLeaveOutcome`.
- Audit actions: `leave_created`, `leave_submitted`, `leave_approved`, `leave_rejected`, `leave_cancelled`, `leave_undo`, `balance_consumed`, `balance_restored`, `unpaid_leave_payroll_input`.

### D13 — Idempotency

Row-count gated status updates + early return on already-cancelled / already-pending undo.

### D16 — Leave type paid flips

Historical payroll uses `paid_snapshot` (fallback `lt.paid` only when snapshot null — pre-H2 rows).

### D19 — Half-day

**Deferred.** Decimal inclusive days remain.

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-h2-leave-tests.php` | **CLEAR** |
| Phase B / C / D / E / F / G / H | **CLEAR** |
| ESS Phase C / E | **CLEAR** |

---

## Definition of Done

```text
[x] paid/unpaid behavior correct
[x] unpaid leave no longer behaves as paid leave
[x] paid leave does not create absence deduction
[x] balance consumption / restoration correct
[x] overdraw blocked (capped types)
[x] overlap blocked + concurrency-safe create
[x] cancellation real and idempotent
[x] undo safe (attendance + balance)
[x] attendance ownership via leave_request_id
[x] ESS uses canonical service + balance guard
[x] Matrix / no-matrix path intact
[x] notifications + audit wired
[x] posted payroll protected (reject unsafe mutate)
[x] calendar-day preserved; half-day / working-day deferred
[x] additive migration only
[x] Phase B–H + ESS regressions CLEAR
[x] certification + roadmap
```

### Known limitations / deferred

1. Working-day / holiday-aware leave calculation.  
2. AM/PM half-day.  
3. Pre-H2 attendance rows without `leave_request_id` cannot be auto-reversed on cancel.  
4. Posted payroll: cancel/undo blocked — no silent GL/payroll rewrite; no new correction engine in H2.  
5. Balance history / carry-forward (roadmap residual).

---

## Do not start

**Phase I (Attendance engine)** — not started by this certification.
