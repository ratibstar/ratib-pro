# RATIB ERP — HR Phase G Approval Matrix Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase F `17839695` / Audit `4215646c`  
**Audit:** `docs/hr/HR-PHASE-G-APPROVAL-MATRIX-AUDIT.md`

---

## Architecture

```text
Domain leave / permission / request (status SoT)
        ↓ pending
ApprovalOversightService::process
        ↓ gateOversightDecision
HrApprovalMatrixService   ← governance / progression only
        ↓ final stage or no matrix
Existing domain finalizers
  approveLeave / rejectLeave
  setHrStatus (permission)
  setEmployeeRequestStatus (request / certificate types)
```

No ApprovalEngine2. No EAP routing. No Legacy WorkflowService for HR.  
Company approve routes remain blocked.

---

## Schema (additive)

| Table | Purpose |
|-------|---------|
| `rateb_hr_approval_matrices` | Company + source_key + request_type config + version |
| `rateb_hr_approval_matrix_stages` | Ordered stages (approver_type: oversight\|user\|role) |
| `rateb_hr_approval_progress` | Per-record progress with **frozen** `stages_snapshot_json` + `matrix_version` |

Migration: `rateb-erp/migrations/248_hr_phase_g_approval_matrix.sql`  
Domain tables (`rateb_leave_requests`, etc.) **not** altered.

---

## Behavior

| Case | Result |
|------|--------|
| No matrix / schema missing / empty stages | **Passthrough** — exact pre-G single-shot Oversight |
| Matrix + intermediate approve | Progress advances; domain stays `pending` |
| Matrix + final approve | Existing domain approve runs; progress `completed` |
| Reject any stage | Existing domain reject; progress `rejected` |
| Matrix version bump after start | In-flight uses snapshot — path not silently rewritten |
| Certificate | `hr_request` + `request_type=salary_certificate` (no separate entity) |

---

## Decisions applied

| ID | Decision |
|----|----------|
| D1 | Leave + permission + employee requests (certificate via request_type) |
| D2 | Oversight remains execution authority; matrix is overlay |
| D3 | Additive matrix + progress only |
| D4 | No matrix ⇒ current behavior |
| D5 | Final stage only calls existing domain finalizers |

Approver types implemented: `oversight`, `user`, `role` (via `AuthorizationService::getUserRoleIds`).  
**Not** invented: direct manager hierarchy.

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-g-approval-matrix-tests.php` | **CLEAR** |
| Phase F regression | **CLEAR** |

---

## Definition of Done

```text
[x] Audit accepted
[x] Additive matrix + progress schema
[x] HrApprovalMatrixService (no domain status UPDATE)
[x] Oversight hook + undo reset
[x] Leave / permission / request coverage
[x] Certificate via request_type
[x] Version snapshot binding
[x] Safe fallback when no matrix
[x] No EAP / Legacy Workflow / ApprovalEngine2
[x] Company approve still blocked
[x] Payroll untouched
[x] Certification + roadmap
```

### Deferred

- Company-side stage actor UI (inbox remains read-only)
- Manager hierarchy resolver
- Matrix admin CRUD screens (service `saveMatrix` available for config/tests)
- Phase H leave depth
