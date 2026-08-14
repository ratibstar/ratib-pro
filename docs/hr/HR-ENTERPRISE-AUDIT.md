# RATIB ERP — HR-0 Enterprise Audit

**Status:** COMPLETE (evidence only)  
**Date:** 2026-08-14  
**Scope:** Online ERP HR + adjacent Payroll / Recruitment / ESS mobile + out-of-scope stacks  
**Implementation:** NONE — STOP after HR-0 … HR-3 docs  
**Destructive changes:** NONE (no DROP / ALTER / route delete / payroll rewrite)

---

## Executive verdict

RATIB does **not** have a single Enterprise HR platform. It has **several coexisting HR stacks**. The menu in the transformation prompt (`/erp/human_resources/...` with Decisions, Contracts, Violations, Succession, Allowances, Letters, Settings) is a **target specification**. It is **not** the current production navigation.

**Production ERP Human Resources (what users actually open):**

| Surface | URL prefix (`rateb_app_route`) | Data | Used for |
|---------|--------------------------------|------|----------|
| Operational HR | `/admin/hr/*` | `rateb_employees`, `rateb_hr_*`, attendance, leave, ops payroll | **Canonical live HR** (sidebar + ESS) |
| Enterprise HRMS (Phase 23A) | `/admin/hrm/*` | `rateb_hrm_*` | Additive talent/org overlay; **not in main HR sidebar** |
| Enterprise Payroll (Phase 24A) | `/admin/payroll/*` | `rateb_payroll_*` (new namespace) | Additive payroll platform; **does not replace** `/hr/payroll` |
| Recruitment (Phase 15A) | `/admin/recruitment/*` | `rateb_recruitment_*` | Sibling module; **no hire → employee bridge** |

**Also present, must not be treated as ERP production UI:**

| Surface | Location | Status |
|---------|----------|--------|
| RATEB Pro / agency HR | `pages/hr.php`, `api/hr/*`, `js/hr/*` | Separate product HR (not ERP Admin) |
| Offline V2 HR BM | `rateb-erp/public/v2/js/business/hr-module.js` | Architecture Lock: **not** a production ERP frontend |
| Offline V1 HR | `rateb-erp/offline/**` Phase 4 / 23B | Flags default OFF; wraps online services |

**Do not create HR2 / Payroll2 / Employee2.** Those already exist as 23A/24A additive layers. The transformation must **unify by bridging**, not by adding a third employee table.

Estimated completion vs the Enterprise HR target in this prompt: **~32%**.

---

## 1. Current architecture

```
Admin shell  (/admin/*  — official ERP frontend)
├── Operational HR   HrControllers / HrExtendedControllers / HrService
│     rateb_employees  ← ESS + attendance + leave + ops payroll
├── HRMS overlay     HumanResourcesControllers / HumanResourcesDomainServices
│     rateb_hrm_employee_profiles  (legacy_employee_id soft-link, no sync job)
├── Payroll overlay  Payroll*Controllers / PayrollDomainServices
│     rateb_payroll_batches / items  (legacy_employee_id + hrm_employee_profile_id)
├── Recruitment      sibling  rateb_recruitment_candidates
└── Approval inbox   ApprovalOversightService  (hr_leave, hr_permission, hr_request, hr_payroll)

Mobile ESS  /api/v1/hr/*  → HrEss*Service → HrService + rateb_employees.user_id
```

Stack constraints (verified): PHP Core + MySQL/MariaDB. No Laravel/Symfony. Controllers are thin-to-CRUD. Tenant via `company_id` + `TenantContext`. Branch via `branch_id` on ops employee/attendance/leave/payroll lines.

### 1.1 Prompt routes vs actual routes

Prompt path `/erp/human_resources` does **not** exist. `rateb_app_route('hr')` yields `admin/hr` (HR is not in the ops conflict-root list).

| Prompt (target spec) | Actual production | Status |
|----------------------|-------------------|--------|
| `/erp/human_resources` | `admin/hr` dashboard | Exists (ops stats only) |
| `.../employees` | `admin/hr/employees` | Exists (ops) + parallel `admin/hrm/employees` |
| `.../contracts` | — | **Missing** as HR employment contracts |
| `.../recruitment` | `admin/recruitment` (sibling, not under HR) | Partial |
| `.../attendance` | `admin/hr/attendance` | Partial (daily records, no punch engine) |
| `.../leaves` | `admin/hr/leaves` | Partial |
| `.../payroll` | `admin/hr/payroll` **and** `admin/payroll/*` | Dual |
| `.../expenses` | — | **Missing** as HR expenses (enterprise payroll has `rateb_payroll_reimbursements` unused as HR UI) |
| `.../decisions` | — | **Missing** unified Decision engine |
| `.../allowances` | ops payroll components + enterprise earning types | Partial, not a dedicated module |
| `.../violations` | `rateb_hrm_disciplinary_actions` schema; **no controller** | Schema-only |
| `.../letters` | request types include certificates | Partial (request, not letter engine) |
| `.../requests` | `admin/hr/requests` | Partial |
| `.../settings` | — | **Missing** dedicated HR Settings screen |

Prompt sidebar groups (pending actions, efficiency, succession, fingerprint logs, work periods, sanctions, attendance proof, salary stop, promotions-as-decisions, WPS transfers) are **not** in `config/hr-menu.php`.

Current HR sidebar (`rateb-erp/config/hr-menu.php`): overview, employees, departments, job titles, holidays, workplaces, permission requests, daily attendance, bulk attendance, monthly attendance report, leave requests/types/balances/report, loans, payroll list/components/structure, documents, employee requests, fleet.

HRMS (`/hrm/*`) is reachable by URL and internal dashboard links only — **not wired into `sidebar-hr-nav.php`**.

---

## 2. Answers to Definition of Done (23 questions)

### 1. Where is Employee Master?

**Live / ESS / payroll-ops / attendance / leave:** `rateb_employees`  
- Model: `Rateb\App\Models\Employee` in `rateb-erp/app/models/HrModels.php`  
- Controller: `HrEmployeesController` in `HrControllers.php`  
- Fields (ops): `employee_code`, `name`, `email`, `phone`, `national_id`, `department_id`, `job_title_id`, `job_title`, `branch_id`, `hire_date`, `salary_base`, `user_id`, `status` (`active|inactive|terminated`), `notes`  
- Missing on ops master: Arabic/English split names, nationality, gender, DOB, address, emergency contact, manager, employment type, probation, contract, bank, GOSI, iqama, dependents, qualifications, insurance, history.

**HRMS overlay (not live SoT):** `rateb_hrm_employee_profiles`  
- Service: `EmployeeProfileService` in `HumanResourcesDomainServices.php`  
- Extra: `first_name`/`last_name` + AR, `manager_profile_id`, `employment_type`, `grade_id`, `org_unit_id`, `location_id`, `position_id`, `workflow_status`, `termination_date`, `legacy_employee_id`  
- Child tables: contacts, dependents, emergency contacts, certifications, licenses, skills, languages.  
- **No sync job** from/to `rateb_employees`. Link is optional `legacy_employee_id`.

**Third system:** `api/hr/employees.php` (RATEB Pro) — out of ERP Admin.

### 2. Where is Payroll Engine?

**Live engine used by HR UI + ESS payslips (legacy lines):**  
`HrService::generatePayrollLines` / `approvePayroll` / `postPayroll`  
Tables: `rateb_payroll_periods`, `rateb_payroll_lines`, `rateb_hr_payroll_components`, `rateb_hr_payroll_structures`, `rateb_hr_loans`.

Calculation (verified in `HrService.php`):

```
active employees.salary_base
+ structure allowances
− structure deductions
− loan installment in period
− (salary_base/30) × absent attendance days
= net
```

`postPayroll` **only sets status = posted**. It does **not** call `AccountingService`. Name implies GL; behavior is a status flip.

**Additive engine (not replacing live):** `PayrollCalculationService::calculateBatch` sums `rateb_payroll_items` already stored; does **not** pull attendance/leave; no auto GL (`accounting_post_ref` metadata only).

### 3. Where is Attendance Engine?

**Live:** `rateb_attendance_records` unique `(company_id, employee_id, attendance_date)`  
Statuses: `present|absent|late|leave|holiday`  
Controllers: `HrAttendanceController`, `HrAttendanceBulkController`  
ESS: `HrEssAttendanceService` → `HrService` (check-in/out writes same table).  
Related: `rateb_hr_workplaces` (geo), `rateb_hr_permission_requests`, `rateb_hr_holidays` + `HrService::syncHolidayAttendance`.

**Not present:** raw punches, shifts, work-period engine, overtime from attendance, late/early policy engine, attendance proof workflow, sanctions, corrections/approvals as a calculation pipeline.  
ESS comment: “No shift/payroll/attendance policy calculations.”

Payroll **re-counts** `status = 'absent'` days itself — there is no Attendance→Payroll input DTO. This violates the target rule “Payroll must not recompute attendance.”

### 4. Where is Leave Engine?

**Live:** `rateb_leave_types`, `rateb_leave_requests`, `rateb_leave_balances`  
Service: `HrService` (`ensureDefaultLeaveTypes`, `approveLeave`, `applyApprovedLeave`, balances, reports)  
Default catalog (codes): annual, sick, unpaid, emergency, maternity, paternity, hajj, marriage, bereavement, study, exam, compensatory, work_injury, iddah.  
Request statuses: `pending|approved|rejected|cancelled` (not Draft→Scheduled→Active→Returned).  
On approve: increments used balance + writes `attendance_records.status = 'leave'` for each day if missing.  
ESS: `HrEssLeaveService` correctly **reuses** `HrService` (no duplicate policy).  
HRMS does **not** own leave.

### 5. Where is Contract Engine?

**HR employment contracts: missing** as first-class entity.  
Document type lookup includes `'contract'` on `rateb_hr_documents` (metadata only; `filesEnabled = false` on documents controller).  
Recruitment has `rateb_recruitment_contracts` (candidate visa/recruitment contract — not employment).  
EPROC `contracts` module is supplier/procurement — not HR.

### 6. Where is Recruitment?

Sibling module Phase 15A: `rateb-erp/migrations/181_recruitment_platform.sql`  
Services: `RecruitmentWorkflowService`, `RecruitmentAgencyService`, `RecruitmentContractService`, `RecruitmentSupport`, `RecruitmentTimelineService`  
Routes: `admin/recruitment`, candidates, agencies  
Workflow includes `deployed`. **No code creates `rateb_employees` or HRMS profile on deploy.**  
Website career portal (`CareerApplicationService`) is marketing/CMS — not ERP HR hire.

### 7. Where is Approval Workflow?

**Not a configurable HR Approval Matrix.** Three mechanisms:

1. **Oversight inbox** — `ApprovalOversightService` sources: `hr_leave`, `hr_permission`, `hr_request`, `hr_payroll` (draft periods). Company-side approve/reject routes are **blocked** (`$blockCompanyApprovalAction`) and redirected to oversight.  
2. **HRMS workflow** — `HumanResourcesWorkflowService` for employee/training/performance status only (not multi-step manager→HR→finance).  
3. **Enterprise Approval platform** (`approval` module) — generic; HR does not register a reusable request-type matrix here as the HR engine.

No SLA, delegate, or per-request-type stage configuration for HR.

### 8. Where is Decision Engine?

**Missing.** Promotions/transfers exist as HRMS CRUD (`PromotionService`, `TransferService`) writing `rateb_hrm_promotions` / `rateb_hrm_transfers`. They are not a typed Decision with old/new values, approval, and payroll/org side effects. No termination/salary-stop/absence-deduction decision types as a unified engine.

### 9. Where is Performance?

HRMS: `rateb_hrm_performance_reviews`, `rateb_hrm_goals`, `rateb_hrm_competencies`  
Services: `PerformanceReviewService`, `GoalService`, `CompetencyService`  
Workflow: `draft → submitted → approved → closed → archived`  
UI: `admin/hrm/performance`, goals, competencies (not in main HR sidebar).  
Missing vs target: cycles, KPIs as first-class, self-review vs manager-review, calibration, development plan, “employee efficiency” screen.

### 10. Where is Succession?

**Missing.** No tables, routes, services, or menu items for critical positions / successors / readiness.

### 11. Where are Documents?

Ops: `rateb_hr_documents` — title, doc_type (`contract|id_copy|certificate|medical|general`), issue/expiry, notes. File upload on this controller is **disabled**.  
HRMS: `rateb_hrm_employee_documents_meta` — `storage_key` metadata; `EmployeeDocumentMetaService`.  
ESS: `HrEssPayslipDocumentService` + `HrEssDocumentController` (`/api/v1/hr/documents`, payslip file).  
Generic CrudController document panel is registered on HR CRUD routes.  
**Do not invent a new file store** — reuse existing document/storage services; HR currently under-uses them.

### 12. Where are Violations?

`rateb_hrm_disciplinary_actions` + `HrmDisciplinaryAction` model. **No domain service, no controller, no UI.** Schema-only (`action_type` default `warning`). Rewards table similarly schema-light.

### 13. Where are HR Settings?

**No dedicated HR Settings screen.** Defaults are bootstrapped in `HrService::bootstrapTenant()` (leave types + job titles). Leave types / holidays / workplaces / payroll components are scattered CRUD. Mobile console setting: `migrations/203_hr_mobile_console_setting.sql`. RATEB Pro has `api/hr/settings.php` (out of ERP).

### 14. Tables per domain

See §8 Database map.

### 15. Services that MUST be reused

| Service | Reuse as |
|---------|----------|
| `HrService` | Live attendance/leave/ops payroll/balances/reports |
| `HrEss*` + `HrEssEmployeeResolverService` | Mobile/ESS — already wraps `HrService` |
| `NotificationService` | Only notifier (do not create a second) |
| `AccountingService` | Future payroll/expense posting (not used by HR today) |
| `AuditService` | Existing audit log |
| `ApprovalOversightService` | Pending inbox foundation |
| `HumanResourcesWorkflowService` | HRMS status transitions (extend, don’t fork) |
| `PayrollWorkflowService` | Enterprise batch status (if that engine is later chosen) |
| `TenantContext` / `rateb_erp_mw` / entity permissions | AuthZ |
| Existing file/document storage on CRUD | Attachments |

### 16. Duplicate / parallel services

| Function | Old / live | Additive / unused as SoT | Third |
|----------|------------|--------------------------|-------|
| Employee | `rateb_employees` | `rateb_hrm_employee_profiles` | Pro `api/hr/employees.php` |
| Department | `rateb_hr_departments` | `rateb_hrm_departments` + `rateb_hrm_org_units` | |
| Job | `rateb_hr_job_titles` | `rateb_hrm_positions` + `rateb_hrm_grades` | |
| Workplace / location | `rateb_hr_workplaces` | `rateb_hrm_locations` | |
| Payroll run | `rateb_payroll_periods/lines` | `rateb_payroll_batches/items` | Pro `api/hr/salaries.php` |
| Loans | `rateb_hr_loans` | `rateb_payroll_loans` | Pro `api/hr/advances.php` |
| Documents | `rateb_hr_documents` | `rateb_hrm_employee_documents_meta` | Pro `api/hr/documents.php` |
| Attendance | `rateb_attendance_records` | — | Pro `api/hr/attendance.php` |
| Workflow | leave pending/approved | HRMS workflow_status | Recruitment workflow; Payroll batch workflow; Approval platform |
| AssignmentService name | Recruitment `AssignmentService` | HRMS `HrmAssignmentService` | (intentional rename) |

**Do not delete or merge automatically.** Documented for a later approved unification phase.

### 17. Mobile / ESS APIs

Registered in `rateb-erp/routes/modules/api.php` under `/api/v1/hr/*`:

| Method | Path | Controller | Domain service |
|--------|------|------------|----------------|
| GET | `/me` | HrEssMeController | HrEssEmployeeResolverService |
| GET | `/profile` | HrEssProfileController | HrEssProfileService |
| GET/POST | `/attendance/today\|history\|check-in\|check-out` | HrEssAttendanceController | HrEssAttendanceService → HrService |
| GET/POST | `/leave/balances\|requests\|apply` | HrEssLeaveController | HrEssLeaveService → HrService |
| GET | `/payslips`, `/payslips/{id}`, `/file` | HrEssPayslipController | HrEssPayslipDocumentService |
| GET | `/documents` + file | HrEssDocumentController | HrEssPayslipDocumentService |
| GET/POST | `/notifications*` | HrEssNotificationsController | **NotificationService** |
| GET | `/dashboard` | HrEssDashboardController | HrEssPhaseCService |
| GET/POST | `/requests` | HrEssEmployeeRequestsController | (ESS request adapter) |
| GET/POST | `/permission-requests` | HrEssPermissionRequestsController | HrEssPermissionRequestService |
| GET | `/ratings` | HrEssRatingsController | |
| GET | `/payment-methods` | HrEssPaymentMethodsController | |
| POST | `/settings/change-password` | HrEssSettingsController | |

Client: `ratib_hr_mobile/` adapters under `lib/core/adapters/erp_*`.  
Identity: `rateb_employees.user_id` only — **not** HRMS profiles.  
Approvals for others (manager inbox) are **not** exposed as ESS APIs.

### 18. Accounting integration

| Path | Behavior |
|------|----------|
| `HrService::postPayroll` | Status `posted` only — **no journal** |
| Enterprise payroll | `accounting_post_ref` column; `PayrollSupport`: “No auto GL posting” |
| Expenses | No HR expense posting |
| `AccountingService` | Used by stock, payments, inter-branch — **not by HR** |

Payroll/expenses **cannot** currently create wrong GL from HR because they **do not post**. The risk is **operational false confidence** (UI says posted) and a future naive GL implementation.

### 19. Notifications integration

`NotificationService` exists and is used by ESS list/mark-read, oversight `notifyOversightPending`, inventory, CRM, contracts, billing.  
**No dedicated HR notifiers** for contract expiry, probation, document expiry, leave decision, payroll completion, attendance exceptions, birthday.  
Verified gap: `HrEssLeaveService::apply` inserts `pending` and does **not** call `NotificationService` / `notifyOversightPending` / `WorkflowSubmissionService`. Oversight pending notify is used for procurement submissions today, not leave apply.  
Do **not** create NotificationService #2.

### 20. Tenant isolation issues

- Most ops/HRMS/payroll tables have `company_id` + Model `$tenantScoped = true`.  
- `HrEmployeesController::autoLinkEmployeeUser`: if no user in company, falls back to **global email lookup** (`ORDER BY id ASC LIMIT 1`) — cross-tenant bind risk.  
- `HrEssEmployeeResolverService`: lookup by `user_id` **without** `company_id`; if no row matches token company, it can **return another company’s employee**. Email fallback can be global. Check-in then writes token `company_id` + foreign `employee_id`.  
- `bindEmployeeUser`: `UPDATE rateb_employees SET user_id … WHERE id = :eid` — **no `company_id` predicate**.  
- Payroll show query joins lines without extra `company_id` predicate (relies on tenant-scoped `find($periodId)`).  
- Oversight leave approve uses `findByIdUnscoped` (documented: branch filter would hide rows). Correct for oversight, dangerous if copied into company controllers.

### 21. RBAC issues

Module `hr` with slugs: `hr.view|create|update|delete|training|performance|promotions|transfers|admin|manage|oversight`.  
Entity map (`entity-permissions.php`): `hr`, `hr-employees`, `hr-attendance`, `hr-leaves`, `hr-payroll` all use the **same** `hr.view` / `hr.manage` — **no separation** of payroll-sensitive vs directory-only.  
Enterprise payroll has its own `payroll.*` module (finer: calculate/review/approve/post).  
Ops HR payroll is gated by `hr.manage`, not `payroll.approve` / `payroll.post`.  
Company leave/permission/request/payroll **approve|reject** routes are hard-blocked to platform oversight; **`POST /hr/payroll/{id}/post` is still live** for company `hr.manage` (status flip, no GL, no AuditService log).  
Enterprise `/payroll/batches/{id}/transition` remains company-side (`payroll.review`/`approve`/`post`) — not redirected to oversight.  
ESS `/api/v1/hr/*` uses **ApiAuth only** — no `rateb_api_mw('hr')` / `hr.view` plan-module gate. Any bearer with an employee link can use ESS (including change-password).  
Salary_base is visible on employee index to anyone with `hr.view`.  
Recruitment is a separate module (`recruitment.*`).

### 22. Performance issues

- `HrEmployeesController::export` loads `all(5000, 0)`.  
- `generatePayrollLines` loops employees and runs **per-employee** absence COUNT + structure query + loan query (**N+1**).  
- Dashboard `HrService::dashboardStats` is 4 aggregate queries (acceptable) but has no caching; does not scale to “thousands of employees per request” for richer dashboards.  
- HRMS `boardCounts` COUNTs every workflow status per entity (small).  
- Attendance unique key is good; missing covering indexes for dashboard date+status beyond `idx_attendance_date`.  
- Missing index on `rateb_employees.user_id` (ESS resolver hot path).  
- Nav pending badges can COUNT storms (project already disables cold COUNT).

### 23. Top 10 risks blocking Enterprise-ready HR

1. **Dual Employee SoT** without sync — payroll/ESS use ops; talent/org use HRMS.  
2. **Dual Payroll SoT** — live generator vs enterprise batch summer; neither posts GL.  
3. **ESS tenant bind / attendance write** — resolver can return another tenant’s employee; `bindEmployeeUser` lacks `company_id`.  
4. **Company can still POST ops payroll `post`** while approve is oversight-only; no AuditService / no GL.  
5. **Attendance is a daily status row, not an engine** — payroll recomputes absences.  
6. **No employment contract entity** — cannot drive expiry, probation, renewal, salary from contract.  
7. **Recruitment does not hire into Employee Master.**  
8. **HRMS is an orphan UI** (not in production HR sidebar) — high chance of a third parallel screen if prompt menu is implemented naively.  
9. **Sensitive salary + coarse RBAC** — `hr.view` sees `salary_base`; ESS has no `hr.view` module gate.  
10. **Fourth HR product** (`pages/hr.php` / root `api/hr/*`) if anyone “unifies” the wrong tree.

---

## 3. Routes (production ERP)

Prefix: `rateb_app_route($path)` → `admin/{path}` for `hr`, `hrm`, `payroll`, `recruitment`.

### 3.1 Operational HR — `routes/modules/ops.php`

CRUD family (`hrCrudRoutes`): employees, departments, job-titles, holidays, workplaces, permission-requests, attendance, leaves, leave-types, loans, loan-types, documents, fleet, requests.  
Extra: `hr/attendance/bulk`, `hr/leaves/balances`, leave/permission/request **approve/reject blocked** to oversight, payroll generate/approve/post/payslip/export, `hr/reports`, `hr/reports/leaves`.  
Dashboard: `HrDashboardController` @ `hr`.

### 3.2 HRMS — Phase 23A

`hrm/dashboard`, employees (+ create/store/show/transition), departments, positions, organization (units/locations), training, performance, promotions, transfers, goals, competencies, reports, timeline.

### 3.3 Enterprise payroll — Phase 24A

`payroll`, `payroll/dashboard`, cycles, batches (+ calculate/transition), payslips, loans, advances, overtime, salary-structures, reports, timeline.

### 3.4 Recruitment — Phase 15A

`recruitment`, candidates, agencies (see recruitment tests / ops allowlist).

---

## 4. Controllers

| File | Classes (role) |
|------|----------------|
| `app/controllers/Company/HrControllers.php` | HrDashboard, HrEmployees, departments/job titles/attendance/leaves/payroll (ops) |
| `app/controllers/Company/HrExtendedControllers.php` | holidays, workplaces, permission requests, loans, components, structures, documents, fleet, requests, reports, bulk attendance |
| `app/controllers/Company/HumanResourcesControllers.php` | Hrm* (23A thin) |
| Payroll controllers (ops.php imports) | PayrollDashboard, Batches, Cycles, Payslips, Loans, Advances, Overtime, SalaryStructures, Reports, Timeline |
| `app/controllers/Api/HrEss*.php` | 13 ESS API controllers |

---

## 5. Services / repositories / models

No separate Repository layer. Models in `HrModels.php`, `HrmModels.php`, `PayrollModels.php`.

**Ops:** `HrService` (god-file: dashboard, leave catalog, payroll generate, holiday sync, reports, balances).  
**HRMS:** `HumanResourcesDomainServices.php` (god-file: 16 final classes listed in grep). `HumanResourcesWorkflowService`, `HumanResourcesSupport`, `EmployeeTimelineService`.  
**Payroll overlay:** `PayrollDomainServices.php` (15 final classes), `PayrollWorkflowService`, `PayrollSupport`, `PayrollTimelineService`.  
**ESS:** HrEssLeave/Attendance/Profile/PayslipDocument/PermissionRequest/PhaseC/EmployeeResolver.  
**Recruitment:** Recruitment* services (sibling).

---

## 6. Permissions / RBAC

Seed: `066_hr_module.sql` (`hr.view`, `hr.manage`); expanded in `189` (`hr.create|update|delete|training|performance|promotions|transfers|admin|oversight`).  
Payroll overlay: `payroll.view|create|calculate|review|approve|post|admin|manage`.  
Implies bundles in `config/permissions-system.php`.  
Company modules include `hr`, `payroll`, `recruitment` separately.

---

## 7. Workflows / notifications / audit

- Leave/request/permission/payroll-draft → oversight.  
- HRMS entity workflow + `rateb_hrm_status_history` + `rateb_hrm_timeline`.  
- Payroll batch workflow + `rateb_payroll_status_history`. Table `rateb_payroll_audit` + `PayrollAudit` model exist — **never written by app code**.  
- `AuditService` used in recruitment agencies; ops HR CRUD relies on generic controller audit where present.  
- Live oversight leave approve → `HrService` path: **no AuditService** log. Ops payroll `post` and ESS mutations (check-in, leave apply, change-password): **unaudited**.  
- Salary/bank/contract change audit: **incomplete** (no bank fields; salary is a column update).  
- ESS payslips: `HrEssPayslipDocumentService` dual-reads legacy `rateb_payroll_lines` **and** enterprise `rateb_payroll_payslips`.

---

## 8. Database map

**Rule for this phase:** no DROP/ALTER.

### 8.1 Operational HR (`067`, `068`, `074`, `080`, `121`, `136`–`139`)

| Table | Purpose | Owner | company_id | Used by | Duplicate? | Status |
|-------|---------|-------|------------|---------|------------|--------|
| rateb_hr_departments | Dept lookup | ops HR | Y | hr/departments, employee FK | vs hrm_departments | **Live** |
| rateb_hr_job_titles | Job titles | ops HR | Y | hr/job-titles | vs hrm_positions | **Live** |
| rateb_employees | Employee master | ops HR | Y | ESS, attendance, leave, ops payroll | vs hrm profiles | **Live SoT** |
| rateb_attendance_records | Daily attendance | ops HR | Y | UI + ESS + payroll absences | — | **Live** |
| rateb_leave_types | Leave catalog | ops HR | Y | leaves + ESS | — | **Live** |
| rateb_leave_requests | Leave requests | ops HR | Y | UI + ESS + oversight | — | **Live** |
| rateb_leave_balances | Entitlement/used | ops HR | Y | balances UI + ESS | — | **Live** |
| rateb_payroll_periods | Ops payroll run | ops HR | Y | hr/payroll + oversight | vs payroll_batches | **Live** |
| rateb_payroll_lines | Ops payslip lines | ops HR | Y | generate/show/ESS | vs payroll_items | **Live** |
| rateb_hr_holidays | Holidays | ops HR | Y | holidays + sync attendance | — | **Live** |
| rateb_hr_workplaces | Geo workplaces | ops HR | Y | workplaces + ESS geo | vs hrm_locations | **Live** |
| rateb_hr_permission_requests | Time permission | ops HR | Y | UI + ESS + oversight | — | **Live** |
| rateb_hr_loan_types / rateb_hr_loans | Ops loans | ops HR | Y | loans + payroll deduct | vs payroll_loans | **Live** |
| rateb_hr_payroll_components / structures | Salary components | ops HR | Y | payroll generate | vs payroll_salary_* | **Live** |
| rateb_hr_employee_requests | Certificates etc. | ops HR | Y | requests + ESS + oversight | — | **Live** |
| rateb_hr_fleet | Vehicle assign | ops HR | Y | fleet UI | not EAM | **Live** |
| rateb_hr_documents | Doc metadata | ops HR | Y | documents UI | vs hrm meta | **Live (meta)** |

FK gaps: ops tables generally **index** company/employee but **lack FOREIGN KEY** constraints (unlike 189/190).  
`branch_id` added later (`121`, `127`) — nullable historically.

### 8.2 HRMS (`189`) — 30 tables `rateb_hrm_*`

departments, positions, grades, locations, org_units, employee_profiles, employee_documents_meta, employee_contacts, dependents, emergency_contacts, certifications, licenses, skills, languages, training, training_history, performance_reviews, goals, competencies, disciplinary_actions, rewards, transfers, promotions, assignments, notes, comments, timeline, tags, entity_tags, status_history.

company_id + FK to `rateb_companies`. Soft delete. `public_uuid`. Optimistic `version`.  
**UI coverage:** profiles, depts, positions, org, training, performance, goals, competencies, promotions, transfers.  
**Schema-only / thin:** disciplinary, rewards, tags, licenses/skills/languages/dependents (services exist for some; no nav).

### 8.3 Enterprise payroll (`190`) — 26 tables `rateb_payroll_*`

salary_structures/components, earning/deduction types, employee_salary, cycles, run_periods, batches, items, payslips, overtime, bonuses, commissions, loans + installments, advances, reimbursements, settlements, adjustments, notes, comments, timeline, attachments_meta, status_history, assignments, audit.

**Does not ALTER** `rateb_payroll_periods`. Run periods named `rateb_payroll_run_periods` to avoid collision.

### 8.4 Recruitment (`181`)

agencies, candidates, passports, visas, medicals, contracts, interviews, skills, languages, experiences, educations, notes, activities, status_history, timeline, assignments.

### 8.5 Naming / orphans

- Inconsistent prefixes: `rateb_employees` (no `hr_`), `rateb_hr_*`, `rateb_hrm_*`, `rateb_payroll_*` (two generations).  
- No evidence of unused empty legacy HR tables beyond the dual-stack itself.  
- SQLite branch schema mirrors these tables (`schema/sqlite/branch-erp-schema.sql`).

---

## 9. Employee master field gap (ops vs target)

| Target field | Ops `rateb_employees` | HRMS profile / children |
|--------------|----------------------|-------------------------|
| employee number | employee_code | code |
| national / residency ID | national_id | — |
| name | name (single) | first/last + AR |
| nationality / gender / DOB | — | — |
| contact | email, phone | contacts table |
| emergency / address | — | emergency_contacts; no address |
| department / branch / job | department_id, branch_id, job_title_id | dept/position/org/location |
| manager | — | manager_profile_id |
| employment status/type | status enum | workflow_status + employment_type |
| hire / probation / contract | hire_date only | hire_date, termination_date |
| salary | salary_base | enterprise `rateb_payroll_employee_salary` |
| bank / GOSI / insurance | — | — |
| documents / qualifications / skills / dependents | docs table; no quals | certs, skills, dependents |
| history | — | timeline + status_history |

---

## 10. Lifecycle (actual)

**Ops employee:** `active | inactive | terminated` (CRUD status, not audited state machine).  
**HRMS employee:** `draft → registered → active ⇄ on_leave|suspended → terminated → archived` via `HumanResourcesWorkflowService` (actor, reason, from/to, version). Missing vs target: Candidate, Offer, Pre-Hire, Probation, Confirmed, Transferred, Final Settlement as first-class states.  
**Leave:** pending → approved|rejected|cancelled.  
**Ops payroll:** draft → approved → posted.  
**Payroll batch:** draft → prepared → calculated → reviewed → approved → posted → closed → archived.

---

## 11. Organization

Ops: flat departments + job titles + branches (company branches module) + workplaces.  
HRMS: parent_id departments, org_units (`unit_type`), positions, grades, locations, manager_profile_id, assignments.  
**No cost_center on employee.** Accounting cost centers exist separately; HR does not assign them.  
No effective-dated org history (only timeline events if used).

---

## 12. Saudi HR readiness (facts only — not a compliance claim)

| Topic | Evidence |
|-------|----------|
| GOSI integration | **Not found** in ERP HR migrations/services |
| WPS / bank transfer file | **Not found** (no WPS exporter under HR/payroll) |
| Iqama / passport expiry on employee | Ops: no; Recruitment: passport/visa tables; HR docs expiry dates generic |
| Saudization reporting | **Not found** |
| End-of-service | Request type `end_of_service`; enterprise `rateb_payroll_settlements` type includes `eos`; **no labor-rule engine** |
| Leave catalog | Includes hajj, iddah, maternity — **configuration**, not legal certification |
| Nationality | Recruitment candidates / career portal — not ops employee |

**Do not claim legal compliance.** Split later: Core HR + Saudi configuration + Government integrations.

---

## 13. Tests (existing)

`rateb-erp/tests/hr/`: Phase 23A HRMS; ESS phases C–J (hardening, attendance, leave, payslip/docs, profile, permission requests, push, device registry, offline).  
`tests/payroll/PayrollPhase24ATest.php`, `tests/recruitment/RecruitmentPhase15ATest.php`.  
**Gaps:** no tenant-isolation IDOR suite dedicated to employee/payroll; no payroll GL regression (because no GL); no contract/decision tests.

---

## 14. Integrations / offline / frontend lock

- Official ERP UI: Admin `views/company/hr|hrm|payroll|recruitment` under `/admin/*`.  
- `public/v2` HR module is Offline V2 BM only — **must not** become a second ERP SPA (Architecture Lock).  
- Offline V1 Phase 4/23B/24B: wrap online; flags off; leave approve / payroll post rejected offline.  
- Identity: Online ERP is Authentication Authority; ESS uses user session/token then `user_id` on employee. Identity module must not store passwords (boundary already documented elsewhere).

---

## 15. Prior audits (not treated as source of truth)

| Doc | Use |
|-----|-----|
| `rateb-erp/offline-v2/docs/P16-00-HR-AUDIT.md` | Closest prior ERP HR map; **outdated on ESS REST** (it claimed no HR API — `/api/v1/hr/*` now exists) |
| `rateb-erp/docs/PHASE_23A_HR_ONLINE.md` | Confirms additive `rateb_hrm_*`, no replace of `/hr/*` |
| `rateb-erp/docs/PHASE_24A_PAYROLL_ONLINE.md` | Confirms additive payroll, no auto GL |
| Root `HR_*AUDIT*.md` | Mix of Pro `pages/hr.php` issues and older notes — **do not apply blindly to ERP** |

---

## 16. Technical debt / blockers (non-code)

- God-files: `HrService`, `HumanResourcesDomainServices`, `PayrollDomainServices`.  
- Additive platforms were explicitly designed **not** to replace live HR — unification was deferred; that deferral is now the main Enterprise blocker.  
- Prompt menu implementation without unification = **Architecture Conflict (parallel HR)**.  
- Feature flags: enterprise layers are always-on routes if module enabled; no flag hiding `/hrm` from accidental use.  
- Six overlapping approval mechanisms (company route block, platform oversight, dormant `WorkflowService` HR entity types, EAP `ApprovalService` unwired, HRMS lifecycle workflow, enterprise `PayrollWorkflowService`) — only oversight + HRMS/payroll-batch workflows are live for HR.  
- Ops HR tables: **zero FOREIGN KEYs** in 067/074 (indexes only); no vacancy table; GOSI/WPS/Iqama/Saudization absent.  
- Schema-only HRMS models without domain services: disciplinary, rewards, dependents, emergency contacts, licenses, skills, languages, tags.  
- Admin extras (not HR module UI): `/admin/hr-mobile` console; SW caches `admin/hr` / recruitment / payroll but **not** `admin/hrm`.

---

## 17. Independent corroboration (2026-08-14)

Deep-dive agents confirmed the dual-stack map and added the ESS tenant-bind, payroll-post asymmetry, unused `rateb_payroll_audit`, leave-apply notification gap, and missing `user_id` index — incorporated above. Root `HR_*AUDIT*.md` remains Ratib Pro-oriented and is not ERP SoT.

---

## 18. Phase boundary

**HR-0 Audit: COMPLETE.**  
Next: HR-1 Gap Analysis, HR-2 Architecture, HR-3 Roadmap (same docs set).  
**Do not implement business logic, migrations, or production behavior until audit + architecture are approved.**
