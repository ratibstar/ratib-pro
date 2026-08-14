# RATIB ERP — HR-2 Target Architecture

**Status:** COMPLETE (design only — no code)  
**Depends on:** HR-0 Audit, HR-1 Gap Analysis  
**Constraints:** PHP Core + MySQL; Admin `/admin/*` only; no Laravel; no second ERP SPA; no NotificationService #2; no auto-drop of additive tables.

---

## 1. Architecture principles

1. **One production ERP frontend:** Admin shell (`rateb_app_route` → `/admin/*`).  
2. **One live Employee Master for money & time:** `rateb_employees`.  
3. **Domain separation without folder renaming:** keep `Hr*`, `HumanResources*`, `Payroll*` files; introduce clear ports/facades in docs and later thin wrappers.  
4. **No Parallel Architecture:** do not create `HR2`, `Employee2`, `Payroll3`. Unify 23A/24A by **bridges**, not by new tables that redefine the same entity.  
5. **Attendance owns time facts; Payroll consumes them.**  
6. **Online ERP remains Authentication Authority.** ESS resolves `user_id` → employee; never stores passwords in HR.  
7. **AccountingService is the only GL poster** for payroll/expenses when posting is implemented.  
8. **`public/v2` HR BM stays offline infrastructure** — never a production ERP UI (Architecture Lock).  
9. **RATEB Pro `pages/hr.php` is out of scope** unless a separate ADR merges products.  
10. **Destructive schema changes require explicit approval.** Prefer additive columns / link tables.

---

## 2. Target logical domains (not mandatory new folders)

```
HR
├── Core
│   ├── EmployeeMaster          (rateb_employees + link to HRMS profile)
│   ├── Organization            (ops depts → HRMS org/positions)
│   ├── Job / Position
│   └── Lifecycle / Audit
├── Recruitment                 (sibling module + HireBridge)
├── Contracts                   (new additive employment contracts)
├── Attendance                  (extend rateb_attendance_records)
├── Leave                       (extend HrService)
├── Payroll                     (ops interim → optional enterprise)
├── Compensation                (allowances, deductions, loans, advances)
├── Performance                 (existing HRMS)
├── Succession                  (future)
├── Requests                    (extend + Approval Matrix)
├── Decisions                   (facade over promotions/transfers/termination…)
├── Disciplinary                (existing schema + service)
├── Documents                   (meta + existing storage)
├── Expenses                    (ADR: HR vs Accounting owner)
├── Reporting
├── Notifications               (NotificationService triggers)
└── Settings
```

Physical layout today (`app/controllers/Company`, `app/services`, `views/company/hr|hrm|payroll`) **remains**. New code should land next to existing owners, not under `public/v2`.

---

## 3. Canonical ownership

| Concern | Canonical write path | Consumers | Forbidden |
|---------|----------------------|-----------|-----------|
| Employee identity for pay/time | `rateb_employees` via `HrEmployeesController` / future `EmployeeMasterService` | Attendance, Leave, Ops Payroll, ESS | Creating employees only in HRMS without ops row |
| Talent / org rich profile | `rateb_hrm_employee_profiles` with **required** `legacy_employee_id` when active | Performance, promotions, org chart | Becoming second payroll employee key |
| Attendance facts | `rateb_attendance_records` + future punch/exception tables linked to same employee_id | Leave apply, Payroll input DTO, ESS | Payroll inventing absences without attendance rows |
| Leave | `rateb_leave_*` via `HrService` | ESS, oversight, attendance leave days | Duplicate leave tables in HRMS/payroll |
| Ops payroll (interim SoT) | `rateb_payroll_periods/lines` via `HrService` | Payslips ESS, oversight | Calling it “posted to GL” without AccountingService |
| Enterprise payroll (candidate) | `rateb_payroll_batches/items` | Future WPS/advanced calc | Becoming SoT before attendance feed + GL adapter |
| Recruitment | `rateb_recruitment_*` | HireBridge | Owning employee master |
| Approvals | `ApprovalOversightService` + future matrix config | Leave, requests, payroll, decisions | Per-module approve that bypasses oversight |
| Notifications | `NotificationService` | All HR events | New notifier service |
| GL | `AccountingService` | Payroll post, expenses | HR writing journal tables directly |

---

## 4. Service map (target reuse)

```
Controllers (thin)
  → Domain facades
       EmployeeMasterFacade
       AttendanceEngine (wraps HrService + future calc)
       LeaveEngine (HrService)
       PayrollRunFacade (ops HrService now; switch behind interface later)
       ContractService (new)
       DecisionService (new facade)
       HireBridge (new; calls EmployeeMaster + Recruitment)
       RequestWorkflow (extends requests + oversight)
  → Existing specialists
       HumanResourcesWorkflowService
       PayrollWorkflowService
       RecruitmentWorkflowService
       NotificationService
       AccountingService
       AuditService
  → Models (tenantScoped)
```

ESS stays:

```
HrEss*Controller → HrEss*Service → HrService / NotificationService / Resolver
```

Flutter must keep using ports/adapters; **no business rules in the app**.

---

## 5. Workflows

### 5.1 Employee lifecycle (target, extend HRMS + mirror ops)

```
Candidate (recruitment)
 → Offer → Pre-Hire
 → Active (ops status=active; HRMS workflow active)
 → Probation → Confirmed
 → Suspended / Transferred / On Leave
 → Terminated → Final Settlement → Archived
```

Every transition records: actor, timestamp, previous, new, reason, source, reference.  
Implementation preference: extend `HumanResourcesWorkflowService` + write ops `status` in the same transaction when linked.

### 5.2 Attendance pipeline (target)

```
Raw punch / ESS check-in
 → Daily calculation (late/early/absent/OT)
 → rateb_attendance_records (+ exception rows)
 → Approval for corrections
 → PayrollAttendanceInput DTO
 → Payroll calculation
```

### 5.3 Leave pipeline (extend)

```
Draft/Submitted → Manager/HR (matrix) → Approved
 → Attendance leave days (existing applyApprovedLeave)
 → Balance history
 → Payroll (paid/unpaid flag from leave type)
```

### 5.4 Payroll pipeline (target)

```
EmployeeMaster + Contract + AttendanceInput + Leaves
 + SalaryTransactions + Allowances + Deductions + Advances
 → Payroll Calculation
 → Review → Approval (oversight)
 → AccountingService post
 → Payment transfer / WPS (Saudi integration layer)
 → Payslip
```

**Interim:** keep `generatePayrollLines` but feed it from an AttendanceInput helper (same absences source, one place).  
**Do not** rewrite payroll in the first implementation phase.

### 5.5 Requests / Decisions

Configurable Approval Matrix (stored config, not hardcoded stages):

```
Draft → Submitted → [optional Manager] → [optional HR] → [optional Finance] → Completed
```

Decisions are typed envelopes:

```
type, employee_id, effective_date, reason, old_values, new_values,
attachments, requester, approver, status, audit
```

Types map to existing tables where possible (promotion → `rateb_hrm_promotions`, etc.).

---

## 6. APIs

| Surface | Pattern |
|---------|---------|
| Admin HTML | Keep CSRF form posts; thin controllers |
| ESS REST | Keep `/api/v1/hr/*`; add endpoints only via HrEss* → domain services |
| Internal events (optional) | Prefer existing timeline/audit; introduce EventBus only if platform already uses it consistently |

No new public “HR2 API” namespace.

---

## 7. Database boundaries

| Boundary | Rule |
|----------|------|
| Tenant | Every HR row: `company_id`; queries always scoped |
| Branch | Prefer non-null `branch_id` for active employees |
| Soft links | `legacy_employee_id`, `hrm_employee_profile_id`, `attendance_ref`, `leave_ref` |
| Additive only until ADR | New contract/decision/matrix tables OK; DROP forbidden |
| Indexes | `(company_id, employee_id, date)`, payroll period uniqueness already present |
| FK | Prefer additive FKs on new tables; backfill carefully on old |

**Proposed future additive tables (names illustrative — require ADR before create):**

- `rateb_hr_employment_contracts`  
- `rateb_hr_salary_transactions` (unified ledger)  
- `rateb_hr_approval_matrix` / steps  
- `rateb_hr_decisions` (or decision header + links)  
- `rateb_hr_attendance_punches` / `rateb_hr_attendance_exceptions`  
- Saudi config tables under `rateb_hr_sa_*` (separate from core)

---

## 8. Security architecture

1. **RBAC:** split `hr.payroll.view` / `hr.salary.view` from directory `hr.view` (new slugs via migration seed — later).  
2. **IDOR:** every show/update: `id + company_id` (and branch scope where required).  
3. **Employee↔user bind:** company-scoped only; remove global email fallback or gate behind super-admin + audit.  
4. **CSRF:** keep on all Admin POSTs.  
5. **ESS:** never trust client `employee_id`; resolver only.  
6. **Mass assignment:** keep `$fillable` allowlists.  
7. **Sensitive fields:** bank/salary in separate tables or encrypted columns later — not in list exports by default.  
8. **Audit integrity:** append-only history for salary/contract/bank/termination.

---

## 9. Multi-tenant & isolation

```
company_id  → hard boundary
branch_id   → operational scope
department  → soft scope (reporting)
RBAC        → action boundary
```

URL/API ID changes must not cross company. ESS token company must match employee company after bind.

---

## 10. Integrations

| System | Integration style |
|--------|-------------------|
| Recruitment | HireBridge on `deployed` / accepted offer |
| Accounting | `AccountingService` adapter for payroll/expense post |
| Notifications | Triggers from leave/payroll/contract/document jobs |
| Documents module | Reuse storage; HR holds metadata + ACL |
| Logistics drivers | Already links `rateb_employees` — keep |
| POS / Inventory | No ownership |
| Offline V1/V2 | Consume published domain services; no second SoT |

---

## 11. UI architecture

- **Single HR nav** (`config/hr-menu.php`) becomes the Enterprise menu **after** capabilities exist.  
- Fold `/hrm/*` screens into HR nav groups (Performance, Org) without renaming routes initially (redirects OK).  
- `/payroll/*` remains module nav until payroll SoT decision.  
- Prompt paths `/erp/human_resources/...` should be implemented as **aliases or menu labels** to `/admin/hr/...`, not a new tree.  
- Pending Actions → single **Approval Inbox** view over oversight sources.

---

## 12. Performance architecture

- Paginate all employee/attendance/payroll lists.  
- Payroll generate: batch-load absences, structures, loans (replace N+1).  
- Dashboard: pre-aggregated counters / short TTL cache per company.  
- Reports: shared query objects; stream exports.  
- Avoid loading `all(5000)` without filters.

---

## 13. Saudi configuration layer (future)

```
Core HR  →  Saudi Configuration (rules, identifiers)  →  Government Integrations (GOSI, WPS)
```

Core must run without government connectors. No “compliant” badge without verified integrations.

---

## 14. Testing architecture

| Layer | Required |
|-------|----------|
| Security | tenant isolation, IDOR, CSRF, RBAC matrix for salary |
| Domain | lifecycle, leave approve→attendance, payroll input DTO, contract states |
| Accounting | payroll post creates expected journals **when enabled**; no post when feature flag off |
| Regression | existing ESS + Phase 23A/24A tests stay green |
| Feature flags | risky GL / new contract enforcement off by default |

---

## 15. Deployment safety

- Additive migrations only by default.  
- Feature flags for: GL posting, contract-required-for-payroll, hire-bridge auto-create.  
- Reversible data backfills.  
- No production behavior change without tests.  
- Fast deploy paths already cover `rateb-erp` under `public/` / app — follow deploy rules when implementation starts.

---

## 16. Architecture conflicts to raise (STOP)

Raise **Architecture Conflict** and stop if asked to:

1. Build a new Employee table as SoT while keeping `rateb_employees` live.  
2. Add ERP HR screens under `public/v2`.  
3. Create NotificationService2 / PayrollAccountingService bypassing AccountingService.  
4. Merge RATEB Pro `api/hr/*` into ERP silently.  
5. Drop `rateb_hrm_*` or `rateb_payroll_*` without ADR + migration plan.  
6. Make Payroll recompute attendance in a second divergent formula permanently.

---

## Phase boundary

**HR-2 Target Architecture: COMPLETE.**  
Next: `docs/hr/HR-ENTERPRISE-ROADMAP.md`.  
**No implementation without approval.**
