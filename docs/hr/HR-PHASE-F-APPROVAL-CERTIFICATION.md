# RATIB ERP — HR Phase F Approval Inbox Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase E `bf32010d`  
**Audit:** `docs/hr/HR-PHASE-F-APPROVAL-AUDIT.md`

---

## Architecture

```text
Company HR UI (read-only inbox)
  HrApprovalInboxController
  HrApprovalInboxService
        ↓ listPending(company, category=hr)
  ApprovalOversightService   ← SoT (unchanged)
        ↓ decide (platform only)
  HrService / existing module services
```

No ApprovalEngine2. No shared pending table. No company approve unlock.

---

## Inbox coverage

| Type | Source key | Pending status | In inbox |
|------|------------|----------------|----------|
| Leave | `hr_leave` | `pending` | Yes |
| Permission | `hr_permission` | `pending` | Yes |
| Request | `hr_request` | `pending` | Yes |
| Payroll | `hr_payroll` | `draft` | Yes |
| Decision | — | — | Deferred (module absent) |
| Expense | — | — | Deferred (not HR queue) |

---

## Governance preserved

- Company `POST …/approve|reject` remains `$blockCompanyApprovalAction`.
- Payroll `draft → approved → posted` unchanged; post still status lock (Phase D/E).
- Accounting flag remains OFF by default (Phase E).
- Inbox allowed_action = `view_only_in_company_inbox`.
- SA can open `/admin/oversight/approvals?type=hr`.

---

## UX wiring

- Menu: `hr_pending_actions` → `hr/approvals-inbox` (AR: عمليات بانتظار إجراء)
- Dashboard banner + leave/payroll stats link to inbox
- Nav badge aggregates leave + permission + request + payroll counts

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-f-approval-tests.php` | **CLEAR** (0/13) |
| Phase B–E + ESS regressions | **CLEAR** |

---

## Definition of Done

```text
[x] Approval architecture audited
[x] Inbox aggregator reuses ApprovalOversightService
[x] Company isolation enforced
[x] Leave/permission/request/payroll listed
[x] Decisions/Expenses deferred (not invented)
[x] No company approve from inbox
[x] Company approve routes still blocked
[x] Payroll workflow unchanged
[x] Menu label عمليات بانتظار إجراء
[x] No destructive migration / no Approval2
[x] Regressions PASS
[x] Documentation updated
```

### Deferred

- Decisions module  
- HR expenses pending queue  
- Company-side multi-stage approval matrix (Phase G)
