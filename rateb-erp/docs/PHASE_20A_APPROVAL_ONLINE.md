# Phase 20A — Enterprise Approval Workflow Platform (ONLINE FOUNDATION)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline. No Queue / Replay / SDK / SW / IDB changes.  
**Migration:** `migrations/186_approval_platform_enterprise.sql`

## Executive Summary

Phase 20A adds a **tenant-scoped Enterprise Approval Platform (EAP)** on additive `rateb_eap_*` tables. It is a reusable approval engine for Procurement, Recruitment, Accounting, CRM, Projects, Assets, HR, and future modules. It does **not** replace legacy `rateb_approval_*`, `WorkflowService`, `WorkflowSubmissionService`, or admin `/admin/oversight/approvals*`. UI routes live under `/approvals/*` guarded by `rateb_erp_mw('approval', …)`.

## Repository Audit (pre-20A)

| Area | Status |
|------|--------|
| Legacy `rateb_approval_workflows` / instances / actions | **Preserved** (untouched) |
| Legacy `WorkflowService` / `ApprovalOversightService` | **Preserved** (untouched) |
| Centralized reusable EAP for all modules | **Missing → created** (`rateb_eap_*`) |
| Offline Approvals | Deferred to **Phase 20B** |
| Offline Foundation / Queue / Replay / SDK | **Frozen — not modified** |

## Architecture

```
Controllers (thin, company/approvals)
  → Domain services (Approval*, Template*, Stage*, Rule*, Chain*, …)
  → ApprovalWorkflowService ONLY for workflow_status
  → Models (Eap* → rateb_eap_*)
  → Database
```

Notification **sending** reuses existing `NotificationService` (ONLINE ONLY). `ApprovalNotificationMetaService` stores metadata links only. Attachment binaries remain ONLINE ONLY (`rateb_eap_attachment_meta` = metadata).

## Workflow

**Only via `ApprovalWorkflowService`:**  
`draft → submitted → pending → approved | rejected`  
also: `cancelled`, `archived` (see allowed transition map)

## Offline readiness (for 20B later)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Template / stage / rule / chain CRUD | `ApprovalTemplateService`, `ApprovalStageService`, `ApprovalRuleService`, `ApprovalChainService` | YES | Master-definition style |
| Request create / update (non-status) | `ApprovalRequestService` | YES | Draft/metadata |
| Request workflow transition | `ApprovalWorkflowService` | YES | Must call service |
| Action record (approve/reject meta) | `ApprovalActionService` | YES | Tenant-scoped |
| Delegation / escalation meta | `ApprovalDelegationService`, `ApprovalEscalationService` | YES | |
| Comment / timeline / audit | `ApprovalCommentService`, `ApprovalTimelineService`, `ApprovalAuditService` | YES | |
| SLA / reminder schedule meta | tables `rateb_eap_sla`, `rateb_eap_reminders` | PARTIAL | Meta yes; timer dispatch **NO** |
| Notification meta link | `ApprovalNotificationMetaService` | PARTIAL | Meta yes; send **NO** |
| Notification sending | `NotificationService` | **NO** | **ONLINE ONLY** |
| Attachment binary upload | Document / file services | **NO** | **ONLINE ONLY** (meta table only) |
| Cross-tenant / admin oversight | legacy oversight | **NO** | Unsupported offline |
| Legacy WorkflowService paths | `WorkflowService` | N/A | Out of EAP scope |

## RBAC

| Slug | Role |
|------|------|
| `approval.view` | view EAP |
| `approval.create` | create templates / requests |
| `approval.submit` | submit / transition submit path |
| `approval.approve` | approve pending |
| `approval.reject` | reject pending |
| `approval.delegate` | delegate authority |
| `approval.admin` | admin |
| `approval.manage` | all (implies above) |

## Files Created

- `migrations/186_approval_platform_enterprise.sql`
- `app/models/ApprovalModels.php`
- `app/services/ApprovalSupport.php`
- `app/services/ApprovalWorkflowService.php`
- `app/services/ApprovalTimelineService.php`
- `app/services/ApprovalDomainServices.php`
- `app/controllers/Company/ApprovalPlatformControllers.php`
- `views/company/approvals/**`
- `tests/approval/*`
- `docs/PHASE_20A_APPROVAL_ONLINE.md`

## Files Modified (additive)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/entity-permissions.php`
- `config/permission-labels-{en,ar}.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## Tests

```bash
php tests/approval/run-approval-phase20a-tests.php
```

## Production readiness

1. Run migration `186_approval_platform_enterprise.sql`
2. Ensure plan module `approval` is enabled for the tenant
3. Grant `approval.view` / `approval.manage` (seeded to `company-full-access` / `super-admin`)
4. Use `/approvals` for the enterprise platform; legacy workflows / admin oversight remain unchanged
5. Phase 20B may wrap these services — Offline flags must default OFF — Baseline untouched

## Success criteria

- ONLINE EAP domain complete and multi-tenant
- Workflow only through `ApprovalWorkflowService`
- Legacy approval + Offline Foundation unchanged
- Gate tests CLEAR
