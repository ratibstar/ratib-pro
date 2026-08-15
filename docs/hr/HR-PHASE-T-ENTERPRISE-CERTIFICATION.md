# RATIB ERP — HR Phase T Enterprise Certification

**Status:** COMPLETE  
**Date:** 2026-08-15  
**Base:** Phase S Workforce Intelligence (`a48cce77` / stamp `7a283981`) + T inventory (`6dbb875e`)  
**Commit:** pending stamp  
**Deploy:** pending stamp  

---

## Objective

Harden and unify HR for enterprise production (T0–T9) **without** new business modules, Employee SoT, Approval Engine, payroll rewrite, Flutter, manager FK, live GOSI/WPS, or Phase U.

---

## Gates

| Gate | Result | Evidence |
|------|--------|----------|
| T0 UX Unification | PASS | Command Center is primary nav entry. Employee 360 adds Saudi + Workforce risk tabs. Duplicate Saudi reports menu item removed; reports-hub labeled ops exports. |
| T1 Employee 360 | PASS | Lazy `loadTab`; single-employee `employeeComplianceProfile` LIMIT 1; risk uses bounded COUNTs. No new employee master. |
| T2 Approvals | PASS | Inbox shows current stage, `stages_history`, pending/final via existing `HrApprovalMatrixService::decisionContext`. No new engine. |
| T3 Integrity | PASS | Compact CC widget (duplicates / orphans / HRMS / contracts / salary). `HrEmployeeIntegrityService` remains read-only. |
| T4 Production Readiness | PASS | `HrEnterpriseReadinessService::snapshot()` + `docs/hr/HR-PHASE-T-PRODUCTION-READINESS.md` (247–257, cron, flags, indexes, blockers). |
| T5 Security | PASS | Tenant `company_id`; 360 salary deny-by-default without RBAC helpers; ESS resolver bind; manager soft-link 403; documents company+employee; CSRF on inbox decide; APIs use `TenantContext`. |
| T6 Automation | PASS | Unique reminder ledger (`uq_hr_ops_reminder`); `claimReminder` idempotent; cron re-claim unclaimed keys; ops audit retained. |
| T7 Saudi Compliance | PASS | GOSI/WPS readiness only. `external_send_enabled = false`. `external_sent = 0`. No curl / GOSI_API. |
| T8 Performance | PASS | CC/inbox/analytics/workforce/ESS bounded. 360 not invoked from Command Center loops. |
| T9 Regression | PASS | Phase B C D E F G H H2 I J K L M N O P Q R S + T suites. |

---

## Delivered

| Area | Implementation |
|------|----------------|
| Inventory | `HrEnterpriseReadinessService` — migrations, cron, flags, compact integrity, production blockers |
| UX | Menu + Command Center hub links + 360 Saudi/risk tabs + integrity card |
| Integrity | Additive read-only contract/salary orphan COUNTs |
| Inbox | Matrix stage history + pending/final display |
| Tests | `tests/hr/HrPhaseTEnterpriseTest.php` / `run-hr-phase-t-tests.php` |
| Docs | This cert + production-readiness |

**Explicit non-goals met:** no Phase U, no live GOSI/WPS, no payroll rewrite, no Approval Engine, no Flutter, no manager FK, no new business features, no migration 258.

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-t-tests.php` | **CLEAR** |
| Phase B–S regressions | **CLEAR** |

---

## Visible improvements

- Command Center is the HR home; integrity + Saudi + workforce widgets on one screen.
- Employee 360 shows Saudi compliance and workforce risk without a second master.
- Approval Inbox shows stage progress history.

## Critical findings

- Salary/GOSI amounts remain RBAC-gated; 360 now **refuses salary** if RBAC helpers are missing (deny-by-default).
- Live GOSI/WPS is still intentionally disabled.

## Fixed in Phase T

- Duplicate Saudi reports sidebar item (route kept under Saudi compliance hub).
- Inbox stage history was matrix-internal only; now displayed.
- Compact integrity not previously shown on Command Center.

## Deferred

- Phase U
- Live GOSI/WPS connectors
- FK constraints until orphan counts are zero
- Unified document center (`unified_center_deferred`)
- Manager hierarchy FK (HRMS soft-link remains)

## Production requirements

See `docs/hr/HR-PHASE-T-PRODUCTION-READINESS.md`. Apply 247–257. Keep connectors OFF. Schedule `bin/erp-cron.php`.

## Remaining risks

- Environments missing 254–257 cannot persist Saudi/GOSI/WPS/plan targets.
- Integrity COUNTs surface data quality; they do not repair it.
- Manager team scope remains optional HRMS soft-link.

**Exit:** HR enterprise hardening T0–T9 certified. **No Phase U started.**
