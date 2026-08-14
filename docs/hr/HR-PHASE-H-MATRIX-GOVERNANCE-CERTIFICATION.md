# RATIB ERP — HR Phase H Matrix Governance Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase G `53fbeb5d`  
**Audit:** `docs/hr/HR-PHASE-H-MATRIX-GOVERNANCE-AUDIT.md`

---

## Objective

Prevent unsafe matrix **misconfiguration** without rewriting the approval engine.

---

## Architecture (unchanged decide path)

```text
Oversight process
  → HrApprovalMatrixService::gateOversightDecision
  → domain finalizers (final/reject only)

Configuration path (Phase H):
  HrApprovalMatrixValidator
  → saveMatrix(activate=false|true)
  → activateMatrix / deactivateMatrix
  → AuditService
```

---

## Governance controls

| Control | Behavior |
|---------|----------|
| Validator | Hard errors before save/activate |
| Approver types | `oversight` \| `user` \| `role` only — **no silent coerce** |
| User approver | Exists, same company, active; SA user rejected (use oversight) |
| Role approver | Exists; **company-scoped only** (platform-global roles rejected) |
| Stage order | Contiguous `1..N`, unique codes |
| Duplicate actors | **Warning** (no invented SoD hard-fail) |
| DRAFT / ACTIVE | `enabled=0` / `enabled=1` (reuse Phase G column) |
| Default save | DRAFT unless `activate=true` |
| Deactivate | Rollback; in-flight keeps `stages_snapshot_json` |
| Precedence | Specific `request_type` beats wildcard `''`; enabled only |
| Self-approval | Runtime deny when actor = requester employee `user_id` (non-SA) |
| Sources | `hr_leave` / `hr_permission` / `hr_request` only |

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-h-matrix-governance-tests.php` | **CLEAR (0/15)** |
| Phase G / F regressions | **CLEAR** |

---

## Definition of Done

```text
[x] Audit documented
[x] HrApprovalMatrixValidator
[x] saveMatrix validates + draft default
[x] activateMatrix / deactivateMatrix
[x] No silent approver coercion
[x] Company-safe user/role checks
[x] Contiguous stages
[x] Specific beats wildcard
[x] Runtime self-approval guard
[x] Config audit logs
[x] No engine / payroll / accounting / EAP changes
[x] Company approve still blocked
[x] Certification + roadmap
```

### Deferred

- Admin matrix UI screens  
- Invented SoD hard-fail rules  
- Former roadmap “Leave depth” (now **Phase H2**)
