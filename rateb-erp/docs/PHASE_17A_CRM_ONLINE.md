# Phase 17A — Enterprise CRM Platform (ONLINE FOUNDATION)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline:** Do NOT implement Offline. No Queue / Replay / SDK / SW / IDB changes.  
**Migration:** `migrations/183_crm_platform_enterprise.sql`

## Executive Summary

Phase 17A adds a **tenant-scoped Enterprise CRM** module (leads, contacts, companies, opportunities, pipelines, activities, campaigns, timeline). It is distinct from CMS marketing leads (`rateb_cms_leads`) and CMS newsletter campaigns. Existing `rateb_customers` remains the accounting/POS customer master and may be linked optionally.

## Repository Audit (pre-17A)

| Area | Status |
|------|--------|
| Standalone company CRM module | **Missing → created** |
| `rateb_customers` (accounting) | Reused as optional link only |
| CMS leads / newsletter campaigns | **Not overloaded** |
| Supplier follow-up / timeline patterns | Pattern reference only |
| Offline CRM | Deferred to **Phase 17B** |

## Architecture

```
Controllers (thin)
  → Domain services (Lead, Opportunity, Pipeline, Meeting, Call, Task, Campaign, Timeline, Activity, Assignment, Workflow, Contact, Company, Note)
  → Models (rateb_crm_*)
  → Database
```

**Only `CrmWorkflowService` may change lead `workflow_status`.**

## Workflow

`new → contacted → qualified → proposal → won|lost → archived`

## Offline readiness (for 17B later)

| Operation | Service | Offline Replay Compatible |
|-----------|---------|---------------------------|
| Lead create/update draft | `LeadService` | YES |
| Lead workflow transition | `CrmWorkflowService` | YES |
| Note / task / meeting create | Note/Task/Meeting services | YES |
| Opportunity create / stage move | `OpportunityService` | YES |
| Campaign create | `CampaignService` | YES |
| Binary attachment upload | — | **NO** (not in 17A) |
| Mass email send / government APIs | — | **NO** |

## RBAC

| Slug | Role |
|------|------|
| `crm.view` | view |
| `crm.create` | create |
| `crm.update` | update |
| `crm.delete` | soft-delete |
| `crm.assign` | assignments |
| `crm.pipeline` | pipelines/stages |
| `crm.activities` | meetings/calls/tasks |
| `crm.campaign` | campaigns |
| `crm.admin` | admin (implies core) |
| `crm.manage` | all |

## Files Created

- `migrations/183_crm_platform_enterprise.sql`
- `app/models/CrmModels.php`
- `app/services/CrmSupport.php`
- `app/services/CrmWorkflowService.php`
- `app/services/CrmTimelineService.php`
- `app/services/CrmDomainServices.php`
- `app/services/CrmActivityServices.php`
- `app/controllers/Company/CrmControllers.php`
- `views/company/crm/**`
- `tests/crm/*`
- `docs/PHASE_17A_CRM_ONLINE.md`

## Files Modified (additive)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/entity-permissions.php`
- `config/permission-labels-{en,ar}.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## Tests

```bash
php tests/crm/run-crm-phase17a-tests.php
```

## Production readiness

1. Run migration `183_crm_platform_enterprise.sql`
2. Enable plan module `crm` for the tenant
3. Grant `crm.*` permissions (seeded to `company-full-access` / `super-admin`)
4. Phase 17B may wrap these services — Offline flags must default OFF — Baseline untouched

## Success criteria

- ✔ Domain services own business rules  
- ✔ No Offline / Queue / Replay / SDK / Baseline changes  
- ✔ Distinct from CMS leads  
- ✔ Future 17B can call these services directly  
