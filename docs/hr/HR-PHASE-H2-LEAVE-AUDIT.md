# RATIB ERP — HR Phase H2 Leave Depth Audit

**Status:** COMPLETE (evidence only — no implementation)  
**Date:** 2026-08-14  
**Base commit:** `12cfcf71` (Phase H Matrix Governance PASS)  
**Rule:** Discover leave SoT from code/DB. Do **not** change business logic until this audit is accepted.

---

## 0. Scope lock

| In scope for later H2 impl | Out of scope / forbidden |
|----------------------------|---------------------------|
| Leave policy / balance / paid vs unpaid | ApprovalEngine2 / replace Oversight or Matrix |
| Day calculation / holidays / weekends | Payroll formula rewrite (beyond unpaid leave input) |
| Approval → balance / attendance timing | Accounting / GL changes |
| Cancellation / rejection integrity | Employee identity redesign |
| ESS consistency + tenant isolation | EAP / Legacy Workflow for leave |

**Approval remains:** `ApprovalOversightService` + `HrApprovalMatrixService` (unchanged).

---

## 1. Canonical sources (discovered)

| Concern | Canonical SoT | Notes |
|---------|---------------|-------|
| Employee identity | `rateb_employees.id` + `company_id` | Phase C; ESS via `user_id` resolver |
| Leave request | `rateb_leave_requests` | Status: `pending\|approved\|rejected\|cancelled` |
| Leave type / policy | `rateb_leave_types` | Columns: `code`, `name`, **`paid`**, `days_per_year`, `status` |
| Leave balance | `rateb_leave_balances` | Per `(company, employee, leave_type, year)`: `entitled_days`, `used_days` |
| Approval state | Request `status` + Oversight decide | Company approve **blocked**; matrix may stage while status stays `pending` |
| Attendance representation | `rateb_attendance_records.status` | Includes `leave`, `absent`, `holiday`, `present`, `late` |
| Holidays calendar | `rateb_hr_holidays` | Synced to attendance via `HrService::syncHolidayAttendance` |
| Payroll absence input | Attendance `status='absent'` only | Phase D certified |
| ESS leave API | `HrEssLeaveService` → `HrService` / `LeaveRequest` | No second leave store |

**Do not create** duplicate leave entities / Leave2.

---

## 2. Leave type model (`paid` flag exists)

Schema (`067_hr_tables.sql` + `137` codes):

- `paid TINYINT(1) NOT NULL DEFAULT 1`
- Defaults in `HrService::defaultLeaveTypeDefinitions()` include explicit `'unpaid' => paid=0`

| Code | Default paid | Default days/year |
|------|--------------|-------------------|
| annual | 1 | 21 |
| sick | 1 | 30 |
| **unpaid** | **0** | null |
| emergency / maternity / … | 1 | various |

**Finding:** Payroll treatment is **stored explicitly** on the type (`paid`), not inferred from the name alone.  
**Gap:** Runtime leave approve + payroll **ignore** `paid` (see §6). No additive column needed for PAID/UNPAID distinction — the flag already exists; behavior must wire it.

---

## 3. Balance model (actual)

### Formula in code

```text
entitled_days = leave_types.days_per_year (or 0)
used_days     = SUM(leave_requests.days)
                WHERE status = 'approved'
                  AND YEAR(start_date) = balance_year
remaining     = entitled_days - used_days   (computed in SELECT)
```

Implemented in `HrService::syncLeaveBalancesForEmployee` (recomputed on read / after approve).

### What counts as consumption

| Status | Counts toward `used_days`? |
|--------|----------------------------|
| pending | **No** |
| approved | **Yes** |
| rejected | **No** |
| cancelled | **No** |
| draft | N/A (no draft status on leave requests) |

**No pending reservation** today — ESS/Admin can create overlapping-protected pending requests without reducing balance until approve.

### Missing vs enterprise target

| Concept | Present? |
|---------|----------|
| Opening balance / carry-forward | **No** |
| Accrual engine | **No** (static `days_per_year` only) |
| Manual adjustments table | **No** |
| Pro-rata hire date | **No** |
| Multi-year / expiry | **No** |

---

## 4. Approval timing (actual)

```text
create (Admin/ESS)
  → status = pending
  → notify oversight (ESS)
  → optional matrix stages (domain stays pending)
  → Oversight final approve
       → status = approved
       → applyApprovedLeave():
            1) syncLeaveBalances (used_days includes this request)
            2) for each calendar day in [start,end]:
                 if no attendance row → INSERT status='leave'
  → Oversight reject
       → status = rejected
       → no attendance write; balance unchanged
```

**Recommended charter timing** (`pending → approved → balance consumed`) **matches** current balance consumption.  
Attendance write is **coupled** to approve (not a separate step).

### Undo gap

Oversight undo for leave: `resetHrStatus` → `pending` only.  
**Does not:**

- delete auto-created attendance `leave` rows  
- re-sync balances (next sync would drop `used_days` once status is no longer approved — OK if sync runs)  
- reverse matrix progress is handled by Phase G `resetProgress`

**Risk:** After undo, orphan `attendance.status=leave` days may remain until manually fixed.

---

## 5. Day / date calculation (actual)

| Path | Formula |
|------|---------|
| ESS `HrEssLeaveService::inclusiveDays` | Inclusive calendar days: `(end-start)/86400 + 1` |
| Admin `HrLeavesController::collectData` | Same inclusive calendar formula if `days` empty |
| Form UI | Allows `step=0.5` (half-day **entry** possible manually) |
| `applyApprovedLeave` | Iterates **every calendar day** start→end (no weekend skip) |

**Not applied today:**

- Weekend exclusion  
- Holiday exclusion from `days` or from attendance leave inserts  
- Working-day calendars  
- Half-day as first-class policy (only free-form `days` decimal)

Holidays exist (`rateb_hr_holidays` + sync to attendance `holiday`) but **leave day count and leave attendance loop do not consult holidays**.

---

## 6. Unpaid leave ↔ payroll (Phase D deferred finding — confirmed)

| Step | Behavior |
|------|----------|
| Approve unpaid leave | Same as paid: attendance `status='leave'` |
| Payroll `generatePayrollLines` | Deducts only attendance `status='absent'` |
| Attendance `leave` | **Explicitly NOT deducted** (Phase D comment) |
| `leave_types.paid` | **Never read** by `applyApprovedLeave` or payroll generate |

**Conclusion (from code, not assumption):**

> Unpaid leave currently behaves like paid leave for payroll: no absence deduction; salary remains whole for those days.

This is the Phase D gap `D1-unpaid` — deferred then; **in scope for H2 implementation** after audit acceptance.

### Safe direction for later impl (proposal only)

Smallest additive path (no Payroll2):

1. Keep `leave_types.paid` as SoT.  
2. On approve unpaid: write attendance as `absent` **or** a dedicated unpaid marker that payroll counts — **OR** leave as `leave` but teach payroll to deduct days from approved unpaid leave in period.  
3. Prefer one clear rule in implementation plan; do not invent multiple semantics.

---

## 7. Overlap

`HrService::hasOverlappingLeaveRequest`:

- Scopes: `company_id` + `employee_id`  
- Statuses: `pending` **and** `approved`  
- Date predicate: `start_date <= end AND end_date >= start`  
- Used by ESS apply (409 duplicate)

Admin create path: **no** call to overlap check found in `HrLeavesController` — Admin can create overlapping pending/approved unless UI/process prevents it.

---

## 8. Cancellation

- Status enum includes `cancelled`.  
- ESS list filter accepts `cancelled`.  
- **No** dedicated cancel service/API found that sets cancelled and reverses attendance/balance.  
- Reject ≠ cancel.

---

## 9. Attendance interaction

| Event | Effect |
|-------|--------|
| Approve leave | Insert missing days as `leave` (calendar inclusive) |
| Existing attendance row | **Skipped** (no overwrite) |
| Holiday sync | Separate `holiday` status |
| Payroll | Counts `absent` only |

**Gap:** Approved leave spanning a weekend/holiday still creates `leave` rows for those days if empty — may inflate leave attendance counts in reports without affecting payroll.

---

## 10. Payroll / accounting separation

- Ops payroll formula unchanged (Phase D).  
- Accounting adapter still flag-gated OFF (Phase E).  
- H2 must not break `draft → approved → posted` or enable GL by default.

---

## 11. ESS consistency

| Topic | Status |
|-------|--------|
| Identity | Resolver + company scope (Phase B) |
| Days formula | Matches Admin inclusive calendar |
| Balance DTO | `entitled` / `used` / `remaining` |
| Overlap | Enforced on apply |
| Balance check before apply | **Not enforced** (can request more than remaining) |
| Notify oversight | After successful create |

---

## 12. Audit / tenant isolation

| Area | Finding |
|------|---------|
| Leave approve | Domain update + attendance; Oversight audit on decide |
| Balance sync | Silent recompute (no dedicated balance audit log) |
| Tenant | All leave tables company-scoped; ESS/Admin queries include `company_id` |
| Cross-company leave type | ESS validates `leave_type_id` + `company_id` |

---

## 13. Half-day / partial-day

| Support | Reality |
|---------|---------|
| Schema `days DECIMAL(5,1)` | Allows 0.5 |
| Admin form step 0.5 | Manual entry |
| Auto day calc | Integer inclusive days only |
| Attendance apply | Full calendar day rows only |
| Architecture support for true half-day attendance | **Weak** — no AM/PM attendance status |

**Verdict:** Do **not** invent half-day engine in H2 unless required; document as deferred or limited to manual `days` without attendance split.

---

## 14. Contracts

Employment contracts still not leave SoT (Phase J in roadmap). Leave does not read contracts today.

---

## 15. Findings summary (priority for future impl)

| ID | Finding | Severity | Suggested H2 action (later) |
|----|---------|----------|-----------------------------|
| H2-F1 | Unpaid `paid=0` ignored by attendance/payroll | **High** | Wire explicit unpaid → payroll input |
| H2-F2 | Days = calendar inclusive; no weekend/holiday logic | Medium | Decide policy; optional working-day calc |
| H2-F3 | Undo leave leaves attendance `leave` orphans | Medium | Reverse attendance on undo/cancel |
| H2-F4 | No cancel flow / reverse path | Medium | Additive cancel + reverse |
| H2-F5 | No pending balance reservation | Info | Keep unless product requires hold |
| H2-F6 | No carry-forward / accrual / adjustments | Medium | Additive tables if product requires |
| H2-F7 | ESS no remaining-balance guard on apply | Medium | Optional validate remaining |
| H2-F8 | Admin create skips overlap check | Medium | Reuse `hasOverlappingLeaveRequest` |
| H2-F9 | Half-day not first-class | Low | Defer |
| H2-F10 | Approval/Matrix paths OK | Info | Do not rewrite |

---

## 16. Recommended implementation order (NOT executed)

1. **H2-A** — Unpaid leave payroll integrity (use existing `paid` flag).  
2. **H2-B** — Approve/undo/cancel ↔ attendance reverse consistency.  
3. **H2-C** — Day policy (calendar vs working days + holidays) — product decision required.  
4. **H2-D** — Balance guards (ESS remaining; Admin overlap).  
5. **H2-E** — Accrual/carry-forward only if required (additive schema).  
6. Tests + ESS leave regressions + Phase D payroll regressions.  
7. Certification `HR-PHASE-H2-LEAVE-CERTIFICATION.md`.

---

## 17. Audit gate

```text
[x] Canonical leave tables mapped
[x] paid flag exists and unpaid default documented
[x] Balance formula + pending non-consumption confirmed
[x] Approval → balance + attendance timing confirmed
[x] Unpaid=paid-for-payroll Phase D finding re-confirmed from code
[x] Day calc / holidays / half-day assessed
[x] ESS / overlap / cancel / undo gaps listed
[x] Implementation plan sketched — NOT executed
[ ] Implementation (blocked until audit acceptance)
```

**STOP.** Await acceptance before any leave business-logic change.
