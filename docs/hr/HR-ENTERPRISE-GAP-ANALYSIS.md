# RATIB ERP — HR-1 Enterprise Gap Analysis

**Status:** COMPLETE (analysis only)  
**Depends on:** `docs/hr/HR-ENTERPRISE-AUDIT.md`  
**Rule:** Reuse / extend existing; never invent parallel Employee2 / Payroll2 / Attendance2.

**Legend**

| Column | Meaning |
|--------|---------|
| Existing | Production path exists and is used |
| Partial | Exists but incomplete vs Enterprise target |
| Missing | Not found in ERP Admin HR domain |
| Reuse | What to reuse (canonical) |
| Action | Recommended next action (no implementation yet) |

---

## Capability matrix

| Capability | Existing | Partial | Missing | Reuse | Action |
|------------|----------|---------|---------|-------|--------|
| Employee Master (ops) | `rateb_employees` + `HrEmployeesController` | Fields thin; salary on master; single name | AR/EN names, DOB, nationality, manager, bank, GOSI, probation | **Canonical live master** for attendance/leave/payroll/ESS | Extend columns carefully; do not replace with HRMS profiles |
| Employee Master (HRMS) | `rateb_hrm_employee_profiles` + children | Soft-link only; not in sidebar | Sync with ops; national ID; bank | Talent overlay + richer profile | Make `legacy_employee_id` mandatory for active profiles; sync policy |
| Employee lifecycle audit | HRMS workflow + status_history | Ops status is plain enum | Candidate→Offer→Pre-Hire→Final Settlement | `HumanResourcesWorkflowService` | Extend statuses; mirror critical transitions onto ops with audit |
| Organization (ops) | departments, job titles, branches | Flat | Division/section/team, effective dates, cost center assign | Keep ops depts as payroll FKs until bridge | Bridge to HRMS org_units; never break branch/company |
| Organization (HRMS) | depts parent_id, org_units, positions, grades, locations | Not in main nav | Historical structure, cost centers | `OrganizationService`, `PositionService`, `GradeService` | Promote into Admin HR nav after link to ops |
| Employment contracts | — | Doc type `contract` metadata | Full contract lifecycle + renewals + alerts | Document storage + ApprovalOversight | New **additive** `rateb_hr_contracts` (or approved name) linked to `rateb_employees` — **not** a second employee table |
| Recruitment | Sibling `/recruitment` | No hire bridge; not under HR menu | Manpower request / requisition as HR objects | `RecruitmentWorkflowService` | Event/bridge: deployed → create/link employee |
| Attendance engine | Daily records + bulk + ESS punches | Status row only | Shifts, schedules, OT engine, exceptions approval, proof | `rateb_attendance_records`, `HrEssAttendanceService`, workplaces | Extend calculation layer **on top of** same table; feed payroll DTO |
| Leave management | Types, requests, balances, approve→attendance | No accrual/carry-forward/return/cancellation depth | Full lifecycle Draft→Returned | `HrService` + ESS leave | Extend types/balances; keep ESS reuse |
| Ops payroll | Periods/lines generate/approve/post | Absences only; no late/OT; post≠GL | Unified salary transactions; WPS; payslip GL | `HrService::generatePayrollLines` | Harden as interim engine; later optionally migrate to batches **with bridge** |
| Enterprise payroll | Batches/items/loans/OT/advances/settlements | Sum items; soft refs | Attendance-driven calc; auto GL | Soft-links + `PayrollWorkflowService` | Do not make SoT until attendance/leave inputs + GL adapter ready |
| Allowances / deductions | Ops components/structures; enterprise earning/deduction types | Dual catalogs | Unified salary transaction ledger | Ops structures for live; enterprise types for future | Unify catalog under one write path |
| Advances / loans | Ops HR loans; enterprise loans/advances; Pro advances API | Dual | Single ledger | Ops loans used by generatePayrollLines | Keep ops as live until enterprise consumes same deductions |
| Expenses | — | `rateb_payroll_reimbursements` table | HR expense UI + accounting | Accounting cash vouchers / future reimbursements | Decide owner: Accounting vs HR Compensation — ADR |
| Employee requests | `rateb_hr_employee_requests` + ESS | Types: salary cert, EOS, experience, other | Configurable Approval Matrix / multi-stage | Oversight + request table | Extend types + matrix config; no second workflow engine |
| Decisions | Promotions/transfers CRUD | — | Unified Decision types (termination, salary stop, etc.) | HRMS promotion/transfer + oversight | Introduce Decision facade over existing tables; don’t clone controllers |
| Performance | Reviews/goals/competencies | No cycles/calibration/dev plan | Efficiency + succession KPIs | HRMS performance services | Expand 23A; wire into HR nav |
| Succession | — | — | Entire talent pipeline | Positions + competencies | New domain tables only after P2; no parallel employee |
| Violations / disciplinary | Schema `rateb_hrm_disciplinary_actions` | — | Investigation, appeal, payroll link | Schema + AuditService | Add service/UI on existing table |
| Documents | Ops meta + HRMS meta + ESS docs | File upload weak on ops controller | Versioning, retention, ACL, expiry alerts | Existing document/storage + meta tables | Enable storage reuse; expiry jobs via NotificationService |
| Letters | Request types cover certificates | — | Template letter engine | Requests + documents | Templates on request completion |
| HR Settings | Bootstrap defaults | Scattered CRUD | Central settings / Saudi rules | Leave types, holidays, workplaces, components | Single Settings screen aggregating existing |
| Approval Inbox | Oversight sources for leave/permission/request/payroll | No SLA/priority/delegate | Unified HR inbox UI with decisions/expenses | `ApprovalOversightService` | Extend sources; UX inbox; keep company approve blocked |
| Dashboard | Ops counts (employees, present, leaves, draft payroll) | Thin | Workforce/turnover/contracts/recruitment tiles | `HrService::dashboardStats` | Aggregate APIs with caching; no full table scans |
| Reporting | Monthly attendance report, leave report; HRMS board; payroll reports | Ad-hoc SQL per screen | Shared report query layer | `HrService` reports + export helpers | Reporting service with reusable queries |
| Notifications | ESS reads NotificationService; oversight pending notify | — | Expiry / leave / payroll / attendance alerts | **NotificationService only** | Add HR event triggers |
| ESS / Mobile | `/api/v1/hr/*` + `ratib_hr_mobile` | Manager approvals missing | Full ESS letters/self-service depth | HrEss* → HrService | Keep thin adapters; never duplicate policy in Flutter |
| Audit & security | CSRF on forms; tenant models; payroll audit table | Coarse RBAC; email bind risk | Field-level salary ACL; contract audit | AuditService + status_history | Fix bind scope; split payroll perms |
| Multi-tenant | company_id ubiquitous on new tables | Nullable branch history; email fallback | Strict IDOR tests | TenantContext | Security phase first |
| Accounting post | — | Metadata refs | Real payroll GL via AccountingService | AccountingService | Adapter only after correctness |
| Saudi readiness | Leave catalog + recruitment passport/visa | EOS request type; settlement type `eos` | GOSI/WPS/iqama/saudization engines | Config tables later | Core vs Saudi config vs Gov integrations |
| Offline V2 HR BM | `public/v2/js/business/hr-module.js` | Not production UI | Admin-owned shared libs extraction | Architecture Lock | Do **not** add ERP screens under v2 |
| RATEB Pro HR | `pages/hr.php` / `api/hr/*` | Separate product | ERP unification | Out of scope | Do not merge into ERP without ADR |

---

## Gap themes (priority)

### P0 — Integrity / security / correctness

1. Choose **one live Employee Master** (`rateb_employees`) and require HRMS link.  
2. Stop treating `postPayroll` as accounting.  
3. Close cross-tenant email bind.  
4. Split salary visibility permissions.  
5. Prove attendance absences → payroll input (even if still simple).

### P1 — Core HR engines

Contracts, attendance calculation layer, leave depth, request approval matrix, unified approval inbox.

### P2 — Talent / decisions / docs

Recruitment hire bridge, performance depth, disciplinary UI, documents storage, decision facade.

### P3 — Advanced

Succession, analytics, ESS manager flows, GOSI/WPS, automation.

---

## Explicit non-gaps (already OK enough to extend)

- ESS leave/attendance **already call** `HrService` (good pattern).  
- ESS notifications already use `NotificationService`.  
- Oversight already centralizes company approval blocking for HR.  
- Enterprise layers are additive SQL (`IF NOT EXISTS`) — safe to keep while bridging.  
- Stack (PHP/MySQL, no new framework) is correct for this program.

---

## Duplicate systems decision table (no auto-merge)

| Duplicate | Canonical (recommended) | Secondary | Merge policy |
|-----------|-------------------------|-----------|--------------|
| Employees | `rateb_employees` | `rateb_hrm_employee_profiles` | Soft-link required; single write API later |
| Departments | ops for FKs short-term | HRMS for hierarchy | Sync or replace FK after ADR |
| Payroll run | ops periods/lines (live) | enterprise batches | Bridge or migrate with dual-run period |
| Loans | ops loans (live deduct) | payroll_loans | Feed one deduct source |
| Documents | pick one meta+storage path | other meta | Map `legacy_document_id` |
| Pro HR APIs | ERP Admin | `api/hr/*` | Separate product — no silent merge |

---

## Phase boundary

**HR-1 Gap Analysis: COMPLETE.**  
Next: `docs/hr/HR-ENTERPRISE-ARCHITECTURE.md`.
