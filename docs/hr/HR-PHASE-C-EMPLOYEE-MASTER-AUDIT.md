# RATIB ERP — HR Phase C0 Employee Master Audit

**Status:** COMPLETE (evidence only — no data mutation in C0)  
**Date:** 2026-08-14  
**Base:** Phase B commit `1062441c`  
**Rule:** Do not invent Employee2 / merge HRMS automatically.

---

## 1. Domain → Employee source map

| Domain | Current Employee Source | Employee ID | Company Scope | Read/Write | Canonical? |
|--------|-------------------------|-------------|---------------|------------|------------|
| **Ops HR / Admin** | `rateb_employees` via `Employee` model + `HrEmployeesController` | `id` (PK) | `company_id` + tenantScoped | R/W | **YES — live master** |
| **ESS / Mobile** | `rateb_employees` via `HrEssEmployeeResolverService` (`user_id` / email+company) | `rateb_employees.id` | Token `company_id` (Phase B) | R (+ bind user_id) | **YES** |
| **Attendance** | `rateb_attendance_records.employee_id` → `rateb_employees.id` | FK-like int (no DB FK) | `company_id` on row | R/W | Uses ops master |
| **Leave** | `rateb_leave_requests.employee_id` → `rateb_employees.id` | same | `company_id` | R/W | Uses ops master |
| **Ops Payroll** | `rateb_payroll_lines.employee_id` + `HrService::generatePayrollLines` reads `salary_base` | same | `company_id` | R/W lines | Uses ops master |
| **Ops salary components** | `rateb_hr_payroll_structures.employee_id` | same | `company_id` | R/W | Uses ops master |
| **Loans / requests / docs / fleet / permissions** | `employee_id` → `rateb_employees` | same | `company_id` | R/W | Uses ops master |
| **HRMS (Phase 23A)** | `rateb_hrm_employee_profiles` | profile `id`; optional `legacy_employee_id` | `company_id` | R/W overlay | **NO — additive talent** |
| **Enterprise Payroll (24A)** | Soft-links `legacy_employee_id` and/or `hrm_employee_profile_id` on `rateb_payroll_*` | soft ints | `company_id` | R/W overlay | **NO — not ops SoT** |
| **Recruitment** | Candidates (no employee until hire bridge — missing) | candidate `id` | `company_id` | R/W sibling | Separate |
| **Employment Contracts** | **Missing** as first-class HR entity | — | — | — | N/A |
| **Logistics drivers** | Links `rateb_employees` (migration 224) | ops id | company | R | Uses ops master |
| **RATEB Pro** | Root `api/hr/*` / `employees` table | **out of ERP** | control/pro | — | Out of scope |

---

## 2. Canonical Employee Identity (conclusion)

```text
Canonical live Employee Master = rateb_employees
Primary identity            = rateb_employees.id  (INT PK)
Business identifier         = rateb_employees.employee_code  (UNIQUE per company_id)
Tenant boundary             = rateb_employees.company_id
ESS link                    = rateb_employees.user_id  (optional)
```

**Do not create** `rateb_hr_employees` / Employee2.

HRMS `rateb_hrm_employee_profiles` is an **additive overlay** with optional `legacy_employee_id` soft-link. It is **not** the source for attendance, leave, ESS, or ops payroll generation.

---

## 3. Identity field roles

| Field | Role |
|-------|------|
| `id` | Primary identity (DB) |
| `employee_code` | Business number; unique `(company_id, employee_code)` |
| `company_id` | Tenant boundary (required) |
| `user_id` | Optional link to `rateb_users` for ESS |
| `national_id` | Optional government id (not unique constraint) |
| `email` / `phone` | Contact; **not** unique identity |
| `salary_base` | **Live ops basic salary** used by `generatePayrollLines` |
| `status` | `active\|inactive\|terminated` |
| `branch_id` | Branch scope (nullable historically) |

---

## 4. HRMS ↔ rateb_employees relationship

| Question | Answer |
|----------|--------|
| Authoritative for pay/time/ESS? | **`rateb_employees`** |
| Why HRMS exists? | Phase 23A additive talent/org/performance; soft-link only |
| Who reads HRMS? | `/admin/hrm/*` UI + HRMS services |
| Who writes HRMS? | `EmployeeProfileService` etc. |
| Mapping table? | **No** — column `legacy_employee_id` on profile |
| Sync job? | **None** |
| Unify now? | **No automatic merge** — link/report only in Phase C |

---

## 5. Canonical Salary Source (ops live path)

| Layer | Source | Used by live ops payroll? |
|-------|--------|---------------------------|
| **Basic** | `rateb_employees.salary_base` | **YES** (`HrService::generatePayrollLines`) |
| Allowances/deductions | `rateb_hr_payroll_structures` + components | **YES** |
| Loans | `rateb_hr_loans` | **YES** (installment deduct) |
| Absences | `rateb_attendance_records` | **YES** (÷30) |
| Enterprise salary | `rateb_payroll_employee_salary` (+ effective_from/to) | **NO** for ops generator |
| Contract salary | Employment contract entity | **Missing** |

**Silent change risk:** Admin employee edit updates `salary_base` via `CrudController::update`, which logs generic `AuditService::log('update', …, $data)` with **new payload only** — **old salary not captured as a dedicated salary-change event**.

Enterprise `EmployeeSalaryService::update` has timeline + company scope; ops path is the integrity gap for C4.

---

## 6. Integrity risks (detection targets — no auto-fix)

1. HRMS profiles with `legacy_employee_id` NULL (orphaned overlay).  
2. HRMS `legacy_employee_id` pointing to missing / other-company employee.  
3. Duplicate `employee_code` should be blocked by UK — verify.  
4. Duplicate `user_id` within company (ambiguous ESS).  
5. Same email → multiple employees in one company.  
6. Attendance/leave/payroll lines with `employee_id` missing or wrong `company_id`.  
7. `user_id` set across companies (should be prevented by Phase B bind).  
8. Enterprise payroll rows with `legacy_employee_id` orphan.

**FK status:** Ops child tables generally **lack FOREIGN KEY** to `rateb_employees` (indexes only). Do **not** add FKs until orphan report is clean.

---

## 7. Tenant / bind regression (Phase B)

Verified still in code:

- `HrEssEmployeeResolverService` requires company; bind includes `company_id`.  
- `autoLinkEmployeeUser` company-scoped only.  

C1 must **regression-test**, not rewrite.

---

## 8. Recommended Phase C fixes (safe)

| ID | Fix | Type |
|----|-----|------|
| C1 | Document + enforce canonical = `rateb_employees` in code comments/service diagnostics | Docs + tests |
| C2 | Read-only integrity diagnostics for HRMS link health | Diagnostic service |
| C3 | Read-only duplicate/orphan reports (no DELETE/merge) | Diagnostic |
| C4 | Dedicated salary_base change audit (old/new/actor/company) on ops employee update | Hardening |
| C4b | Enterprise salary update: ensure old/new in AuditService when basic_salary changes | Hardening |
| — | FK migrations | **Deferred** until orphan counts known |

---

## 9. Explicit non-goals

- No Employee2 table  
- No HRMS→ops auto-merge  
- No payroll/attendance/leave rewrite  
- No employment contract module  
- No payroll workflow change (`draft→approved→posted`)
