# RATIB ERP — HR Phase I Employee Master 360 Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Product Gap Audit:** `docs/hr/HR-PRODUCT-GAP-AUDIT.md` (`8c0862f9`)  
**Base:** Phase H / H2 leave integrity already shipped; Phase I does **not** modify H2.

---

## Objective

Upgrade the existing Admin employee detail route into a professional **Employee Master 360** page that answers:

> Who is this employee?

using the existing RATIB data model — **without** a second employee master.

---

## Canonical identity

| Concern | Source |
|---------|--------|
| Employee | `rateb_employees` |
| PK | `rateb_employees.id` |
| Tenant | `rateb_employees.company_id` |
| Business code | `employee_code` |
| ESS link | `user_id` (boolean “ESS linked” only; no auto-link on view) |

Route (unchanged canonical):

`/admin/hr/employees/{id}`

Lazy tab endpoint (read-only):

`GET /admin/hr/employees/{id}/360-tab?tab=…`

---

## Architecture

```text
HrEmployeesController::show
  → HrEmployee360Service::loadShell (header + overview KPIs)
  → views/company/hr/employees/show.php

HrEmployeesController::show360Tab
  → HrEmployee360Service::loadTab
  → views/company/hr/employees/360-tab.php (HTML fragment)
```

- Read-only aggregation service.
- Reuses `HrService` leave balances / leave list / attendance YTD.
- Reuses `HrApprovalMatrixService::progressSummary` for pending request stage labels.
- No mutation, no new audit engine, no Employee2 table, no H2 leave logic changes, no Flutter/mobile changes.

---

## Tabs & data sources

| Tab | Source | Notes |
|-----|--------|-------|
| Overview | employee + balances + attendance YTD + pending counts | Server-rendered first paint |
| Employment | employee fields + optional HRM manager name | Contracts **deferred** (not commercial contracts) |
| Salary | `salary_base` + `rateb_hr_payroll_structures` | Gated by salary auth flags |
| Attendance | month aggregate + `employeeAttendanceYtd` | Link to existing attendance list |
| Leaves | `leaveBalancesForEmployee` + `listLeaveRequestsForEmployee` | Canonical leave SoT |
| Requests | `rateb_hr_employee_requests` (non-letter) | Stage via matrix progress when pending |
| Letters | request types salary/experience/EOS | PDF **deferred** (Phase L) |
| Payroll | `rateb_payroll_lines` + period status | Posted = period lock (Phase D) |
| Documents | `rateb_documents` + `rateb_hr_documents` | Unified center deferred |
| Violations | HRM disciplinary via `legacy_employee_id` if present | Empty / unavailable otherwise |
| Timeline | audit logs + leaves + requests + payroll lines | Existing sources only |

---

## Authorization

| Data | Gate |
|------|------|
| Page access | Existing `hr-employees` middleware |
| Tenant | `id AND company_id = current` → else **404** (no existence leak) |
| Salary / payroll detail | `hr-payroll` view **or** `hr-employees` manage |
| Quick actions | Only when corresponding manage/view flags allow |

Viewing 360 does **not** write AuditService business events.

---

## Performance

- Initial load: header + overview + KPI aggregates only.
- Secondary tabs: lazy HTML fetch + client cache (`hr-employee-360.js`).
- Bounded `LIMIT` on list queries; no per-day attendance dump on first paint.

---

## Deferred (explicit)

- Employment contracts
- Letter PDF generation
- HireBridge
- Manager approval decide path (delivered in Phase J Actionable Inbox)
- ESS / mobile parity (Phase M)
- Working-day / half-day leave
- GPS attendance
- GOSI / WPS
- New approval engine / new employee master / new audit engine

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-i-tests.php` | **CLEAR** |
| Phase B / C / D / E / F / G / H | **CLEAR** |
| ESS Phase C / E | **CLEAR** |

---

## Definition of Done

```text
[x] Employee 360 on existing employee detail route
[x] Useful header + overview
[x] Salary authorization
[x] Leave balance / history canonical
[x] Attendance summary
[x] Payroll history (Phase D semantics)
[x] Requests + letters status (no fake PDF)
[x] Documents where available
[x] Violations empty/unavailable when unsupported
[x] Timeline from existing sources
[x] No fake data / no duplicate master
[x] Tenant isolation + RBAC
[x] No H2 / mobile / contracts / letter PDF work
[x] Lazy tabs / bounded queries
[x] Tests + certification + roadmap
```
