# RATIB ERP — HR Phase M Decisions + Disciplinary Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase L Letters (`d69e1ef5`)  
**Commit:** `5ca733c1`  
**Deploy:** [Deploy #3016](https://github.com/ratibstar/ratib-pro/actions/runs/31832590253) success

---

## Objective

Turn employee decisions and disciplinary actions into a real HR workflow linked to `rateb_employees`: **request → Oversight/Matrix approve → execute once**, with full audit. No new Approval Engine. No payroll formula / accounting changes. No manager hierarchy. No Phase N.

---

## Decision types

| Type key | Arabic |
|----------|--------|
| `promotion` | ترقية |
| `salary_adjustment` | تعديل راتب |
| `transfer` | نقل |
| `salary_stop` | إيقاف راتب |
| `absence_deduction` | حسم غياب/تأخير |
| `salary_movement` | حركة على الراتب |
| `termination` | إنهاء خدمة |

SoT: `rateb_hr_decisions` (migration `252`). Oversight source: `hr_decision`.

---

## Architecture

```text
HrDecisionsController::store
  → HrDecisionService::create (status=pending)
  → ApprovalOversightService::notifyPendingSubmission(hr_decision)
  → Approvals inbox / Oversight + Matrix (Phase F/G/J + M)
  → finalizeApprove → status=approved → execute once (CAS)
  → employee patch (salary_base / status / dept / title) + Audit

HrDisciplinaryService::create
  → ensure HRMS profile soft-link (legacy_employee_id)
  → rateb_hrm_disciplinary_actions (+ additive legacy_employee_id)
  → Audit hr_disciplinary_create
```

**Sensitive types** (`salary_adjustment`, `salary_movement`, `salary_stop`, `termination`): never mutate employee unless status is `approved` (execute CAS).

**salary_stop:** sets `status=inactive` (does not zero `salary_base`).

**absence_deduction:** recorded on decision payload only — no `generatePayrollLines` rewrite.

---

## Surfaces

| Surface | Route |
|---------|--------|
| Decisions list / create / execute | `hr/decisions`, `hr/decisions/create`, `POST …/execute` |
| Disciplinary list / create | `hr/disciplinary`, `hr/disciplinary/create` |
| Approvals inbox | `hr_decision` actionable (with leave/permission/request) |
| Employee 360 | Decisions + Violations tabs |
| Migration | `252_hr_phase_m_decisions.sql` (additive) |

RBAC: `rateb_erp_mw('hr', '', 'hr-employees')`.

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-m-tests.php` | **CLEAR** |
| Phase B–L regressions | **CLEAR** (F/H/J assertions updated for Phase M source) |

---

## Explicit non-goals (deferred)

- Mobile / ESS redesign  
- GOSI / WPS  
- Payroll redesign / Accounting vouchers  
- Manager hierarchy  
- Phase N (Performance / org nav)  
- Auto-apply absence deduction into payroll lines  

---

## Remaining risks

- Absence/late deduction is HR-visible only until a future payroll deduction bridge.  
- Promotion/transfer optional HRMS promo/xfer row linkage columns exist but thin CRUD HRMS screens remain separate.  
- Execute is auto-invoked on final Matrix approve; manual execute remains for approved-not-executed recovery.

**Exit:** All seven decision types + disciplinary CRUD; approve/reject/Matrix; execute-once; 360; tenant + RBAC + audit. **Met.**
