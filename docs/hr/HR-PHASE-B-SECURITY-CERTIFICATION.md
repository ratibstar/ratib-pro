# RATIB ERP — HR Phase B Security Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base audit commit:** `6918853f`  
**Scope:** B1–B5 only (hardening). No payroll/attendance/accounting redesign.

---

## B1 ESS Tenant Isolation

| Item | Detail |
|------|--------|
| **Finding** | `HrEssEmployeeResolverService` could return another company’s employee when token company mismatched; `bindEmployeeUser` lacked `company_id`; Admin `autoLinkEmployeeUser` had global email fallback. |
| **Root cause** | Intentional “platform SA / wrong token company” fallbacks that crossed tenant boundaries. |
| **Fix** | Require `companyId > 0`; all lookups `AND company_id = :cid`; reject `tenant_mismatch`; `bindEmployeeUser(employeeId, userId, companyId)` updates only matching company; Admin auto-link company-scoped only. |
| **Tests** | `HrPhaseBSecurityTest` resolver/bind/autoLink cases. |
| **Result** | **PASS** |

Canonical company source: API `TenantContext::companyId()` / token company (unchanged). No third resolution mechanism.

---

## B2 Payroll Authorization

| Item | Detail |
|------|--------|
| **Finding** | Company `POST …/hr/payroll/{id}/post` remained live while approve was oversight-blocked; risk of perceiving post as approval bypass. |
| **Root cause** | UI workflow is intentional: oversight **approves** (`draft→approved`), company **posts** (`approved→posted`). Service already required `approved` for post, but lacked explicit tenant guard + audit. |
| **Actual statuses** | `draft` → `approved` → `posted` (`rateb_payroll_periods.status`). |
| **Fix** | `loadPayrollPeriodForMutation` enforces company match; `postPayroll` still rejects non-`approved`; controller + oversight pass `company_id`; show/export/payslip SQL scoped by `pl.company_id`. Approve route remains company-blocked (`$blockCompanyApprovalAction`). |
| **Invariant** | `draft|rejected|* → posted` **denied**; only `approved → posted`. |
| **Tests** | post/approve status guards + tenant guard + company-scoped queries. |
| **Result** | **PASS** |

---

## B3 Payroll Audit Trail

| Item | Detail |
|------|--------|
| **Finding** | `rateb_payroll_audit` existed (migration 190) but was unused. |
| **Root cause** | Ops payroll (`HrService`) never wrote audit rows; enterprise table was additive only. |
| **Implementation** | Reuse `PayrollAudit` + existing `AuditService` from `HrService::recordPayrollAudit` (no new audit service). |
| **Events written** | `calculated` (on generate lines), `approved`, `posted` with payload `from_status` / `to_status` / period meta. Entity: `hr_payroll_period`. |
| **Tamper resistance** | No routes/controllers for payroll audit CRUD; append-only from service. |
| **Schema gap (documented)** | Table has no dedicated `previous_state`/`new_state` columns — carried in `payload_json`. No destructive ALTER. |
| **Result** | **PASS** (table correctly used for ops sensitive actions) |

---

## B4 ESS Leave Oversight Notification

| Item | Detail |
|------|--------|
| **Finding** | ESS leave apply persisted `pending` without notifying oversight. |
| **Root cause** | `HrEssLeaveService::apply` had no call to `ApprovalOversightService::notifyPendingSubmission` / `NotificationService`. |
| **Fix** | After successful `LeaveRequest::create`, call `ApprovalOversightService::notifyPendingSubmission(…, 'hr_leave', …)` which uses existing `NotificationService::notifyOversightPending`. |
| **Semantics** | Notify only if `$id > 0` after persist; no notify on validation/overlap failure. |
| **Duplicate risk** | Single call site in service (not controller); Admin CRUD leave path unchanged (no double notify on ESS). |
| **Tests** | Phase B + Phase E leave notify wiring. |
| **Result** | **PASS** |

---

## B5 Regression & Security

| Suite | Result |
|-------|--------|
| `php tests/hr/run-hr-phase-b-security-tests.php` | **CLEAR** (0/14 failed) |
| `php tests/hr/run-ess-phase-e-leave-tests.php` | **CLEAR** (0/11 failed) |
| `php tests/hr/run-ess-phase-c-hardening-tests.php` | **CLEAR** (0/10 failed) |
| `php tests/hr/run-hr-phase23a-tests.php` | 2 pre-existing fails (routes live in `ops.php` not `company.php`; `hrm/*` not in sidebar) — **out of Phase B scope** |

### Certification checklist

```text
[x] ESS cannot cross tenant boundaries
[x] bindEmployeeUser cannot cross tenant boundaries
[x] Payroll cannot bypass approval (draft→posted denied)
[x] Payroll post is correctly authorized (approved + company + CSRF + manage)
[x] Payroll is tenant-isolated
[x] Payroll sensitive actions are audited
[x] rateb_payroll_audit is correctly used (ops period events)
[x] ESS leave submission triggers oversight notification
[x] No duplicate notification introduced (single service call)
[x] Existing ESS leave/hardening tests pass
[x] New security tests pass
[x] No destructive migration
[x] No duplicate services introduced
[x] No duplicate HR/Payroll architecture introduced
```

---

## Remaining risks (documented, not fixed in B)

1. Company may still **post** after oversight **approve** (by design / UI). If product wants post oversight-only, that is a separate ADR.  
2. ESS still has no `hr.view` plan-module API gate (ApiAuth only) — deferred from Phase B scope expansion.  
3. `AuditService` session `company_id` may differ from ops company in edge SA cases — payload includes period `company_id`.  
4. Pre-existing HrPhase23A sidebar/route test expectations are stale.
