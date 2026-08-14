# RATIB ERP — HR-3 Implementation Roadmap

**Status:** COMPLETE (plan only — awaiting approval before any implementation)  
**Depends on:** HR-0, HR-1, HR-2  
**Default:** no code / no migrations until this roadmap is explicitly approved.

---

## Priority bands

| Band | Focus |
|------|--------|
| **P0** | Data integrity, security, tenant isolation, payroll correctness, Employee Master |
| **P1** | Contracts, Attendance engine, Leave depth, Requests, Approvals inbox |
| **P2** | Recruitment hire bridge, Performance, Documents, Decisions, Disciplinary |
| **P3** | Succession, analytics, ESS manager flows, automation, government integrations |

Each phase below is **small and testable**. Do not skip P0.

---

## Phase A — Governance & freeze (docs / flags only)

**Goal:** Prevent parallel HR while transforming.

- [ ] Approve HR-0…HR-3 docs.  
- [ ] ADR: canonical Employee Master = `rateb_employees`.  
- [ ] ADR: ops payroll remains interim SoT until bridge criteria met.  
- [ ] ADR: no ERP screens under `public/v2`.  
- [ ] ADR: RATEB Pro HR out of scope.  
- [ ] Feature-flag registry for future GL / contracts / hire-bridge (defaults OFF).

**Exit:** Written ADRs; no production behavior change.

---

## Phase B — P0 Security & tenant hardening

**Goal:** Stop cross-company employee/user bind and salary over-exposure.

1. Remove or strictly gate global email fallback in `HrEmployeesController::autoLinkEmployeeUser`.  
2. Fix `HrEssEmployeeResolverService`: always scope by token `company_id`; remove unsafe cross-tenant return; `bindEmployeeUser` must include `company_id`.  
3. Ensure payroll show/export queries include `company_id`.  
4. Align ops payroll **post** with approve policy (block company post **or** require oversight + AuditService log); clarify UI “posted ≠ GL”.  
5. Add index on `rateb_employees.user_id` (additive).  
6. Decide ESS module gate: require company HR plan / `hr.view` via API middleware where appropriate (without breaking mobile).  
7. Introduce finer permissions for salary/payroll view (seed only; wire gradually).  
8. Tests: IDOR employee show, ESS resolver company mismatch, cross-tenant attendance write, payroll period cross-company, payroll post authorization.

**Exit:** Security tests green; no intentional cross-tenant bind.

---

## Phase C — P0 Employee Master integrity

**Goal:** One live master + optional rich profile.

1. Inventory orphan HRMS profiles (`legacy_employee_id` null).  
2. Admin tool: link HRMS profile ↔ ops employee (manual, audited).  
3. Policy: creating “active” HRMS profile requires ops employee link (feature-flagged).  
4. Extend ops employee fields **additively** only where missing and required for P1 (e.g. manager_user_id / manager_employee_id, name_ar) — **no destructive ALTER**.  
5. Audit log on salary_base changes.

**Exit:** Linkage report; salary change audit; no second master write path for ESS.

---

## Phase D — P0 Payroll correctness (no rewrite)

**Goal:** Make live payroll honest and less N+1; do not rebuild engine.

1. Extract `PayrollAttendanceInput` from attendance absences (single query per period).  
2. Refactor `generatePayrollLines` to batch-load structures/loans/absences.  
3. Rename UI copy / flash messages so “posted” ≠ “posted to GL” until Accounting adapter exists.  
4. Document field-level formula in payslip notes.  
5. Regression tests for generate/approve/post status transitions.  
6. **Do not** switch SoT to enterprise batches in this phase.

**Exit:** Same business results, clearer semantics, faster generate, tests.

---

## Phase E — P0 Accounting adapter (flagged OFF)

**Goal:** Real GL path without turning it on in production by default.

1. Design `PayrollAccountingAdapter` calling `AccountingService` only.  
2. Map accounts via HR Settings (additive config).  
3. Feature flag `hr.payroll.gl_posting`.  
4. When OFF: keep status-only post. When ON: journal + status in one transaction.  
5. Accounting regression tests with flag ON in test env.

**Exit:** Adapter merged; production flag OFF.

---

## Phase F — P1 Approval Inbox unification

**Goal:** One HR pending inbox UX on top of `ApprovalOversightService`.

1. HR Approval Inbox page listing leave / permission / request / payroll (+ later decisions).  
2. Columns: type, employee, requester, age, amount (if any), link.  
3. Keep company-side approve blocked; actions go through oversight services.  
4. Optional: priority/SLA fields additive later.  
5. Wire prompt “عمليات بانتظار إجراء” to this inbox (labels), not four orphan pages.

**Exit:** Inbox usable; existing oversight still works.

---

## Phase G — P1 Requests + Approval Matrix

**Goal:** Configurable stages without a second workflow product.

1. Additive matrix tables (request_type → stages).  
2. Map leave / employee requests / permission into matrix where possible.  
3. Extend request types (transfer, resignation, attendance correction, advance) carefully.  
4. ESS create still hits same domain services.  
5. Tests per type for stage skip rules.

**Exit:** At least leave + certificate request use matrix config.

---

## Phase H — P1 Leave depth

**Goal:** Closer to enterprise leave without breaking ESS.

1. Balance history / carry-forward / expiry fields (additive).  
2. Cancellation & return-from-leave flows.  
3. Unpaid leave → payroll input flag.  
4. Keep `HrEssLeaveService` → `HrService`.  
5. Notification triggers on submit/approve/reject via `NotificationService`.

**Exit:** ESS leave tests still green; new leave tests added.

---

## Phase I — P1 Attendance engine (extend, don’t fork)

**Goal:** Raw punch → daily → exceptions → payroll input.

1. Additive punch / exception tables linked to `employee_id`.  
2. Calculation job writes/updates `rateb_attendance_records`.  
3. Work periods / shifts as config (start simple).  
4. Late/early/OT fields or linked exception rows.  
5. Payroll uses AttendanceInput only (stop ad-hoc COUNT in generator).  
6. ESS check-in remains the punch source for mobile.

**Exit:** Changing attendance calculation does not require editing payroll SQL.

---

## Phase J — P1 Employment contracts

**Goal:** First-class contracts for the live employee.

1. ADR + additive `rateb_hr_employment_contracts` (name final in ADR).  
2. Lifecycle Draft→…→Terminated.  
3. Link allowances/basic salary/job/department/dates.  
4. Expiry notifications (cron + NotificationService).  
5. Optional flag: payroll requires active contract.  
6. Attachments via existing storage.

**Exit:** Contract CRUD + expiry alerts; payroll still works with flag OFF.

---

## Phase K — P2 Recruitment HireBridge

**Goal:** Candidate → Employee without duplicate independent records.

1. On recruitment `deployed` / accepted offer: create or link `rateb_employees`.  
2. Optional HRMS profile create with `legacy_employee_id`.  
3. Idempotent by candidate id.  
4. Prevent duplicate candidates (unique national id per company if present).  
5. Do not modify recruitment ownership of candidate tables.

**Exit:** One happy-path hire creates linked employee; tests for idempotency.

---

## Phase L — P2 Documents & letters

**Goal:** Real document lifecycle on existing storage.

1. Enable file attach for HR documents using existing storage service.  
2. Version + expiry + access control.  
3. Letter templates for salary/experience certificates on request completion.  
4. ESS document list continues via domain service.

**Exit:** Upload/download audited; expiry notification job.

---

## Phase M — P2 Decisions & disciplinary

**Goal:** Unify decisions; activate disciplinary schema.

1. Decision facade + additive header table linking existing promotion/transfer rows.  
2. Types: termination, salary stop, salary adjustment, promotion, transfer, absence deduction, payroll approval (link).  
3. Disciplinary service/UI on `rateb_hrm_disciplinary_actions`.  
4. Side effects go through EmployeeMaster / Attendance / Payroll transactions — no silent column edits.  
5. Full audit trail.

**Exit:** At least termination + promotion decision flows; disciplinary CRUD.

---

## Phase N — P2 Performance & org nav

**Goal:** Bring HRMS talent features into the main HR product surface.

1. Add Performance / Org / Training entries to `hr-menu.php` (routes stay `/hrm/*` or redirect).  
2. Performance cycles / self vs manager review (extend 23A).  
3. Do not create Performance2 tables.

**Exit:** Users reach performance from HR nav; 23A tests green.

---

## Phase O — P3 Succession, analytics, ESS, Saudi integrations

1. Succession tables for critical positions / successors / readiness.  
2. Reporting architecture service (workforce, turnover, OT, contract expiry, movements).  
3. ESS manager approvals API (authorized only).  
4. Saudi config module (identifiers, iqama expiry).  
5. GOSI / WPS connectors as separate integration packages — feature-flagged, not claimed “compliant” until certified.  
6. Dashboard enterprise tiles with cached aggregates.

**Exit:** Optional modules behind flags; core HR unaffected if OFF.

---

## Cross-cutting for every implementation phase

| Gate | Requirement |
|------|-------------|
| Tests | Security + domain + regression listed in phase |
| Migrations | Additive, reversible where possible, backup-safe |
| Feature flags | Risky behavior default OFF |
| Deploy | Follow fast deploy / PX-Deploy integrity rules |
| Docs | Update this roadmap checkbox status |
| Parallel ban | If duplicate SoT appears, STOP → Architecture Conflict |
| Accounting | No direct journal SQL from HR |
| Notifications | `NotificationService` only |
| Frontend | Admin views + existing CSS/JS files; no inline CSS/JS; no new SPA framework |

---

## Suggested first implementation slice (after approval)

**Recommended Phase 1 (execution):** **Phase B + C + D only**  
(Security bind fixes + employee link policy + payroll honesty/N+1).  

This delivers integrity without touching contracts/recruitment/UI megamenu.

---

## Explicitly deferred / forbidden until later ADR

- Dropping `rateb_hrm_*` or `rateb_payroll_*`  
- Rewriting payroll from scratch  
- Building `/erp/human_resources` as a second route tree  
- Merging RATEB Pro HR  
- Auto GL in production without flag + tests  
- Government “compliance” marketing claims  

---

## Phase boundary

**HR-3 Roadmap: COMPLETE.**  
**STOP — do not start implementation code until Audit + Architecture + this Roadmap are approved.**
