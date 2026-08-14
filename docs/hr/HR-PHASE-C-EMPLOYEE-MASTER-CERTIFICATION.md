# RATIB ERP — HR Phase C Employee Master Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase B `1062441c`  
**Audit:** `docs/hr/HR-PHASE-C-EMPLOYEE-MASTER-AUDIT.md`

---

## C0 Audit

| Item | Result |
|------|--------|
| Canonical employee source | **`rateb_employees`** (`Employee` model) |
| Primary identity | `rateb_employees.id` |
| Business id | `employee_code` (unique per `company_id`) |
| Tenant | `company_id` |
| ESS mapping | `user_id` (optional) |
| HRMS | `rateb_hrm_employee_profiles` + soft `legacy_employee_id` — **not** live SoT |
| Duplicate representations | Ops master + HRMS overlay + enterprise soft-links |
| Risks | Unlinked HRMS; silent `salary_base` edits (addressed C4); missing FKs (deferred) |

---

## C1 Identity

| Rule | Implementation |
|------|----------------|
| Canonical | `rateb_employees` — documented on model + `HrEmployeeIntegrityService::CANONICAL_TABLE` |
| No Employee2 | No new employee tables |
| Tenant isolation | Existing `tenantScoped` + Phase B ESS bind; HRMS/enterprise legacy link now asserts same company |
| User mapping | `user_id`; bind/autoLink company-scoped (Phase B regression verified) |

---

## C2 HRMS ↔ rateb_employees

| Question | Answer |
|----------|--------|
| Authoritative | **`rateb_employees`** for pay/time/ESS |
| HRMS role | Additive talent overlay |
| Mapping | Soft column `legacy_employee_id` (no mapping table, no sync job) |
| Fix | `HumanResourcesSupport::assertLegacyEmployee` on HRMS create/update and enterprise salary create/update |
| Auto-merge | **Not done** (forbidden) |

---

## C3 Integrity

| Capability | Detail |
|------------|--------|
| Service | `HrEmployeeIntegrityService::diagnoseCompany($companyId)` |
| Duplicates | `user_id`, `national_id`, email (informational) within company |
| Orphans | attendance / leave / payroll_lines missing employee; leave cross-company; HRMS unlinked / orphan legacy |
| Mutation | **None** — report only |
| FK migrations | **Deferred** until orphan counts known on production |

---

## C4 Salary Governance

| Item | Detail |
|------|--------|
| Canonical ops salary | **`rateb_employees.salary_base`** (+ structures/components/loans/absences) |
| Enterprise salary | `rateb_payroll_employee_salary` — additive, not ops generator SoT |
| Ops path | Admin employee CRUD → `afterSuccessfulUpdate/Store` → `salary_changed` / `salary_created` via existing `AuditService` + `PayrollAudit` |
| Payload | employee, company, old/new, effective_date, source, change_type |
| Authorization | Unchanged — still requires `hr-employees` manage + tenant write guards on CrudController |
| Cross-company | TenantScoped update + inheritTenantFromRecord; legacy links deny mismatch |
| Effective dating | Ops path stamps change date (does **not** rewrite historical payroll periods — engine untouched) |
| Payroll workflow | **Unchanged** `draft → approved → posted` |

### Salary path classification

| Path | Class | Action |
|------|-------|--------|
| Admin employee `salary_base` edit | Authorized + was audit-gap | Dedicated salary audit added |
| Ops payroll generate | Safe (reads salary) | No change |
| Enterprise `EmployeeSalaryService` | Authorized overlay | Audit + legacy tenant check |
| Direct SQL outside app | Dangerous / out of band | Documented only |

---

## C5 Tests

| Suite | Result |
|-------|--------|
| `php tests/hr/run-hr-phase-c-security-tests.php` | **CLEAR** (0/16 failed) |
| `php tests/hr/run-hr-phase-b-security-tests.php` | **CLEAR** (0/14 failed) |
| `php tests/hr/run-ess-phase-e-leave-tests.php` | **CLEAR** (0/11 failed) |
| `php tests/hr/run-ess-phase-c-hardening-tests.php` | **CLEAR** (0/10 failed) |

### Definition of Done

```text
[x] Canonical Employee source identified
[x] Employee identity documented
[x] HRMS ↔ rateb_employees relationship documented
[x] Tenant isolation verified
[x] bindEmployeeUser regression verified
[x] autoLinkEmployeeUser regression verified
[x] Duplicate employee detection completed (diagnostic)
[x] Orphan employee reference detection completed (diagnostic)
[x] Payroll/attendance/leave/contract/ESS references verified in audit
[x] Canonical salary source identified
[x] Salary change paths audited
[x] Unauthorized / cross-company salary linkage blocked (legacy assert)
[x] Salary changes audited with old/new/effective
[x] Phase B tests remain PASS (regression run)
[x] New Phase C tests PASS
[x] No destructive migration
[x] No duplicate architecture
[x] Documentation updated
```

### Deferred / remaining risks

- Production orphan counts unknown until `diagnoseCompany` is run per tenant.  
- No DB FOREIGN KEY added (safe — data not proven clean).  
- Employment contracts still missing (Phase D+/P1).  
- HRMS↔ops still unsynced by design (manual link only).  
- Email is not unique identity.  
- ESS `/api/v1/hr/*` still lacks `hr.view` plan gate (Phase B deferral).

---

## Recommended Next Phase

**Phase D — Payroll correctness** (batch attendance inputs, clearer “posted ≠ GL”, no engine rewrite).
