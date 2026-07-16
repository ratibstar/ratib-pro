# P16-00 — Phase 16 Human Resources (HR) Audit (Enterprise Report)

**Status:** COMPLETE (evidence only)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Implementation:** NONE — STOP after audit  
**Scope:** ONLINE ERP HR (`rateb-erp/` operational `/hr/*` + Phase 23A `/hrm/*` + Phase 24A Payroll adjacency + Recruitment adjacency) — **NOT** Offline V1 as SoT, **NOT** Offline V2 BM (no HR BM yet)

---

## Executive verdict

Online HR is a **dual stack**:

1. **Operational HR** (`/hr/*`) — `rateb_employees`, attendance, leave, holidays, loans, fleet, documents, legacy payroll via `HrService`.  
2. **Enterprise HRMS** Phase 23A (`/hrm/*`) — additive `rateb_hrm_*` profiles, org, training, performance, promotions/transfers; soft-links only to legacy.  
3. **Enterprise Payroll** Phase 24A (`/payroll/*`) — separate module; soft-links to HRMS/legacy; **no auto GL**.  
4. **Recruitment** Phase 15A (`/recruitment/*`) — sibling; candidate contracts ≠ HR employment contracts.

Surface is **MVC + session RBAC**, not a JSON HR API. Offline V2 has **no HR BusinessModule**. Offline V1 (Phase 4 + 23B) wraps online services (flags OFF) — frozen reference only.

Offline V2 HR BM must:

- Own **HR documents only** (`hr.*` entities).
- Depend on **identity** (mandatory); optional **accounting** / **crm** via published APIs only.
- **Never** own inventory, sales, procurement, CRM data, authentication, or GL.
- Treat Recruitment and Payroll as optional peers (events), not owned state.

---

## 1. Employees

| Item | Evidence |
|------|----------|
| Ops table | `rateb_employees` — `migrations/067_hr_tables.sql`, catchup `080`, branch `121`, job titles `136` |
| Ops model / controller | `Employee` / `HrEmployeesController` — `app/models/HrModels.php`, `app/controllers/Company/HrControllers.php` |
| Ops routes | `GET/POST …/hr/employees*` — `routes/modules/ops.php` |
| Ops fields | `employee_code`, names, email/phone, national_id, department_id, job_title(_id), hire_date, **salary_base**, user_id, branch_id, status `active\|inactive\|terminated` |
| HRMS table | `rateb_hrm_employee_profiles` — `migrations/189_hr_platform_enterprise.sql` |
| HRMS service | `EmployeeProfileService` — `list`, `get`, `create`, `update`, `softDelete` — `HumanResourcesDomainServices.php` |
| HRMS routes | `…/hrm/employees*`, `POST …/transition` |
| Link | `legacy_employee_id` soft-link; **no sync job** |

---

## 2. Departments

| Stack | Table | Routes |
|-------|-------|--------|
| Ops | `rateb_hr_departments` | `…/hr/departments*` |
| HRMS | `rateb_hrm_departments` (parent_id, manager_profile_id) | `GET/POST …/hrm/departments` |

Menu: `config/hr-menu.php`.

---

## 3. Positions

| Stack | Evidence |
|-------|----------|
| Ops | Job titles `rateb_hr_job_titles` — `…/hr/job-titles*` |
| HRMS | `rateb_hrm_positions` + `rateb_hrm_grades` — `…/hrm/positions` |

`legacy_job_title_id` on HRMS positions.

---

## 4. Organization Structure

| Item | Evidence |
|------|----------|
| Tables | `rateb_hrm_org_units`, `rateb_hrm_locations` |
| Service | `OrganizationService` — `HumanResourcesDomainServices.php` |
| Routes | `GET …/hrm/organization`, `POST …/units`, `POST …/locations` |
| Ops workplaces | `rateb_hr_workplaces` — attendance geo, not org tree |

---

## 5. Attendance

| Item | Evidence |
|------|----------|
| Table | `rateb_attendance_records` (unique company+employee+date) |
| Statuses | `present\|absent\|late\|leave\|holiday` |
| Controllers | `HrAttendanceController`, `HrAttendanceBulkController` |
| Routes | `…/hr/attendance*`, `…/hr/attendance/bulk` |
| Related | `rateb_hr_permission_requests`; holidays → `HrService::syncHolidayAttendance` |
| **HRMS** | Does **not** own attendance |

---

## 6. Leave Management

| Item | Evidence |
|------|----------|
| Tables | `rateb_leave_types`, `rateb_leave_requests`, `rateb_leave_balances` |
| Service | `HrService::ensureDefaultLeaveTypes`, `approveLeave`, `rejectLeave`, `leaveBalances*`, `leaveReport` |
| Routes | `…/hr/leaves*`, balances, leave-types |
| Oversight | `ApprovalOversightService` source `hr_leave` |
| **HRMS** | No leave tables |

---

## 7. Overtime

| Item | Evidence |
|------|----------|
| Ops HR | **No** dedicated overtime table under `/hr/*` |
| Enterprise Payroll | `rateb_payroll_overtime` — `OvertimeService` — `…/payroll/overtime` |
| Fields | profile/legacy employee refs, hours, rate_multiplier, amount, `attendance_ref`, status |

---

## 8. Payroll integration

### Legacy (under HR module)

| Item | Evidence |
|------|----------|
| Tables | `rateb_payroll_periods`, `rateb_payroll_lines`; components/structures; loans |
| Service | `HrService::generatePayrollLines`, `approvePayroll`, `postPayroll` (**status flip only — no journal/GL**) |
| Routes | `…/hr/payroll*` |

### Enterprise (separate module)

| Item | Evidence |
|------|----------|
| Migration / doc | `190_payroll_platform_enterprise.sql`, `docs/PHASE_24A_PAYROLL_ONLINE.md` |
| Soft links | `hrm_employee_profile_id`, `legacy_employee_id`, `attendance_ref`, `leave_ref`; `accounting_post_ref` metadata only |
| Routes | `…/payroll/*` |
| Perms | `payroll.view\|create\|calculate\|review\|approve\|post\|admin\|manage` |

**Boundary:** Payroll may reference HR employee IDs; HR BM must not post GL.

---

## 9. Contracts

| Kind | Status |
|------|--------|
| HR employment contracts | **Missing** as first-class HR/HRMS entity |
| Recruitment contracts | `rateb_recruitment_contracts` — sibling module |
| Supplier / eproc contracts | **Not HR** |

---

## 10. Documents

| Stack | Evidence |
|-------|----------|
| Ops | `rateb_hr_documents` — `…/hr/documents*` |
| HRMS | `rateb_hrm_employee_documents_meta` — meta only (`storage_key`); no binary SoT for offline |

---

## 11. Recruitment

**Sibling module — not owned by HR.**

| Item | Path |
|------|------|
| Migration / doc | `181_recruitment_platform.sql`, `docs/PHASE_15A_RECRUITMENT_ONLINE.md` |
| Workflow | `RecruitmentWorkflowService` — draft→…→deployed→archived |
| Routes / perms | `…/recruitment/*`, `recruitment.*` |

**Gap:** no automated `deployed` → employee / HRMS profile create.

---

## 12. Onboarding

**Not implemented** as a first-class online entity/workflow under `hr`/`hrm`. Proxies: employee `draft→registered→active`, recruitment `ready→deployed`, training enroll.

---

## 13. Performance

| Item | Evidence |
|------|----------|
| Tables | `rateb_hrm_performance_reviews`, `rateb_hrm_goals`, `rateb_hrm_competencies` |
| Workflow | draft→submitted→approved→closed→archived |
| Routes | `…/hrm/performance*`, goals, competencies |

Schema-only (limited UI): `rateb_hrm_disciplinary_actions`, `rateb_hrm_rewards`.

---

## 14. Training

| Item | Evidence |
|------|----------|
| Tables | `rateb_hrm_training`, `rateb_hrm_training_history` |
| Service | `TrainingService` — CRUD + `enroll` |
| Workflow | planned→scheduled→in_progress→completed\|cancelled→archived |
| Routes | `…/hrm/training*` |

---

## 15. Assets assignment

**Not HR-owned.**

| System | Assignee model |
|--------|----------------|
| EAM `rateb_eam_asset_assignments` | `assignee_user_id` (identity user) |
| Legacy `rateb_asset_assignments` | `assigned_to` user |
| HR fleet `rateb_hr_fleet` | `assigned_employee_id` (ops employee) — vehicles only |

HR BM may emit employee↔user link; must **not** write asset tables.

---

## 16. Permissions

### HR / HRMS (`hr` module)

Seeded `066` + expanded `189`; labels in `config/permission-labels-*.php`:

| Slug | Intent |
|------|--------|
| `hr.view` | view |
| `hr.create` / `hr.update` / `hr.delete` | CRUD / soft-delete |
| `hr.training` / `hr.performance` / `hr.promotions` / `hr.transfers` | domain gates |
| `hr.admin` / `hr.manage` | admin bundles |
| `hr.oversight` | leave / request approvals |

Route gate: `rateb_erp_mw('hr', '<perm>', '<entity>')`.

### Payroll / Recruitment

Separate `payroll.*` and `recruitment.*` modules.

---

## 17. APIs

**No dedicated online REST HR/HRMS/Payroll API** under `api/`.

Surface = HTML form POST on `hr/…`, `hrm/…`, `payroll/…`, `recruitment/…`.

Offline V1 delta/replay under `offline/**` — not Online API SoT.

---

## 18. Database

| Migration | Role |
|-----------|------|
| `066_hr_module.sql` | `hr.view` / `hr.manage` seed |
| `067_hr_tables.sql` | core ops tables |
| `068_hr_leave_balances.sql` | balances |
| `074_hr_extended.sql` | holidays, workplaces, loans, components, fleet, documents |
| `080_hr_production_catchup.sql` | idempotent catchup |
| `121_user_employee_branches.sql` | employee branch |
| `136`–`139` | job titles + leave catalogs |
| `181_recruitment_platform.sql` | recruitment sibling |
| `189_hr_platform_enterprise.sql` | **30** `rateb_hrm_*` + granular `hr.*` |
| `190_payroll_platform_enterprise.sql` | enterprise payroll |

### `rateb_hrm_*` (189)

departments, positions, grades, locations, org_units, employee_profiles, employee_documents_meta, employee_contacts, dependents, emergency_contacts, certifications, licenses, skills, languages, training, training_history, performance_reviews, goals, competencies, disciplinary_actions, rewards, transfers, promotions, assignments, notes, comments, timeline, tags, entity_tags, status_history

---

## 19. Services

| File | Role |
|------|------|
| `app/services/HrService.php` | Ops dashboard, leave, payroll generate/approve/post, reports |
| `HumanResourcesDomainServices.php` | HRMS domain services (profiles, org, training, performance, …) |
| `HumanResourcesWorkflowService.php` | Sole `workflow_status` writer |
| `HumanResourcesSupport.php` | Tenant/UUID/version/actor |
| `EmployeeTimelineService.php` | Append-only `rateb_hrm_timeline` |
| `PayrollDomainServices.php` + `PayrollWorkflowService.php` | Enterprise payroll peer |
| Controllers | `HrControllers.php`, `HrExtendedControllers.php`, `HumanResourcesControllers.php` |
| Tests / docs | `tests/hr/HrPhase23ATest.php`, `docs/PHASE_23A_HR_ONLINE.md`, `PHASE_24A_PAYROLL_ONLINE.md` |

---

## 20. Workflow

### Employee / Training / Performance (`HumanResourcesWorkflowService`)

```
Employee: draft → registered → active ⇄ on_leave|suspended → terminated → archived
Training: planned → scheduled → in_progress → completed|cancelled → archived
Performance: draft → submitted → approved → closed → archived
```

Side effects: optimistic `version`; `rateb_hrm_status_history`; timeline; terminate sets `termination_date`.

### Legacy leave / payroll

- Leave: `pending → approved|rejected|cancelled`
- Payroll period: `draft → approved → posted` (status only)

### Payroll batch (`PayrollWorkflowService`)

`draft → prepared → calculated → reviewed → approved → posted → closed → archived`

### Recruitment

`draft → … → deployed → archived` (sibling).

---

## 21. Reports

| Report | Path |
|--------|------|
| Ops monthly | `…/hr/reports` — `HrService::monthlyReport` |
| Leave report | `…/hr/reports/leaves` |
| HRMS reports | `…/hrm/reports` — board counts + timeline (not analytic) |
| Payroll reports | `…/payroll/reports` |

---

## 22. Notifications

**None dedicated** for HR/HRMS/Payroll leave/payroll notifiers. Approvals via oversight queues; audit logs in places.

---

## 23. Integration boundaries

| System | Behavior |
|--------|----------|
| **Identity** | `TenantContext`; `rateb_employees.user_id`; HRMS assignees; RBAC `hr.*` |
| **Accounting** | Legacy `postPayroll` = status only; enterprise `accounting_post_ref` — **no auto GL** |
| **CRM** | **None** |
| **Inventory / Sales / Procurement** | **None** (ownership) |
| **Assets / EAM** | Assign by **user_id**, not employee profile |
| **Recruitment** | Parallel; no hire→employee bridge |
| **Approval** | Leave, employee requests, legacy payroll |

### Offline V2 published APIs (deps)

| Module | Use |
|--------|-----|
| Identity | **Mandatory** — session, claims, rbac |
| Accounting | Optional — payroll post ref / events only; HR never posts GL |
| CRM | Optional — none required for core HR |
| Inventory / Sales / Procurement | **Forbidden ownership** |

---

## 24. Sync boundaries

### Offline V1 (flags default OFF)

| Phase | Doc | Scope |
|-------|-----|-------|
| 4 | `offline/docs/PHASE_4_HR_OFFLINE_REPORT.md` | attendance, leave draft, employee directory (no salary) |
| 23B | `offline/docs/PHASE_23B_HUMAN_RESOURCES_OFFLINE.md` | HRMS drafts + workflow.transition; meta docs |
| 24B | `offline/docs/PHASE_24B_PAYROLL_OFFLINE.md` | payroll drafts; no GL as SoT |

**Rejected offline:** leave approve, payroll post/GL, binary upload, notifications.

### Offline V2 implication

| May sync | Must NOT sync |
|----------|---------------|
| Employee / dept / position / org drafts | Passwords / tokens |
| Workflow transitions + status history | Binary document bytes |
| Training / performance / promotion / transfer drafts | Leave approvals, payroll post, GL |
| Leave **drafts** only | Salary as SoT without policy |
| Attendance events | Computed report boards as SoT |
| Document meta | Inventory / CRM / sales / procurement state |

---

## Required BusinessModule surface (future — not this phase)

### Suggested entity prefix

`hr.*` — employee, department, position, grade, org_unit, location, attendance, leave_type, leave_request, leave_balance, training, performance_review, goal, competency, promotion, transfer, document_meta, timeline, status_history, assignment

### Suggested APIs (`module.hr.*`)

`upsertEmployee` · `transitionEmployee` · `upsertDepartment` · `upsertPosition` · `upsertOrgUnit` · `recordAttendance` · `createLeaveRequest` · `listLeaveBalances` · `createTraining` / `transitionTraining` · `createPerformanceReview` / `transitionPerformance` · `createPromotion` · `createTransfer` · `createDocumentMeta` · `listTimeline` · `linkIdentityUser` · `getDiagnostics` / `runSelfTest`

### Suggested DTOs

`EmployeeDraftDTO` · `WorkflowTransitionDTO` · `AttendanceDTO` · `LeaveRequestDTO` · `TrainingDTO` · `PerformanceReviewDTO` · `DocumentMetaDTO` · `IdentityLinkDTO` · `PayrollEmployeeRefDTO` (link only)

### Suggested events

`hr:ready` · `hr:employee_created` · `hr:employee_transitioned` · `hr:leave_requested` · `hr:attendance_recorded` · `hr:training_completed` · `hr:hire_ready` (optional from recruitment)

### Suggested permissions

`hr.view` · `hr.create` · `hr.update` · `hr.delete` · `hr.training` · `hr.performance` · `hr.promotions` · `hr.transfers` · `hr.oversight` · `hr.manage`

---

## Reusable components

- Dual vocabulary awareness: ops `rateb_employees` vs HRMS profiles + soft links  
- Org: department / position / grade / org_unit / location  
- Attendance + leave types/requests/balances  
- Sole workflow authorities (`HumanResourcesWorkflowService`, peers)  
- Soft-delete + `public_uuid` + company/branch + optimistic `version`  
- Timeline + status_history append models  
- Permission matrix `hr.*`  
- Meta-only documents for offline-safe sync  
- Explicit no-auto-GL payroll posting pattern  

---

## Non-reusable components

- PHP Model + Session/`TenantContext` / `rateb_erp_mw` / Bootstrap  
- Controllers / views / CSRF form posts  
- Direct SQL in services; god-files (`HrService`, `HumanResourcesDomainServices`)  
- Offline V1 queue/adapters/flags/SDK  
- Website portal recruitment UI  
- EAM/legacy asset assignment controllers  
- Hardcoded report boards as sync SoT  

---

## Risks

| ID | Severity | Risk |
|----|----------|------|
| R1 | Critical | Dual employee SoT (ops vs HRMS) without sync |
| R2 | Critical | Dual payroll SoT (legacy `/hr/payroll` vs `/payroll/*`) |
| R3 | High | No employment-contract entity in HR |
| R4 | High | No hire bridge Recruitment → Employee/HRMS |
| R5 | High | `salary_base` on ops employee vs enterprise salary tables — sensitive / offline policy |
| R6 | Medium | Schema-only HRMS tables without full services/UI |
| R7 | Medium | Assets assign by user; fleet by employee — inconsistent |
| R8 | Medium | No notifications for leave/payroll |
| R9 | Medium | Legacy `postPayroll` name implies GL but only flips status |
| R10 | Medium | No onboarding module |
| R11 | Info | Offline V1 wraps online — V2 must not inherit V1 queue |
| R12 | Info | Offline V2 has **no** HR BM yet |

---

## Missing abstractions

1. **HrEmployeePort** — unified employee write with optional ops↔HRMS link  
2. **HrWorkflowPort** — sole transition for employee/training/performance  
3. **AttendancePort** / **LeavePort** — drafts + online-only approve  
4. **OrgDirectoryPort** — dept/position/grade/org/location  
5. **PayrollLinkPort** — ref to payroll without owning batches/GL  
6. **IdentityLinkPort** — bind `user_id`  
7. **DocumentMetaPort** — meta only  
8. **TimelinePort** — append + query  
9. **HireFromRecruitmentPort** (optional event consumer)  
10. Clear rule: HR never writes inventory/sales/procurement/CRM/GL  

---

## Recommended HR BusinessModule implementation plan

1. **Charter** HR BM — docs only; mandatory `identity >= 1.0.0`; optional `accounting` / `crm` via published APIs only.  
2. Local `hr.*` storage — never SQL into other modules.  
3. Prefer **HRMS profile** as V2 employee document; ops employee as legacy bridge DTO.  
4. Implement **HrWorkflowPort** + **TimelinePort** + org directory first.  
5. Attendance + leave drafts next; approvals online-only or identity-gated.  
6. Training / performance / promotion / transfer.  
7. Auth/RBAC via `module.identity.*` only.  
8. Optional: subscribe to recruitment `deployed`; publish events for payroll/accounting.  
9. Sync: HR business events only; no binary; no GL; no CRM ownership.  
10. Self-tests + host wiring + evidence.  
11. **STOP** before Payroll BM unless chartered separately.

---

## Architecture conflict check

No Platform or existing BusinessModule modification is required for this **audit**.  
If future implementation requires changing Platform / Identity / Inventory / Procurement / Sales / Accounting / CRM — **STOP** and raise Architecture Conflict.

---

## Phase boundary

**Phase 16 HR Audit: COMPLETE**  
**Do NOT implement HR BusinessModule in this phase.**  
**STOP.**
