# RATIB ERP — HR Phase K HireBridge + Employment Contracts Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase J Actionable Inbox (`67f1ef5c`)

---

## Objective

1. **HireBridge:** Recruitment `ready → deployed` creates or links `rateb_employees`.  
2. **Employment contracts:** First-class HR contracts on the live employee (not commercial supplier contracts).

---

## Architecture

```text
RecruitmentWorkflowService::transition(…, deployed)
  → HrHireBridgeService::hireFromCandidate
       · idempotent via rateb_employees.recruitment_candidate_id
       · national_id match → link (no duplicate)
       · else Employee::create (EM- codes)
       · optional draft HrEmploymentContract from recruitment contract

HrEmploymentContractService
  → rateb_hr_employment_contracts
  → draft → active → expired|terminated
  → cron expiry status + near-expiry notifyCompany
```

| Canonical | Forbidden |
|-----------|-----------|
| `rateb_employees` Employee SoT | Employee2 / second master |
| `rateb_hr_employment_contracts` | Reusing `rateb_contracts` / eProc / `rateb_recruitment_contracts` as employment SoT |
| Additive migration `250_…` | DROP / payroll / approval engine / letter PDF |

---

## Contract fields

`employee_id`, `contract_no`, `start_date`, `end_date`, `salary`, `status`, plus `company_id`, `alert_days`, optional recruitment refs.

Statuses: `draft` → `active` → `expired` | `terminated`.

---

## Surfaces

| Surface | Route |
|---------|--------|
| Register | `hr/employment-contracts` |
| Detail / activate / terminate | `hr/employment-contracts/{id}` |
| Employee 360 Employment tab | lists contracts |
| Cron | `hr_employment_contract_status`, `hr_employment_contract_alerts` |

RBAC: `rateb_erp_mw('hr', '', 'hr-employees')`.

---

## Audit actions

- `hirebridge_create` / `hirebridge_link` / `create` on `hr_employees`
- `hr_employment_contract_create|update|activate|terminate`

---

## Explicit non-goals (Phase L+)

- Letter PDF  
- Manager approvals changes  
- Mobile / ESS parity  
- GOSI / WPS  
- Payroll redesign / require-active-contract flag enforcement  

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-k-tests.php` | **CLEAR** |
| Phase B–J regressions | **CLEAR** |

---

## Exit criteria

Deployed candidate becomes/links an employee without duplicates; employment contracts lifecycle works with tenant isolation, RBAC, audit, 360 visibility, and expiry alerts — without touching commercial contracts or payroll/approval engines.
