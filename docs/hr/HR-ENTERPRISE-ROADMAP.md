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

**Status:** COMPLETE (2026-08-14)  
**Audit:** `docs/hr/HR-PHASE-F-APPROVAL-AUDIT.md`  
**Certification:** `docs/hr/HR-PHASE-F-APPROVAL-CERTIFICATION.md`

### Completed

1. F0 — Mapped leave/permission/request/payroll pending sources to `ApprovalOversightService`.  
2. `HrApprovalInboxService` — company-scoped read-only aggregator + ApprovalItem DTO.  
3. `GET hr/approvals-inbox` + menu **عمليات بانتظار إجراء** + dashboard banner.  
4. Company approve routes remain blocked; SA deep-links to oversight.  
5. Decisions/Expenses documented deferred (modules absent).  
6. Tests: `tests/hr/HrPhaseFApprovalTest.php` + B–E/ESS regressions.

### Deferred

- Decisions / HR expenses queues.  
- Approval matrix stages (Phase G).  
- Priority/SLA fields.

### Remaining risks

- Inbox counts depend on oversight source definitions staying in sync.  
- Company users can view pending but cannot approve (by design).

**Exit:** Inbox usable; existing oversight still works. **Met.**

---

## Phase G — P1 Requests + Approval Matrix

**Status:** COMPLETE (2026-08-14)  
**Audit:** `docs/hr/HR-PHASE-G-APPROVAL-MATRIX-AUDIT.md`  
**Certification:** `docs/hr/HR-PHASE-G-APPROVAL-MATRIX-CERTIFICATION.md`

### Completed

1. Additive `rateb_hr_approval_matrices` / `_stages` / `_progress` (version + stage snapshot).  
2. `HrApprovalMatrixService` — governance overlay; no domain status writes.  
3. `ApprovalOversightService` gates leave / permission / request; final stage uses existing finalizers.  
4. No matrix ⇒ exact pre-G single-shot behavior.  
5. Certificate via `request_type` on employee requests (no separate workflow).  
6. EAP / Legacy WorkflowService not used for HR decide.  
7. Tests: `tests/hr/HrPhaseGApprovalMatrixTest.php`.

### Deferred

- Company stage-actor UI (inbox stays read-only).  
- Manager hierarchy.  
- Dedicated matrix admin screens (`saveMatrix` API ready).

### Remaining risks

- In-flight progress depends on snapshot integrity.  
- Role/user stage checks matter only if non-SA actors reach Oversight process.

**Exit:** Leave + certificate/request can use matrix config; fallback intact. **Met.**

---

## Phase H — P1 Approval Matrix Production Governance

**Status:** COMPLETE (2026-08-14)  
**Audit:** `docs/hr/HR-PHASE-H-MATRIX-GOVERNANCE-AUDIT.md`  
**Certification:** `docs/hr/HR-PHASE-H-MATRIX-GOVERNANCE-CERTIFICATION.md`

### Completed

1. `HrApprovalMatrixValidator` — hard reject invalid sources/stages/approvers.  
2. `saveMatrix` defaults to DRAFT (`enabled=0`); `activateMatrix` / `deactivateMatrix`.  
3. No silent coercion of unknown approver types.  
4. Company-scoped user/role validation; specific `request_type` beats wildcard.  
5. Runtime self-approval guard; config changes via `AuditService`.  
6. Tests: `tests/hr/HrPhaseHMatrixGovernanceTest.php`.

### Deferred

- Matrix admin UI.  
- SoD hard-fail policy (warnings only today).

**Exit:** Unsafe matrices cannot activate; Oversight engine unchanged. **Met.**

---

## Phase H2 — P1 Leave depth (former Phase H charter)

**Status:** COMPLETE (`docs/hr/HR-PHASE-H2-LEAVE-CERTIFICATION.md`)

**Delivered:**

1. Canonical create: `HrService::createPendingLeaveRequest` (Admin + ESS).  
2. Overlap + balance overdraw guards (transaction + employee `FOR UPDATE`).  
3. Paid/unpaid via `leave_types.paid` + `paid_snapshot`; unpaid → batch payroll deduct days (existing `/30`).  
4. Cancel + Oversight undo: restore balance once; reverse only `leave_request_id` attendance.  
5. Posted payroll overlap blocks cancel/undo (no silent mutate).  
6. Additive migration `249_hr_phase_h2_leave_integrity.sql`.  
7. Tests: `run-hr-phase-h2-leave-tests.php` + B–H / ESS regressions CLEAR.

**Deferred (explicit):** working-day/holiday calendar; AM/PM half-day; balance carry-forward/expiry; payroll correction engine for already-posted periods.

---

## Phase I — P1 Employee Master 360 (Admin product)

**Status:** COMPLETE (`docs/hr/HR-PHASE-I-EMPLOYEE-360-CERTIFICATION.md`)

**Delivered:**

1. Canonical show route upgraded to Employee 360 (`hr/employees/{id}`).  
2. `HrEmployee360Service` company-scoped read-only aggregation.  
3. Header + KPIs + tabs (overview server-rendered; others lazy).  
4. Leave/attendance/payroll/requests/documents/timeline from existing SoT.  
5. Salary/payroll gated by existing RBAC; foreign employee → 404.  
6. Employment contracts / letter PDF / mobile explicitly deferred.

**Exit:** Admin can open an employee and answer “Who is this?” without a second master.

---

## Phase I (legacy roadmap note) — Attendance engine (extend, don’t fork)

**Status:** DEFERRED (renumbered — Employee Master 360 took Phase I product slot per Product Gap Audit)

**Goal:** Raw punch → daily → exceptions → payroll input.

1. Additive punch / exception tables linked to `employee_id`.  
2. Calculation job writes/updates `rateb_attendance_records`.  
3. Work periods / shifts as config (start simple).  
4. Late/early/OT fields or linked exception rows.  
5. Payroll uses AttendanceInput only (stop ad-hoc COUNT in generator).  
6. ESS check-in remains the punch source for mobile.

**Exit:** Changing attendance calculation does not require editing payroll SQL.

---

## Phase J — P0 Actionable Approval Inbox (Company product)

**Status:** COMPLETE (`docs/hr/HR-PHASE-J-ACTIONABLE-INBOX-CERTIFICATION.md`)

**Delivered:**

1. `hr/approvals-inbox` actionable for leave / permission / request via Oversight + Matrix.  
2. Server-side actor authorization (`canActorDecide`: user / role / oversight).  
3. Intermediate matrix stages advance without domain finalize; final uses existing finalizers.  
4. Payroll remains view-only in this inbox.  
5. Legacy company `hr/*/approve` routes stay blocked.  
6. Optional comment audited on inbox decide; stage / last actor / next outcome in UI.

**Exit:** Authorized company actors can approve/reject from the inbox without ApprovalEngine3.

---

## Phase J (legacy roadmap note) — P1 Employment contracts

**Status:** DEFERRED (renumbered — Actionable Inbox took Phase J product slot per Product Gap Audit)

**Goal:** First-class contracts for the live employee.

1. ADR + additive `rateb_hr_employment_contracts` (name final in ADR).  
2. Lifecycle Draft→…→Terminated.  
3. Link allowances/basic salary/job/department/dates.  
4. Expiry notifications (cron + NotificationService).  
5. Optional flag: payroll requires active contract.  
6. Attachments via existing storage.

**Exit:** Contract CRUD + expiry alerts; payroll still works with flag OFF.

---

## Phase K — P0/P1 HireBridge + Employment contracts

**Status:** COMPLETE (`docs/hr/HR-PHASE-K-HIREBRIDGE-CONTRACTS-CERTIFICATION.md`)

**Delivered:**

1. `ready→deployed` HireBridge → create/link `rateb_employees` (idempotent).  
2. Duplicate prevention via `recruitment_candidate_id` + national_id link.  
3. Additive `rateb_hr_employment_contracts` (not commercial contracts).  
4. Lifecycle draft→active→expired/terminated + near-expiry alerts.  
5. Admin register + Employee 360 Employment tab.  
6. Tenant isolation, `hr-employees` RBAC, AuditService events.

**Exit:** Hire creates/links employee; employment contracts visible and actionable in Admin HR.

---

## Phase K (legacy roadmap note) — P2 Recruitment HireBridge only

**Status:** SUPERSEDED by Phase K product delivery above (HireBridge shipped with employment contracts).

---

## Phase L — P1 Letters + Employee Documents

**Status:** COMPLETE (`docs/hr/HR-PHASE-L-LETTERS-CERTIFICATION.md`)

**Delivered:**

1. Letter types: salary / employment / experience / EOS on `rateb_hr_employee_requests`.  
2. Approve via existing Oversight + Matrix (inbox).  
3. Arabic PDF issue → `rateb_documents` via DocumentService.  
4. Download from `hr/letters` + Employee 360 Letters tab.  
5. Additive migration `251` (`document_id`, `issued_at`, `issued_by`).  
6. Audit: request create / issue / reissue / download.

**Exit:** Request → approve → issue PDF → download audited.

---

## Phase M — P2 Decisions & disciplinary

**Status:** COMPLETE (2026-08-14)  
**Certification:** `docs/hr/HR-PHASE-M-DECISIONS-DISCIPLINARY-CERTIFICATION.md`

**Goal:** Unify decisions; activate disciplinary schema.

1. Decision facade + additive `rateb_hr_decisions` (Oversight source `hr_decision`).  
2. Types: promotion, salary adjustment/movement, transfer, salary stop, absence deduction, termination.  
3. Disciplinary service/UI on `rateb_hrm_disciplinary_actions` linked to `rateb_employees`.  
4. Side effects on Employee Master after approval only (execute-once CAS); salary audited; no payroll formula rewrite.  
5. Full audit trail (create / approve / reject / execute).  
6. Employee 360 Decisions + Violations tabs; inbox actionable.

**Exit:** At least termination + promotion decision flows; disciplinary CRUD. **Met.**

---

## Phase N — HR Command Center (visible product upgrade)

**Status:** COMPLETE (2026-08-14)  
**Certification:** `docs/hr/HR-PHASE-N-HR-COMMAND-CENTER-CERTIFICATION.md`

**Goal:** Make Admin HR feel like one integrated system on entry.

1. HR Command Center dashboard (workforce today, pending approvals/decisions, contracts, payroll, recent activity).  
2. Quick actions + employee search → Employee 360.  
3. Approval Center card (leave/request/decision/permission).  
4. Alerts via `NotificationService` + bounded domain queries.  
5. Employee 360 hub links.  
6. No SoT / payroll / approval / leave / ESS / mobile changes. No migration.

**Exit:** Users landing on `hr` see a command center, not a thin stat strip. **Met.**

> Note: Earlier roadmap draft used “Phase N” for Performance/org nav. That work is **deferred** (does not block this Command Center certification).

---

## Phase O — Organization + Succession + HR Analytics

**Status:** COMPLETE (2026-08-14)  
**Certification:** `docs/hr/HR-PHASE-O-ANALYTICS-CERTIFICATION.md`

1. Organization structure from `rateb_hr_departments` / job titles / `rateb_employees` + 360 links.  
2. Additive succession tables + Admin UI (critical role, holder, successors, readiness, skill gaps).  
3. Analytics snapshot + reports hub with filters and existing ExportController.  
4. Command Center analytics widgets.  
5. Salary aggregates gated by payroll/employee RBAC.  
6. No SoT / payroll formula / accounting / approval / leave / ESS / mobile / GOSI changes.

**Deferred from earlier O draft:** ESS manager API, Saudi/GOSI/WPS packages, cached enterprise tiles.

**Exit:** Managers can answer headcount / structure / attendance-leave-payroll-contracts attention from Admin HR. **Met.**

---

## Phase P — ESS Parity + Manager Self-Service — COMPLETE

**Commit:** `ebb3de21`  
**Cert:** `docs/hr/HR-PHASE-P-ESS-MANAGER-CERTIFICATION.md`

| Gate | Result |
|------|--------|
| P0 ESS | PASS |
| P1 Manager | PASS |
| P2 Approvals | PASS |
| P3 Certificates | PASS |
| P4 Payslips | PASS |
| P5 Notifications | PASS |
| P6 Security | PASS |
| P7 Saudi HR foundation | PASS |
| P8 Regression | PASS |

**Exit:** Employee ESS + manager team on same HR SoT. **Met.**

---

## Phase Q — (not started)

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
