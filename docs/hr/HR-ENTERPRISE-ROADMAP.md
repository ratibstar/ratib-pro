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

**Status:** COMPLETE (2026-08-14)  
**Certification:** `docs/hr/HR-PHASE-B-SECURITY-CERTIFICATION.md`

### Implemented controls

1. ESS resolver + `bindEmployeeUser` company-scoped; no cross-tenant fallback.  
2. Admin `autoLinkEmployeeUser` company-scoped only.  
3. Ops payroll `approve`/`post` tenant guard; `post` requires `approved` (no draft bypass).  
4. Payroll show/export/payslip queries scoped by `company_id`.  
5. `rateb_payroll_audit` + `AuditService` on calculate/approve/post.  
6. ESS leave apply → `ApprovalOversightService::notifyPendingSubmission` (existing NotificationService).  
7. Additive index migration `247_hr_phase_b_ess_user_company_index.sql`.  
8. Tests: `tests/hr/HrPhaseBSecurityTest.php`.

### Remaining risks

- Company post-after-approve remains intentional UI workflow (not oversight-only).  
- ESS API still lacks `hr.view` module middleware gate (deferred).  
- Pre-existing Phase 23A nav/route test drift (out of scope).

**Exit:** Security tests green; no intentional cross-tenant bind. **Met.**

---

## Phase C — P0 Employee Master integrity

**Status:** COMPLETE (2026-08-14)  
**Audit:** `docs/hr/HR-PHASE-C-EMPLOYEE-MASTER-AUDIT.md`  
**Certification:** `docs/hr/HR-PHASE-C-EMPLOYEE-MASTER-CERTIFICATION.md`

### Scope completed

1. C0 — Employee Master audit (canonical = `rateb_employees`).  
2. C1 — Canonical identity documented; no Employee2.  
3. C2 — HRMS soft-link `legacy_employee_id` same-company assert; no auto-merge.  
4. C3 — Read-only duplicate/orphan diagnostics (`HrEmployeeIntegrityService::diagnoseCompany`).  
5. C4 — Ops `salary_base` + enterprise salary change audit (old/new/effective) via existing `AuditService` / `PayrollAudit`.  
6. C5 — `tests/hr/HrPhaseCSecurityTest.php` + Phase B regression.

### Deferred

- Production orphan remediation / FK migrations.  
- Manual Admin “link HRMS ↔ ops” UI tool.  
- Employment contracts module.  
- Payroll calculation rewrite (Phase D).

### Remaining risks

- Dual representation (ops + HRMS) remains until manual linking improves.  
- Historical orphan rows may exist; diagnostics report only.  
- Direct DB salary updates outside app still unaudited.

**Exit:** Linkage/salary audit hardening without second master. **Met.**

---

## Phase D — P0 Payroll correctness (no rewrite)

**Status:** COMPLETE (2026-08-14)  
**Audit:** `docs/hr/HR-PHASE-D-PAYROLL-AUDIT.md`  
**Certification:** `docs/hr/HR-PHASE-D-PAYROLL-CORRECTNESS-CERTIFICATION.md`

### Completed

1. D0 — Full ops payroll flow traced (attendance → lines → approve → post; no GL/transfer).  
2. D1 — Absence inputs batch-loaded; period BETWEEN month bounds; leave ≠ absent.  
3. D2 — Formula documented; `salary_base` confirmed; enterprise overlay not competing.  
4. D3 — State machine unchanged; `post` idempotent when already posted.  
5. D4 — UI/lang clarify posted ≠ GL ≠ bank; audit flags `gl_posted`/`bank_transfer` false.  
6. D5 — Read-only `HrPayrollIntegrityService::diagnosePeriod`.  
7. D6 — `tests/hr/HrPhaseDSecurityTest.php` + B/C/ESS regressions.

### Deferred

- Unpaid leave payroll distinction.  
- Historical effective-dated ops salary.  
- GL adapter (Phase E, flag OFF).  
- Bank / WPS transfers.

### Remaining risks

- Operators may still mentally equate “post” with accounting until training/UI habit settles.  
- Generate-time salary snapshot can diverge from intended effective dates without process discipline.  
- N+1 removed for absences/structures/loans; other HR report queries unchanged.

**Exit:** Same business formula, clearer financial-state semantics, tests. **Met.**

---

## Phase E — P0 Accounting adapter (flagged OFF)

**Status:** COMPLETE (2026-08-14) — **flag remains OFF in production**  
**Audit:** `docs/hr/HR-PHASE-E-ACCOUNTING-AUDIT.md`  
**Certification:** `docs/hr/HR-PHASE-E-ACCOUNTING-CERTIFICATION.md`

### Completed

1. E0 — AccountingService API + COA payroll accounts audited.  
2. `HrPayrollAccountingConfig` — env flag `HR_PAYROLL_ACCOUNTING_ENABLED` default OFF.  
3. `HrPayrollAccountingAdapter` — maps payroll line sums → `AccountingService::createManualDraft`.  
4. Company / fiscal / idempotency / failure audit wired.  
5. Reconciliation diagnostic aware of flag + journal marker.  
6. Tests: `tests/hr/HrPhaseEAccountingTest.php` + B/C/D/ESS regressions.

### Deferred

- Enabling flag in production.  
- Ledger-auto-posted journals.  
- Cost-center / per-component mapping.  
- Bank / WPS (out of scope).

### Remaining risks

- Operators enabling the flag without staging validation.  
- Draft journals still require finance posting discipline.  
- Closed fiscal periods cause accounting_failed while payroll stays posted.

**Exit:** Adapter merged; production flag OFF. **Met.**

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
