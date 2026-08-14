# RATIB ERP — HR Phase J Actionable Approval Inbox Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase I Employee Master 360 (`a95c7080`); Phase G/H matrix + Oversight intact.

---

## Objective

Turn **عمليات بانتظار إجراء** (`hr/approvals-inbox`) from Phase F watch-only into an **actionable** company approval workspace for leave / permission / request — **without** a second approval engine.

---

## Architecture (locked)

```text
Company inbox UI
  → HrApprovalInboxController::decide
  → HrApprovalInboxService::decide
       · tenant company_id from session (never POST)
       · actor from session
       · HrApprovalMatrixService::canActorDecide
  → ApprovalOversightService::process
       · HrApprovalMatrixService::gateOversightDecision
       · domain finalizer only on final / passthrough
```

| Allowed | Forbidden |
|---------|-----------|
| `ApprovalOversightService` | ApprovalEngine3 |
| `HrApprovalMatrixService` | HrWorkflowService2 |
| Existing leave/permission/request finalizers | New state machine / EAP / Legacy Workflow |
| Audit `hr_inbox_approve` / `hr_inbox_reject` | Unlocking dead `hr/*/approve` company routes |

---

## Actionable sources

| Source | Inbox |
|--------|--------|
| `hr_leave` | Approve / reject when authorized |
| `hr_permission` | Approve / reject when authorized |
| `hr_request` | Approve / reject when authorized |
| `hr_payroll` | **View-only** (existing payroll Oversight / payroll flow) |

---

## Authorization

- Server resolves authenticated actor + ops company.
- Never trusts POST `company_id` / `approver_id` / `employee_id` / `role_id`.
- Cross-company record → `access_denied`.
- Approver types (G/H):
  - **user** — actor must equal required user
  - **role** — actor must hold company-scoped role id
  - **oversight** — SA or `hr.manage` / `hr.oversight` (not open to all)
- No manager hierarchy invented.
- Route middleware: module `hr` access; **real** gate is `canActorDecide` + matrix gate inside `process`.

---

## Matrix behavior

```text
request → current stage → authorized actor → approve
  → if not final: advance progress; domain stays pending
  → if final: existing domain finalizer
reject → authorized actor → progress rejected → domain reject
```

---

## UI

Inbox shows employee, summary, stage (n/max), required approver type, last stage actor, next outcome, and approve/reject (+ optional comment audited) when `can_act`.

Unauthorized actionable rows: “awaiting authorized actor”.  
Payroll: “view only”.

---

## Explicit non-goals

- Employment contracts / letter PDF / HireBridge
- Payroll rewrite / generic payroll approve from inbox
- Reviving blocked company `hr/*/approve` routes
- New decision-history table (uses progress last-actor + audit)

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-j-tests.php` | **CLEAR** |
| `run-hr-phase-f-approval-tests.php` | **CLEAR** (decide-via-Oversight) |
| Phase B / C / D / E / G / H / H2 / I | **CLEAR** |

---

## Exit criteria

Authorized company actors can approve/reject leave, permission, and request from the inbox with stage visibility; intermediate matrix stages do not finalize; payroll stays non-generic; tenant isolation holds; legacy approve routes stay blocked; G/H rules intact.
