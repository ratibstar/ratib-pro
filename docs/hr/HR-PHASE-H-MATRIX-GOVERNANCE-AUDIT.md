# RATIB ERP — HR Phase H0 Matrix Governance Audit

**Status:** COMPLETE (evidence)  
**Date:** 2026-08-14  
**Base:** Phase G `53fbeb5d`  
**Rule:** Harden configuration safety — do **not** rewrite the approval engine.

---

## 1. Current surface (discovered)

| Asset | Role | Config safety |
|-------|------|---------------|
| `HrApprovalMatrixService::gateOversightDecision` | Runtime gate | OK — passthrough / stage / finalize |
| `HrApprovalMatrixService::saveMatrix` | Upsert + always `enabled=1` | **Unsafe for production** — weak validation |
| `HrApprovalMatrixService::resetProgress` | Undo cleanup | OK |
| `ApprovalOversightService` | Execution authority | Unchanged — must stay |
| Migration 248 | matrices / stages / progress | Additive OK |
| Matrix controllers / HR routes | **None** | Config is service-only today |
| EAP / Legacy Workflow | Not used | Keep forbidden |

---

## 2. Misconfiguration risks (Phase G → H)

| Risk | Phase G behavior | Phase H requirement |
|------|------------------|---------------------|
| Empty / null matrix name | Allowed (`null`) | Reject |
| Zero stages | Rejected only after normalize empties | Explicit validator error |
| Stage order gaps (1,3) | Allowed | Reject — require contiguous `1..N` |
| Unknown approver_type | **Silently coerced to `oversight`** | **Hard reject** — no silent convert |
| Invalid user ref | Not checked | User exists, same company, active |
| Invalid / cross-tenant role | Not checked | Role exists; `company_id` must match matrix company (no platform-global role as stage actor) |
| Duplicate company+source+request_type | DB unique | Keep; deterministic resolve |
| Specific vs wildcard | Exact then wildcard | Document + certify precedence |
| Always activate on save | `enabled=1` always | Separate draft save vs activate |
| Deactivate / rollback | Missing | `deactivateMatrix` → `enabled=0`; in-flight keeps snapshot |
| Duplicate same user stages | Allowed silently | Warning (no invented SoD hard-fail) |
| Self-approval | Not checked | Runtime deny when actor = requester `user_id` (non-SA) |
| Audit of config changes | Missing | `AuditService` on save/activate/deactivate |
| Notification confusion | N/A (no matrix notify) | Do not add new notifier |

---

## 3. Design decisions for Phase H

1. **`HrApprovalMatrixValidator`** — pure validation; returns `{ok, errors[], warnings[]}`.  
2. **Reuse `enabled`** as DRAFT(`0`) / ACTIVE(`1`) — no new lifecycle column.  
3. **`saveMatrix(..., activate: bool = false)`** — validate always; persist stages; set enabled per flag; bump version on update.  
4. **`activateMatrix` / `deactivateMatrix`** — validate-before-activate; deactivate is safe rollback.  
5. **Reject unknown approver types** — never coerce.  
6. **Role rule** — only tenant roles with `rateb_roles.company_id = matrix.company_id`.  
7. **Runtime self-approval** — if stage `user` and actor equals linked employee `user_id`, deny (SA exempt).  
8. **No UI required for exit** — service API + tests + docs; optional admin UI deferred.  
9. **Roadmap** — this Phase H supersedes former “Leave depth” numbering; leave depth deferred as H2.

---

## 4. Non-goals

- No ApprovalEngine2 / EAP / Legacy Workflow  
- No payroll / accounting / leave calc changes  
- No company approve unlock  
- No manager hierarchy  
- No invented Decisions/Expenses sources  

---

## 5. Audit gate

```text
[x] saveMatrix unsafe paths identified
[x] enabled reused for draft/active
[x] Validator + activate/deactivate planned
[x] Runtime self-approval planned
[x] Implementation
```
