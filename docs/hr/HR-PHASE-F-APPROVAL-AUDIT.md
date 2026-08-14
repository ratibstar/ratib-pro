# RATIB ERP — HR Phase F0 Approval Architecture Audit

**Status:** COMPLETE (evidence)  
**Date:** 2026-08-14  
**Base:** Phase E `bf32010d`  
**Rule:** Unify inbox discovery — do **not** rewrite approval engines.

---

## 1. Module map (discovered)

| Module | Pending Source | Approval Service | Controller | Status (pending) | Audit | Notification | RBAC |
|--------|----------------|------------------|------------|------------------|-------|--------------|------|
| **Leave** | `rateb_leave_requests` | `ApprovalOversightService` → `HrService::approveLeave` | Company routes **blocked**; oversight decide | `pending` | Oversight + leave path | ESS: `notifyPendingSubmission('hr_leave')` | `hr-leaves`; approve = `workflows.manage` (platform) |
| **Permission** | `rateb_hr_permission_requests` | `ApprovalOversightService` | Company approve **blocked** | `pending` | Oversight | Optional notify on create | `hr-attendance` |
| **Employee requests** | `rateb_hr_employee_requests` | `ApprovalOversightService` | Company approve **blocked** | `pending` | Oversight | Via create paths | `hr-leaves` entity map |
| **Payroll** | `rateb_payroll_periods` | `ApprovalOversightService` → `HrService::approvePayroll` | Company approve **blocked**; company **post** allowed after approved | Pending oversight = `draft` | Phase B/D payroll audit | — | `hr-payroll` |
| **Decisions** | **None** (no HR decisions module/table in live ops) | — | — | — | — | — | — |
| **Expenses** | **None** as HR pending queue (expense GL is accounting, not HR inbox) | Accounting journals/vouchers separate | Accounting oversight sources | — | — | — | — |

---

## 2. Canonical approval hub (already exists)

```text
Admin / Platform:
  /admin/oversight/approvals
  ApprovalOversightService::listPending / decide / notifyPendingSubmission
  NotificationService::notifyOversightPending
```

HR source keys already registered:

- `hr_leave`
- `hr_permission`
- `hr_request`
- `hr_payroll`

Company POST `…/approve|reject` for leave/permission/request/payroll is **`$blockCompanyApprovalAction`**.

---

## 3. Gaps for Phase F

| Gap | Fix in Phase F |
|-----|----------------|
| No single HR page labeled “عمليات بانتظار إجراء” | Add company-scoped **read-only** HR Approval Inbox |
| Pending counts split across leave/payroll cards | Aggregate via `HrApprovalInboxService` |
| Decisions / Expenses | **Document deferred** — no fake modules |
| Duplicate Approval Engine | **Forbidden** — reuse `ApprovalOversightService` |

---

## 4. Design decision

```text
HrApprovalInboxService  (aggregator / normalizer only)
        ↓ reads
ApprovalOversightService (SoT for pending + decide)
        ↓
Existing module services (HrService, etc.)
```

Inbox **does not** call approve/reject for company users.  
Actions remain on platform oversight (SA) or deep-links to source show pages.

---

## 5. Display status mapping (UI only)

| Source status | Inbox display |
|---------------|---------------|
| leave/permission/request `pending` | `pending` |
| payroll `draft` | `pending` (awaiting oversight approve) |
| payroll `approved` | not in pending queue (company can post) |
| approved/rejected/posted | completed / out of inbox |

No new shared DB status column.

---

## 6. Non-goals

- No ApprovalEngine2 / WorkflowEngine2  
- No payroll/leave/request business rewrite  
- No AccountingService change  
- No destructive migration  
- No Decisions/Expenses invention
